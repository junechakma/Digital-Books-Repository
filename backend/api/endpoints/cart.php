<?php
/**
 * Cart API Endpoint
 * Handles student cart operations for multiple book downloads
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/CartSystem.php';
require_once __DIR__ . '/../classes/Book.php';

// Get database connection
$database = getDatabase();
$db = $database->getConnection();

if (!$db) {
    sendErrorResponse('Database connection failed', 500);
}

// Initialize models
$cartSystem = new CartSystem($db);
$book = new Book($db);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($cartSystem);
            break;

        case 'POST':
            handlePostRequest($cartSystem, $book);
            break;

        case 'DELETE':
            handleDeleteRequest($cartSystem);
            break;

        default:
            sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    logActivity("Cart API error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Cart operation failed', 500);
}

/**
 * Handle GET requests (get cart contents, stats)
 */
function handleGetRequest($cartSystem) {
    $action = $_GET['action'] ?? 'get_cart';
    $session_id = $_GET['session_id'] ?? '';

    switch ($action) {
        case 'get_cart':
            if (empty($session_id)) {
                sendErrorResponse('Session ID is required');
            }

            $cart_contents = $cartSystem->getCartContents($session_id);
            sendSuccessResponse($cart_contents, 'Cart contents retrieved successfully');
            break;

        case 'get_count':
            if (empty($session_id)) {
                sendErrorResponse('Session ID is required');
            }

            $count = $cartSystem->getCartCount($session_id);
            sendSuccessResponse(['count' => $count], 'Cart count retrieved successfully');
            break;

        case 'stats':
            $stats = $cartSystem->getCartStats();
            sendSuccessResponse($stats, 'Cart statistics retrieved successfully');
            break;

        case 'cleanup':
            // Admin maintenance function
            $cleaned = $cartSystem->cleanupExpiredCarts();
            sendSuccessResponse(['cleaned_count' => $cleaned], "Cleaned up $cleaned expired cart items");
            break;

        default:
            sendSuccessResponse([
                'message' => 'UCSI Digital Library - Cart API',
                'version' => '1.0',
                'endpoints' => [
                    'GET /cart?action=get_cart&session_id=xxx' => 'Get cart contents',
                    'GET /cart?action=get_count&session_id=xxx' => 'Get cart item count',
                    'POST /cart' => 'Add book to cart',
                    'DELETE /cart' => 'Remove book from cart or clear cart'
                ]
            ], 'Cart API information retrieved successfully');
    }
}

/**
 * Handle POST requests (add to cart)
 */
function handlePostRequest($cartSystem, $book) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    $action = $input['action'] ?? 'add_to_cart';

    switch ($action) {
        case 'add_to_cart':
            // Validate required fields
            $required_fields = ['session_id', 'book_id'];
            $missing_fields = validateRequiredFields($input, $required_fields);

            if (!empty($missing_fields)) {
                sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
            }

            $session_id = sanitizeInput($input['session_id']);
            $book_id = (int)$input['book_id'];

            // Verify book exists
            $book_data = $book->getById($book_id);
            if (!$book_data) {
                sendErrorResponse('Book not found', 404);
            }

            $result = $cartSystem->addToCart($session_id, $book_id);

            if ($result['success']) {
                sendSuccessResponse($result['data'], $result['message']);
            } else {
                sendErrorResponse($result['message'], 400);
            }
            break;

        default:
            sendErrorResponse('Invalid action. Use "add_to_cart"');
    }
}

/**
 * Handle DELETE requests (remove from cart, clear cart)
 */
function handleDeleteRequest($cartSystem) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    $action = $input['action'] ?? 'remove_from_cart';

    switch ($action) {
        case 'remove_from_cart':
            // Validate required fields
            $required_fields = ['session_id', 'book_id'];
            $missing_fields = validateRequiredFields($input, $required_fields);

            if (!empty($missing_fields)) {
                sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
            }

            $session_id = sanitizeInput($input['session_id']);
            $book_id = (int)$input['book_id'];

            $result = $cartSystem->removeFromCart($session_id, $book_id);

            if ($result['success']) {
                sendSuccessResponse($result['data'], $result['message']);
            } else {
                sendErrorResponse($result['message'], 400);
            }
            break;

        case 'clear_cart':
            // Validate required fields
            $required_fields = ['session_id'];
            $missing_fields = validateRequiredFields($input, $required_fields);

            if (!empty($missing_fields)) {
                sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
            }

            $session_id = sanitizeInput($input['session_id']);

            $success = $cartSystem->clearCart($session_id);

            if ($success) {
                sendSuccessResponse([], 'Cart cleared successfully');
            } else {
                sendErrorResponse('Failed to clear cart', 500);
            }
            break;

        default:
            sendErrorResponse('Invalid action. Use "remove_from_cart" or "clear_cart"');
    }
}
?>