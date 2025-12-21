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
            'message' => 'Review not found or you do not have permission to delete this review'
        ]);
        exit;
    }
    
    // Delete the review
    $sql = "DELETE FROM afprotechs_feedbacks WHERE feedback_id = ? AND student_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('is', $feedback_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete review: ' . $stmt->error
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