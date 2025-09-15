<?php
/**
 * Cart Files Download Endpoint
 * Serves multiple PDF files as a ZIP archive with token verification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/CartSystem.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/Download.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

// Check if download token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    sendErrorResponse('Download token is required', 401);
}

$download_token = sanitizeInput($_GET['token']);

try {
    // Get database connection
    $database = getDatabase();
    $db = $database->getConnection();

    if (!$db) {
        sendErrorResponse('Database connection failed', 500);
    }

    // Initialize models
    $cartSystem = new CartSystem($db);
    $book = new Book($db);
    $download = new Download($db);

    // Verify download token and get session
    $session_query = "SELECT * FROM cart_download_sessions
                     WHERE download_token = ? AND otp_verified = TRUE
                     AND downloaded_at IS NULL AND expires_at > NOW()";
    $session_stmt = $db->prepare($session_query);
    $session_stmt->execute([$download_token]);
    $session = $session_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        sendErrorResponse('Invalid or expired download token', 401);
    }

    $student_email = $session['student_email'];
    $student_id = explode('@', $student_email)[0];
    $books_data = json_decode($session['books_data'], true);

    // Check if single book download is requested
    $single_book_id = $_GET['book_id'] ?? null;

    if ($single_book_id) {
        // Download single book from cart
        downloadSingleBookFromCart($book, $download, $session, (int)$single_book_id, $student_email, $student_id);
    } else {
        // Download all books as ZIP
        downloadCartAsZip($book, $download, $cartSystem, $session, $books_data, $student_email, $student_id);
    }

} catch (Exception $e) {
    logActivity("Cart download error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Download failed', 500);
}

/**
 * Download single book from cart
 */
function downloadSingleBookFromCart($book, $download, $session, $book_id, $student_email, $student_id) {
    // Get book details
    $book_data = $book->getById($book_id);
    if (!$book_data) {
        sendErrorResponse('Book not found', 404);
    }

    // Check if book is in the cart session
    $books_in_session = json_decode($session['books_data'], true);
    $book_in_cart = false;
    foreach ($books_in_session as $cart_book) {
        if ($cart_book['id'] == $book_id) {
            $book_in_cart = true;
            break;
        }
    }

    if (!$book_in_cart) {
        sendErrorResponse('Book not found in your cart', 403);
    }

    // Find PDF file
    $file_path = findBookPDFFile($book_data);
    if (!$file_path) {
        sendErrorResponse('PDF file not available for this book', 404);
    }

    // Record individual download
    $client_ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $download_recorded = $download->recordDownload($book_id, $student_email, $client_ip, $user_agent);

    // Serve the file
    servePDFFile($file_path, $book_data['title'], $student_email);
}

/**
 * Download entire cart as ZIP file
 */
function downloadCartAsZip($book, $download, $cartSystem, $session, $books_data, $student_email, $student_id) {
    $session_id = $session['id'];

    // Create temporary directory for ZIP creation
    $temp_dir = sys_get_temp_dir() . '/ucsi_cart_' . $student_id . '_' . time();
    if (!mkdir($temp_dir, 0755, true)) {
        sendErrorResponse('Failed to create temporary directory', 500);
    }

    $zip_filename = "UCSI_Books_Cart_{$student_id}_" . date('Y-m-d_H-i-s') . ".zip";
    $zip_path = $temp_dir . '/' . $zip_filename;

    // Create ZIP archive
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        sendErrorResponse('Failed to create ZIP archive', 500);
    }

    $successful_books = [];
    $failed_books = [];

    // Add each book to ZIP
    foreach ($books_data as $book_info) {
        $book_data = $book->getById($book_info['id']);
        if (!$book_data) {
            $failed_books[] = $book_info['title'] . ' (Book not found)';
            continue;
        }

        $file_path = findBookPDFFile($book_data);
        if (!$file_path) {
            $failed_books[] = $book_data['title'] . ' (PDF file not available)';
            continue;
        }

        // Create safe filename for ZIP
        $safe_filename = createSafeFilename($book_data['title'], $book_data['author']) . '.pdf';

        // Add file to ZIP
        if ($zip->addFile($file_path, $safe_filename)) {
            $successful_books[] = $book_data;
        } else {
            $failed_books[] = $book_data['title'] . ' (Failed to add to archive)';
        }
    }

    // Add a README file to the ZIP
    $readme_content = createReadmeContent($successful_books, $failed_books, $student_email);
    $zip->addFromString('README.txt', $readme_content);

    // Close ZIP file
    $zip->close();

    if (empty($successful_books)) {
        // Clean up and send error
        unlink($zip_path);
        rmdir($temp_dir);
        sendErrorResponse('No books could be added to the download. Please contact support.', 500);
    }

    // Record cart download
    $client_ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $download_query = "INSERT INTO downloads
                      (download_session_id, student_email, student_id, total_books,
                       download_type, user_ip, user_agent)
                      VALUES (?, ?, ?, ?, 'cart', ?, ?)";
    $download_stmt = $GLOBALS['db']->prepare($download_query);
    $download_stmt->execute([
        $session_id,
        $student_email,
        $student_id,
        count($successful_books),
        $client_ip,
        $user_agent
    ]);

    // Complete the download session
    $cartSystem->completeDownload($session_id);

    // Serve ZIP file
    serveZipFile($zip_path, $zip_filename, $temp_dir);
}

