<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Load AFPROTECH module configuration first
require_once __DIR__ . '/../config/config.php';

// Prefer the shared PDO connection if available (used by other modules)
$pdoConnection = null;
try {
    // Try to reuse the root db_connection.php (one level above modules/)
    $rootDbPath = realpath(__DIR__ . '/../../../db_connection.php');
    if ($rootDbPath && file_exists($rootDbPath)) {
        require_once $rootDbPath; // defines $pdo
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdoConnection = $pdo;
        }
    }
} catch (Throwable $t) {
    // Ignore and fall back to module-specific connection
}

/**
 * Fetch events using PDO (shared connection)
 */
function fetchAfprotechEventsWithPdo(PDO $pdo): array {
    // Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS afprotechs_events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            event_title VARCHAR(255) NOT NULL,
            event_description TEXT DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            event_location VARCHAR(255) DEFAULT NULL,
            event_status VARCHAR(50) DEFAULT 'Upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_start_date (start_date),
            INDEX idx_status (event_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Auto-update event statuses based on dates
    $pdo->exec("
        UPDATE afprotechs_events 
        SET event_status = CASE
            WHEN CURDATE() > DATE(end_date) THEN 'Finished'
            WHEN CURDATE() BETWEEN DATE(start_date) AND DATE(end_date) THEN 'Ongoing'
            WHEN DATE(start_date) BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 5 DAY THEN 'Upcoming'
            ELSE 'Upcoming'
        END
        WHERE start_date IS NOT NULL AND end_date IS NOT NULL
    ");

    // Seed sample data when empty (helps first-time mobile access)
    $eventCount = (int)$pdo->query("SELECT COUNT(*) FROM afprotechs_events")->fetchColumn();
    if ($eventCount === 0) {
        $sampleEvents = [
            [
                'title' => 'INTRAMURALS 2026',
                'description' => 'Intramurals 2025 is the annual athletic and team-building celebration bringing together students, faculty, and staff for a week of competitive sports, games, and activities.',
                'start_date' => '2025-12-01 08:00:00',
                'end_date' => '2025-12-01 17:00:00',
                'location' => 'LSPU Malvar',
                'status' => 'Upcoming'
            ],
            [
                'title' => 'SINUOY DAYS',
                'description' => 'Annual cultural festival celebrating local traditions and heritage with performances, exhibits, and community activities.',
                'start_date' => '2025-12-01 09:00:00',
                'end_date' => '2025-12-01 18:00:00',
                'location' => 'LSPU Malvar',
                'status' => 'Upcoming'
            ],
            [
                'title' => 'AFPROTECH General Assembly',
                'description' => 'Monthly general assembly meeting for all AFPROTECH members to discuss upcoming activities and organizational matters.',
                'start_date' => '2025-12-15 14:00:00',
                'end_date' => '2025-12-15 16:00:00',
                'location' => 'AFPROTECH Room',
                'status' => 'Upcoming'
            ]
        ];

        $insert = $pdo->prepare("
            INSERT INTO afprotechs_events (event_title, event_description, start_date, end_date, event_location, event_status)
            VALUES (:title, :description, :start_date, :end_date, :location, :status)
        ");

        foreach ($sampleEvents as $event) {
            $insert->execute([
                ':title' => $event['title'],
                ':description' => $event['description'],
                ':start_date' => $event['start_date'],
                ':end_date' => $event['end_date'],
                ':location' => $event['location'],
                ':status' => $event['status'],
            ]);
        }
    }

    // Check if status column exists for older databases
    $statusColumn = $pdo->query("SHOW COLUMNS FROM afprotechs_events LIKE 'event_status'")->fetchColumn();
    $sql = $statusColumn !== false
        ? "SELECT event_id, event_title, event_description, start_date, end_date, event_location, COALESCE(event_status, 'Upcoming') as event_status FROM afprotechs_events ORDER BY start_date ASC"
        : "SELECT event_id, event_title, event_description, start_date, end_date, event_location, 'Upcoming' as event_status FROM afprotechs_events ORDER BY start_date ASC";

    $stmt = $pdo->query($sql);
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'event_id' => (int)$row['event_id'],
            'event_title' => $row['event_title'],
            'event_description' => $row['event_description'],
            'start_date' => $row['start_date'] ? date('Y-m-d H:i:s', strtotime($row['start_date'])) : null,
            'end_date' => $row['end_date'] ? date('Y-m-d H:i:s', strtotime($row['end_date'])) : null,
            'event_location' => $row['event_location'],
            'event_status' => $row['event_status']
        ];
    }

    return $events;
}

// Try shared PDO connection first for better reliability on mobile
if ($pdoConnection instanceof PDO) {
    try {
        $events = fetchAfprotechEventsWithPdo($pdoConnection);
        echo json_encode([
            'success' => true,
            'data' => $events,
            'count' => count($events),
            'server' => 'AFPROTECH Events Backend (shared DB)',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } catch (Throwable $t) {
        // Fall back to module-specific connection below
    }
}

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Ensure the afprotechs_events table exists
    $createEventsTable = "
        CREATE TABLE IF NOT EXISTS afprotechs_events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            event_title VARCHAR(255) NOT NULL,
            event_description TEXT DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            event_location VARCHAR(255) DEFAULT NULL,
            event_status VARCHAR(50) DEFAULT 'Upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_start_date (start_date),
            INDEX idx_status (event_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createEventsTable);
    
    // Check if we have any events, if not create sample data
    $checkEvents = $conn->query("SELECT COUNT(*) as count FROM afprotechs_events");
    $eventCount = $checkEvents->fetch_assoc()['count'];
    
    if ($eventCount == 0) {
        // Insert sample events
        $sampleEvents = [
            [
                'title' => 'INTRAMURALS 2026',
                'description' => 'Intramurals 2025 is the annual athletic and team-building celebration bringing together students, faculty, and staff for a week of competitive sports, games, and activities.',
                'start_date' => '2025-12-01 08:00:00',
                'end_date' => '2025-12-01 17:00:00',
                'location' => 'LSPU Malvar',
                'status' => 'Upcoming'
            ],
            [
                'title' => 'SINUOY DAYS',
                'description' => 'Annual cultural festival celebrating local traditions and heritage with performances, exhibits, and community activities.',
                'start_date' => '2025-12-01 09:00:00',
                'end_date' => '2025-12-01 18:00:00',
                'location' => 'LSPU Malvar',
                'status' => 'Upcoming'
            ],
            [
                'title' => 'AFPROTECH General Assembly',
                'description' => 'Monthly general assembly meeting for all AFPROTECH members to discuss upcoming activities and organizational matters.',
                'start_date' => '2025-12-15 14:00:00',
                'end_date' => '2025-12-15 16:00:00',
                'location' => 'AFPROTECH Room',
                'status' => 'Upcoming'
            ]
        ];
        
        foreach ($sampleEvents as $event) {
            $stmt = $conn->prepare("INSERT INTO afprotechs_events (event_title, event_description, start_date, end_date, event_location, event_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $event['title'], $event['description'], $event['start_date'], $event['end_date'], $event['location'], $event['status']);
            $stmt->execute();
        }
    }
    
    // First, check if event_status column exists
    $column_check = $conn->query("SHOW COLUMNS FROM afprotechs_events LIKE 'event_status'");
    $has_status_column = $column_check && $column_check->num_rows > 0;
    
    // Fetch all events from afprotechs_events table
    if ($has_status_column) {
        $sql = "
            SELECT 
                event_id,
                event_title,
                event_description,
                start_date,
                end_date,
                event_location,
                COALESCE(event_status, 'Upcoming') as event_status
            FROM afprotechs_events 
            ORDER BY start_date ASC
        ";
    } else {
        $sql = "
            SELECT 
                event_id,
                event_title,
                event_description,
                start_date,
                end_date,
                event_location,
                'Upcoming' as event_status
            FROM afprotechs_events 
            ORDER BY start_date ASC
        ";
    }
    
    $result = $conn->query($sql);
    
    if ($result) {
        $events = [];
        
        while ($row = $result->fetch_assoc()) {
            // Format dates for mobile app
            $start_date = $row['start_date'] ? date('Y-m-d H:i:s', strtotime($row['start_date'])) : null;
            $end_date = $row['end_date'] ? date('Y-m-d H:i:s', strtotime($row['end_date'])) : null;
            
            $events[] = [
                'event_id' => (int)$row['event_id'],
                'event_title' => $row['event_title'],
                'event_description' => $row['event_description'],
                'start_date' => $start_date,
                'end_date' => $end_date,
                'event_location' => $row['event_location'],
                'event_status' => $row['event_status']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $events,
            'count' => count($events),
            'server' => 'AFPROTECH Events Backend',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch events: ' . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>