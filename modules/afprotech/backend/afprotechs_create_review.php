<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use AFPROTECH's own database connection
require_once __DIR__ . '/../config/config.php';
// Also include main database connection for student lookup
require_once __DIR__ . '/../../../db_connection.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get form data
$product_id = $_POST['product_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$rating = $_POST['rating'] ?? 0;
$review_text = $_POST['review_text'] ?? '';

// Validate required fields
if (empty($product_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

if (empty(trim($student_id))) {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required'
    ]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Rating must be between 1 and 5'
    ]);
    exit;
}

if (empty(trim($review_text))) {
    echo json_encode([
        'success' => false,
        'message' => 'Review text is required'
    ]);
    exit;
}

// Look up student information from main database
$student_name = '';
try {
    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name FROM student WHERE id_number = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if ($student) {
        $student_name = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']);
        // Clean up extra spaces
        $student_name = preg_replace('/\s+/', ' ', $student_name);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID not found in database'
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error looking up student information: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Create feedbacks table if it doesn't exist
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS afprotechs_feedbacks (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            student_id VARCHAR(20) NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES afprotechs_products(product_id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_product (student_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($createTableSql);
    
    // Check if student has already reviewed this product
    $checkSql = "SELECT feedback_id FROM afprotechs_feedbacks WHERE student_id = ? AND product_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('si', $student_id, $product_id);
    $checkStmt->execute();
    $existingReview = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existingReview) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already reviewed this product. You can edit your existing review.'
        ]);
        exit;
    }
    
    // Insert new review
    $sql = "INSERT INTO afprotechs_feedbacks (product_id, student_id, customer_name, rating, review_text) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('issis', $product_id, $student_id, $student_name, $rating, $review_text);
    
    if ($stmt->execute()) {
        $feedback_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'feedback_id' => $feedback_id,
            'customer_name' => $student_name
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit review: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>