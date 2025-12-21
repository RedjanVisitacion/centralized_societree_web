<?php
// Start output buffering and clean any existing output
ob_start();
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Use AFPROTECH's own database connection (not the main db_connection.php)
require_once __DIR__ . '/../config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Get event ID from URL parameter or JSON input
$event_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['event_id'])) {
    $event_id = (int)$_GET['event_id'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = isset($input['event_id']) ? (int)$input['event_id'] : null;
}

if (!$event_id) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Event ID is required'
    ]);
    exit;
}

try {
    // Check if event exists
    $check_sql = "SELECT event_id, event_title FROM afprotechs_events WHERE event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $event_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Event not found'
        ]);
        exit;
    }
    
    $event = $result->fetch_assoc();
    
    // Check if there are attendance records for this event
    $attendance_check_sql = "SELECT COUNT(*) as count FROM afprotechs_attendance WHERE event_id = ?";
    $attendance_stmt = $conn->prepare($attendance_check_sql);
    $attendance_stmt->bind_param('i', $event_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance_count = $attendance_result->fetch_assoc()['count'];
    
    if ($attendance_count > 0) {
        // Start transaction to delete attendance records first, then the event
        $conn->begin_transaction();
        
        try {
            // Delete attendance records first
            $delete_attendance_sql = "DELETE FROM afprotechs_attendance WHERE event_id = ?";
            $delete_attendance_stmt = $conn->prepare($delete_attendance_sql);
            $delete_attendance_stmt->bind_param('i', $event_id);
            
            if (!$delete_attendance_stmt->execute()) {
                throw new Exception('Failed to delete attendance records: ' . $delete_attendance_stmt->error);
            }
            
            // Now delete the event
            $delete_event_sql = "DELETE FROM afprotechs_events WHERE event_id = ?";
            $delete_event_stmt = $conn->prepare($delete_event_sql);
            $delete_event_stmt->bind_param('i', $event_id);
            
            if (!$delete_event_stmt->execute()) {
                throw new Exception('Failed to delete event: ' . $delete_event_stmt->error);
            }
            
            // Commit the transaction
            $conn->commit();
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Event and related attendance records deleted successfully',
                'deleted_event' => $event['event_title'],
                'deleted_attendance_count' => $attendance_count
            ]);
            
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            throw $e;
        }
    } else {
        // No attendance records, safe to delete event directly
        $delete_sql = "DELETE FROM afprotechs_events WHERE event_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param('i', $event_id);
        
        if ($delete_stmt->execute()) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Event deleted successfully',
                'deleted_event' => $event['event_title']
            ]);
        } else {
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete event: ' . $delete_stmt->error
            ]);
        }
        
        $delete_stmt->close();
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    // Ensure connection is closed
    if (isset($conn)) {
        $conn->close();
    }
    // End output buffering
    ob_end_flush();
}
?>