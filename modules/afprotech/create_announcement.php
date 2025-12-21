<?php
// Start output buffering and clean any existing output
ob_start();
ob_clean();

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}

try {
    $title = $_POST['announcement_title'] ?? '';
    $content = $_POST['announcement_content'] ?? '';
    $datetime = $_POST['announcement_datetime'] ?? '';

    // Validate required fields
    if (!$title || !$content || !$datetime) {
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Required fields missing"
        ]);
        exit;
    }

    // Create table if it doesn't exist
    $createTable = "
        CREATE TABLE IF NOT EXISTS afprotechs_announcements (
            announcement_id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_title VARCHAR(255) NOT NULL,
            announcement_content TEXT NOT NULL,
            announcement_datetime DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if (!$conn->query($createTable)) {
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create table: " . $conn->error
        ]);
        exit;
    }

    // Insert announcement (using MySQLi syntax)
    $stmt = $conn->prepare("INSERT INTO afprotechs_announcements (announcement_title, announcement_content, announcement_datetime) VALUES (?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sss", $title, $content, $datetime);
        
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode([
                "status" => "success",
                "message" => "Announcement created successfully",
                "saved_data" => [
                    "title" => $title,
                    "datetime" => $datetime
                ]
            ]);
        } else {
            ob_clean();
            echo json_encode([
                "status" => "error",
                "message" => "Failed to insert announcement",
                "error" => $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Failed to prepare statement",
            "error" => $conn->error
        ]);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => "Exception occurred",
        "error" => $e->getMessage()
    ]);
}

// Close connection and end output buffering
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
ob_end_flush();
?>

