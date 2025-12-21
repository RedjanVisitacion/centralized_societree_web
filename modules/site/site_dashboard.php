<?php
require_once(__DIR__ . '/../../db_connection.php');

$announcements = [];
$events = [];
$services = [];
$db_error = null;

if (!isset($conn) || !$conn) {
    $db_error = 'Database connection failed.';
} else {
    $result = $conn->query("SELECT * FROM site_announcement ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $result->free();
    }

    $result = $conn->query("SELECT * FROM site_event ORDER BY event_date DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $result->free();
    }

    $result = $conn->query("SELECT * FROM site_service ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SITE</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
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
            background: #20a8f8;
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
            border-left: 5px solid #081b5b;
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
                    <img src="../../assets/logo/site_2.png" alt="SITE Logo">
                    <h4>Society of Information Technology Enthusiasts</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="site_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_event.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Event</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_service.php">
                        <i class="bi bi-wrench-adjustable"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_penalties.php">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Penalties</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_balance.php">
                        <i class="bi bi-wallet2"></i>
                        <span>Balance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_chat.php">
                        <i class="bi bi-chat-dots"></i>
                        <span>Chat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_report.php">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="attendance.php">
                        <i class="bi bi-clipboard-check"></i>
                        <span>Attendance</span>
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
                <a class="text-decoration-none ms-2" href="site_chat.php" title="Messages">
                    <i class="bi bi-chat-dots fs-5"></i>
                </a>
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
            <h2 class="mb-4">Dashboard</h2>

            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Announcements Section -->
            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-megaphone me-2"></i>Recent Announcements</h5>
                    <a href="site_announcement.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="bi bi-megaphone"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($ann['announcement_title'] ?? ''); ?></h6>
                                    <p class="mb-2"><?php echo htmlspecialchars(substr($ann['announcement_content'] ?? '', 0, 100)); ?>...</p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i><?php echo isset($ann['created_at']) ? date('M d, Y', strtotime($ann['created_at'])) : 'N/A'; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No announcements yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Events Section -->
            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-calendar-event me-2"></i>Upcoming Events</h5>
                    <a href="site_event.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $evt): ?>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="bi bi-calendar-event"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($evt['event_name'] ?? ''); ?></h6>
                                    <p class="mb-2"><?php echo htmlspecialchars(substr($evt['event_description'] ?? '', 0, 100)); ?>...</p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i><?php echo isset($evt['event_date']) ? date('M d, Y', strtotime($evt['event_date'])) : 'N/A'; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No upcoming events</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Services Section -->
            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-wrench-adjustable me-2"></i>Available Services</h5>
                    <a href="site_service.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $svc): ?>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="bi bi-wrench"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($svc['service_title'] ?? ''); ?></h6>
                                    <p class="mb-2"><?php echo htmlspecialchars(substr($svc['service_description'] ?? '', 0, 100)); ?>...</p>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i><?php echo isset($svc['created_at']) ? date('M d, Y', strtotime($svc['created_at'])) : 'N/A'; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No services available</p>
                    <?php endif; ?>
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