<?php
/**
 * Global Configuration for UCSI Digital Library API
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Configuration
header('Access-Control-Allow-Origin: http://localhost:5173'); // Vite dev server
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type to JSON by default
header('Content-Type: application/json; charset=utf-8');

// Configuration constants
define('BASE_PATH', dirname(dirname(__DIR__)));
define('UPLOAD_PATH', BASE_PATH . '/uploads/books/');
define('COVER_PATH', BASE_PATH . '/uploads/covers/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['application/pdf']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Security settings
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Pagination settings
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Include required files
require_once __DIR__ . '/database.php';

/**
 * Send JSON response
 * @param array $data
 * @param int $status_code
 */
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send error response
 * @param string $message
 * @param int $status_code
 * @param array $details
 */
function sendErrorResponse($message, $status_code = 400, $details = []) {
    $response = [
        'success' => false,
        'error' => $message,
        'details' => $details
    ];
    sendJsonResponse($response, $status_code);
}

/**
 * Send success response
 * @param array $data
 * @param string $message
 */
function sendSuccessResponse($data = [], $message = 'Success') {
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data
    ];
    sendJsonResponse($response);
}

/**
 * Validate required fields
 * @param array $data
 * @param array $required_fields
 * @return array Missing fields
 */
function validateRequiredFields($data, $required_fields) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random filename
 * @param string $original_name
 * @return string
 */
function generateSecureFilename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $uuid = bin2hex(random_bytes(16));
    $timestamp = time();
    return $uuid . '_' . $timestamp . '.' . strtolower($extension);
}

/**
 * Get client IP address
 * @return string
 */
function getClientIP() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Log activity
 * @param string $message
 * @param string $level
 */
function logActivity($message, $level = 'INFO') {
    $log_file = BASE_PATH . '/logs/api.log';
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $log_entry = "[$timestamp] [$level] [IP: $ip] $message" . PHP_EOL;

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>