<?php
/**
 * Student Download Verification API
 * Handles OTP generation and verification for UCSI students
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/StudentVerification.php';
require_once __DIR__ . '/../classes/Download.php';

// Get database connection
$database = getDatabase();
$db = $database->getConnection();

if (!$db) {
    sendErrorResponse('Database connection failed', 500);
}

// Initialize models
$studentVerification = new StudentVerification($db);
$book = new Book($db);
$download = new Download($db);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handlePostRequest($studentVerification, $book);
            break;

        case 'GET':
            handleGetRequest($studentVerification);
            break;

        default:
            sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    logActivity("Download verification error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Verification failed', 500);
}

/**
 * Handle POST requests (OTP generation and verification)
 */
function handlePostRequest($studentVerification, $book) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'request_otp':
            handleOTPRequest($studentVerification, $book, $input);
            break;

        case 'verify_otp':
            handleOTPVerification($studentVerification, $input);
            break;

        default:
            sendErrorResponse('Invalid action. Use "request_otp" or "verify_otp"');
    }
}

/**
 * Handle OTP request
 */
function handleOTPRequest($studentVerification, $book, $input) {
    // Validate required fields
    $required_fields = ['email', 'book_id'];
    $missing_fields = validateRequiredFields($input, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    $email = sanitizeInput($input['email']);
    $book_id = (int)$input['book_id'];

    // Verify book exists and is active
    $book_data = $book->getById($book_id);
    if (!$book_data) {
        sendErrorResponse('Book not found', 404);
    }

    // Generate OTP
    $result = $studentVerification->generateDownloadOTP($email, $book_id);

    if ($result['success']) {
        sendSuccessResponse($result['data'], $result['message']);
    } else {
        sendErrorResponse($result['message'], 400);
    }
}

/**
 * Handle OTP verification
 */
function handleOTPVerification($studentVerification, $input) {
    // Validate required fields
    $required_fields = ['email', 'otp_code', 'book_id'];
    $missing_fields = validateRequiredFields($input, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    $email = sanitizeInput($input['email']);
    $otp_code = sanitizeInput($input['otp_code']);
    $book_id = (int)$input['book_id'];

    // Verify OTP
    $result = $studentVerification->verifyDownloadOTP($email, $otp_code, $book_id);

    if ($result['success']) {
        // Generate download token for secure download
        $download_token = bin2hex(random_bytes(32));

        // Store download token temporarily (valid for 5 minutes)
        $token_data = [
            'email' => $email,
            'book_id' => $book_id,
            'expires' => time() + 300 // 5 minutes
        ];

        // In production, store this in Redis or database
        // For now, we'll use session or return it directly
        session_start();
        $_SESSION['download_tokens'][$download_token] = $token_data;

        sendSuccessResponse([
            'download_token' => $download_token,
            'email' => $email,
            'book_id' => $book_id,
            'expires_in' => 300,
            'download_url' => "/api/secure_download?token=$download_token"
        ], 'OTP verified successfully. Download authorized.');
    } else {
        sendErrorResponse($result['message'], 400);
    }
}

/**
 * Handle GET requests (statistics and info)
 */
function handleGetRequest($studentVerification) {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'stats':
            $stats = $studentVerification->getStudentStats();
            sendSuccessResponse($stats, 'Statistics retrieved successfully');
            break;

        case 'cleanup':
            // Admin maintenance function
            $cleaned = $studentVerification->cleanupExpiredOTPs();
            sendSuccessResponse(['cleaned_count' => $cleaned], "Cleaned up $cleaned expired OTPs");
            break;

        default:
            sendSuccessResponse([
                'message' => 'UCSI Digital Library - Student Download Verification API',
                'version' => '1.0',
                'endpoints' => [
                    'POST /verify_download' => 'Request OTP or verify OTP',
                    'GET /verify_download?action=stats' => 'Get verification statistics'
                ],
                'actions' => [
                    'request_otp' => 'Generate OTP for download (requires: email, book_id)',
                    'verify_otp' => 'Verify OTP and get download token (requires: email, otp_code, book_id)'
                ]
            ], 'API information retrieved successfully');
    }
}
?>