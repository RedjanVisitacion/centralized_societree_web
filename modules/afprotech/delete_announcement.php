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
    $id = $_POST['announcement_id'] ?? '';

    if (!$id) {
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Announcement ID is required"
        ]);
        exit;
    }

    // Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS afprotechs_announcements (
            announcement_id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_title VARCHAR(255) NOT NULL,
            announcement_content TEXT NOT NULL,
            announcement_datetime DATETIME NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $conn->prepare("DELETE FROM afprotechs_announcements WHERE announcement_id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode([
                "status" => "success",
                "message" => "Announcement deleted successfully"
            ]);
        } else {
            ob_clean();
            echo json_encode([
                "status" => "error",
                "message" => "Failed to delete announcement",
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

