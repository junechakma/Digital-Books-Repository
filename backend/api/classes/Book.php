<?php
/**
 * Book Model Class - Updated to match BookForm.tsx exactly
 * Handles all book-related database operations
 */

require_once __DIR__ . '/../config/config.php';

class Book {
    private $conn;
    private $table_name = "books";

    // Properties matching BookForm.tsx exactly
    public $id;
    public $title;           // Required
    public $author;          // Required
    public $subject;         // Required
    public $description;     // Required
    public $cover_image;     // Optional (coverImage from form)
    public $pdf_url;         // Optional (pdfUrl from form)
    public $edition;         // Optional
    public $publication_date; // Optional (publicationDate from form)
    public $publisher;       // Optional
    public $isbn;           // Optional
    public $book_hash;      // Optional (bookHash from form)
    public $source;         // Optional
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all books with pagination and filtering
     * @param int $page
     * @param int $limit
     * @param string $search
     * @param string $subject
     * @return array
     */
    public function getAll($page = 1, $limit = 20, $search = '', $subject = '') {
        $offset = ($page - 1) * $limit;

        $query = "SELECT * FROM " . $this->table_name . " WHERE status = 'active'";
        $params = [];

        // Add search filter
        if (!empty($search)) {
            $query .= " AND (MATCH(title, author, description) AGAINST (? IN BOOLEAN MODE)
                        OR title LIKE ? OR author LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        // Add subject filter
        if (!empty($subject)) {
            $query .= " AND subject = ?";
            $params[] = $subject;
        }

        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'active'";
        $count_params = [];

        if (!empty($search)) {
            $count_query .= " AND (MATCH(title, author, description) AGAINST (? IN BOOLEAN MODE)
                             OR title LIKE ? OR author LIKE ?)";
            $search_param = "%{$search}%";
            $count_params[] = $search;
            $count_params[] = $search_param;
            $count_params[] = $search_param;
        }

        if (!empty($subject)) {
            $count_query .= " AND subject = ?";
            $count_params[] = $subject;
        }

        $count_stmt = $this->conn->prepare($count_query);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'books' => $books,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * Get book by ID
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new book - matches BookForm.tsx structure
     * @param array $data
     * @return int|false Book ID on success, false on failure
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                 (title, author, subject, description, cover_image, pdf_url,
                  edition, publication_date, publisher, isbn, book_hash, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Validate required fields
        if (empty(trim($data['title'])) || empty(trim($data['author'])) ||
            empty(trim($data['subject'])) || empty(trim($data['description']))) {
            return false;
        }

        $success = $stmt->execute([
            trim($data['title']),
            trim($data['author']),
            trim($data['subject']),
            trim($data['description']),
            !empty($data['cover_image']) ? trim($data['cover_image']) : null,
            !empty($data['pdf_url']) ? trim($data['pdf_url']) : null,
            !empty($data['edition']) ? trim($data['edition']) : null,
            !empty($data['publication_date']) ? $data['publication_date'] : null,
            !empty($data['publisher']) ? trim($data['publisher']) : null,
            !empty($data['isbn']) ? trim($data['isbn']) : null,
            !empty($data['book_hash']) ? trim($data['book_hash']) : null,
            !empty($data['source']) ? trim($data['source']) : null
        ]);

        return $success ? $this->conn->lastInsertId() : false;
    }

    /**
     * Update book - matches BookForm.tsx fields
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];

        $allowed_fields = [
            'title', 'author', 'subject', 'description', 'cover_image', 'pdf_url',
            'edition', 'publication_date', 'publisher', 'isbn', 'book_hash', 'source'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['title', 'author', 'subject', 'description'])) {
                    // Required fields - don't allow empty values
                    if (!empty(trim($data[$field]))) {
                        $fields[] = "$field = ?";
                        $params[] = trim($data[$field]);
                    }
                } else {
                    // Optional fields - allow empty values (will be set to NULL)
                    $fields[] = "$field = ?";
                    $params[] = !empty($data[$field]) ? trim($data[$field]) : null;
                }
            }
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) .
                ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'active'";
        $params[] = $id;

        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete book (soft delete)
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $query = "UPDATE " . $this->table_name . " SET status = 'inactive' WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }

    /**
     * Hard delete book (remove from database and file system)
     * @param int $id
     * @return bool
     */
    public function hardDelete($id) {
        // First get the file paths
        $book = $this->getById($id);
        if (!$book) {
            return false;
        }

        // Delete from database
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([$id]);

        if ($success) {
            // Delete physical files if they exist
            if ($book['pdf_url'] && !filter_var($book['pdf_url'], FILTER_VALIDATE_URL)) {
                $pdf_path = BASE_PATH . '/' . ltrim($book['pdf_url'], '/');
                if (file_exists($pdf_path)) {
                    unlink($pdf_path);
                }
            }

            if ($book['cover_image'] && !filter_var($book['cover_image'], FILTER_VALIDATE_URL)) {
                $cover_path = BASE_PATH . '/' . ltrim($book['cover_image'], '/');
                if (file_exists($cover_path)) {
                    unlink($cover_path);
                }
            }
        }

        return $success;
    }

    /**
     * Get all unique subjects
     * @return array
     */
    public function getSubjects() {
        $query = "SELECT DISTINCT subject FROM " . $this->table_name . "
                 WHERE status = 'active' ORDER BY subject";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get books by subject
     * @param string $subject
     * @param int $limit
     * @return array
     */
    public function getBySubject($subject, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . "
                 WHERE subject = ? AND status = 'active'
                 ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$subject, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search books
     * @param string $search_term
     * @param int $limit
     * @return array
     */
    public function search($search_term, $limit = 20) {
        $query = "SELECT * FROM " . $this->table_name . "
                 WHERE status = 'active' AND
                 (MATCH(title, author, description) AGAINST (? IN BOOLEAN MODE)
                  OR title LIKE ? OR author LIKE ?)
                 ORDER BY
                 MATCH(title, author, description) AGAINST (? IN BOOLEAN MODE) DESC,
                 created_at DESC
                 LIMIT ?";

        $search_param = "%{$search_term}%";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$search_term, $search_param, $search_param, $search_term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent books
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . "
                 WHERE status = 'active'
                 ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get book statistics
     * @return array
     */
    public function getStats() {
        $stats = [];

        // Total books
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Books by subject
        $query = "SELECT subject, COUNT(*) as count FROM " . $this->table_name . "
                 WHERE status = 'active' GROUP BY subject ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['books_by_subject'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent uploads (last 30 days)
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                 WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['recent_uploads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Books with PDF URLs vs without
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN pdf_url IS NOT NULL AND pdf_url != '' THEN 1 ELSE 0 END) as with_pdf,
                    SUM(CASE WHEN pdf_url IS NULL OR pdf_url = '' THEN 1 ELSE 0 END) as without_pdf
                  FROM " . $this->table_name . " WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $pdf_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pdf_availability'] = $pdf_stats;

        return $stats;
    }

    /**
     * Validate book data against BookForm requirements
     * @param array $data
     * @return array Validation result
     */
    public function validateBookData($data) {
        $errors = [];

        // Required fields from BookForm.tsx
        $required_fields = ['title', 'author', 'subject', 'description'];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        // Validate email format if provided
        if (isset($data['cover_image']) && !empty($data['cover_image'])) {
            if (!filter_var($data['cover_image'], FILTER_VALIDATE_URL) &&
                !preg_match('/^\//', $data['cover_image'])) {
                $errors['cover_image'] = 'Cover image must be a valid URL or file path';
            }
        }

        if (isset($data['pdf_url']) && !empty($data['pdf_url'])) {
            if (!filter_var($data['pdf_url'], FILTER_VALIDATE_URL) &&
                !preg_match('/^\//', $data['pdf_url'])) {
                $errors['pdf_url'] = 'PDF URL must be a valid URL or file path';
            }
        }

        // Validate publication date format
        if (isset($data['publication_date']) && !empty($data['publication_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['publication_date'])) {
                $errors['publication_date'] = 'Publication date must be in YYYY-MM-DD format';
            }
        }

        // Validate ISBN format (basic check)
        if (isset($data['isbn']) && !empty($data['isbn'])) {
            $isbn = preg_replace('/[^0-9X]/', '', strtoupper($data['isbn']));
            if (strlen($isbn) !== 10 && strlen($isbn) !== 13) {
                $errors['isbn'] = 'ISBN must be 10 or 13 digits';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Format book data for frontend (matches expected structure)
     * @param array $book_data
     * @return array
     */
    public function formatForFrontend($book_data) {
        return [
            'id' => $book_data['id'],
            'title' => $book_data['title'],
            'author' => $book_data['author'],
            'subject' => $book_data['subject'],
            'description' => $book_data['description'],
            'coverImage' => $book_data['cover_image'], // Note: matches frontend camelCase
            'pdfUrl' => $book_data['pdf_url'],          // Note: matches frontend camelCase
            'edition' => $book_data['edition'],
            'publicationDate' => $book_data['publication_date'], // Note: matches frontend camelCase
            'publisher' => $book_data['publisher'],
            'isbn' => $book_data['isbn'],
            'bookHash' => $book_data['book_hash'],      // Note: matches frontend camelCase
            'source' => $book_data['source'],
            'createdAt' => $book_data['created_at'],    // Note: matches frontend camelCase
            'updatedAt' => $book_data['updated_at']     // Note: matches frontend camelCase
        ];
    }

    /**
     * Convert frontend data to database format
     * @param array $frontend_data
     * @return array
     */
    public function convertFromFrontend($frontend_data) {
        return [
            'title' => $frontend_data['title'] ?? '',
            'author' => $frontend_data['author'] ?? '',
            'subject' => $frontend_data['subject'] ?? '',
            'description' => $frontend_data['description'] ?? '',
            'cover_image' => $frontend_data['coverImage'] ?? null,
            'pdf_url' => $frontend_data['pdfUrl'] ?? null,
            'edition' => $frontend_data['edition'] ?? null,
            'publication_date' => $frontend_data['publicationDate'] ?? null,
            'publisher' => $frontend_data['publisher'] ?? null,
            'isbn' => $frontend_data['isbn'] ?? null,
            'book_hash' => $frontend_data['bookHash'] ?? null,
            'source' => $frontend_data['source'] ?? null
        ];
    }
}
?>