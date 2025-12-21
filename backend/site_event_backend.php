<?php
// site_event_backend.php
// Handles create / update / delete and fetch for SITE events
require_once(__DIR__ . '/../db_connection.php');

// Debug: Check if this file is being included and if POST data is received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received: " . print_r($_POST, true));
}

// Ensure the events table exists (helpful when DB doesn't yet contain it)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_event (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT NOT NULL,
        event_datetime DATETIME DEFAULT NULL,
        event_location VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Non-fatal; reading code later will surface DB error to UI
    $db_error = 'Error ensuring site_event table exists: ' . $e->getMessage();
}

// Ensure attendance table exists for events with VARCHAR columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_event_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        morning_in VARCHAR(20) DEFAULT NULL,
        morning_out VARCHAR(20) DEFAULT NULL,
        afternoon_in VARCHAR(20) DEFAULT NULL,
        afternoon_out VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES site_event(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // If table creation fails, try to modify existing columns
    try {
        $pdo->exec("ALTER TABLE site_event_attendance MODIFY COLUMN morning_in VARCHAR(20) DEFAULT NULL");
        $pdo->exec("ALTER TABLE site_event_attendance MODIFY COLUMN morning_out VARCHAR(20) DEFAULT NULL");
        $pdo->exec("ALTER TABLE site_event_attendance MODIFY COLUMN afternoon_in VARCHAR(20) DEFAULT NULL");
        $pdo->exec("ALTER TABLE site_event_attendance MODIFY COLUMN afternoon_out VARCHAR(20) DEFAULT NULL");
    } catch (PDOException $e2) {
        $db_error = 'Error with attendance table: ' . $e2->getMessage();
    }
}

// Ensure finalized flag columns exist so we can lock morning/afternoon sequences
try {
    // MySQL supports ADD COLUMN IF NOT EXISTS in recent versions; use try/catch to avoid errors on older versions
    $pdo->exec("ALTER TABLE site_event_attendance ADD COLUMN IF NOT EXISTS morning_finalized TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE site_event_attendance ADD COLUMN IF NOT EXISTS afternoon_finalized TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {
    // ignore — if DB version doesn't support IF NOT EXISTS, it's non-fatal here. We'll proceed.
}

// Detect whether finalized columns exist (some DB servers may not have been altered)
try {
    $currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'site_event_attendance' AND column_name IN ('morning_finalized','afternoon_finalized')");
    $colStmt->execute([$currentDb]);
    $foundCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasMorningFinalized = in_array('morning_finalized', $foundCols);
    $hasAfternoonFinalized = in_array('afternoon_finalized', $foundCols);
} catch (PDOException $e) {
    // If detection fails, assume flags are not present to avoid SQL errors
    $hasMorningFinalized = false;
    $hasAfternoonFinalized = false;
}

// Handle attendance mark/unmark requests (must be checked before create/update event handling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_event_id']) && isset($_POST['attendance_field'])) {
    error_log("ATTENDANCE HANDLER TRIGGERED!");
    $attendanceEventId = (int) $_POST['attendance_event_id'];
    $allowedFields = ['morning_in','morning_out','afternoon_in','afternoon_out'];
    $field = $_POST['attendance_field'];

    // Debug: Log the request
    error_log("Attendance request: event_id=$attendanceEventId, field=$field");

    if ($attendanceEventId <= 0 || !in_array($field, $allowedFields)) {
        $error = 'Invalid attendance request.';
        error_log("Invalid attendance request: event_id=$attendanceEventId, field=$field");
    } else {
        try {
            // Check current value first
            $checkStmt = $pdo->prepare("SELECT $field FROM site_event_attendance WHERE event_id = ?");
            $checkStmt->execute([$attendanceEventId]);
            $current = $checkStmt->fetchColumn();
            
            if ($current === 'activated') {
                // Turn OFF - set to NULL
                $updateStmt = $pdo->prepare("UPDATE site_event_attendance SET $field = NULL WHERE event_id = ?");
                $updateResult = $updateStmt->execute([$attendanceEventId]);
                $success = ucfirst(str_replace('_', ' ', $field)) . ' turned OFF.';
                error_log("Successfully turned OFF for event_id=$attendanceEventId, field=$field");
            } else {
                // Turn ON - set to 'activated'
                $updateStmt = $pdo->prepare("UPDATE site_event_attendance SET $field = 'activated' WHERE event_id = ?");
                $updateResult = $updateStmt->execute([$attendanceEventId]);
                
                if ($updateResult && $updateStmt->rowCount() > 0) {
                    $success = ucfirst(str_replace('_', ' ', $field)) . ' turned ON.';
                    error_log("Successfully turned ON existing row for event_id=$attendanceEventId, field=$field");
                } else {
                    // If no rows were updated, insert a new row
                    $insertStmt = $pdo->prepare("INSERT INTO site_event_attendance (event_id, $field) VALUES (?, 'activated')");
                    $insertResult = $insertStmt->execute([$attendanceEventId]);
                    
                    if ($insertResult) {
                        $success = ucfirst(str_replace('_', ' ', $field)) . ' turned ON.';
                        error_log("Successfully turned ON new row for event_id=$attendanceEventId, field=$field");
                    } else {
                        $error = 'Failed to turn ON attendance.';
                        error_log("Turn ON failed for event_id=$attendanceEventId, field=$field");
                    }
                }
            }
            
            error_log("About to redirect to: " . $_SERVER['PHP_SELF']);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $error = 'Attendance error: ' . $e->getMessage();
            error_log("Attendance error: " . $e->getMessage());
        }
    }
}

