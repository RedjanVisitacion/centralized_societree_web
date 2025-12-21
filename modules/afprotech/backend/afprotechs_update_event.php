<?php
// Start output buffering and clean any existing output
ob_start();
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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

// Allow POST and PUT methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Only POST and PUT methods allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['event_id']) || empty($input['event_id'])) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Event ID is required'
    ]);
    exit;
}

// Convert event_id to integer
$input['event_id'] = (int)$input['event_id'];

try {
    // Check if event exists
    $check_sql = "SELECT event_id FROM afprotechs_events WHERE event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $input['event_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Event not found'
        ]);
        exit;
    }
    
    // Build dynamic update query
    $update_fields = [];
    $values = [];
    
    $allowed_fields = ['event_title', 'event_description', 'start_date', 'end_date', 'event_location'];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== null) {
            $value = trim($input[$field]);
            
            // Special handling for date fields
            if (($field === 'start_date' || $field === 'end_date') && $value) {
                // Ensure date is in YYYY-MM-DD format
                $date = DateTime::createFromFormat('Y-m-d', $value);
                if ($date) {
                    $value = $date->format('Y-m-d');
                } else {
                    // Try other common formats
                    $date = DateTime::createFromFormat('m/d/Y', $value) ?: DateTime::createFromFormat('d/m/Y', $value);
                    if ($date) {
                        $value = $date->format('Y-m-d');
                    }
                }
            }
            
            $update_fields[] = "$field = ?";
            $values[] = $value;
        }
    }
    
    if (empty($update_fields)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No valid fields to update'
        ]);
        exit;
    }
    
    // Add event_id for WHERE clause
    $values[] = $input['event_id'];
    
    $sql = "UPDATE afprotechs_events SET " . implode(', ', $update_fields) . " WHERE event_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
        exit;
    }
    
    // Create type string for bind_param (all strings except event_id which is int)
    $types = str_repeat('s', count($values) - 1) . 'i';
    
    // Debug: Log the SQL and values
    error_log("Update SQL: " . $sql);
    error_log("Values: " . print_r($values, true));
    error_log("Types: " . $types);
    
    $bind_result = $stmt->bind_param($types, ...$values);
    if (!$bind_result) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to bind parameters: ' . $stmt->error
        ]);
        exit;
    }
    
    $execute_result = $stmt->execute();
    if ($execute_result) {
        $affected_rows = $stmt->affected_rows;
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Event updated successfully',
            'affected_rows' => $affected_rows
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update event: ' . $stmt->error
        ]);
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