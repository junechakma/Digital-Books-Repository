<?php
/**
 * API Testing Script for UCSI Digital Library Backend
 * Run this script to test all API endpoints
 */

// Configuration
$base_url = 'http://localhost/digital-books-repository/backend/api';
$test_results = [];

echo "🧪 UCSI Digital Library API Testing\n";
echo "===================================\n\n";

/**
 * Make HTTP request and return response
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers)
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

/**
 * Test endpoint and record results
 */
function testEndpoint($name, $url, $method = 'GET', $data = null, $expected_code = 200) {
    global $test_results;

    echo "Testing: $name\n";
    echo "URL: $url\n";
    echo "Method: $method\n";

    $result = makeRequest($url, $method, $data);

    $success = ($result['http_code'] == $expected_code && empty($result['error']));

    $test_results[] = [
        'name' => $name,
        'success' => $success,
        'http_code' => $result['http_code'],
        'expected_code' => $expected_code,
        'error' => $result['error']
    ];

    if ($success) {
        echo "✅ PASS (HTTP {$result['http_code']})\n";

        // Try to decode JSON response
        $decoded = json_decode($result['response'], true);
        if ($decoded && isset($decoded['success'])) {
            echo "   Response: " . ($decoded['success'] ? 'Success' : 'Failed') . "\n";
            if (isset($decoded['message'])) {
                echo "   Message: {$decoded['message']}\n";
            }
        }
    } else {
        echo "❌ FAIL (HTTP {$result['http_code']}, Expected: $expected_code)\n";
        if ($result['error']) {
            echo "   Error: {$result['error']}\n";
        }
        echo "   Response: {$result['response']}\n";
    }

    echo "\n";

    return $success ? json_decode($result['response'], true) : null;
}

// Test 1: Database Connection & Basic Books Endpoint
echo "1. Testing Database Connection\n";
echo "------------------------------\n";
$books_response = testEndpoint(
    'Get All Books',
    "$base_url/books",
    'GET'
);

// Test 2: Book Statistics
echo "2. Testing Book Statistics\n";
echo "--------------------------\n";
testEndpoint(
    'Get Book Statistics',
    "$base_url/books?stats=true",
    'GET'
);

// Test 3: Get Subjects
echo "3. Testing Subjects Endpoint\n";
echo "----------------------------\n";
testEndpoint(
    'Get All Subjects',
    "$base_url/books?subjects=true",
    'GET'
);

// Test 4: Search Functionality
echo "4. Testing Search Functionality\n";
echo "-------------------------------\n";
testEndpoint(
    'Search Books',
    "$base_url/search?q=computer",
    'GET'
);

testEndpoint(
    'Search by Subject',
    "$base_url/search?subject=Computer Science",
    'GET'
);

// Test 5: Individual Book Retrieval
echo "5. Testing Individual Book Retrieval\n";
echo "------------------------------------\n";
testEndpoint(
    'Get Book by ID',
    "$base_url/books?id=1",
    'GET'
);

testEndpoint(
    'Get Non-existent Book',
    "$base_url/books?id=99999",
    'GET',
    null,
    404  // Expecting 404 for non-existent book
);

// Test 6: Create New Book (POST)
echo "6. Testing Book Creation\n";
echo "------------------------\n";
$new_book_data = [
    'title' => 'Test API Book',
    'author' => 'API Tester',
    'subject' => 'Computer Science',
    'description' => 'Book created via API test',
    'pdf_filename' => 'test-api-book.pdf',
    'pdf_path' => 'uploads/books/2024/test-api-book.pdf',
    'pdf_size' => 1024000,
    'edition' => '1st Edition',
    'publisher' => 'Test Publisher',
    'isbn' => '978-0123456789'
];

$created_book = testEndpoint(
    'Create New Book',
    "$base_url/books",
    'POST',
    $new_book_data
);

$created_book_id = null;
if ($created_book && isset($created_book['data']['id'])) {
    $created_book_id = $created_book['data']['id'];
    echo "   Created Book ID: $created_book_id\n\n";
}

