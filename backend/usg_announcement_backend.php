<?php
// Database connection and form processing at the TOP of the file
require_once(__DIR__ . '/../db_connection.php');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_title'])) {
    $title = trim($_POST['announcement_title']);
    $content = trim($_POST['announcement_content']);
    $datetime = !empty($_POST['announcement_datetime']) ? $_POST['announcement_datetime'] : date('Y-m-d H:i:s');
    
    // Validate inputs
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        try {
            // Prepare SQL statement
            $sql = "INSERT INTO usg_announcement (announcement_title, announcement_content, announcement_datetime) 
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Execute statement
            if ($stmt->execute([$title, $content, $datetime])) {
                $success = 'Announcement saved successfully!';
                // Refresh the page to show new announcement
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = 'Error saving announcement';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch existing announcements to display
try {
    $stmt = $pdo->query("SELECT * FROM usg_announcement ORDER BY announcement_datetime DESC");
    $announcements = $stmt->fetchAll();
} catch(PDOException $e) {
    $announcements = [];
    $db_error = 'Error loading announcements: ' . $e->getMessage();
}
?>