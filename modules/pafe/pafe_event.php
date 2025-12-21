<?php
require_once '../../db_connection.php';

// Create tables if they don't exist
try {
    // Events table
    $createEventsTable = "CREATE TABLE IF NOT EXISTS pafe_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        event_time TIME NOT NULL,
        location VARCHAR(255) NOT NULL,
        morning_session_locked BOOLEAN DEFAULT FALSE,
        afternoon_session_locked BOOLEAN DEFAULT FALSE,
        morning_auto_lock_time TIME NULL,
        afternoon_auto_lock_time TIME NULL,
        auto_lock_enabled BOOLEAN DEFAULT FALSE,
        qr_code_data VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($createEventsTable);
    
    // Add missing columns to existing table if they don't exist
    try {
        // Check if auto_lock_enabled column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM pafe_events LIKE 'auto_lock_enabled'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pafe_events ADD COLUMN auto_lock_enabled BOOLEAN DEFAULT FALSE");
        }
        
        // Check if morning_auto_lock_time column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM pafe_events LIKE 'morning_auto_lock_time'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pafe_events ADD COLUMN morning_auto_lock_time TIME NULL");
        }
        
        // Check if afternoon_auto_lock_time column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM pafe_events LIKE 'afternoon_auto_lock_time'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pafe_events ADD COLUMN afternoon_auto_lock_time TIME NULL");
        }
    } catch (PDOException $e) {
        // Columns might already exist, continue silently
    }
    
    // Attendance table
    $createAttendanceTable = "CREATE TABLE IF NOT EXISTS pafe_event_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        student_id INT NOT NULL,
        session_type ENUM('morning', 'afternoon') NOT NULL,
        attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES pafe_events(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (event_id, student_id, session_type)
    )";
    $pdo->exec($createAttendanceTable);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Auto-lock sessions based on time settings
function checkAndAutoLockSessions($pdo) {
    try {
        $current_time = date('H:i:s');
        $current_date = date('Y-m-d');
        
        // Get events that have auto-lock enabled and are happening today
        $stmt = $pdo->prepare("SELECT * FROM pafe_events WHERE auto_lock_enabled = 1 AND event_date = ?");
        $stmt->execute([$current_date]);
        $events = $stmt->fetchAll();
        
        foreach ($events as $event) {
            $updated = false;
            
            // Check morning session auto-lock
            if (!$event['morning_session_locked'] && 
                isset($event['morning_auto_lock_time']) && $event['morning_auto_lock_time'] && 
                $current_time >= $event['morning_auto_lock_time']) {
                
                $update_stmt = $pdo->prepare("UPDATE pafe_events SET morning_session_locked = 1 WHERE id = ?");
                $update_stmt->execute([$event['id']]);
                $updated = true;
            }
            
            // Check afternoon session auto-lock
            if (!$event['afternoon_session_locked'] && 
                isset($event['afternoon_auto_lock_time']) && $event['afternoon_auto_lock_time'] && 
                $current_time >= $event['afternoon_auto_lock_time']) {
                
                $update_stmt = $pdo->prepare("UPDATE pafe_events SET afternoon_session_locked = 1 WHERE id = ?");
                $update_stmt->execute([$event['id']]);
                $updated = true;
            }
        }
    } catch (PDOException $e) {
        // Log error but don't display to user
        error_log("Auto-lock error: " . $e->getMessage());
    }
}

// Run auto-lock check
checkAndAutoLockSessions($pdo);

// Handle update auto-lock settings
if (isset($_POST['update_auto_lock'])) {
    $event_id = (int)$_POST['event_id'];
    $auto_lock_enabled = isset($_POST['auto_lock_enabled']) ? 1 : 0;
    $morning_auto_lock_time = !empty($_POST['morning_auto_lock_time']) ? $_POST['morning_auto_lock_time'] : null;
    $afternoon_auto_lock_time = !empty($_POST['afternoon_auto_lock_time']) ? $_POST['afternoon_auto_lock_time'] : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE pafe_events SET auto_lock_enabled = ?, morning_auto_lock_time = ?, afternoon_auto_lock_time = ? WHERE id = ?");
        $stmt->execute([$auto_lock_enabled, $morning_auto_lock_time, $afternoon_auto_lock_time, $event_id]);
        
        $success_message = "Auto-lock settings updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating auto-lock settings: " . $e->getMessage();
    }
}

