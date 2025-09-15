<?php
/**
 * Secure PDF Download with Token-based Authentication
 * Only verified UCSI students can download
 */

require_once __DIR__ . '/../config/config.php';
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
    $book = new Book($db);
    $download = new Download($db);

    // Verify download token
    session_start();
    if (!isset($_SESSION['download_tokens'][$download_token])) {
        sendErrorResponse('Invalid or expired download token', 401);
    }

    $token_data = $_SESSION['download_tokens'][$download_token];

    // Check if token has expired
    if (time() > $token_data['expires']) {
        unset($_SESSION['download_tokens'][$download_token]);
        sendErrorResponse('Download token has expired. Please verify your email again.', 401);
    }

    $email = $token_data['email'];
    $book_id = $token_data['book_id'];

    // Get book details
    $book_data = $book->getById($book_id);

    if (!$book_data) {
        sendErrorResponse('Book not found', 404);
    }

    // Determine file path - check both pdf_url (from form) and generated paths
    $file_path = null;

    if (!empty($book_data['pdf_url'])) {
        // If PDF URL is provided in the book form, use it
        $pdf_url = $book_data['pdf_url'];

        // Check if it's a local file path or external URL
        if (filter_var($pdf_url, FILTER_VALIDATE_URL)) {
            // External URL - redirect to it
            header("Location: $pdf_url");
            exit();
        } else {
            // Local file path
            $file_path = BASE_PATH . '/' . ltrim($pdf_url, '/');
        }
    } else {
        // Legacy path - look for files in uploads directory
        $possible_paths = [
            BASE_PATH . '/uploads/books/' . $book_data['id'] . '.pdf',
            BASE_PATH . '/uploads/books/2024/' . $book_data['id'] . '.pdf',
            BASE_PATH . '/public/books/' . urlencode($book_data['title']) . '.pdf'
        ];

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $file_path = $path;
                break;
            }
        }
    }

    // If no file found, show error
    if (!$file_path || !file_exists($file_path)) {
        logActivity("Download failed - file not found for book ID: {$book_id}, Title: {$book_data['title']}", 'ERROR');
        sendErrorResponse('PDF file not available for download', 404);
    }

    // Verify file is readable
    if (!is_readable($file_path)) {
        logActivity("Download failed - file not readable: $file_path", 'ERROR');
        sendErrorResponse('File not accessible', 403);
    }

    // Extract student ID from email
    $student_id = explode('@', $email)[0];

    // Record the download
    $client_ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $download_recorded = $download->recordDownload($book_id, $email, $client_ip, $user_agent);

    if (!$download_recorded) {
        logActivity("Failed to record download for book ID: $book_id, student: $email", 'WARNING');
    }

    // Clean up the used token
    unset($_SESSION['download_tokens'][$download_token]);

    // Prepare file for download
    $file_size = filesize($file_path);
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $book_data['title']) . '.pdf';

    // Set headers for secure PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    // Handle range requests for large files (enables resume downloads)
    if (isset($_SERVER['HTTP_RANGE'])) {
        handleRangeRequest($file_path, $file_size);
    } else {
        // Standard download
        ob_clean();
        flush();
        readfile($file_path);
    }

    // Log successful download
    logActivity("Verified download completed: Book ID $book_id, Title: {$book_data['title']}, Student: $email, IP: $client_ip");

    exit();

} catch (Exception $e) {
    logActivity("Secure download error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Download failed', 500);
}

/**
 * Handle HTTP range requests for partial content downloads
 * Enables resume functionality for large files
 */
function handleRangeRequest($file_path, $file_size) {
    $range = $_SERVER['HTTP_RANGE'];

    // Parse range header
    if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header('Content-Range: bytes */' . $file_size);
        exit();
    }

    $start = intval($matches[1]);
    $end = !empty($matches[2]) ? intval($matches[2]) : $file_size - 1;

    // Validate range
    if ($start > $end || $start >= $file_size || $end >= $file_size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header('Content-Range: bytes */' . $file_size);
        exit();
    }

    $length = $end - $start + 1;

    // Set partial content headers
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
    header('Content-Length: ' . $length);

    // Read and output the requested range
    $file = fopen($file_path, 'rb');
    fseek($file, $start);

    $buffer_size = 8192; // 8KB buffer
    while ($length > 0 && !feof($file)) {
        $read_size = min($buffer_size, $length);
        $data = fread($file, $read_size);
        echo $data;
        $length -= strlen($data);

        // Flush output to prevent memory issues
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    fclose($file);
}
?>