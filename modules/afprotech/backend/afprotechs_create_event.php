<?php
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['event_title', 'event_description', 'start_date', 'end_date'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => "Field '$field' is required"
        ]);
        exit;
    }
}

try {
    // Prepare and execute insert statement
    $sql = "INSERT INTO afprotechs_events (event_title, event_description, start_date, end_date, event_location) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $event_location = $input['event_location'] ?? '';
    
    $stmt->bind_param(
        'sssss',
        $input['event_title'],
        $input['event_description'],
        $input['start_date'],
        $input['end_date'],
        $event_location
    );
    
    if ($stmt->execute()) {
        $event_id = $conn->insert_id;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'event_id' => $event_id
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create event: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>