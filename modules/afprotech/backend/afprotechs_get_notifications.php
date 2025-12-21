<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db_connection.php';

try {
    // Get student ID from query parameter
    $student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    
    // Get optional parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    
    // Validate limit
    if ($limit > 100) $limit = 100;
    if ($limit < 1) $limit = 10;
    
    // Build query
    $where_clause = "WHERE student_id = ?";
    $params = [$student_id];
    
    if ($unread_only) {
        $where_clause .= " AND is_read = FALSE";
    }
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT 
            id,
            notification_type,
            title,
            message,
            related_id,
            is_read,
            created_at,
            read_at
        FROM afprotech_notifications 
        $where_clause
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM afprotech_notifications 
        $where_clause
    ");
    $count_stmt->execute([$student_id]);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get unread count
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread 
        FROM afprotech_notifications 
        WHERE student_id = ? AND is_read = FALSE
    ");
    $unread_stmt->execute([$student_id]);
    $unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Format notifications for mobile app
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'id' => (int)$notification['id'],
            'type' => $notification['notification_type'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'related_id' => $notification['related_id'],
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at'],
            'read_at' => $notification['read_at'],
            'time_ago' => timeAgo($notification['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_notifications,
        'pagination' => [
            'total' => (int)$total_count,
            'unread' => (int)$unread_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get notifications: ' . $e->getMessage(),
        'data' => []
    ]);
}

// Helper function to calculate time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}
?>