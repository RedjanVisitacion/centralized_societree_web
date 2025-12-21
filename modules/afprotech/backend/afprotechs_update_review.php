<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get form data
$feedback_id = $_POST['feedback_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$rating = $_POST['rating'] ?? 0;
$review_text = $_POST['review_text'] ?? '';

// Validate required fields
if (empty($feedback_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Feedback ID is required'
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

try {
    // Verify that the review belongs to the student
    $checkSql = "SELECT feedback_id FROM afprotechs_feedbacks WHERE feedback_id = ? AND student_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('is', $feedback_id, $student_id);
    $checkStmt->execute();
    $existingReview = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if (!$existingReview) {
        echo json_encode([
            'success' => false,
            'message' => 'Review not found or you do not have permission to edit this review'
        ]);
        exit;
    }
    
    // Update the review
    $sql = "UPDATE afprotechs_feedbacks SET rating = ?, review_text = ?, updated_at = CURRENT_TIMESTAMP WHERE feedback_id = ? AND student_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('isis', $rating, $review_text, $feedback_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Review updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update review: ' . $stmt->error
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