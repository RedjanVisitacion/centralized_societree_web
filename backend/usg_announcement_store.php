<?php
// backend/usg_announcement_backend.php
require_once(__DIR__ . '/../db_connection.php');

// Define announcement types
$announcement_types = [
    'event' => 'Event',
    'cleaning' => 'Cleaning',
    'meeting' => 'Meeting',
    'seminar' => 'Seminar',
    'workshop' => 'Workshop',
    'maintenance' => 'Maintenance',
    'urgent' => 'Urgent',
    'important' => 'Important'
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_title'])) {
    $title = trim($_POST['announcement_title']);
    $content = trim($_POST['announcement_content']);
    $type = !empty($_POST['announcement_type']) ? $_POST['announcement_type'] : '';
    $datetime = !empty($_POST['announcement_datetime']) ? $_POST['announcement_datetime'] : date('Y-m-d H:i:s');
    
    // Validate inputs
    if (empty($title) || empty($content) || empty($type)) {
        $error = 'Title, type, and content are required';
    } elseif (!array_key_exists($type, $announcement_types)) {
        $error = 'Invalid announcement type selected';
    } else {
        try {
            // Use the existing $pdo connection from db_connection.php
            // Prepare SQL statement
            $sql = "INSERT INTO usg_announcement (announcement_title, announcement_type, announcement_content, announcement_datetime) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Execute statement
            if ($stmt->execute([$title, $type, $content, $datetime])) {
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