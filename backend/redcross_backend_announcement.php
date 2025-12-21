<?php
require_once __DIR__ . '/../db_connection.php';

// Ensure a mysqli connection named $conn exists for this backend
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        die('MySQLi connection failed: ' . $conn->connect_error);
    }
}

if (!function_exists('rc_clean')) {
    function rc_clean($conn, $value) {
        return htmlspecialchars(trim($conn->real_escape_string((string)$value)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_announcement_icon')) {
    function get_announcement_icon($type) {
        switch ($type) {
            case 'event':
                return 'calendar-event-fill';
            case 'verification':
                return 'shield-check-fill';
            case 'certificate':
                return 'award-fill';
            case 'general':
            default:
                return 'megaphone-fill';
        }
    }
}

if (!function_exists('get_priority_class')) {
    function get_priority_class($priority) {
        return $priority === 'high' ? 'warning' : 'secondary';
    }
}

if (!function_exists('format_target_audience')) {
    function format_target_audience($audience) {
        return ucfirst(str_replace('_', ' ', $audience));
    }
}

$announce_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = rc_clean($conn, $_POST['title'] ?? '');
    $body     = rc_clean($conn, $_POST['body'] ?? '');
    $schedule = $_POST['scheduled_at'] ?? '';

    // Map old "schedule" field to optional expires_at in the new schema
    $expires_at = null;
    if ($schedule !== '') {
        $ts = strtotime($schedule);
        if ($ts !== false) {
            $expires_at = date('Y-m-d H:i:s', $ts);
        }
    }

    if ($title && $body) {
        // Insert using new redcross_announcements schema
        // We default to a general announcement for all audiences with normal priority.
        $announcement_type = 'general';
        $target_audience   = 'all';
        $priority          = 'normal';
        $is_active         = 1;

        $stmt = $conn->prepare(
            "INSERT INTO redcross_announcements (
                title,
                body,
                announcement_type,
                target_audience,
                priority,
                created_by,
                created_at,
                expires_at,
                is_active
            ) VALUES (?, ?, ?, ?, ?, NULL, NOW(), ?, ?)"
        );
        $stmt->bind_param(
            'ssssssi',
            $title,
            $body,
            $announcement_type,
            $target_audience,
            $priority,
            $expires_at,
            $is_active
        );

        if ($stmt->execute()) {
            $announce_message = 'Announcement created.';
        } else {
            $announce_message = 'Error: ' . $conn->error;
        }

        $stmt->close();
    } else {
        $announce_message = 'Title and content are required.';
    }
}

// Fetch all announcements from database
$all_announcements = [];
$recent_announcements = [];
$old_announcements = [];

// Get all active announcements from database (including expired ones for display)
$query = "SELECT id, title, body, announcement_type, target_audience, priority, 
                 created_by, created_at, expires_at, updated_at, is_active
          FROM redcross_announcements
          WHERE is_active = 1
          ORDER BY 
             CASE priority 
                 WHEN 'high' THEN 1 
                 WHEN 'normal' THEN 2 
                 ELSE 3 
             END,
             created_at DESC";

$all_result = $conn->query($query);

// Check if query executed successfully
if (!$all_result) {
    $announce_message = "Database error: Unable to fetch announcements. " . $conn->error;
}

if ($all_result) {
    while ($row = $all_result->fetch_assoc()) {
        $all_announcements[] = $row;
        
        // Separate into recent (last 30 days) and old
        $created_date = new DateTime($row['created_at']);
        $thirty_days_ago = new DateTime('-30 days');
        
        if ($created_date >= $thirty_days_ago) {
            $recent_announcements[] = $row;
        } else {
            $old_announcements[] = $row;
        }
    }
    $all_result->free();
    
    // Debug: Add message about fetched data
    if (empty($all_announcements)) {
        $announce_message = "No announcements found in database. Please check if data exists and is_active = 1.";
    }
} else {
    $announce_message = "Failed to execute database query.";
}

// Keep the original $announcements array for backward compatibility
$announcements = $all_announcements;

// Get announcement statistics
$stats = [
    'total' => count($all_announcements),
    'recent' => count($recent_announcements),
    'old' => count($old_announcements),
    'high_priority' => 0,
    'by_type' => []
];

// Calculate statistics
foreach ($all_announcements as $announcement) {
    if ($announcement['priority'] === 'high') {
        $stats['high_priority']++;
    }
    
    $type = $announcement['announcement_type'];
    if (!isset($stats['by_type'][$type])) {
        $stats['by_type'][$type] = 0;
    }
    $stats['by_type'][$type]++;
}

