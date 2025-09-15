<?php
/**
 * Secure PDF Download API Endpoint
 * Handles secure serving of PDF files with download tracking
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/Download.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendErrorResponse('Book ID is required');
}

$book_id = (int)$_GET['id'];

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

    // Get book details
    $book_data = $book->getById($book_id);

    if (!$book_data) {
        sendErrorResponse('Book not found', 404);
    }

    // Get client information
    $client_ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $user_email = $_GET['email'] ?? null; // Optional email for tracking

    // Check for recent downloads from same IP (anti-spam)
    if ($download->hasRecentDownload($book_id, $client_ip, 2)) {
        sendErrorResponse('Please wait before downloading this file again', 429);
    }

    // Construct full file path
    $file_path = BASE_PATH . '/' . $book_data['pdf_path'];

    // Verify file exists
    if (!file_exists($file_path)) {
        logActivity("Download failed - file not found: {$book_data['pdf_path']}", 'ERROR');
        sendErrorResponse('File not found', 404);
    }

    // Verify file is readable
    if (!is_readable($file_path)) {
        logActivity("Download failed - file not readable: {$book_data['pdf_path']}", 'ERROR');
        sendErrorResponse('File not accessible', 403);
    }

    // Record the download
    $download_recorded = $download->recordDownload($book_id, $user_email, $client_ip, $user_agent);

    if (!$download_recorded) {
        logActivity("Failed to record download for book ID: $book_id", 'WARNING');
    }

    // Prepare file for download
    $file_size = filesize($file_path);
    $file_name = $book_data['pdf_filename'];

    // Clean filename for download
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
    logActivity("Download completed: Book ID $book_id, Title: {$book_data['title']}, IP: $client_ip");

    exit();

} catch (Exception $e) {
    logActivity("Download error: " . $e->getMessage(), 'ERROR');
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