// Create new event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_title']) && empty($_POST['event_id'])) {
    $title = trim($_POST['event_title']);
    $description = trim($_POST['event_description'] ?? '');
    $datetime = !empty($_POST['event_datetime']) ? $_POST['event_datetime'] : null;
    $location = trim($_POST['event_location'] ?? '');

    if (empty($title) || empty($description)) {
        $error = 'Title and description are required.';
    } else {
        try {
            $sql = "INSERT INTO site_event (event_title, event_description, event_datetime, event_location) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$title, $description, $datetime, $location])) {
                // Get the newly inserted event id
                $newEventId = $pdo->lastInsertId();

                // Create attendance row for this event (template with empty times)
                try {
                    $attStmt = $pdo->prepare('INSERT INTO site_event_attendance (event_id) VALUES (?)');
                    $attStmt->execute([$newEventId]);
                } catch (PDOException $e) {
                    // log or set db_error but don't block creation
                    $db_error = 'Event created, but failed to create attendance template: ' . $e->getMessage();
                }

                $success = 'Event created successfully.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = 'Failed to create event.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Update event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id']) && !empty($_POST['event_id'])) {
    $id = (int) $_POST['event_id'];
    $title = trim($_POST['event_title'] ?? '');
    $description = trim($_POST['event_description'] ?? '');
    $datetime = !empty($_POST['event_datetime']) ? $_POST['event_datetime'] : null;
    $location = trim($_POST['event_location'] ?? '');

    if ($id <= 0 || empty($title) || empty($description)) {
        $error = 'Invalid input for update.';
    } else {
        try {
            $sql = "UPDATE site_event SET event_title = ?, event_description = ?, event_datetime = ?, event_location = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$title, $description, $datetime, $location, $id])) {
                $success = 'Event updated successfully.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = 'Failed to update event.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Delete event (supports both GET and POST requests)
if ((isset($_GET['delete_event_id']) && is_numeric($_GET['delete_event_id'])) || (isset($_POST['delete_event_id']) && is_numeric($_POST['delete_event_id']))) {
    $id = isset($_GET['delete_event_id']) ? (int) $_GET['delete_event_id'] : (int) $_POST['delete_event_id'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM site_event WHERE id = ?');
            if ($stmt->execute([$id])) {
                // Cascade delete will remove attendance because of FK ON DELETE CASCADE
                $success = 'Event deleted successfully.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = 'Failed to delete event.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid event id to delete.';
    }
}

// Fetch events for display
try {
    // Fetch events and related attendance (if any)
    // Build SELECT columns conditionally to support DBs that don't have finalized columns
    $attendanceCols = 'a.morning_in, a.morning_out, a.afternoon_in, a.afternoon_out';
    $attendanceCols .= $hasMorningFinalized ? ', a.morning_finalized' : ', 0 AS morning_finalized';
    $attendanceCols .= $hasAfternoonFinalized ? ', a.afternoon_finalized' : ', 0 AS afternoon_finalized';
    $attendanceCols .= ', a.id as attendance_id';

    $sql = "SELECT e.*, $attendanceCols FROM site_event e LEFT JOIN site_event_attendance a ON a.event_id = e.id ORDER BY e.event_datetime DESC, e.created_at DESC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
    $db_error = 'Error loading events: ' . $e->getMessage();
}

?>
