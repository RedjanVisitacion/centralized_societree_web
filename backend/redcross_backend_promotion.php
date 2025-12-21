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
        return htmlspecialchars(trim($conn->real_escape_string($value)), ENT_QUOTES, 'UTF-8');
    }
}

$promo_message = '';

// Ensure the event registrations table exists before any operations
$create_table_sql = "
CREATE TABLE IF NOT EXISTS redcross_event_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    registration_notes TEXT,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'absent', 'cancelled') DEFAULT 'registered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration (campaign_id, member_id),
    FOREIGN KEY (campaign_id) REFERENCES redcross_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES redcross_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->query($create_table_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = rc_clean($conn, $_POST['title'] ?? '');
    $description = rc_clean($conn, $_POST['description'] ?? '');
    $event_link  = rc_clean($conn, $_POST['event_link'] ?? '');
    $image_path  = null;

    if ($title && $description) {
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . '/../assets/img/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                $promo_message = 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
            } elseif ($_FILES['image']['size'] > $max_size) {
                $promo_message = 'File too large. Maximum size is 5MB.';
            } else {
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['image']['name']));
                $target = $upload_dir . $filename;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image_path = 'assets/img/' . $filename;
                } else {
                    $promo_message = 'Failed to upload image.';
                }
            }
        }

        // Only proceed if no upload error occurred
        if (empty($promo_message)) {
            $stmt = $conn->prepare(
                "INSERT INTO redcross_campaigns (title, description, event_link, created_at)
                 VALUES (?, ?, ?, NOW())"
            );
            $stmt->bind_param('sss', $title, $description, $event_link);

            if ($stmt->execute()) {
                $promo_message = 'Campaign created successfully.';
            } else {
                $promo_message = 'Error creating campaign: ' . $conn->error;
            }

            $stmt->close();
        }
    } else {
        $promo_message = 'Title and description are required.';
    }
}

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_event') {
    $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
    $member_id = (int) ($_POST['member_id'] ?? 0);
    $registration_notes = rc_clean($conn, $_POST['registration_notes'] ?? '');
    
    if ($campaign_id > 0 && $member_id > 0) {
        // Check if already registered
        $check_stmt = $conn->prepare("SELECT id FROM redcross_event_registrations WHERE campaign_id = ? AND member_id = ?");
        $check_stmt->bind_param('ii', $campaign_id, $member_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $promo_message = 'Member is already registered for this event.';
        } else {
            // Register for event
            $register_stmt = $conn->prepare(
                "INSERT INTO redcross_event_registrations (campaign_id, member_id, registration_notes, registered_at, status) 
                 VALUES (?, ?, ?, NOW(), 'registered')"
            );
            $register_stmt->bind_param('iis', $campaign_id, $member_id, $registration_notes);
            
            if ($register_stmt->execute()) {
                $promo_message = 'Successfully registered for the event!';
            } else {
                $promo_message = 'Failed to register for event: ' . $conn->error;
            }
            $register_stmt->close();
        }
        $check_stmt->close();
    } else {
        $promo_message = 'Please select both an event and a member.';
    }
}

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    $registration_id = (int) ($_POST['registration_id'] ?? 0);
    $attendance_status = rc_clean($conn, $_POST['attendance_status'] ?? '');
    
    if ($registration_id > 0 && in_array($attendance_status, ['attended', 'absent', 'cancelled'])) {
        $attendance_stmt = $conn->prepare("UPDATE redcross_event_registrations SET status = ? WHERE id = ?");
        $attendance_stmt->bind_param('si', $attendance_status, $registration_id);
        
        if ($attendance_stmt->execute()) {
            $promo_message = 'Attendance status updated successfully.';
        } else {
            $promo_message = 'Failed to update attendance: ' . $conn->error;
        }
        $attendance_stmt->close();
    }
}

// View tracking removed since views column doesn't exist in database
// if (isset($_GET['view'])) {
//     $view_id = (int) $_GET['view'];
//     if ($view_id > 0) {
//         // Views tracking would go here if column existed
//     }
// }

// Fetch campaigns with registration counts
$campaigns = [];
$result = $conn->query("
    SELECT c.*, 
           COUNT(r.id) as registration_count,
           COUNT(CASE WHEN r.status = 'attended' THEN 1 END) as attended_count
    FROM redcross_campaigns c
    LEFT JOIN redcross_event_registrations r ON c.id = r.campaign_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    $result->free();
}

// Fetch active members for registration
$members = [];
$members_result = $conn->query("SELECT id, full_name, member_id, email FROM redcross_members WHERE status = 'active' ORDER BY full_name ASC");
if ($members_result) {
    while ($row = $members_result->fetch_assoc()) {
        $members[] = $row;
    }
    $members_result->free();
}

// Fetch event registrations for monitoring
$registrations = [];
$selected_campaign = (int) ($_GET['campaign_id'] ?? 0);

if ($selected_campaign > 0) {
    $reg_result = $conn->query("
        SELECT r.*, m.full_name, m.member_id, m.email, c.title as campaign_title
        FROM redcross_event_registrations r
        JOIN redcross_members m ON r.member_id = m.id
        JOIN redcross_campaigns c ON r.campaign_id = c.id
        WHERE r.campaign_id = {$selected_campaign}
        ORDER BY r.registered_at DESC
    ");
    if ($reg_result) {
        while ($row = $reg_result->fetch_assoc()) {
            $registrations[] = $row;
        }
        $reg_result->free();
    }
}

