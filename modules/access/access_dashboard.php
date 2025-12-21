<?php
require_once __DIR__ . '/../../db_connection.php';

// Default stats
$sr_total = 0;
$sr_pending = 0;
$sr_approved = 0;
$sr_completed = 0;
$active_users = 0;
$latest_uploads = 0;
$pending_feedback = 0;

// Initialize arrays for dynamic content
$recent_announcements = [];
$recent_learning_materials = [];
$recent_service_requests = [];

try {
    // Service request counts
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending_cnt,
            SUM(status = 'Approved') AS approved_cnt,
            SUM(status = 'Completed') AS completed_cnt
        FROM access_service_requests
    ");
    if ($row = $stmt->fetch()) {
        $sr_total = (int) $row['total'];
        $sr_pending = (int) $row['pending_cnt'];
        $sr_approved = (int) $row['approved_cnt'];
        $sr_completed = (int) $row['completed_cnt'];
    }

    // Active members (use access_members.status = 'active')
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM access_members WHERE status = 'active'");
    $active_users = (int) $stmt->fetchColumn();

    // Latest uploads = recent learning materials
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM access_learning_materials WHERE created_at >= (NOW() - INTERVAL 1 DAY)");
    $latest_uploads = (int) $stmt->fetchColumn();

    // Open feedback = total feedback rows
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM access_feedback");
    $pending_feedback = (int) $stmt->fetchColumn();

    // Fetch recent announcements
    $stmt = $pdo->query("
        SELECT title, content, created_at 
        FROM access_announcements 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $recent_announcements = $stmt->fetchAll();

    // Fetch recent learning materials
    $stmt = $pdo->query("
        SELECT title, description, created_at 
        FROM access_learning_materials 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $recent_learning_materials = $stmt->fetchAll();

    // Fetch recent service requests with member names
    $stmt = $pdo->query("
        SELECT sr.id, sr.title, sr.status, sr.created_at,
               m.full_name AS requester_name
        FROM access_service_requests sr
        LEFT JOIN access_members m ON m.id = sr.member_id
        ORDER BY sr.created_at DESC 
        LIMIT 5
    ");
    $recent_service_requests = $stmt->fetchAll();

} catch (PDOException $e) {
    // On error keep defaults; dashboard stays readable
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ACCESS</title>
    <link rel="icon" href="../../assets/logo/access_2.png" type="image/png">
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
            background: #25bcd9;
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
            border-left: 5px solid #430fa0;
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

        .card-feature {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            height: 100%;
        }

        .card-feature h5 {
            font-weight: 600;
            margin-bottom: 15px;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pill.pending {
            background: rgba(255,193,7,0.15);
            color: #c59000;
        }

        .status-pill.approved {
            background: rgba(13,110,253,0.15);
            color: #0b5ed7;
        }

        .status-pill.completed {
            background: rgba(25,135,84,0.15);
            color: #198754;
        }

        .section-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
        }

        .table thead {
            background: #f5f7fb;
        }

        .profile-summary p {
            margin-bottom: 6px;
        }

        .form-text {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .feedback-rating {
            color: #f4b400;
        }

        .list-group-item {
            border: none;
            border-bottom: 1px solid #f1f1f1;
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card-icon.primary {
            background: rgba(13,110,253,0.1);
            color: #0d6efd;
        }

        .stat-card-icon.warning {
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }

        .stat-card-icon.success {
            background: rgba(25,135,84,0.1);
            color: #198754;
        }

        .stat-card-icon.info {
            background: rgba(13,202,240,0.1);
            color: #0dcaf0;
        }

        .notification-badge {
            position: relative;
        }

        .notification-badge::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #dc3545;
            border-radius: 50%;
            border: 2px solid white;
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
                    <img src="../../assets/logo/access_2.png" alt="ACCESS Logo">
                    <h4>Active Certified Computer Enhance Student Society</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="access_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_learning_hub.php">
                        <i class="bi bi-lightbulb"></i>
                        <span>Learning Hub</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_gallery.php">
                        <i class="bi bi-file-earmark-image"></i>
                        <span>Gallery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_service_requests.php">
                        <i class="bi bi-tools"></i>
                        <span>Service Requests & Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_feedback.php">
                        <i class="bi bi-chat-dots"></i>
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
                <div class="notifications notification-badge" style="cursor: pointer;">
                    <i class="bi bi-bell fs-5"></i>
                </div>
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <div class="user-name">Admin ACCESS</div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-3">ACCESS Admin Dashboard</h2>
            <p class="text-muted mb-4">Central overview of service requests, communication, documentation, and system activity.</p>

            <!-- Overview Statistic Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card-feature d-flex align-items-center justify-content-between">
                        <div>
                            <div class="section-label mb-1">Total Service Requests & Tasks</div>
                            <h3 class="mb-0"><?php echo $sr_total; ?></h3>
                            <small class="text-muted">All time</small>
                        </div>
                        <div class="stat-card-icon info">
                            <i class="bi bi-stack"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card-feature d-flex align-items-center justify-content-between">
                        <div>
                            <div class="section-label mb-1">Pending</div>
                            <h3 class="mb-0 text-warning"><?php echo $sr_pending; ?></h3>
                            <small class="text-muted">Awaiting review</small>
                        </div>
                        <div class="stat-card-icon warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card-feature d-flex align-items-center justify-content-between">
                        <div>
                            <div class="section-label mb-1">Approved</div>
                            <h3 class="mb-0 text-primary"><?php echo $sr_approved; ?></h3>
                            <small class="text-muted">In progress</small>
                        </div>
                        <div class="stat-card-icon primary">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card-feature d-flex align-items-center justify-content-between">
                        <div>
                            <div class="section-label mb-1">Completed</div>
                            <h3 class="mb-0 text-success"><?php echo $sr_completed; ?></h3>
                            <small class="text-muted">Finished</small>
                        </div>
                        <div class="stat-card-icon success">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links + System Status -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Quick Links</h5>
                            <span class="section-label">Major Modules</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="access_service_requests.php" class="btn btn-outline-primary w-100 text-start">
                                    <i class="bi bi-tools me-2"></i>Service Requests & Tasks
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="access_gallery.php" class="btn btn-outline-primary w-100 text-start">
                                    <i class="bi bi-images me-2"></i>Gallery
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="access_announcement.php" class="btn btn-outline-primary w-100 text-start">
                                    <i class="bi bi-megaphone me-2"></i>Announcements
                                </a>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100 text-start">
                                    <i class="bi bi-people me-2"></i>Users
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100 text-start">
                                    <i class="bi bi-journal-text me-2"></i>Logs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">System Status</h5>
                            <span class="badge bg-success">Operational</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="section-label text-uppercase">Active Users</div>
                                <h4 class="mb-0"><?php echo $active_users; ?></h4>
                                <small class="text-muted">Registered members</small>
                            </div>
                            <div class="col-6">
                                <div class="section-label text-uppercase">New Learning Materials</div>
                                <h4 class="mb-0"><?php echo $latest_uploads; ?></h4>
                                <small class="text-muted">Added today</small>
                            </div>
                            <div class="col-6">
                                <div class="section-label text-uppercase">Pending Requests</div>
                                <h4 class="mb-0 text-warning"><?php echo $sr_pending; ?></h4>
                                <small class="text-muted">Awaiting review</small>
                            </div>
                            <div class="col-6">
                                <div class="section-label text-uppercase">Total Feedback</div>
                                <h4 class="mb-0 text-primary"><?php echo $pending_feedback; ?></h4>
                                <small class="text-muted">All submissions</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Service Requests & Tasks + Notifications -->
            <div class="row g-4 mb-4">
                <div class="col-xl-8">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Recent Service Requests & Tasks</h5>
                            <div>
                                <a href="access_service_requests.php" class="small text-decoration-none me-2">View all</a>
                                <span class="section-label">Last 5 entries</span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Requested By</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_service_requests)): ?>
                                        <?php foreach ($recent_service_requests as $request): 
                                            $display_id = 'SR-' . str_pad($request['id'], 4, '0', STR_PAD_LEFT);
                                            $requester = $request['requester_name'] ?: 'Unknown Member';
                                            $title = $request['title'];
                                            $status = $request['status'];
                                            $created = date('M d, Y', strtotime($request['created_at']));
                                            
                                            // Status class mapping
                                            $status_class = 'pending';
                                            if ($status === 'Approved') $status_class = 'approved';
                                            elseif ($status === 'Completed') $status_class = 'completed';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($display_id); ?></td>
                                            <td><?php echo htmlspecialchars($requester); ?></td>
                                            <td><?php echo htmlspecialchars($title); ?></td>
                                            <td><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                            <td><?php echo htmlspecialchars($created); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No service requests found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Notifications</h5>
                            <div>
                                <?php 
                                $notification_count = 0;
                                if ($sr_pending > 0) $notification_count++;
                                if (!empty($recent_announcements)) $notification_count++;
                                if ($latest_uploads > 0) $notification_count++;
                                if ($pending_feedback > 0) $notification_count++;
                                ?>
                                <?php if ($notification_count > 0): ?>
                                <span class="badge bg-danger rounded-pill me-2"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                                <span class="section-label">System Alerts</span>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php if ($sr_pending > 0): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>Pending Service Requests</strong>
                                    <div class="text-muted small"><?php echo $sr_pending; ?> request(s) awaiting review</div>
                                </div>
                                <span class="badge bg-warning rounded-pill"><?php echo $sr_pending; ?></span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($recent_announcements)): ?>
                            <li class="list-group-item">
                                <strong>Latest Announcement</strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($recent_announcements[0]['title']); ?></div>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($latest_uploads > 0): ?>
                            <li class="list-group-item">
                                <strong>New Learning Materials</strong>
                                <div class="text-muted small"><?php echo $latest_uploads; ?> new material(s) uploaded today</div>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($pending_feedback > 0): ?>
                            <li class="list-group-item">
                                <strong>Feedback Available</strong>
                                <div class="text-muted small"><?php echo $pending_feedback; ?> feedback submission(s) to review</div>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($sr_pending == 0 && empty($recent_announcements) && $latest_uploads == 0 && $pending_feedback == 0): ?>
                            <li class="list-group-item text-center text-muted">
                                <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                                All caught up! No new notifications.
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Latest Announcements + Recent Activity Logs -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Latest Announcements</h5>
                            <a href="access_announcement.php" class="small text-decoration-none">View all</a>
                        </div>
                        <div class="list-group">
                            <?php if (!empty($recent_announcements)): ?>
                                <?php foreach ($recent_announcements as $announcement): 
                                    $title = $announcement['title'];
                                    $content = $announcement['content'];
                                    $created = date('M d, Y', strtotime($announcement['created_at']));
                                    
                                    // Truncate content for preview
                                    $preview = strlen($content) > 80 ? substr($content, 0, 80) . '...' : $content;
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($title); ?></strong>
                                        <span class="text-muted small"><?php echo htmlspecialchars($created); ?></span>
                                    </div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($preview); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item text-center text-muted">
                                    <i class="bi bi-megaphone fs-4 d-block mb-2"></i>
                                    No announcements available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-feature h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Recent Learning Hub Uploads</h5>
                            <div>
                                <a href="access_learning_hub.php" class="btn btn-sm btn-outline-secondary">View All</a>
                            </div>
                        </div>
                        <div class="activity-list">
                            <?php if (!empty($recent_learning_materials)): ?>
                                <?php foreach ($recent_learning_materials as $material): 
                                    $title = $material['title'];
                                    $description = $material['description'];
                                    $created = date('M d, Y', strtotime($material['created_at']));
                                    
                                    // Truncate description for preview
                                    $preview = strlen($description) > 60 ? substr($description, 0, 60) . '...' : $description;
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="bi bi-lightbulb"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($title); ?></h6>
                                        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($preview); ?></p>
                                        <p class="mb-0 small text-muted"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($created); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-lightbulb fs-1 d-block mb-2"></i>
                                    <p class="mb-0">No learning materials uploaded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        });
    </script>
</body>
</html>