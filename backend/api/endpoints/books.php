<?php
/**
 * Books API Endpoint
 * Handles CRUD operations for books
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Book.php';

// Get database connection
$database = getDatabase();
$db = $database->getConnection();

if (!$db) {
    sendErrorResponse('Database connection failed', 500);
}

// Initialize Book model
$book = new Book($db);

// Get request method and handle routing
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$query_params = $_GET;

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($book, $query_params);
            break;

        case 'POST':
            handlePostRequest($book);
            break;

        case 'PUT':
            handlePutRequest($book, $query_params);
            break;

        case 'DELETE':
            handleDeleteRequest($book, $query_params);
            break;

        default:
            sendErrorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    logActivity("Books API error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Internal server error', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($book, $params) {
    // Get single book by ID
    if (isset($params['id'])) {
        $book_data = $book->getById($params['id']);
        if ($book_data) {
            sendSuccessResponse($book_data, 'Book retrieved successfully');
        } else {
            sendErrorResponse('Book not found', 404);
        }
        return;
    }

    // Get books by subject
    if (isset($params['subject']) && !isset($params['search'])) {
        $subject = sanitizeInput($params['subject']);
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $books = $book->getBySubject($subject, $limit);
        sendSuccessResponse($books, 'Books retrieved successfully');
        return;
    }

    // Search books
    if (isset($params['search'])) {
        $search = sanitizeInput($params['search']);
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $books = $book->search($search, $limit);
        sendSuccessResponse($books, 'Search results retrieved successfully');
        return;
    }

    // Get recent books
    if (isset($params['recent'])) {
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $books = $book->getRecent($limit);
        sendSuccessResponse($books, 'Recent books retrieved successfully');
        return;
    }

    // Get book statistics
    if (isset($params['stats'])) {
        $stats = $book->getStats();
        sendSuccessResponse($stats, 'Statistics retrieved successfully');
        return;
    }

    // Get all subjects
    if (isset($params['subjects'])) {
        $subjects = $book->getSubjects();
        sendSuccessResponse($subjects, 'Subjects retrieved successfully');
        return;
    }

    // Get all books with pagination and filtering
    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $limit = isset($params['limit']) ? min(MAX_PAGE_SIZE, max(1, (int)$params['limit'])) : DEFAULT_PAGE_SIZE;
    $search = isset($params['q']) ? sanitizeInput($params['q']) : '';
    $subject = isset($params['filter_subject']) ? sanitizeInput($params['filter_subject']) : '';

    $result = $book->getAll($page, $limit, $search, $subject);
    sendSuccessResponse($result, 'Books retrieved successfully');
}

/**
 * Handle POST requests (Create new book)
 */
function handlePostRequest($book) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    // Validate required fields
    $required_fields = ['title', 'author', 'subject', 'pdf_filename', 'pdf_path'];
    $missing_fields = validateRequiredFields($input, $required_fields);

    if (!empty($missing_fields)) {
        sendErrorResponse('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Sanitize input
    $data = sanitizeInput($input);

    // Create book
    $book_id = $book->create($data);

    if ($book_id) {
        logActivity("Book created: ID $book_id, Title: {$data['title']}");
        sendSuccessResponse(['id' => $book_id], 'Book created successfully');
    } else {
        sendErrorResponse('Failed to create book', 500);
    }
}

/**
 * Handle PUT requests (Update book)
 */
function handlePutRequest($book, $params) {
    if (!isset($params['id'])) {
        sendErrorResponse('Book ID is required');
    }

    $book_id = (int)$params['id'];

    // Check if book exists
    $existing_book = $book->getById($book_id);
    if (!$existing_book) {
        sendErrorResponse('Book not found', 404);
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }

    // Sanitize input
    $data = sanitizeInput($input);

    // Update book
    $success = $book->update($book_id, $data);

    if ($success) {
        logActivity("Book updated: ID $book_id, Title: {$existing_book['title']}");
        sendSuccessResponse([], 'Book updated successfully');
    } else {
        sendErrorResponse('Failed to update book', 500);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($book, $params) {
    if (!isset($params['id'])) {
        sendErrorResponse('Book ID is required');
    }

    $book_id = (int)$params['id'];

    // Check if book exists
    $existing_book = $book->getById($book_id);
    if (!$existing_book) {
        sendErrorResponse('Book not found', 404);
    }

    // Determine if hard delete is requested
    $hard_delete = isset($params['hard']) && $params['hard'] === 'true';

    if ($hard_delete) {
        $success = $book->hardDelete($book_id);
        $message = 'Book permanently deleted';
    } else {
        $success = $book->delete($book_id);
        $message = 'Book deleted successfully';
    }

    if ($success) {
        logActivity("Book deleted: ID $book_id, Title: {$existing_book['title']}, Hard: " . ($hard_delete ? 'Yes' : 'No'));
        sendSuccessResponse([], $message);
    } else {
        sendErrorResponse('Failed to delete book', 500);
    }
}
?>