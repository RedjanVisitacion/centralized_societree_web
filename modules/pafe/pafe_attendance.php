<?php
session_start();
require_once '../../db_connection.php';

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_attendance':
                try {
                    $attendance_id = $_POST['attendance_id'];
                    $stmt = $pdo->prepare("UPDATE pafe_event_attendance SET status = 'approved', approved_at = NOW() WHERE id = ?");
                    $stmt->execute([$attendance_id]);
                    $success_message = "Attendance approved successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error approving attendance: " . $e->getMessage();
                }
                break;
                
            case 'deny_attendance':
                try {
                    $attendance_id = $_POST['attendance_id'];
                    $reason = $_POST['denial_reason'] ?? 'No reason provided';
                    $stmt = $pdo->prepare("UPDATE pafe_event_attendance SET status = 'denied', denial_reason = ?, denied_at = NOW() WHERE id = ?");
                    $stmt->execute([$reason, $attendance_id]);
                    $success_message = "Attendance denied successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error denying attendance: " . $e->getMessage();
                }
                break;
                
            case 'bulk_approve':
                try {
                    $attendance_ids = $_POST['attendance_ids'] ?? [];
                    if (!empty($attendance_ids)) {
                        $placeholders = str_repeat('?,', count($attendance_ids) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE pafe_event_attendance SET status = 'approved', approved_at = NOW() WHERE id IN ($placeholders)");
                        $stmt->execute($attendance_ids);
                        $success_message = "Bulk approval completed for " . count($attendance_ids) . " attendance records!";
                    }
                } catch (PDOException $e) {
                    $error_message = "Error in bulk approval: " . $e->getMessage();
                }
                break;
        }
    }
}

// Update attendance table to include status if not exists
try {
    $db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    
    // Function to check if column exists
    $checkColumn = function($column_name) use ($pdo, $db_name) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'pafe_event_attendance' 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$db_name, $column_name]);
        return $stmt->fetchColumn() > 0;
    };
    
    // Add status column if it doesn't exist
    if (!$checkColumn('status')) {
        $pdo->exec("ALTER TABLE pafe_event_attendance ADD COLUMN status ENUM('pending', 'approved', 'denied') DEFAULT 'pending'");
    }
    
    // Add approved_at column if it doesn't exist
    if (!$checkColumn('approved_at')) {
        $pdo->exec("ALTER TABLE pafe_event_attendance ADD COLUMN approved_at TIMESTAMP NULL");
    }
    
    // Add denied_at column if it doesn't exist
    if (!$checkColumn('denied_at')) {
        $pdo->exec("ALTER TABLE pafe_event_attendance ADD COLUMN denied_at TIMESTAMP NULL");
    }
    
    // Add denial_reason column if it doesn't exist
    if (!$checkColumn('denial_reason')) {
        $pdo->exec("ALTER TABLE pafe_event_attendance ADD COLUMN denial_reason TEXT NULL");
    }
} catch (PDOException $e) {
    // Column might already exist or table doesn't exist, continue
}

