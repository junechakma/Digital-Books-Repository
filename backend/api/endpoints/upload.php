<?php
/**
 * File Upload API Endpoint
 * Handles PDF and cover image uploads
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/FileUpload.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// Get database connection
$database = getDatabase();
$db = $database->getConnection();

if (!$db) {
    sendErrorResponse('Database connection failed', 500);
}

// Initialize models
$book = new Book($db);
$file_upload = new FileUpload();

try {
    // Check if this is a complete book upload or just file upload
    $upload_type = $_POST['upload_type'] ?? 'complete';

    switch ($upload_type) {
        case 'complete':
            handleCompleteBookUpload($book, $file_upload);
            break;

        case 'pdf_only':
            handlePDFOnlyUpload($file_upload);
            break;

        case 'cover_only':
            handleCoverOnlyUpload($file_upload);
            break;

        default:
            sendErrorResponse('Invalid upload type');
    }

} catch (Exception $e) {
    logActivity("Upload error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Upload failed: ' . $e->getMessage(), 500);
}

/**
 * Handle complete book upload (PDF + metadata + optional cover)
 */
function handleCompleteBookUpload($book, $file_upload) {
    // Validate required fields
    $required_fields = ['title', 'author', 'subject', 'semester', 'academic_year'];
    $missing_fields = validateRequiredFields($_POST, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Check if PDF file is uploaded
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse('PDF file is required');
    }

    // Prepare routing information for smart upload
    $routing_info = [
        'title' => sanitizeInput($_POST['title']),
        'subject' => sanitizeInput($_POST['subject']),
        'semester' => sanitizeInput($_POST['semester']),
        'academic_year' => sanitizeInput($_POST['academic_year'])
    ];

    // Upload PDF file with smart routing
    $pdf_result = $file_upload->uploadPDFWithRouting($_FILES['pdf_file'], $routing_info);
    if (!$pdf_result['success']) {
        sendErrorResponse($pdf_result['message']);
    }

    $pdf_data = $pdf_result['data'];

    // Upload cover image if provided
    $cover_data = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $cover_result = $file_upload->uploadCoverImageWithRouting($_FILES['cover_image'], $routing_info);
        if ($cover_result['success']) {
            $cover_data = $cover_result['data'];
        }
        // Don't fail if cover upload fails, just log it
        else {
            logActivity("Cover upload failed: " . $cover_result['message'], 'WARNING');
        }
    }

    // Prepare book data
    $book_data = [
        'title' => sanitizeInput($_POST['title']),
        'author' => sanitizeInput($_POST['author']),
        'subject' => sanitizeInput($_POST['subject']),
        'semester' => sanitizeInput($_POST['semester']),
        'academic_year' => (int) sanitizeInput($_POST['academic_year']),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'edition' => sanitizeInput($_POST['edition'] ?? ''),
        'publication_date' => !empty($_POST['publication_date']) ? $_POST['publication_date'] : null,
        'publisher' => sanitizeInput($_POST['publisher'] ?? ''),
        'isbn' => sanitizeInput($_POST['isbn'] ?? ''),
        'source' => sanitizeInput($_POST['source'] ?? ''),
        'pdf_filename' => $pdf_data['filename'],
        'pdf_path' => $pdf_data['path'],
        'pdf_size' => $pdf_data['size'],
        'book_hash' => $pdf_data['hash'],
        'cover_image' => $cover_data ? $cover_data['path'] : null
    ];

    // Add extracted PDF metadata if available
    if (!empty($pdf_data['metadata'])) {
        $metadata = $pdf_data['metadata'];

        // Use extracted title if not provided manually
        if (empty($book_data['title']) && !empty($metadata['title'])) {
            $book_data['title'] = $metadata['title'];
        }

        // Use extracted author if not provided manually
        if (empty($book_data['author']) && !empty($metadata['author'])) {
            $book_data['author'] = $metadata['author'];
        }
    }

    // Create book record
    $book_id = $book->create($book_data);

    if ($book_id) {
        logActivity("Complete book uploaded: ID $book_id, Title: {$book_data['title']}, PDF: {$pdf_data['filename']}");

        // Return complete book data
        $created_book = $book->getById($book_id);
        sendSuccessResponse([
            'book' => $created_book,
            'pdf_info' => $pdf_data,
            'cover_info' => $cover_data
        ], 'Book uploaded successfully');
    } else {
        // Clean up uploaded files if database insert failed
        $file_upload->deleteFile($pdf_data['path']);
        if ($cover_data) {
            $file_upload->deleteFile($cover_data['path']);
        }
        sendErrorResponse('Failed to create book record', 500);
    }
}

/**
 * Handle PDF-only upload (returns file info for later book creation)
 */
function handlePDFOnlyUpload($file_upload) {
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse('PDF file is required');
    }

    // Check if routing information is provided
    $routing_info = [];
    if (!empty($_POST['title']) && !empty($_POST['subject']) && !empty($_POST['semester']) && !empty($_POST['academic_year'])) {
        $routing_info = [
            'title' => sanitizeInput($_POST['title']),
            'subject' => sanitizeInput($_POST['subject']),
            'semester' => sanitizeInput($_POST['semester']),
            'academic_year' => sanitizeInput($_POST['academic_year'])
        ];
    }

    $result = $file_upload->uploadPDFWithRouting($_FILES['pdf_file'], $routing_info);

    if ($result['success']) {
        logActivity("PDF uploaded: " . $result['data']['filename']);
        sendSuccessResponse($result['data'], 'PDF uploaded successfully');
    } else {
        sendErrorResponse($result['message']);
    }
}

/**
 * Handle cover image-only upload
 */
function handleCoverOnlyUpload($file_upload) {
    if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse('Cover image is required');
    }

    // Check if routing information is provided
    $routing_info = [];
    if (!empty($_POST['title']) && !empty($_POST['subject']) && !empty($_POST['semester']) && !empty($_POST['academic_year'])) {
        $routing_info = [
            'title' => sanitizeInput($_POST['title']),
            'subject' => sanitizeInput($_POST['subject']),
            'semester' => sanitizeInput($_POST['semester']),
            'academic_year' => sanitizeInput($_POST['academic_year'])
        ];
    }

    $result = $file_upload->uploadCoverImageWithRouting($_FILES['cover_image'], $routing_info);

    if ($result['success']) {
        logActivity("Cover image uploaded: " . $result['data']['filename']);
        sendSuccessResponse($result['data'], 'Cover image uploaded successfully');
    } else {
        sendErrorResponse($result['message']);
    }
}
?>