<?php
/**
 * Cart System Class
 * Handles student cart functionality for multiple book downloads
 */

require_once __DIR__ . '/../config/config.php';

class CartSystem {
    private $conn;
    private $cart_table = "student_carts";
    private $download_sessions_table = "cart_download_sessions";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Add book to cart (session-based, no email required)
     * @param string $session_id Browser session ID
     * @param int $book_id Book ID to add
     * @return array Result
     */
    public function addToCart($session_id, $book_id) {
        // Check if book exists
        $book_query = "SELECT id, title FROM books WHERE id = ? AND status = 'active'";
        $book_stmt = $this->conn->prepare($book_query);
        $book_stmt->execute([$book_id]);
        $book = $book_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book) {
            return [
                'success' => false,
                'message' => 'Book not found or not available'
            ];
        }

        // Check cart size limit
        $cart_count = $this->getCartCount($session_id);
        $max_items = $this->getMaxCartItems();

        if ($cart_count >= $max_items) {
            return [
                'success' => false,
                'message' => "Cart is full. Maximum $max_items books allowed."
            ];
        }

        // Check if book already in cart
        $check_query = "SELECT id FROM {$this->cart_table}
                       WHERE session_id = ? AND book_id = ? AND expires_at > NOW()";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->execute([$session_id, $book_id]);