// Fetch attendance statistics
try {
    // Get total attendance count
    $total_attendance_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_event_attendance");
    $total_attendance = $total_attendance_stmt->fetch()['total'] ?? 0;
    
    // Get pending attendance count
    $pending_attendance_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_event_attendance WHERE status = 'pending'");
    $pending_attendance = $pending_attendance_stmt->fetch()['total'] ?? 0;
    
    // Get approved attendance count
    $approved_attendance_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_event_attendance WHERE status = 'approved'");
    $approved_attendance = $approved_attendance_stmt->fetch()['total'] ?? 0;
    
    // Get denied attendance count
    $denied_attendance_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_event_attendance WHERE status = 'denied'");
    $denied_attendance = $denied_attendance_stmt->fetch()['total'] ?? 0;
    
    // Get attendance records with pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;
    
    // Ensure limit and offset are integers for security
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
    $event_filter = isset($_GET['event']) ? $_GET['event'] : 'all';
    $session_filter = isset($_GET['session']) ? $_GET['session'] : 'all';
    
    $where_conditions = [];
    $params = [];
    
    if ($filter !== 'all') {
        $where_conditions[] = "a.status = ?";
        $params[] = $filter;
    }
    
    if ($event_filter !== 'all') {
        $where_conditions[] = "a.event_id = ?";
        $params[] = $event_filter;
    }
    
    if ($session_filter !== 'all') {
        $where_conditions[] = "a.session_type = ?";
        $params[] = $session_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $attendance_stmt = $pdo->prepare("
        SELECT a.*, e.title as event_title, e.event_date, e.event_time, e.location,
               s.first_name, s.last_name, s.email, s.course, s.year, s.section, s.id_number
        FROM pafe_event_attendance a
        LEFT JOIN pafe_events e ON a.event_id = e.id
        LEFT JOIN student s ON a.student_id = s.id_number
        $where_clause
        ORDER BY a.attended_at DESC
        LIMIT $limit OFFSET $offset
    ");
    
    $attendance_stmt->execute($params);
    $attendance_list = $attendance_stmt->fetchAll();
    
    // Get total pages for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pafe_event_attendance a LEFT JOIN pafe_events e ON a.event_id = e.id $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get events for filter dropdown
    $events_stmt = $pdo->query("SELECT id, title, event_date FROM pafe_events ORDER BY event_date DESC");
    $events_list = $events_stmt->fetchAll();
    
} catch (PDOException $e) {
    $total_attendance = 0;
    $pending_attendance = 0;
    $approved_attendance = 0;
    $denied_attendance = 0;
    $attendance_list = [];
    $events_list = [];
    $total_pages = 1;
    $db_error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - PAFE</title>
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

        .attendance-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .scanner-link {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s;
        }

        .scanner-link:hover {
            transform: translateY(-2px);
            color: inherit;
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
            
            .attendance-card {
                padding: 20px;
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

        /* Attendance Management Styles */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(238, 166, 24, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #eea618, #f39c12);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #eea618;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card.total .stat-number { color: #17a2b8; }
        .stat-card.pending .stat-number { color: #ffc107; }
        .stat-card.approved .stat-number { color: #28a745; }
        .stat-card.denied .stat-number { color: #dc3545; }

        .attendance-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .attendance-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .attendance-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .attendance-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .attendance-item.pending {
            border-left: 4px solid #ffc107;
            background: rgba(255, 193, 7, 0.02);
        }

        .attendance-item.approved {
            border-left: 4px solid #28a745;
            background: rgba(40, 167, 69, 0.02);
        }

        .attendance-item.denied {
            border-left: 4px solid #dc3545;
            background: rgba(220, 53, 69, 0.02);
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .student-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .student-details {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .attendance-date {
            color: #95a5a6;
            font-size: 0.8rem;
        }

        .attendance-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .status-approved {
            background: #28a745;
            color: white;
        }

        .status-denied {
            background: #dc3545;
            color: white;
        }

        .event-info {
            background: rgba(238, 166, 24, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .event-details {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .attendance-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .denial-reason {
            background: rgba(220, 53, 69, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            border-left: 4px solid #dc3545;
        }

        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .bulk-actions.show {
            display: block;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #eea618, #f39c12);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .btn-close {
            filter: invert(1);
        }

        .scanner-card {
            background: linear-gradient(135deg, #eea618, #f39c12);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(238, 166, 24, 0.3);
        }

        .scanner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(238, 166, 24, 0.4);
        }

        .scanner-card a {
            color: white;
            text-decoration: none;
        }

        .scanner-card a:hover {
            color: white;
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
                    <a class="nav-link" href="pafe_event.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Event</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="pafe_attendance.php">
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
                    <div class="user-name">Admin</div>
                    <div class="user-role">PAFE</div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($db_error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="bi bi-exclamation-triangle"></i> Database Connection Issue</h5>
                <p><?= htmlspecialchars($db_error) ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <h2 class="mb-4">
                <i class="bi bi-person-check"></i> Attendance Management
            </h2>

            <!-- Statistics Row -->
            <div class="stats-row">
                <div class="stat-card total">
                    <div class="stat-number"><?= $total_attendance ?></div>
                    <div class="stat-label">
                        <i class="bi bi-people"></i> Total Attendance
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?= $pending_attendance ?></div>
                    <div class="stat-label">
                        <i class="bi bi-clock"></i> Pending
                    </div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?= $approved_attendance ?></div>
                    <div class="stat-label">
                        <i class="bi bi-check-circle"></i> Approved
                    </div>
                </div>
                <div class="stat-card denied">
                    <div class="stat-number"><?= $denied_attendance ?></div>
                    <div class="stat-label">
                        <i class="bi bi-x-circle"></i> Denied
                    </div>
                </div>
            </div>

            <!-- Attendance Section -->
            <div class="attendance-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul"></i> Attendance Records
                    </h5>
                    <div class="attendance-filters">
                        <select class="form-select" onchange="filterByEvent(this.value)">
                            <option value="all">All Events</option>
                            <?php foreach ($events_list as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= ($event_filter == $event['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['title']) ?> (<?= date('M d, Y', strtotime($event['event_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select class="form-select" onchange="filterBySession(this.value)">
                            <option value="all" <?= (!isset($_GET['session']) || $_GET['session'] === 'all') ? 'selected' : '' ?>>All Sessions</option>
                            <option value="morning" <?= (isset($_GET['session']) && $_GET['session'] === 'morning') ? 'selected' : '' ?>>Morning Session</option>
                            <option value="afternoon" <?= (isset($_GET['session']) && $_GET['session'] === 'afternoon') ? 'selected' : '' ?>>Afternoon Session</option>
                        </select>
                        
                        <a href="?filter=all&event=<?= $event_filter ?>" class="btn btn-outline-secondary <?= (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : '' ?>">
                            All
                        </a>
                        <a href="?filter=pending&event=<?= $event_filter ?>" class="btn btn-outline-warning <?= (isset($_GET['filter']) && $_GET['filter'] === 'pending') ? 'active' : '' ?>">
                            Pending
                        </a>
                        <a href="?filter=approved&event=<?= $event_filter ?>" class="btn btn-outline-success <?= (isset($_GET['filter']) && $_GET['filter'] === 'approved') ? 'active' : '' ?>">
                            Approved
                        </a>
                        <a href="?filter=denied&event=<?= $event_filter ?>" class="btn btn-outline-danger <?= (isset($_GET['filter']) && $_GET['filter'] === 'denied') ? 'active' : '' ?>">
                            Denied
                        </a>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div id="bulkActions" class="bulk-actions">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="action" value="bulk_approve">
                        <div class="d-flex align-items-center gap-3">
                            <span><strong>Selected:</strong> <span id="selectedCount">0</span> records</span>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check-circle"></i> Bulk Approve
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                                Clear Selection
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (empty($attendance_list)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-check-fill text-muted" style="font-size: 3rem;"></i>
                    <h6 class="text-muted mt-3">No attendance records found</h6>
                    <p class="text-muted">Attendance records will appear here when students mark their attendance</p>
                </div>
                <?php else: ?>
                <div class="attendance-list">
                    <?php foreach ($attendance_list as $attendance): ?>
                    <div class="attendance-item <?= $attendance['status'] ?>">
                        <div class="attendance-header">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($attendance['status'] === 'pending'): ?>
                                <input type="checkbox" class="form-check-input attendance-checkbox" 
                                       value="<?= $attendance['id'] ?>" onchange="updateBulkActions()">
                                <?php endif; ?>
                                
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']) ?>
                                        <small class="text-muted">(ID: <?= htmlspecialchars($attendance['student_id']) ?>)</small>
                                    </div>
                                    <div class="student-details">
                                        <?= htmlspecialchars($attendance['email']) ?> | 
                                        <?= htmlspecialchars($attendance['course']) ?> 
                                        <?= htmlspecialchars($attendance['year']) ?>-<?= htmlspecialchars($attendance['section']) ?>
                                    </div>
                                    <div class="attendance-date">
                                        <i class="bi bi-calendar3"></i>
                                        Attended: <?= date('M d, Y - H:i A', strtotime($attendance['attended_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="attendance-status status-<?= $attendance['status'] ?>">
                                <?= ucfirst($attendance['status']) ?>
                            </span>
                        </div>

                        <div class="event-info">
                            <div class="event-title">
                                <i class="bi bi-calendar-event"></i> <?= htmlspecialchars($attendance['event_title']) ?>
                            </div>
                            <div class="event-details">
                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($attendance['location']) ?> | 
                                <i class="bi bi-clock"></i> <?= date('M d, Y - H:i A', strtotime($attendance['event_date'] . ' ' . $attendance['event_time'])) ?> |
                                <i class="bi bi-sun"></i> Session: 
                                <span class="badge <?= $attendance['session_type'] === 'morning' ? 'bg-info' : 'bg-warning' ?>">
                                    <?= ucfirst($attendance['session_type']) ?>
                                </span>
                            </div>
                        </div>

                        <?php if (isset($attendance['denial_reason']) && $attendance['denial_reason']): ?>
                        <div class="denial-reason">
                            <h6><i class="bi bi-x-circle"></i> Denial Reason:</h6>
                            <p class="mb-0"><?= htmlspecialchars($attendance['denial_reason']) ?></p>
                            <small class="text-muted">
                                Denied on <?= isset($attendance['denied_at']) ? date('M d, Y - H:i A', strtotime($attendance['denied_at'])) : 'N/A' ?>
                            </small>
                        </div>
                        <?php endif; ?>

                        <?php if ($attendance['status'] === 'approved'): ?>
                        <div class="text-success mt-2">
                            <i class="bi bi-check-circle"></i> 
                            Approved on <?= isset($attendance['approved_at']) ? date('M d, Y - H:i A', strtotime($attendance['approved_at'])) : 'N/A' ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($attendance['status'] === 'pending'): ?>
                        <div class="attendance-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="approve_attendance">
                                <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                            </form>

                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="showDenyModal(<?= $attendance['id'] ?>)">
                                <i class="bi bi-x-circle"></i> Deny
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>&event=<?= $event_filter ?>">Previous</a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&event=<?= $event_filter ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>&event=<?= $event_filter ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Deny Attendance Modal -->
    <div class="modal fade" id="denyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle"></i> Deny Attendance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deny_attendance">
                        <input type="hidden" name="attendance_id" id="denyAttendanceId">
                        
                        <p>Are you sure you want to deny this attendance record?</p>
                        
                        <div class="mb-3">
                            <label for="denialReason" class="form-label">Reason for Denial:</label>
                            <textarea name="denial_reason" id="denialReason" class="form-control" rows="3" 
                                      placeholder="Please provide a reason for denying this attendance..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Deny Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
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

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Attendance Management Functions
        function showDenyModal(attendanceId) {
            document.getElementById('denyAttendanceId').value = attendanceId;
            document.getElementById('denialReason').value = '';
            const denyModal = new bootstrap.Modal(document.getElementById('denyModal'));
            denyModal.show();
        }

        function filterByEvent(eventId) {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'pending';
            const currentSession = new URLSearchParams(window.location.search).get('session') || 'all';
            window.location.href = `?filter=${currentFilter}&event=${eventId}&session=${currentSession}`;
        }

        function filterBySession(sessionType) {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'pending';
            const currentEvent = new URLSearchParams(window.location.search).get('event') || 'all';
            window.location.href = `?filter=${currentFilter}&event=${currentEvent}&session=${sessionType}`;
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.attendance-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const bulkForm = document.getElementById('bulkForm');
            
            selectedCount.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                
                // Clear existing hidden inputs
                const existingInputs = bulkForm.querySelectorAll('input[name="attendance_ids[]"]');
                existingInputs.forEach(input => input.remove());
                
                // Add selected IDs as hidden inputs
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'attendance_ids[]';
                    hiddenInput.value = checkbox.value;
                    bulkForm.appendChild(hiddenInput);
                });
            } else {
                bulkActions.classList.remove('show');
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.attendance-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }

        // Confirm bulk approval
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.attendance-checkbox:checked').length;
            if (!confirm(`Are you sure you want to approve ${selectedCount} attendance record(s)?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>