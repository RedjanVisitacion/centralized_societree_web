<?php
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
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Ensure announcements table exists
    $createAnnouncementsTable = "
        CREATE TABLE IF NOT EXISTS afprotechs_announcements (
            announcement_id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_title VARCHAR(255) NOT NULL,
            announcement_content TEXT NOT NULL,
            announcement_datetime DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createAnnouncementsTable);
    
    // Check if we have any announcements, if not create sample data
    $checkAnnouncements = $conn->query("SELECT COUNT(*) as count FROM afprotechs_announcements");
    $announcementCount = $checkAnnouncements->fetch_assoc()['count'];
    
    if ($announcementCount == 0) {
        // Insert sample announcements
        $sampleAnnouncements = [
            [
                'title' => 'Welcome to AFPROTECH',
                'content' => 'Welcome to the Association of Food Processing and Technology Students. We are excited to have you join our community!',
                'datetime' => '2025-12-10 21:00:00'
            ],
            [
                'title' => 'Event Registration Open',
                'content' => 'Registration for upcoming events is now open. Please check the events section for more details and registration links.',
                'datetime' => '2025-12-11 10:00:00'
            ],
            [
                'title' => 'Monthly Meeting Reminder',
                'content' => 'Don\'t forget about our monthly general assembly meeting this Friday. All members are encouraged to attend.',
                'datetime' => '2025-12-12 15:00:00'
            ]
        ];
        
        foreach ($sampleAnnouncements as $announcement) {
            $stmt = $conn->prepare("INSERT INTO afprotechs_announcements (announcement_title, announcement_content, announcement_datetime) VALUES (?, ?, ?)");
            $stmt->execute([$announcement['title'], $announcement['content'], $announcement['datetime']]);
        }
    }
    
    // Backfill missing audit columns for older tables
    try {
        $conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {
        // Column might already exist
    }
    try {
        $conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Fetch all announcements from afprotechs_announcements table
    $sql = "
        SELECT 
            announcement_id,
            announcement_title,
            announcement_content,
            announcement_datetime,
            created_at
        FROM afprotechs_announcements 
        ORDER BY announcement_datetime DESC, created_at DESC
    ";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $announcements = [];
        
        while ($row = $result->fetch_assoc()) {
            // Format dates for mobile app
            $announcement_datetime = $row['announcement_datetime'] ? date('Y-m-d H:i:s', strtotime($row['announcement_datetime'])) : null;
            $created_at = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : null;
            
            $announcements[] = [
                'announcement_id' => (int)$row['announcement_id'],
                'announcement_title' => $row['announcement_title'],
                'announcement_content' => $row['announcement_content'],
                'announcement_datetime' => $announcement_datetime,
                'created_at' => $created_at
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $announcements,
            'count' => count($announcements),
            'server' => 'AFPROTECH Announcements Backend',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch announcements'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>