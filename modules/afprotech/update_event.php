<?php
// Use AFPROTECH's own database connection
require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $event_id = trim($_POST['event_id'] ?? '');
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $event_location = trim($_POST['event_location'] ?? '');

    // Validate required fields
    if (empty($event_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Event ID is required']);
        exit;
    }

    if (empty($event_title)) {
        echo json_encode(['status' => 'error', 'message' => 'Event title is required']);
        exit;
    }

    if (empty($event_description)) {
        echo json_encode(['status' => 'error', 'message' => 'Event description is required']);
        exit;
    }

    if (empty($start_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Start date is required']);
        exit;
    }

    if (empty($end_date)) {
        echo json_encode(['status' => 'error', 'message' => 'End date is required']);
        exit;
    }

    // Validate date format and logic
    $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);

    if (!$start_date_obj || !$end_date_obj) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
        exit;
    }

    if ($end_date_obj < $start_date_obj) {
        echo json_encode(['status' => 'error', 'message' => 'End date cannot be before start date']);
        exit;
    }

    // Check if event exists
    $check_sql = "SELECT event_id FROM afprotechs_events WHERE event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Event not found']);
        exit;
    }

    // Update event (without updated_at since it might not exist in existing table)
    $update_sql = "
        UPDATE afprotechs_events 
        SET event_title = ?, event_description = ?, start_date = ?, end_date = ?, event_location = ?
        WHERE event_id = ?
    ";

    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement']);
        exit;
    }

    $stmt->bind_param("sssssi", $event_title, $event_description, $start_date, $end_date, $event_location, $event_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Event updated successfully',
            'event_id' => $event_id,
            'debug' => [
                'title' => $event_title,
                'description' => $event_description,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'location' => $event_location
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update event']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>