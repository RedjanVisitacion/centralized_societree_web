<?php
require_once __DIR__ . '/../../backend/redcross_backend_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - REDCROSS</title>
    <link rel="icon" href="../../assets/logo/redcross_2.png" type="image/png">
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
            background: #f80305;
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
            border-left: 5px solid #052369;
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
                    <img src="../../assets/logo/redcross_2.png" alt="SITE Logo">
                    <h4>Red Cross Youth</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="redcross_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_membership.php">
                        <i class="bi bi-person-add"></i>
                        <span>Membership</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_patients.php">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Patient Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_promotion.php">
                        <i class="bi bi-chevron-double-up"></i>
                        <span>Promotion</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_report.php">
                        <i class="bi bi-file-earmark"></i>
                        <span>Report</span>
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
            <h2 class="mb-4">Dashboard</h2>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted">Active Members</h6>
                            <div class="display-6"><?php echo $stat_members; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted">Total Volunteer Hours</h6>
                            <div class="display-6"><?php echo number_format($stat_hours, 1); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted">Campaigns</h6>
                            <div class="display-6"><?php echo $stat_campaigns; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h5 class="mb-3">Announcements</h5>
                <div class="activity-list">
                    <?php if (empty($dash_announcements)): ?>
                        <div class="text-muted">No announcements yet.</div>
                    <?php else: ?>
                        <?php foreach ($dash_announcements as $a): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="bi bi-megaphone-fill"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                                    <div class="small text-muted">
                                        <?php echo date('M d, Y h:i A', strtotime($a['when_at'])); ?>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars(mb_strimwidth($a['body'], 0, 120, '...'))); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row g-3 mt-4">
                <div class="col-md-4">
                    <div class="recent-activity h-100">
                        <h5 class="mb-3">New Members</h5>
                        <?php if (empty($recent_members)): ?>
                            <div class="text-muted small">No recent members.</div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recent_members as $member): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon bg-warning-subtle text-warning">
                                            <i class="bi bi-person-badge"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($member['created_at'])); ?>
                                            </div>
                                            <div class="small">
                                                <?php echo htmlspecialchars($member['course'] ?: 'No course'); ?> •
                                                Year &amp; Section <?php echo htmlspecialchars($member['year_section'] ?: 'N/A'); ?> •
                                                Status: <?php echo ucfirst($member['status']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="recent-activity h-100">
                        <h5 class="mb-3">Campaign Activity</h5>
                        <?php if (empty($recent_campaigns)): ?>
                            <div class="text-muted small">No campaign activity.</div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recent_campaigns as $campaign): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-bullseye"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($campaign['title']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($campaign['created_at'])); ?>
                                            </div>
                                            <div class="small">
                                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($campaign['description'] ?? '', 0, 120, '...'))); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="recent-activity h-100">
                        <h5 class="mb-3">Patient Services</h5>
                        <?php if (empty($recent_patients)): ?>
                            <div class="text-muted small">No patient records.</div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recent_patients as $patient): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon bg-success-subtle text-success">
                                            <i class="bi bi-heart-pulse-fill"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($patient['name']); ?></strong>
                                            <div class="small text-muted">
                                                <?php
                                                    $time = $patient['created_at'] ?: $patient['date_of_service'];
                                                    echo date('M d, Y h:i A', strtotime($time));
                                                ?>
                                            </div>
                                            <div class="small">
                                                <?php echo htmlspecialchars($patient['case_description'] ?: 'No case details'); ?><br>
                                                Service date: <?php echo htmlspecialchars($patient['date_of_service'] ?: 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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