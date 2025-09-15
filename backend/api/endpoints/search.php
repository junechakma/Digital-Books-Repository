<?php
/**
 * Search API Endpoint
 * Enhanced search functionality for books
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Book.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

try {
    // Get database connection
    $database = getDatabase();
    $db = $database->getConnection();

    if (!$db) {
        sendErrorResponse('Database connection failed', 500);
    }

    // Initialize Book model
    $book = new Book($db);

    // Get search parameters
    $query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
    $subject = isset($_GET['subject']) ? sanitizeInput($_GET['subject']) : '';
    $author = isset($_GET['author']) ? sanitizeInput($_GET['author']) : '';
    $limit = isset($_GET['limit']) ? min(MAX_PAGE_SIZE, max(1, (int)$_GET['limit'])) : DEFAULT_PAGE_SIZE;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    // Handle different search types
    if (!empty($query)) {
        // Full text search
        $results = performFullTextSearch($book, $query, $subject, $author, $page, $limit);
    } elseif (!empty($subject)) {
        // Subject-based search
        $results = performSubjectSearch($book, $subject, $page, $limit);
    } elseif (!empty($author)) {
        // Author-based search
        $results = performAuthorSearch($book, $author, $page, $limit);
    } else {
        // Return suggestions or recent books
        $results = [
            'books' => $book->getRecent($limit),
            'suggestions' => getSuggestions($book),
            'pagination' => [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => count($book->getRecent($limit)),
                'total_pages' => 1
            ]
        ];
    }

    sendSuccessResponse($results, 'Search completed successfully');

} catch (Exception $e) {
    logActivity("Search error: " . $e->getMessage(), 'ERROR');
    sendErrorResponse('Search failed', 500);
}

/**
 * Perform full text search
 */
function performFullTextSearch($book, $query, $subject = '', $author = '', $page = 1, $limit = 20) {
    // Use the Book model's getAll method with search parameters
    $results = $book->getAll($page, $limit, $query, $subject);

    // If author filter is specified, further filter results
    if (!empty($author)) {
        $filtered_books = array_filter($results['books'], function($book_item) use ($author) {
            return stripos($book_item['author'], $author) !== false;
        });
        $results['books'] = array_values($filtered_books);

        // Update pagination for filtered results
        $results['pagination']['total'] = count($filtered_books);
        $results['pagination']['total_pages'] = ceil(count($filtered_books) / $limit);
    }

    // Add search metadata
    $results['search_info'] = [
        'query' => $query,
        'subject' => $subject,
        'author' => $author,
        'search_type' => 'full_text'
    ];

    return $results;
}

/**
 * Perform subject-based search
 */
function performSubjectSearch($book, $subject, $page = 1, $limit = 20) {
    $offset = ($page - 1) * $limit;
    $books = $book->getBySubject($subject);

    // Apply pagination manually
    $total = count($books);
    $books = array_slice($books, $offset, $limit);

    return [
        'books' => $books,
        'search_info' => [
            'subject' => $subject,
            'search_type' => 'subject'
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ];
}

/**
 * Perform author-based search
 */
function performAuthorSearch($book, $author, $page = 1, $limit = 20) {
    // Use search functionality with author as query
    $books = $book->search($author, $limit * 3); // Get more to filter

    // Filter to books where author matches more closely
    $filtered_books = array_filter($books, function($book_item) use ($author) {
        return stripos($book_item['author'], $author) !== false;
    });

    // Apply pagination
    $total = count($filtered_books);
    $offset = ($page - 1) * $limit;
    $books = array_slice($filtered_books, $offset, $limit);

    return [
        'books' => array_values($books),
        'search_info' => [
            'author' => $author,
            'search_type' => 'author'
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ];
}

/**
 * Get search suggestions
 */
function getSuggestions($book) {
    $suggestions = [];

    // Get popular subjects
    $stats = $book->getStats();
    $subjects = array_slice($stats['books_by_subject'], 0, 5);

    // Get recent books for title suggestions
    $recent_books = $book->getRecent(5);

    return [
        'popular_subjects' => $subjects,
        'recent_titles' => array_map(function($book_item) {
            return [
                'id' => $book_item['id'],
                'title' => $book_item['title'],
                'author' => $book_item['author']
            ];
        }, $recent_books)
    ];
}
?>