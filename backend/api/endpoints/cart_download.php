<?php
/**
 * Cart Download API Endpoint
 * Handles OTP verification and download for entire cart
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/CartSystem.php';
require_once __DIR__ . '/../classes/StudentVerification.php';
require_once __DIR__ . '/../classes/Download.php';

// Get database connection
$database = getDatabase();
$db = $database->getConnection();

if (!$db) {
    sendErrorResponse('Database connection failed', 500);
}

// Initialize models
$cartSystem = new CartSystem($db);
$studentVerification = new StudentVerification($db);
$download = new Download($db);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handlePostRequest($cartSystem, $studentVerification);
            break;

        case 'GET':
            handleGetRequest($cartSystem);
            break;

        default:
            sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    logActivity("Cart download error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Cart download failed', 500);
}

/**
 * Handle POST requests (initialize download, verify OTP)
 */
function handlePostRequest($cartSystem, $studentVerification) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'initialize_download':
            handleInitializeDownload($cartSystem, $studentVerification, $input);
            break;

        case 'verify_otp':
            handleVerifyOTP($cartSystem, $studentVerification, $input);
            break;

        default:
            sendErrorResponse('Invalid action. Use "initialize_download" or "verify_otp"');
    }
}

/**
 * Handle cart download initialization
 */