// Test 7: Update Book (PUT)
if ($created_book_id) {
    echo "7. Testing Book Update\n";
    echo "----------------------\n";
    $update_data = [
        'description' => 'Updated description via API test',
        'edition' => '2nd Edition'
    ];

    testEndpoint(
        'Update Book',
        "$base_url/books?id=$created_book_id",
        'PUT',
        $update_data
    );
}

// Test 8: Pagination
echo "8. Testing Pagination\n";
echo "---------------------\n";
testEndpoint(
    'Get Books Page 1',
    "$base_url/books?page=1&limit=2",
    'GET'
);

// Test 9: File Upload (Mock test - no actual file)
echo "9. Testing Upload Endpoint\n";
echo "--------------------------\n";
testEndpoint(
    'Upload Endpoint (No File)',
    "$base_url/upload",
    'POST',
    null,
    400  // Expecting 400 because no file is uploaded
);

// Test 10: Download Endpoint
echo "10. Testing Download Endpoint\n";
echo "-----------------------------\n";
testEndpoint(
    'Download Non-existent Book',
    "$base_url/download?id=99999",
    'GET',
    null,
    404  // Expecting 404 for non-existent book
);

if ($created_book_id) {
    // This will fail because no actual PDF file exists, but it tests the endpoint
    testEndpoint(
        'Download Test Book (No File)',
        "$base_url/download?id=$created_book_id",
        'GET',
        null,
        404  // Expecting 404 because PDF file doesn't exist
    );
}

// Test 11: Delete Book (Soft Delete)
if ($created_book_id) {
    echo "11. Testing Book Deletion\n";
    echo "-------------------------\n";
    testEndpoint(
        'Soft Delete Book',
        "$base_url/books?id=$created_book_id",
        'DELETE'
    );

    // Verify deletion
    testEndpoint(
        'Verify Deleted Book Not Found',
        "$base_url/books?id=$created_book_id",
        'GET',
        null,
        404  // Should return 404 after soft delete
    );
}

// Test Results Summary
echo "\n🏆 TEST RESULTS SUMMARY\n";
echo "========================\n";

$total_tests = count($test_results);
$passed_tests = array_filter($test_results, function($test) { return $test['success']; });
$passed_count = count($passed_tests);
$failed_count = $total_tests - $passed_count;

echo "Total Tests: $total_tests\n";
echo "Passed: $passed_count\n";
echo "Failed: $failed_count\n";
echo "Success Rate: " . round(($passed_count / $total_tests) * 100, 1) . "%\n\n";

if ($failed_count > 0) {
    echo "❌ FAILED TESTS:\n";
    echo "-----------------\n";
    foreach ($test_results as $test) {
        if (!$test['success']) {
            echo "- {$test['name']} (HTTP {$test['http_code']}, Expected: {$test['expected_code']})\n";
            if ($test['error']) {
                echo "  Error: {$test['error']}\n";
            }
        }
    }
    echo "\n";
}

echo "✅ PASSED TESTS:\n";
echo "-----------------\n";
foreach ($test_results as $test) {
    if ($test['success']) {
        echo "- {$test['name']}\n";
    }
}

echo "\n📋 NEXT STEPS:\n";
echo "===============\n";
echo "1. Fix any failed tests above\n";
echo "2. Test file upload with actual PDF files\n";
echo "3. Test with your React frontend\n";
echo "4. Set up proper authentication if needed\n";
echo "5. Deploy to production environment\n";

if ($passed_count === $total_tests) {
    echo "\n🎉 ALL TESTS PASSED! Your API is ready to use.\n";
} else {
    echo "\n⚠️  Some tests failed. Please check the issues above.\n";
}

echo "\nTo run individual tests, use curl commands like:\n";
echo "curl '$base_url/books'\n";
echo "curl '$base_url/search?q=computer'\n";
echo "\nFor file upload testing, use a tool like Postman or write a custom test.\n";
?>