/**
 * Find PDF file for a book
 * @param array $book_data
 * @return string|false File path or false if not found
 */
function findBookPDFFile($book_data) {
    // Check pdf_url from database
    if (!empty($book_data['pdf_url'])) {
        $pdf_url = $book_data['pdf_url'];

        // If it's an external URL, we can't serve it directly
        if (filter_var($pdf_url, FILTER_VALIDATE_URL)) {
            return false; // External URLs not supported for cart download
        }

        // Local file path
        $file_path = BASE_PATH . '/' . ltrim($pdf_url, '/');
        if (file_exists($file_path) && is_readable($file_path)) {
            return $file_path;
        }
    }

    // Try alternative paths
    $possible_paths = [
        BASE_PATH . '/uploads/books/' . $book_data['id'] . '.pdf',
        BASE_PATH . '/uploads/books/2024/' . $book_data['id'] . '.pdf',
        BASE_PATH . '/public/books/' . urlencode($book_data['title']) . '.pdf'
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }
    }

    return false;
}

/**
 * Create safe filename for ZIP contents
 * @param string $title
 * @param string $author
 * @return string Safe filename
 */
function createSafeFilename($title, $author) {
    // Remove invalid filename characters
    $safe_title = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);
    $safe_author = preg_replace('/[^a-zA-Z0-9._-]/', '_', $author);

    // Limit length
    $safe_title = substr($safe_title, 0, 50);
    $safe_author = substr($safe_author, 0, 30);

    return $safe_title . '_by_' . $safe_author;
}

/**
 * Create README content for the ZIP file
 * @param array $successful_books
 * @param array $failed_books
 * @param string $student_email
 * @return string README content
 */
function createReadmeContent($successful_books, $failed_books, $student_email) {
    $student_id = explode('@', $student_email)[0];
    $download_date = date('Y-m-d H:i:s');

    $content = "UCSI DIGITAL LIBRARY - BANGLADESH BRANCH\n";
    $content .= "========================================\n\n";
    $content .= "Student: $student_id\n";
    $content .= "Download Date: $download_date\n";
    $content .= "Total Books: " . count($successful_books) . "\n\n";

    $content .= "BOOKS INCLUDED IN THIS DOWNLOAD:\n";
    $content .= "--------------------------------\n";
    foreach ($successful_books as $i => $book) {
        $content .= ($i + 1) . ". {$book['title']}\n";
        $content .= "   Author: {$book['author']}\n";
        $content .= "   Subject: {$book['subject']}\n";
        if (!empty($book['edition'])) {
            $content .= "   Edition: {$book['edition']}\n";
        }
        if (!empty($book['publisher'])) {
            $content .= "   Publisher: {$book['publisher']}\n";
        }
        $content .= "\n";
    }

    if (!empty($failed_books)) {
        $content .= "BOOKS NOT AVAILABLE FOR DOWNLOAD:\n";
        $content .= "----------------------------------\n";
        foreach ($failed_books as $i => $failed_book) {
            $content .= ($i + 1) . ". $failed_book\n";
        }
        $content .= "\n";
    }

    $content .= "IMPORTANT NOTES:\n";
    $content .= "----------------\n";
    $content .= "• These books are for educational use only\n";
    $content .= "• Do not redistribute or share these files\n";
    $content .= "• Respect copyright and academic integrity policies\n";
    $content .= "• For support, contact: library@ucsi.edu.my\n\n";

    $content .= "Thank you for using UCSI Digital Library!\n";

    return $content;
}

/**
 * Serve PDF file with proper headers
 * @param string $file_path
 * @param string $title
 * @param string $student_email
 */
function servePDFFile($file_path, $title, $student_email) {
    $file_size = filesize($file_path);
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title) . '.pdf';

    // Set headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Log download
    logActivity("Single book downloaded from cart: $title by $student_email");

    // Serve file
    ob_clean();
    flush();
    readfile($file_path);
    exit();
}

/**
 * Serve ZIP file and cleanup
 * @param string $zip_path
 * @param string $zip_filename
 * @param string $temp_dir
 */
function serveZipFile($zip_path, $zip_filename, $temp_dir) {
    $file_size = filesize($zip_path);

    // Set headers for ZIP download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Log download
    logActivity("Cart ZIP downloaded: $zip_filename");

    // Serve file
    ob_clean();
    flush();
    readfile($zip_path);

    // Clean up temporary files
    unlink($zip_path);
    rmdir($temp_dir);

    exit();
}
?>