function handleInitializeDownload($cartSystem, $studentVerification, $input) {
    // Validate required fields
    $required_fields = ['session_id', 'student_email'];
    $missing_fields = validateRequiredFields($input, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    $session_id = sanitizeInput($input['session_id']);
    $student_email = sanitizeInput($input['student_email']);

    // Validate UCSI email
    $email_validation = $studentVerification->validateUCSIEmail($student_email);
    if (!$email_validation['valid']) {
        sendErrorResponse($email_validation['message'], 400);
    }

    // Initialize cart download session
    $result = $cartSystem->initializeCartDownload($session_id, $student_email);

    if (!$result['success']) {
        sendErrorResponse($result['message'], 400);
    }

    $download_session_id = $result['data']['download_session_id'];

    // Generate OTP for cart download
    $otp_code = sprintf('%06d', mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Store OTP
    $otp_query = "INSERT INTO otp_verifications
                 (email, otp_code, purpose, reference_id, expires_at)
                 VALUES (?, ?, 'cart_download', ?, ?)";
    $otp_stmt = $GLOBALS['db']->prepare($otp_query);
    $otp_success = $otp_stmt->execute([$student_email, $otp_code, $download_session_id, $expires_at]);

    if (!$otp_success) {
        sendErrorResponse('Failed to generate verification code', 500);
    }

    // Update download session with OTP
    $cartSystem->updateSessionWithOTP($download_session_id, $otp_code);

    // Send OTP email
    $email_result = sendCartDownloadOTP($student_email, $otp_code, $result['data']['books'], $result['data']['total_books']);

    if ($email_result['success']) {
        logActivity("Cart download OTP sent: $student_email, Session: $download_session_id, Books: {$result['data']['total_books']}");

        sendSuccessResponse([
            'download_session_id' => $download_session_id,
            'student_email' => $student_email,
            'total_books' => $result['data']['total_books'],
            'books' => $result['data']['books'],
            'expires_in' => 600 // 10 minutes
        ], "Verification code sent to $student_email for {$result['data']['total_books']} books.");
    } else {
        sendErrorResponse('Failed to send verification email: ' . $email_result['message'], 500);
    }
}

/**
 * Handle OTP verification for cart download
 */
function handleVerifyOTP($cartSystem, $studentVerification, $input) {
    // Validate required fields
    $required_fields = ['download_session_id', 'otp_code'];
    $missing_fields = validateRequiredFields($input, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    $download_session_id = sanitizeInput($input['download_session_id']);
    $otp_code = sanitizeInput($input['otp_code']);

    // Get download session
    $session = $cartSystem->getDownloadSession($download_session_id);
    if (!$session) {
        sendErrorResponse('Invalid or expired download session', 404);
    }

    // Verify OTP
    $otp_query = "SELECT * FROM otp_verifications
                 WHERE reference_id = ? AND otp_code = ? AND purpose = 'cart_download'
                 AND is_used = FALSE AND expires_at > NOW()";
    $otp_stmt = $GLOBALS['db']->prepare($otp_query);
    $otp_stmt->execute([$download_session_id, $otp_code]);
    $otp_record = $otp_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp_record) {
        sendErrorResponse('Invalid or expired verification code. Please request a new one.', 400);
    }

    // Mark OTP as used
    $mark_used_query = "UPDATE otp_verifications SET is_used = TRUE, used_at = NOW() WHERE id = ?";
    $mark_used_stmt = $GLOBALS['db']->prepare($mark_used_query);
    $mark_used_stmt->execute([$otp_record['id']]);

    // Generate download token
    $download_token = $cartSystem->verifySessionOTP($download_session_id, $otp_code);

    if (!$download_token) {
        sendErrorResponse('Failed to verify OTP', 500);
    }

    logActivity("Cart download OTP verified: {$session['student_email']}, Session: $download_session_id, Books: {$session['total_books']}");

    sendSuccessResponse([
        'download_token' => $download_token,
        'download_session_id' => $download_session_id,
        'student_email' => $session['student_email'],
        'total_books' => $session['total_books'],
        'books' => $session['books_data'],
        'download_url' => "/api/cart_download_files?token=$download_token",
        'expires_in' => 900 // 15 minutes to download
    ], 'Verification successful. You can now download your books.');
}

/**
 * Handle GET requests (session info, statistics)
 */
function handleGetRequest($cartSystem) {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'session_info':
            $download_session_id = $_GET['session_id'] ?? '';
            if (empty($download_session_id)) {
                sendErrorResponse('Session ID is required');
            }

            $session = $cartSystem->getDownloadSession($download_session_id);
            if (!$session) {
                sendErrorResponse('Session not found', 404);
            }

            sendSuccessResponse([
                'session_id' => $session['id'],
                'student_email' => $session['student_email'],
                'total_books' => $session['total_books'],
                'books' => $session['books_data'],
                'otp_verified' => (bool)$session['otp_verified'],
                'downloaded' => !is_null($session['downloaded_at']),
                'expires_at' => $session['expires_at']
            ], 'Session information retrieved successfully');
            break;

        default:
            sendSuccessResponse([
                'message' => 'UCSI Digital Library - Cart Download API',
                'version' => '1.0',
                'endpoints' => [
                    'POST /cart_download' => 'Initialize download or verify OTP',
                    'GET /cart_download?action=session_info&session_id=xxx' => 'Get session info'
                ],
                'actions' => [
                    'initialize_download' => 'Start cart download process (requires: session_id, student_email)',
                    'verify_otp' => 'Verify OTP and get download token (requires: download_session_id, otp_code)'
                ]
            ], 'Cart download API information retrieved successfully');
    }
}

/**
 * Send cart download OTP email
 * @param string $email
 * @param string $otp_code
 * @param array $books
 * @param int $total_books
 * @return array Result
 */
function sendCartDownloadOTP($email, $otp_code, $books, $total_books) {
    $student_id = explode('@', $email)[0];
    $subject = "UCSI Digital Library - Download Verification for $total_books Books";

    // Create books list for email
    $books_list = '';
    foreach (array_slice($books, 0, 5) as $book) { // Show max 5 books in email
        $books_list .= "• {$book['title']} by {$book['author']}\n";
    }

    if (count($books) > 5) {
        $books_list .= "• ... and " . (count($books) - 5) . " more books\n";
    }

    $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Cart Download Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ca1d26; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            color: #ca1d26;
            text-align: center;
            padding: 15px;
            background-color: #fff;
            border: 2px dashed #ca1d26;
            margin: 20px 0;
        }
        .books-list {
            background-color: #fff;
            padding: 15px;
            border-left: 4px solid #ca1d26;
            margin: 15px 0;
        }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📚 CART DOWNLOAD VERIFICATION</h1>
            <p>UCSI Digital Library - Bangladesh Branch</p>
        </div>
        <div class='content'>
            <h2>Download Verification Code</h2>
            <p>Dear Student (ID: $student_id),</p>
            <p>You have requested to download <strong>$total_books books</strong> from your cart:</p>

            <div class='books-list'>
                <h3>Books in Your Cart:</h3>
                <pre>$books_list</pre>
            </div>

            <p>Your verification code is:</p>
            <div class='otp-code'>$otp_code</div>

            <p><strong>Important:</strong></p>
            <ul>
                <li>This code is valid for 10 minutes only</li>
                <li>Do not share this code with anyone</li>
                <li>Use this code to download all books at once</li>
                <li>After download, your cart will be automatically cleared</li>
            </ul>

            <p>If you did not request this download, please ignore this email.</p>
        </div>
        <div class='footer'>
            <p>UCSI Digital Library - Bangladesh Branch<br>
            This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: UCSI Digital Library <noreply@ucsi.edu.my>',
        'Reply-To: library@ucsi.edu.my',
        'X-Mailer: PHP/' . phpversion()
    ];

    // For development/testing
    if (function_exists('mail')) {
        $success = mail($email, $subject, $message, implode("\r\n", $headers));
        if ($success) {
            return ['success' => true, 'message' => 'Cart download OTP email sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email via mail() function'];
        }
    } else {
        // For development - log the OTP
        logActivity("CART DOWNLOAD OTP for $email: $otp_code ($total_books books)", 'INFO');
        return [
            'success' => true,
            'message' => 'OTP generated successfully (check server logs for development)',
            'dev_otp' => $otp_code
        ];
    }
}
?>