        if ($check_stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Book is already in your cart'
            ];
        }

        // Add to cart
        $insert_query = "INSERT INTO {$this->cart_table} (session_id, book_id) VALUES (?, ?)";
        $insert_stmt = $this->conn->prepare($insert_query);
        $success = $insert_stmt->execute([$session_id, $book_id]);

        if ($success) {
            logActivity("Book added to cart: Session $session_id, Book ID: $book_id ({$book['title']})");
            return [
                'success' => true,
                'message' => "\"" . $book['title'] . "\" added to cart",
                'data' => [
                    'book_id' => $book_id,
                    'book_title' => $book['title'],
                    'cart_count' => $cart_count + 1
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to add book to cart'
        ];
    }

    /**
     * Remove book from cart
     * @param string $session_id
     * @param int $book_id
     * @return array Result
     */
    public function removeFromCart($session_id, $book_id) {
        $delete_query = "DELETE FROM {$this->cart_table}
                        WHERE session_id = ? AND book_id = ?";
        $delete_stmt = $this->conn->prepare($delete_query);
        $success = $delete_stmt->execute([$session_id, $book_id]);

        if ($success && $delete_stmt->rowCount() > 0) {
            logActivity("Book removed from cart: Session $session_id, Book ID: $book_id");
            return [
                'success' => true,
                'message' => 'Book removed from cart',
                'data' => ['cart_count' => $this->getCartCount($session_id)]
            ];
        }

        return [
            'success' => false,
            'message' => 'Book not found in cart'
        ];
    }

    /**
     * Get cart contents
     * @param string $session_id
     * @return array Cart contents
     */
    public function getCartContents($session_id) {
        $query = "SELECT c.book_id, c.added_at, b.title, b.author, b.subject,
                         b.cover_image, b.edition, b.publisher
                  FROM {$this->cart_table} c
                  JOIN books b ON c.book_id = b.id
                  WHERE c.session_id = ? AND c.expires_at > NOW() AND b.status = 'active'
                  ORDER BY c.added_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$session_id]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'books' => $books,
            'total_count' => count($books),
            'expires_at' => $this->getCartExpiry()
        ];
    }

    /**
     * Clear entire cart
     * @param string $session_id
     * @return bool Success
     */
    public function clearCart($session_id) {
        $delete_query = "DELETE FROM {$this->cart_table} WHERE session_id = ?";
        $delete_stmt = $this->conn->prepare($delete_query);
        $success = $delete_stmt->execute([$session_id]);

        if ($success) {
            logActivity("Cart cleared: Session $session_id");
        }

        return $success;
    }

    /**
     * Get cart count
     * @param string $session_id
     * @return int Count
     */
    public function getCartCount($session_id) {
        $query = "SELECT COUNT(*) as count FROM {$this->cart_table}
                 WHERE session_id = ? AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$session_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    /**
     * Initialize cart download session
     * @param string $session_id Browser session
     * @param string $student_email UCSI email
     * @return array Result with download session ID
     */
    public function initializeCartDownload($session_id, $student_email) {
        // Get cart contents
        $cart = $this->getCartContents($session_id);

        if (empty($cart['books'])) {
            return [
                'success' => false,
                'message' => 'Your cart is empty. Add some books before downloading.'
            ];
        }

        // Generate download session ID
        $download_session_id = bin2hex(random_bytes(32));

        // Prepare books data
        $books_data = array_map(function($book) {
            return [
                'id' => $book['book_id'],
                'title' => $book['title'],
                'author' => $book['author']
            ];
        }, $cart['books']);

        // Create download session
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hour to complete download

        $insert_query = "INSERT INTO {$this->download_sessions_table}
                        (id, student_email, total_books, books_data, expires_at)
                        VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $this->conn->prepare($insert_query);
        $success = $insert_stmt->execute([
            $download_session_id,
            $student_email,
            count($cart['books']),
            json_encode($books_data),
            $expires_at
        ]);

        if ($success) {
            // Update cart items with student email
            $update_query = "UPDATE {$this->cart_table} SET student_email = ? WHERE session_id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$student_email, $session_id]);

            logActivity("Cart download session initialized: $download_session_id for $student_email ({$cart['total_count']} books)");

            return [
                'success' => true,
                'message' => 'Download session created. Check your email for verification code.',
                'data' => [
                    'download_session_id' => $download_session_id,
                    'student_email' => $student_email,
                    'total_books' => count($cart['books']),
                    'books' => $books_data,
                    'expires_in' => 3600 // 1 hour
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create download session'
        ];
    }

    /**
     * Get download session
     * @param string $download_session_id
     * @return array|false Session data or false
     */
    public function getDownloadSession($download_session_id) {
        $query = "SELECT * FROM {$this->download_sessions_table}
                 WHERE id = ? AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$download_session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $session['books_data'] = json_decode($session['books_data'], true);
        }

        return $session;
    }

    /**
     * Update download session with OTP
     * @param string $download_session_id
     * @param string $otp_code
     * @return bool Success
     */
    public function updateSessionWithOTP($download_session_id, $otp_code) {
        $query = "UPDATE {$this->download_sessions_table}
                 SET otp_code = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$otp_code, $download_session_id]);
    }

    /**
     * Verify OTP and mark session as verified
     * @param string $download_session_id
     * @param string $otp_code
     * @return bool Success
     */
    public function verifySessionOTP($download_session_id, $otp_code) {
        $query = "UPDATE {$this->download_sessions_table}
                 SET otp_verified = TRUE, download_token = ?
                 WHERE id = ? AND otp_code = ?";

        $download_token = bin2hex(random_bytes(32));
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([$download_token, $download_session_id, $otp_code]);

        if ($success && $stmt->rowCount() > 0) {
            return $download_token;
        }

        return false;
    }

    /**
     * Complete download and clear cart
     * @param string $download_session_id
     * @return bool Success
     */
    public function completeDownload($download_session_id) {
        // Get session data
        $session = $this->getDownloadSession($download_session_id);
        if (!$session) {
            return false;
        }

        // Mark download as completed
        $update_query = "UPDATE {$this->download_sessions_table}
                        SET downloaded_at = NOW() WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->execute([$download_session_id]);

        // Clear the cart (find session_id by email)
        $cart_query = "SELECT DISTINCT session_id FROM {$this->cart_table}
                      WHERE student_email = ?";
        $cart_stmt = $this->conn->prepare($cart_query);
        $cart_stmt->execute([$session['student_email']]);

        while ($cart_row = $cart_stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->clearCart($cart_row['session_id']);
        }

        // Update student stats
        $this->updateStudentStats($session['student_email'], $session['total_books']);

        logActivity("Cart download completed: Session $download_session_id, Student: {$session['student_email']}, Books: {$session['total_books']}");

        return true;
    }

    /**
     * Update student download statistics
     * @param string $student_email
     * @param int $book_count
     */
    private function updateStudentStats($student_email, $book_count) {
        $query = "UPDATE students
                 SET total_downloads = total_downloads + ?, last_download_at = NOW()
                 WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$book_count, $student_email]);
    }

    /**
     * Get maximum cart items from settings
     * @return int Max items
     */
    private function getMaxCartItems() {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'max_cart_items'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['setting_value'] : 10; // Default 10
    }

    /**
     * Get cart expiry time
     * @return string Expiry timestamp
     */
    private function getCartExpiry() {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'cart_expiry_hours'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hours = $result ? (int)$result['setting_value'] : 24; // Default 24 hours

        return date('Y-m-d H:i:s', strtotime("+$hours hours"));
    }

    /**
     * Cleanup expired carts (maintenance function)
     * @return int Number of cleaned up carts
     */
    public function cleanupExpiredCarts() {
        $query = "DELETE FROM {$this->cart_table} WHERE expires_at < NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $deleted_carts = $stmt->rowCount();

        $query2 = "DELETE FROM {$this->download_sessions_table} WHERE expires_at < NOW()";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->execute();
        $deleted_sessions = $stmt2->rowCount();

        if ($deleted_carts > 0 || $deleted_sessions > 0) {
            logActivity("Cleanup completed: $deleted_carts expired carts, $deleted_sessions expired sessions");
        }

        return $deleted_carts + $deleted_sessions;
    }

    /**
     * Get cart statistics
     * @return array Statistics
     */
    public function getCartStats() {
        $stats = [];

        // Active carts
        $query = "SELECT COUNT(DISTINCT session_id) as count FROM {$this->cart_table}
                 WHERE expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['active_carts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Total items in all carts
        $query = "SELECT COUNT(*) as count FROM {$this->cart_table}
                 WHERE expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_cart_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Download sessions today
        $query = "SELECT COUNT(*) as count FROM {$this->download_sessions_table}
                 WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['download_sessions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Completed downloads today
        $query = "SELECT COUNT(*) as count FROM {$this->download_sessions_table}
                 WHERE downloaded_at IS NOT NULL AND DATE(downloaded_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['completed_downloads_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $stats;
    }
}
?>