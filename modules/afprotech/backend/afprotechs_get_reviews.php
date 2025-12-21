<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use AFPROTECH's own database connection
require_once __DIR__ . '/../config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Get product ID from query parameter
$product_id = $_GET['product_id'] ?? '';
$current_student_id = $_GET['current_student_id'] ?? '';

if (empty($product_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

try {
    // Check if feedbacks table exists
    $tableCheck = "SHOW TABLES LIKE 'afprotechs_feedbacks'";
    $result = $conn->query($tableCheck);
    
    if ($result->num_rows == 0) {
        // Table doesn't exist, return empty reviews
        echo json_encode([
            'success' => true,
            'data' => [],
            'average_rating' => 0,
            'total_reviews' => 0,
            'user_review' => null
        ]);
        exit;
    }
    
    // Get reviews for the product
    $sql = "SELECT feedback_id, student_id, customer_name, rating, review_text, created_at, updated_at 
            FROM afprotechs_feedbacks 
            WHERE product_id = ? 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'SQL prepare failed: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    $total_rating = 0;
    $review_count = 0;
    $user_review = null;
    
    while ($row = $result->fetch_assoc()) {
        $review_data = [
            'review_id' => $row['feedback_id'],
            'student_id' => $row['student_id'],
            'customer_name' => $row['customer_name'],
            'rating' => (int)$row['rating'],
            'review_text' => $row['review_text'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'can_edit' => ($current_student_id === $row['student_id'])
        ];
        
        $reviews[] = $review_data;
        
        // Check if this is the current user's review (get the first one for the user_review field)
        if ($current_student_id === $row['student_id'] && $user_review === null) {
            $user_review = $review_data;
        }
        
        $total_rating += (int)$row['rating'];
        $review_count++;
    }
    
    $average_rating = $review_count > 0 ? round($total_rating / $review_count, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => $reviews,
        'average_rating' => $average_rating,
        'total_reviews' => $review_count,
        'user_review' => $user_review
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>