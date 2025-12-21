<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db_connection.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $student_id = isset($input['student_id']) ? trim($input['student_id']) : '';
    $notification_id = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
    $mark_all = isset($input['mark_all']) ? (bool)$input['mark_all'] : false;
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    
    if ($mark_all) {
        // Mark all notifications as read for this student
        $stmt = $pdo->prepare("
            UPDATE afprotech_notifications 
            SET is_read = TRUE, read_at = NOW() 
            WHERE student_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$student_id]);
        $affected_rows = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'message' => "Marked $affected_rows notifications as read",
            'marked_count' => $affected_rows
        ]);
        
    } else {
        // Mark specific notification as read
        if ($notification_id <= 0) {
            throw new Exception('Valid notification ID is required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE afprotech_notifications 
            SET is_read = TRUE, read_at = NOW() 
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$notification_id, $student_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Notification not found or already read');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification_id' => $notification_id
        ]);
    }
    
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark notification as read: ' . $e->getMessage()
    ]);
}
?>