<?php
// Database connection and form processing at the TOP of the file
require_once(__DIR__ . '/../../backend/usg_announcement_store.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - USG</title>
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

        .content-area {
            padding: 30px;
            flex: 1;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
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

        .announcement-title {
            color: #1e174a;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-select {
            height: 50px;
            padding: 8px 12px;
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

        /* Modal Custom Styles */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: #1e174a;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px 10px 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
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

        .alert {
            border-radius: 8px;
            border: none;
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

        /* Custom badge colors for announcement types */
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

        .activity-item {
            position: relative;
        }

        .activity-item .badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            font-weight: 500;
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
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .content-header .btn {
                align-self: flex-end;
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
            
            .content-header .btn {
                align-self: stretch;
                width: 100%;
            }
            
            .modal-dialog {
                margin: 10px;
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
                    <a class="nav-link" href="usg_dashboard.php">
                        <i class="bi bi-house-door-fill"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="usg_announcement.php">
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
            <!-- Display success/error messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i> <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Header with Title and Button -->
            <div class="content-header">
                <h2 class="mb-0">Announcements</h2>
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                    <i class="bi bi-plus-circle"></i> Create Announcement
                </button>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                
                <!-- Announcements List -->
                <div id="announcementsList">
                    <?php if (!empty($announcements)): ?>
                        <?php 
                        // Type badges styling and labels
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
                        
                        <?php foreach ($announcements as $announcement): 
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
                                    <p class="announcement-content"><?php echo nl2br(htmlspecialchars($announcement['announcement_content'])); ?></p>
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
                            <p class="text-muted">No announcements yet. Create your first announcement!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Announcement Modal -->
    <div class="modal fade" id="newAnnouncementModal" tabindex="-1" aria-labelledby="newAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newAnnouncementModalLabel">
                        <i class="bi bi-megaphone me-2"></i>Create New Announcement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="announcementForm" method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="announcementTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="announcementTitle" name="announcement_title" 
                                       placeholder="Enter announcement title" required maxlength="255">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="announcementType" class="form-label">Type</label>
                                <select class="form-select" id="announcementType" name="announcement_type" required>
                                    <option value="" selected disabled>Select announcement type</option>
                                    <option value="event">Event</option>
                                    <option value="cleaning">Cleaning</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="seminar">Seminar</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="important">Important</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="announcementContent" class="form-label">Content</label>
                            <textarea class="form-control" id="announcementContent" name="announcement_content" 
                                      rows="6" placeholder="Enter announcement content..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="announcementDatetime" class="form-label">Date & Time</label>
                            <input type="datetime-local" class="form-control" id="announcementDatetime" 
                                   name="announcement_datetime">
                            <div class="form-text">Set date and time</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveAnnouncementBtn">
                            <i class="bi bi-save me-1"></i> Save Announcement
                        </button>
                    </div>
                </form>
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

            // Set default datetime to now for the announcement form
            const datetimeField = document.getElementById('announcementDatetime');
            if (datetimeField) {
                const now = new Date();
                // Format to YYYY-MM-DDTHH:mm
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                datetimeField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            // Modal show event - reset form when modal is opened
            const newAnnouncementModal = document.getElementById('newAnnouncementModal');
            if (newAnnouncementModal) {
                newAnnouncementModal.addEventListener('show.bs.modal', function() {
                    // Set default datetime
                    if (datetimeField) {
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = String(now.getMonth() + 1).padStart(2, '0');
                        const day = String(now.getDate()).padStart(2, '0');
                        const hours = String(now.getHours()).padStart(2, '0');
                        const minutes = String(now.getMinutes()).padStart(2, '0');
                        
                        datetimeField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                    }
                });
            }
            
            // Handle form submission with loading state
            const announcementForm = document.getElementById('announcementForm');
            if (announcementForm) {
                announcementForm.addEventListener('submit', function() {
                    const saveBtn = document.getElementById('saveAnnouncementBtn');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                    saveBtn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>