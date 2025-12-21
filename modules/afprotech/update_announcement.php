<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}

try {
    $id = $_POST['announcement_id'] ?? '';
    $title = $_POST['announcement_title'] ?? '';
    $content = $_POST['announcement_content'] ?? '';
    $datetime = $_POST['announcement_datetime'] ?? '';

    if (!$id || !$title || !$content || !$datetime) {
        echo json_encode([
            "status" => "error",
            "message" => "Required fields missing"
        ]);
        exit;
    }

    // Ensure table exists and has audit columns
    $conn->query("
        CREATE TABLE IF NOT EXISTS afprotechs_announcements (
            announcement_id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_title VARCHAR(255) NOT NULL,
            announcement_content TEXT NOT NULL,
            announcement_datetime DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $stmt = $conn->prepare("
        UPDATE afprotechs_announcements
        SET announcement_title = ?, announcement_content = ?, announcement_datetime = ?
        WHERE announcement_id = ?
    ");

    $stmt->bind_param("sssi", $title, $content, $datetime, $id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Announcement updated successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update announcement",
            "error" => $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Exception occurred",
        "error" => $e->getMessage()
    ]);
}
?>