// Handle toggle session lock
if (isset($_POST['toggle_session_lock'])) {
    $event_id = (int)$_POST['event_id'];
    $session_type = $_POST['session_type'];
    $current_status = (int)$_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    try {
        $column = $session_type === 'morning' ? 'morning_session_locked' : 'afternoon_session_locked';
        $stmt = $pdo->prepare("UPDATE pafe_events SET $column = ? WHERE id = ?");
        $stmt->execute([$new_status, $event_id]);
        
        $action = $new_status ? 'locked' : 'unlocked';
        $success_message = ucfirst($session_type) . " session $action successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating session: " . $e->getMessage();
    }
}

// Handle delete event
if (isset($_POST['delete_event'])) {
    $event_id = (int)$_POST['event_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pafe_events WHERE id = ?");
        $stmt->execute([$event_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Event deleted successfully!";
        } else {
            $error_message = "Event not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting event: " . $e->getMessage();
    }
}

// Handle edit event
if (isset($_POST['edit_event'])) {
    $event_id = (int)$_POST['event_id'];
    $title = trim($_POST['title']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = trim($_POST['location']);
    $auto_lock_enabled = isset($_POST['auto_lock_enabled']) ? 1 : 0;
    $morning_auto_lock_time = !empty($_POST['morning_auto_lock_time']) ? $_POST['morning_auto_lock_time'] : null;
    $afternoon_auto_lock_time = !empty($_POST['afternoon_auto_lock_time']) ? $_POST['afternoon_auto_lock_time'] : null;
    
    if (!empty($title) && !empty($event_date) && !empty($event_time) && !empty($location)) {
        try {
            $stmt = $pdo->prepare("UPDATE pafe_events SET title = ?, event_date = ?, event_time = ?, location = ?, auto_lock_enabled = ?, morning_auto_lock_time = ?, afternoon_auto_lock_time = ? WHERE id = ?");
            $stmt->execute([$title, $event_date, $event_time, $location, $auto_lock_enabled, $morning_auto_lock_time, $afternoon_auto_lock_time, $event_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Event updated successfully!";
            } else {
                $error_message = "No changes made or event not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Error updating event: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Handle create event
if (isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = trim($_POST['location']);
    $auto_lock_enabled = isset($_POST['auto_lock_enabled']) ? 1 : 0;
    $morning_auto_lock_time = !empty($_POST['morning_auto_lock_time']) ? $_POST['morning_auto_lock_time'] : null;
    $afternoon_auto_lock_time = !empty($_POST['afternoon_auto_lock_time']) ? $_POST['afternoon_auto_lock_time'] : null;
    
    if (!empty($title) && !empty($event_date) && !empty($event_time) && !empty($location)) {
        try {
            // Generate QR code data (event ID will be added after insertion)
            $qr_data = "PAFE_EVENT:" . time() . ":" . md5($title . $event_date . $event_time);
            
            $stmt = $pdo->prepare("INSERT INTO pafe_events (title, event_date, event_time, location, auto_lock_enabled, morning_auto_lock_time, afternoon_auto_lock_time, qr_code_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $event_date, $event_time, $location, $auto_lock_enabled, $morning_auto_lock_time, $afternoon_auto_lock_time, $qr_data]);
            
            // Update QR code data with actual event ID
            $event_id = $pdo->lastInsertId();
            $final_qr_data = "PAFE_EVENT_ID:" . $event_id . ":" . $qr_data;
            
            $stmt = $pdo->prepare("UPDATE pafe_events SET qr_code_data = ? WHERE id = ?");
            $stmt->execute([$final_qr_data, $event_id]);
            
            $success_message = "Event created successfully with QR code and auto-lock settings!";
        } catch (PDOException $e) {
            $error_message = "Error creating event: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - PAFE</title>
    <link rel="icon" href="../../assets/logo/pafe_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

        *{
        font-family: "Oswald", sans-serif;
        font-weight: 500;
        font-style: normal;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: #eea618;
            color: white;
            width: 260px;
            min-height: 100vh;
            transition: all 0.3s;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header img {
            height: 50px;
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .btn-close-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 5px;
            display: none;
        }

        .btn-close-sidebar:hover {
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 5px solid #132e63;
        }

        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 260px;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .search-box {
            width: 300px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .content-area {
            padding: 30px;
            flex: 1;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #1e174a;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .btn-close-sidebar {
                display: block;
            }
            
            .search-box {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 15px;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .search-box {
                width: 100%;
                order: 3;
            }
            
            .user-info {
                margin-left: auto;
            }
            
            .user-details {
                display: none;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .recent-activity {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .activity-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .sidebar-header h4 {
                font-size: 1rem;
            }
        }

        /* Overlay for mobile menu */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }

        /* Event item styling */
        .event-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #eea618;
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .event-actions {
            display: flex;
            gap: 5px;
        }

        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
        }

        .attendance-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .session-control {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .session-control:last-child {
            border-bottom: none;
        }

        .session-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-locked {
            background: #dc3545;
            color: white;
        }

        .status-open {
            background: #28a745;
            color: white;
        }

        .qr-code-section {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .auto-lock-status {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .auto-lock-enabled {
            background: #e8f5e8;
            border: 1px solid #28a745;
            color: #155724;
        }

        .auto-lock-disabled {
            background: #f8f9fa;
            border: 1px solid #6c757d;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .event-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .event-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .event-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/pafe_2.png" alt="PAFE Logo">
                    <h4>Prime Association of Future Educators</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="pafe_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="pafe_event.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Event</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_attendance.php">
                        <i class="bi bi-person-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_feedback.php">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Feedback</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../dashboard.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            <div class="user-info">
                <div class="notifications">
                    <i class="bi bi-bell fs-5"></i>
                </div>
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <div class="user-name">Tim</div>
                    <div class="user-role">Student</div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-4">Events Management</h2>

            <!-- Create Event Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                    <i class="bi bi-plus-circle"></i> Create New Event
                </button>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Events List -->
            <div class="events-container">
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM pafe_events ORDER BY event_date DESC, event_time DESC");
                    $stmt->execute();
                    $events = $stmt->fetchAll();
                    
                    if (count($events) > 0) {
                        foreach ($events as $event) {
                            // Get attendance counts
                            $morning_count = $pdo->prepare("SELECT COUNT(*) as count FROM pafe_event_attendance WHERE event_id = ? AND session_type = 'morning'");
                            $morning_count->execute([$event['id']]);
                            $morning_attendance = $morning_count->fetch()['count'];
                            
                            $afternoon_count = $pdo->prepare("SELECT COUNT(*) as count FROM pafe_event_attendance WHERE event_id = ? AND session_type = 'afternoon'");
                            $afternoon_count->execute([$event['id']]);
                            $afternoon_attendance = $afternoon_count->fetch()['count'];
                            
                            echo '<div class="event-card">';
                            echo '<div class="event-header">';
                            echo '<h3 class="event-title">' . htmlspecialchars($event['title']) . '</h3>';
                            echo '<div class="event-actions">';
                            echo '<button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="editEvent(' . $event['id'] . ', \'' . htmlspecialchars($event['title'], ENT_QUOTES) . '\', \'' . $event['event_date'] . '\', \'' . $event['event_time'] . '\', \'' . htmlspecialchars($event['location'], ENT_QUOTES) . '\', ' . (isset($event['auto_lock_enabled']) ? $event['auto_lock_enabled'] : 0) . ', \'' . (isset($event['morning_auto_lock_time']) ? $event['morning_auto_lock_time'] : '') . '\', \'' . (isset($event['afternoon_auto_lock_time']) ? $event['afternoon_auto_lock_time'] : '') . '\')">';
                            echo '<i class="bi bi-pencil"></i> Edit';
                            echo '</button>';
                            echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteEvent(' . $event['id'] . ', \'' . htmlspecialchars($event['title'], ENT_QUOTES) . '\')">';
                            echo '<i class="bi bi-trash"></i> Delete';
                            echo '</button>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="event-details">';
                            echo '<div class="event-detail-item">';
                            echo '<i class="bi bi-calendar3"></i>';
                            echo '<span>' . date('M d, Y', strtotime($event['event_date'])) . '</span>';
                            echo '</div>';
                            echo '<div class="event-detail-item">';
                            echo '<i class="bi bi-clock"></i>';
                            echo '<span>' . date('h:i A', strtotime($event['event_time'])) . '</span>';
                            echo '</div>';
                            echo '<div class="event-detail-item">';
                            echo '<i class="bi bi-geo-alt"></i>';
                            echo '<span>' . htmlspecialchars($event['location']) . '</span>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="attendance-controls">';
                            echo '<h6 class="mb-3"><i class="bi bi-people"></i> Attendance Management</h6>';
                            
                            // Morning session
                            echo '<div class="session-control">';
                            echo '<div class="session-status">';
                            echo '<strong>Morning Session:</strong>';
                            echo '<span class="status-badge ' . ($event['morning_session_locked'] ? 'status-locked' : 'status-open') . '">';
                            echo $event['morning_session_locked'] ? 'LOCKED' : 'OPEN';
                            echo '</span>';
                            echo '<span class="text-muted">(' . $morning_attendance . ' attendees)</span>';
                            echo '</div>';
                            echo '<form method="POST" style="display: inline;">';
                            echo '<input type="hidden" name="event_id" value="' . $event['id'] . '">';
                            echo '<input type="hidden" name="session_type" value="morning">';
                            echo '<input type="hidden" name="current_status" value="' . $event['morning_session_locked'] . '">';
                            echo '<button type="submit" name="toggle_session_lock" class="btn btn-sm ' . ($event['morning_session_locked'] ? 'btn-success' : 'btn-warning') . '">';
                            echo $event['morning_session_locked'] ? 'Unlock' : 'Lock';
                            echo '</button>';
                            echo '</form>';
                            echo '</div>';
                            
                            // Afternoon session
                            echo '<div class="session-control">';
                            echo '<div class="session-status">';
                            echo '<strong>Afternoon Session:</strong>';
                            echo '<span class="status-badge ' . ($event['afternoon_session_locked'] ? 'status-locked' : 'status-open') . '">';
                            echo $event['afternoon_session_locked'] ? 'LOCKED' : 'OPEN';
                            echo '</span>';
                            echo '<span class="text-muted">(' . $afternoon_attendance . ' attendees)</span>';
                            echo '</div>';
                            echo '<form method="POST" style="display: inline;">';
                            echo '<input type="hidden" name="event_id" value="' . $event['id'] . '">';
                            echo '<input type="hidden" name="session_type" value="afternoon">';
                            echo '<input type="hidden" name="current_status" value="' . $event['afternoon_session_locked'] . '">';
                            echo '<button type="submit" name="toggle_session_lock" class="btn btn-sm ' . ($event['afternoon_session_locked'] ? 'btn-success' : 'btn-warning') . '">';
                            echo $event['afternoon_session_locked'] ? 'Unlock' : 'Lock';
                            echo '</button>';
                            echo '</form>';
                            echo '</div>';
                            
                            // Auto-lock settings section
                            echo '<div class="mt-3">';
                            echo '<button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#autolock-' . $event['id'] . '">';
                            echo '<i class="bi bi-clock-history"></i> Auto-Lock Settings';
                            echo '</button>';
                            echo '<div class="collapse mt-2" id="autolock-' . $event['id'] . '">';
                            echo '<div class="card card-body">';
                            echo '<form method="POST" action="">';
                            echo '<input type="hidden" name="event_id" value="' . $event['id'] . '">';
                            
                            echo '<div class="form-check mb-3">';
                            echo '<input class="form-check-input" type="checkbox" name="auto_lock_enabled" id="autolock_enabled_' . $event['id'] . '"' . ((isset($event['auto_lock_enabled']) && $event['auto_lock_enabled']) ? ' checked' : '') . '>';
                            echo '<label class="form-check-label" for="autolock_enabled_' . $event['id'] . '">';
                            echo 'Enable Automatic Session Locking';
                            echo '</label>';
                            echo '</div>';
                            
                            echo '<div class="row">';
                            echo '<div class="col-md-6">';
                            echo '<label class="form-label">Morning Session Auto-Lock Time</label>';
                            echo '<input type="time" class="form-control" name="morning_auto_lock_time" value="' . (isset($event['morning_auto_lock_time']) ? $event['morning_auto_lock_time'] : '') . '">';
                            echo '<small class="text-muted">Leave empty to disable</small>';
                            echo '</div>';
                            echo '<div class="col-md-6">';
                            echo '<label class="form-label">Afternoon Session Auto-Lock Time</label>';
                            echo '<input type="time" class="form-control" name="afternoon_auto_lock_time" value="' . (isset($event['afternoon_auto_lock_time']) ? $event['afternoon_auto_lock_time'] : '') . '">';
                            echo '<small class="text-muted">Leave empty to disable</small>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="mt-3">';
                            echo '<button type="submit" name="update_auto_lock" class="btn btn-sm btn-primary">';
                            echo '<i class="bi bi-save"></i> Save Auto-Lock Settings';
                            echo '</button>';
                            echo '</div>';
                            
                            // Display current auto-lock status
                            if (isset($event['auto_lock_enabled']) && $event['auto_lock_enabled']) {
                                echo '<div class="mt-2 alert alert-info py-2">';
                                echo '<small><i class="bi bi-info-circle"></i> <strong>Auto-lock is enabled:</strong><br>';
                                if (isset($event['morning_auto_lock_time']) && $event['morning_auto_lock_time']) {
                                    echo 'Morning session will lock at ' . date('h:i A', strtotime($event['morning_auto_lock_time'])) . '<br>';
                                }
                                if (isset($event['afternoon_auto_lock_time']) && $event['afternoon_auto_lock_time']) {
                                    echo 'Afternoon session will lock at ' . date('h:i A', strtotime($event['afternoon_auto_lock_time']));
                                }
                                echo '</small>';
                                echo '</div>';
                            }
                            
                            echo '</form>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="text-center py-5">';
                        echo '<i class="bi bi-calendar-x display-1 text-muted"></i>';
                        echo '<h4 class="text-muted mt-3">No events yet</h4>';
                        echo '<p class="text-muted">Create your first event to get started!</p>';
                        echo '</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">';
                    echo '<i class="bi bi-exclamation-triangle"></i> Error loading events: ' . $e->getMessage();
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createEventModalLabel">Create New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="eventForm" method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="eventTitle" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="eventTitle" name="title" required maxlength="255">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="eventDate" class="form-label">Event Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="eventDate" name="event_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="eventTime" class="form-label">Event Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="eventTime" name="event_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="eventLocation" class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="eventLocation" name="location" required maxlength="255">
                        </div>
                        
                        <!-- Auto-Lock Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Automatic Session Locking</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_lock_enabled" id="autoLockEnabled">
                                    <label class="form-check-label" for="autoLockEnabled">
                                        Enable automatic session locking
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="morningAutoLockTime" class="form-label">Morning Session Auto-Lock Time</label>
                                            <input type="time" class="form-control" id="morningAutoLockTime" name="morning_auto_lock_time">
                                            <small class="text-muted">Leave empty to disable morning auto-lock</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="afternoonAutoLockTime" class="form-label">Afternoon Session Auto-Lock Time</label>
                                            <input type="time" class="form-control" id="afternoonAutoLockTime" name="afternoon_auto_lock_time">
                                            <small class="text-muted">Leave empty to disable afternoon auto-lock</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> A QR code will be automatically generated for this event. Both morning and afternoon attendance sessions will be open by default.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_event" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editEventForm" method="POST" action="">
                    <input type="hidden" id="editEventId" name="event_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editEventTitle" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEventTitle" name="title" required maxlength="255">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editEventDate" class="form-label">Event Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editEventDate" name="event_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editEventTime" class="form-label">Event Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="editEventTime" name="event_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editEventLocation" class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEventLocation" name="location" required maxlength="255">
                        </div>
                        
                        <!-- Auto-Lock Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Automatic Session Locking</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_lock_enabled" id="editAutoLockEnabled">
                                    <label class="form-check-label" for="editAutoLockEnabled">
                                        Enable automatic session locking
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="editMorningAutoLockTime" class="form-label">Morning Session Auto-Lock Time</label>
                                            <input type="time" class="form-control" id="editMorningAutoLockTime" name="morning_auto_lock_time">
                                            <small class="text-muted">Leave empty to disable morning auto-lock</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="editAfternoonAutoLockTime" class="form-label">Afternoon Session Auto-Lock Time</label>
                                            <input type="time" class="form-control" id="editAfternoonAutoLockTime" name="afternoon_auto_lock_time">
                                            <small class="text-muted">Leave empty to disable afternoon auto-lock</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_event" class="btn btn-primary">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEventModalLabel">Delete Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this event?</p>
                    <p><strong id="deleteEventTitle"></strong></p>
                    <p class="text-muted">This will also delete all attendance records for this event. This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" id="deleteEventId" name="event_id">
                        <button type="submit" name="delete_event" class="btn btn-danger">Delete Event</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            
            // Toggle sidebar on menu button click
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
            // Close sidebar methods:
            
            // 1. Close button click
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            // 2. Overlay click
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });

            // 3. Auto-close when clicking menu links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            // 4. Window resize (close on desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('eventDate').setAttribute('min', today);
            document.getElementById('editEventDate').setAttribute('min', today);
            
            // Form validation for create form
            document.getElementById('eventForm').addEventListener('submit', function(e) {
                const title = document.getElementById('eventTitle').value.trim();
                const date = document.getElementById('eventDate').value;
                const time = document.getElementById('eventTime').value;
                const location = document.getElementById('eventLocation').value.trim();
                
                if (!title || !date || !time || !location) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });

            // Form validation for edit form
            document.getElementById('editEventForm').addEventListener('submit', function(e) {
                const title = document.getElementById('editEventTitle').value.trim();
                const date = document.getElementById('editEventDate').value;
                const time = document.getElementById('editEventTime').value;
                const location = document.getElementById('editEventLocation').value.trim();
                
                if (!title || !date || !time || !location) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });
            
            // Generate QR codes for all events
            generateAllQRCodes();
        });

        // Function to open edit modal with event data
        function editEvent(id, title, date, time, location, autoLockEnabled, morningAutoLock, afternoonAutoLock) {
            document.getElementById('editEventId').value = id;
            document.getElementById('editEventTitle').value = title;
            document.getElementById('editEventDate').value = date;
            document.getElementById('editEventTime').value = time;
            document.getElementById('editEventLocation').value = location;
            
            // Set auto-lock settings if provided
            if (typeof autoLockEnabled !== 'undefined') {
                document.getElementById('editAutoLockEnabled').checked = autoLockEnabled == 1;
            }
            if (typeof morningAutoLock !== 'undefined' && morningAutoLock) {
                document.getElementById('editMorningAutoLockTime').value = morningAutoLock;
            }
            if (typeof afternoonAutoLock !== 'undefined' && afternoonAutoLock) {
                document.getElementById('editAfternoonAutoLockTime').value = afternoonAutoLock;
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editEventModal'));
            editModal.show();
        }

        // Function to open delete confirmation modal
        function deleteEvent(id, title) {
            document.getElementById('deleteEventId').value = id;
            document.getElementById('deleteEventTitle').textContent = title;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
            deleteModal.show();
        }

        // Function to generate QR codes for all events
        function generateAllQRCodes() {
            <?php
            if (isset($events) && count($events) > 0) {
                foreach ($events as $event) {
                    echo "generateQRCode(" . $event['id'] . ", '" . addslashes($event['qr_code_data']) . "');\n";
                }
            }
            ?>
        }

        // Function to generate individual QR code
        function generateQRCode(eventId, qrData) {
            const container = document.getElementById('qr-code-' + eventId);
            if (!container) {
                console.error('QR code container not found for event ID:', eventId);
                return;
            }
            
            // Clear any existing content
            container.innerHTML = '';
            
            const canvas = document.createElement('canvas');
            canvas.id = 'qr-canvas-' + eventId; // Add ID for easier access
            
            QRCode.toCanvas(canvas, qrData, {
                width: 200,
                height: 200,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            }, function (error) {
                if (error) {
                    console.error('QR Code generation error:', error);
                    container.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                } else {
                    container.appendChild(canvas);
                    console.log('QR code generated successfully for event ID:', eventId);
                }
            });
        }

        // Function to download QR code
        function downloadQR(eventId, eventTitle) {
            const canvas = document.getElementById('qr-canvas-' + eventId);
            
            if (!canvas) {
                console.error('Canvas not found for event ID:', eventId);
                alert('QR code not found. Please wait a moment and try again, or refresh the page.');
                return;
            }
            
            try {
                const link = document.createElement('a');
                link.download = 'QR_Code_' + eventTitle.replace(/[^a-z0-9]/gi, '_') + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                console.log('QR code downloaded successfully');
            } catch (error) {
                console.error('Download error:', error);
                alert('Error downloading QR code. Please try again.');
            }
        }

        // Function to print QR code
        function printQR(eventId, eventTitle) {
            const canvas = document.getElementById('qr-canvas-' + eventId);
            
            if (!canvas) {
                console.error('Canvas not found for event ID:', eventId);
                alert('QR code not found. Please wait a moment and try again, or refresh the page.');
                return;
            }
            
            try {
                const printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Print QR Code - ' + eventTitle + '</title>');
                printWindow.document.write('<style>');
                printWindow.document.write('body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }');
                printWindow.document.write('h1 { color: #eea618; margin-bottom: 10px; }');
                printWindow.document.write('h2 { color: #333; margin-bottom: 30px; }');
                printWindow.document.write('img { border: 2px solid #eea618; padding: 20px; margin: 20px 0; }');
                printWindow.document.write('.instructions { text-align: left; max-width: 600px; margin: 30px auto; padding: 20px; background: #f8f9fa; border-radius: 8px; }');
                printWindow.document.write('.instructions h3 { color: #eea618; margin-bottom: 15px; }');
                printWindow.document.write('.instructions ol { line-height: 1.8; }');
                printWindow.document.write('@media print { .no-print { display: none; } }');
                printWindow.document.write('</style></head><body>');
                printWindow.document.write('<h1>PAFE Event Attendance</h1>');
                printWindow.document.write('<h2>' + eventTitle + '</h2>');
                printWindow.document.write('<img src="' + canvas.toDataURL('image/png') + '" alt="QR Code">');
                printWindow.document.write('<div class="instructions">');
                printWindow.document.write('<h3>How to Mark Attendance:</h3>');
                printWindow.document.write('<ol>');
                printWindow.document.write('<li>Open the PAFE Attendance Scanner on your device</li>');
                printWindow.document.write('<li>Scan this QR code using your camera</li>');
                printWindow.document.write('<li>Enter your Student ID</li>');
                printWindow.document.write('<li>Select your session (Morning or Afternoon)</li>');
                printWindow.document.write('<li>Click "Mark Attendance" to complete</li>');
                printWindow.document.write('</ol>');
                printWindow.document.write('</div>');
                printWindow.document.write('<button class="no-print" onclick="window.print()" style="padding: 10px 20px; background: #eea618; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 20px;">Print This Page</button>');
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                console.log('Print window opened successfully');
            } catch (error) {
                console.error('Print error:', error);
                alert('Error opening print window. Please try again.');
            }
        }
    </script>
</body>
</html>