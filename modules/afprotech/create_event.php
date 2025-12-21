<?php
// Start output buffering and clean any existing output
ob_start();
ob_clean();

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Use AFPROTECH's own database connection
require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    ob_clean(); // Clear any output
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $event_location = trim($_POST['event_location'] ?? '');

    // Validate required fields
    if (empty($event_title)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Event title is required']);
        exit;
    }

    if (empty($event_description)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Event description is required']);
        exit;
    }

    if (empty($start_date)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Start date is required']);
        exit;
    }

    if (empty($end_date)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'End date is required']);
        exit;
    }

    // Validate date format and logic
    $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);

    if (!$start_date_obj || !$end_date_obj) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }

    if ($end_date_obj < $start_date_obj) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'End date cannot be before start date']);
        exit;
    }

    // Create events table if it doesn't exist (matching existing schema)
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS afprotechs_events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            event_title VARCHAR(255) NOT NULL,
            event_description TEXT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            event_location VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if (!@$conn->query($create_table_sql)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create events table: ' . $conn->error]);
        exit;
    }

    // Add event_status column if it doesn't exist
    $add_status_column = "
        ALTER TABLE afprotechs_events 
        ADD COLUMN IF NOT EXISTS event_status VARCHAR(50) DEFAULT 'Upcoming'
    ";
    @$conn->query($add_status_column); // Don't fail if this doesn't work

    // Insert new event (without event_status since it might not exist in existing table)
    $insert_sql = "
        INSERT INTO afprotechs_events (event_title, event_description, start_date, end_date, event_location) 
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sssss', $event_title, $event_description, $start_date, $end_date, $event_location);

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
        echo json_encode(['success' => false, 'message' => 'Failed to create event: ' . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    // Ensure connection is closed
    if (isset($conn)) {
        $conn->close();
    }
    // End output buffering
    ob_end_flush();
}
?>