<?php
// Include the announcement functions at the top of usg_dashboard.php
require_once(__DIR__ . '/../../backend/usg_announcement_retrieve.php');

// Fetch recent announcements (e.g., last 5)
$recentAnnouncements = getRecentAnnouncements(5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - USG</title>
    <link rel="icon" href="../../assets/logo/usg_2.png" type="image/png">
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
            background: #1e174a;
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
            position: relative;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 5px solid #f9a702;
        }

        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-link .chevron {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .nav-link[aria-expanded="true"] .chevron {
            transform: rotate(90deg);
        }

        .dropdown-menu {
            background: rgba(21, 16, 54, 1);
            border: none;
            border-radius: 0;
            padding: 0;
            margin: 0;
            box-shadow: none;
            width: 100%;
        }

        .dropdown-item {
            color: rgba(255,255,255,0.8);
            padding: 10px 25px 10px 50px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            width: 100%;
            text-decoration: none;
            display: block;
        }

        .dropdown-item:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 5px solid #f9a702;
            text-decoration: none;
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
            background: #080204;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .form-control, .form-control:focus {
            border: 1px solid #ddd;
            box-shadow: none;
            padding: 12px;
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #1e174a;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .content-area {
            padding: 30px;
            flex: 1;
        }

        /* Make this selector more specific */
        .stat-card.university-card {
            background: linear-gradient(135deg, #1e174a 20%, #f9a702 50%, #737373 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card.university-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(111, 66, 193, 0.4);
        }

        .stat-card.university-card .stat-value {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card.university-card .stat-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 400;
            text-align: justify;
            line-height: 1.6;
        }

        /* Dashboard Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e174a;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 1.2rem;
        }

        /* Recent Activity / Announcements - MATCHING usg_announcement.php STYLE */
        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 20px;
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

        .announcement-title {
            color: #1e174a;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .announcement-content {
            color: #555;
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .announcement-date {
            color: #888;
            font-size: 0.9rem;
        }

        /* Custom badge colors for announcement types - FROM usg_announcement.php */
        .bg-event {
            background-color: #fd7e14 !important;
        }

        .bg-cleaning {
            background-color: #5e4c47 !important;
        }
        
        .bg-seminar {
            background-color: #0097b2 !important;
        }
        
        .bg-workshop {
            background-color: #6f42c1 !important;
        }

        .activity-item .badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            font-weight: 500;
        }

        .no-announcements {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-announcements i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
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
            
            .stats-cards {
                grid-template-columns: 1fr;
                gap: 15px;
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
                    <img src="../../assets/logo/usg_2.png" alt="USG Logo">
                    <h4>University of Student Government</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="usg_dashboard.php">
                        <i class="bi bi-house-door-fill"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="usg_announcement.php">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-wrench-adjustable"></i>
                        <span>Services</span>
                        <i class="bi bi-chevron-right chevron"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="usg_attendance.php">Attendance</a></li>
                        <li><a class="dropdown-item" href="#">Violation</a></li>
                        <li><a class="dropdown-item" href="#">Lost and Found</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../dashboard.php">
                        <i class="bi bi-arrow-left-square-fill"></i>
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
            <!-- Welcome Header -->
            <div class="mb-4">
                <h1>Welcome back!</h1>
            </div>

            <div class="stat-card mb-4 university-card">
                <h5 class="mb-0">Vision</h5>
                <div class="stat-label mb-3">The University Student Government - Oroquieta of University of Science and Technology of Southern Philippines will facilitate Oroquieta Campus organiations and promote the interest of students to further cultivate and engage into a conscientious community of student leaders in achieving the common goal.</div>
                <h5 class="mb-0">Mission</h5>
                <div class="stat-label">We, the University Student Government – Oroquieta of University of Science and Technology of Southern Philippines, promote the welfare and unity of the student community through advocacy for and representation of the undergraduates’ student bodies’ diverse interests, concerns and needs. As advocates for undergraduate students, we work to facilitate change and respond to the challenges in our community through active outreach to the student body in a productive partnership with the University administration. In order to strengthen the undergraduate students’ community, we encourage students’ involvement and leadership development through accredited students’ organizations, University committee and USG Oroquieta.</div>
                <div class="stat-label"> </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <!-- <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-megaphone"></i>
                    </div>
                    <div class="stat-label"><?php echo count($recentAnnouncements); ?></div>
                    <div class="stat-value">Total Announcements</div>
                </div> -->

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-label">Under construction</div>
                    <div class="stat-value">Coming soon</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-label">Under construction</div>
                    <div class="stat-value">Coming soon</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-label">Under construction</div>
                    <div class="stat-value">Coming soon</div>
                </div>
            </div>

            <!-- Recent Announcements Section - MATCHING usg_announcement.php FORMAT -->
            <div class="recent-activity">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Announcements</h3>
                    <a href="usg_announcement.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-right me-1"></i> View All
                    </a>
                </div>
                
                <div id="announcementsList">
                    <?php if (!empty($recentAnnouncements)): ?>
                        <?php 
                        // Type badges styling and labels - SAME as usg_announcement.php
                        $type_labels = [
                            'event' => ['badge' => 'bg-event', 'label' => 'Event'],
                            'cleaning' => ['badge' => 'bg-cleaning', 'label' => 'Cleaning'],
                            'meeting' => ['badge' => 'bg-info', 'label' => 'Meeting'],
                            'seminar' => ['badge' => 'bg-seminar', 'label' => 'Seminar'],
                            'workshop' => ['badge' => 'bg-workshop', 'label' => 'Workshop'],
                            'maintenance' => ['badge' => 'bg-secondary', 'label' => 'Maintenance'],
                            'urgent' => ['badge' => 'bg-danger', 'label' => 'Urgent'],
                            'important' => ['badge' => 'bg-warning', 'label' => 'Important']
                        ];
                        ?>
                        
                        <?php foreach ($recentAnnouncements as $announcement): 
                            $type = $announcement['announcement_type'] ?? '';
                            $type_info = $type_labels[$type] ?? ['badge' => 'bg-secondary', 'label' => ucfirst($type)];
                        ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="bi bi-megaphone"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="announcement-title mb-0"><?php echo htmlspecialchars($announcement['announcement_title']); ?></h6>
                                        <?php if (!empty($type)): ?>
                                            <span class="badge rounded-pill <?php echo $type_info['badge']; ?>">
                                                <?php echo $type_info['label']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="announcement-content">
                                        <?php 
                                        $content = htmlspecialchars($announcement['announcement_content']);
                                        // Truncate content if too long for dashboard view
                                        if (strlen($content) > 200) {
                                            echo substr($content, 0, 200) . '...';
                                        } else {
                                            echo $content;
                                        }
                                        ?>
                                    </p>
                                    <small class="announcement-date">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('F j, Y g:i A', strtotime($announcement['announcement_datetime'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone display-1 text-muted mb-3"></i>
                            <p class="text-muted">No announcements yet. Check back later!</p>
                        </div>
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

            // Search functionality
            const searchInput = document.querySelector('.search-box input');
            const searchButton = document.querySelector('.search-box button');
            
            if (searchInput && searchButton) {
                searchButton.addEventListener('click', function() {
                    const searchTerm = searchInput.value.trim();
                    if (searchTerm) {
                        // Filter announcements based on search term
                        const announcementItems = document.querySelectorAll('.activity-item');
                        announcementItems.forEach(item => {
                            const itemText = item.textContent.toLowerCase();
                            if (itemText.includes(searchTerm.toLowerCase())) {
                                item.style.display = 'flex';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    }
                });
                
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = searchInput.value.trim();
                        if (searchTerm) {
                            // Filter announcements based on search term
                            const announcementItems = document.querySelectorAll('.activity-item');
                            announcementItems.forEach(item => {
                                const itemText = item.textContent.toLowerCase();
                                if (itemText.includes(searchTerm.toLowerCase())) {
                                    item.style.display = 'flex';
                                } else {
                                    item.style.display = 'none';
                                }
                            });
                        }
                    }
                });
                
                // Clear search when input is cleared
                searchInput.addEventListener('input', function() {
                    if (this.value.trim() === '') {
                        const announcementItems = document.querySelectorAll('.activity-item');
                        announcementItems.forEach(item => {
                            item.style.display = 'flex';
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>