<?php
require_once __DIR__ . '/../../backend/redcross_backend_announcement.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement - REDCROSS</title>
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

        /* Preview Modal Styles */
        #previewAnnouncementModal .modal-body {
            background-color: #f8f9fa;
        }
        
        #previewAnnouncementModal .border.rounded {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .preview-announcement {
            transition: all 0.3s ease;
        }
        
        .preview-announcement:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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
                    <a class="nav-link" href="redcross_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="redcross_announcement.php">
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Announcements</h2>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="bi bi-plus-circle me-1"></i>
                    Add Announcement
                </button>
            </div>

            <?php if (!empty($announce_message)): ?>
                <div class="alert alert-info py-2 mb-3"><?php echo $announce_message; ?></div>
            <?php endif; ?>

            <!-- Debug Information (remove this after testing) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-warning">
                    <strong>Debug Info:</strong><br>
                    Total announcements fetched: <?php echo count($all_announcements ?? []); ?><br>
                    Recent announcements: <?php echo count($recent_announcements ?? []); ?><br>
                    Old announcements: <?php echo count($old_announcements ?? []); ?><br>
                    <?php if (!empty($all_announcements)): ?>
                        <br><strong>Sample data:</strong><br>
                        <?php foreach (array_slice($all_announcements, 0, 2) as $sample): ?>
                            - ID: <?php echo $sample['id']; ?>, Title: "<?php echo htmlspecialchars($sample['title']); ?>", Type: <?php echo $sample['announcement_type']; ?><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Announcement Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?php echo isset($stats) ? $stats['total'] : 0; ?></h5>
                            <p class="card-text">Total Announcements</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?php echo isset($stats) ? $stats['recent'] : 0; ?></h5>
                            <p class="card-text">Recent (30 days)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><?php echo isset($stats) ? $stats['high_priority'] : 0; ?></h5>
                            <p class="card-text">High Priority</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info"><?php echo isset($stats) ? count($stats['by_type']) : 0; ?></h5>
                            <p class="card-text">Different Types</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Centered modal for creating announcement -->
            <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addAnnouncementModalLabel">Create Announcement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Content</label>
                                    <textarea name="body" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Schedule (optional)</label>
                                    <input type="datetime-local" name="scheduled_at" class="form-control">
                                </div>
                                <div class="col-12 d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" id="previewBtn">
                                        <i class="bi bi-eye me-1"></i>
                                        Preview
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send me-1"></i>
                                        Post Announcement
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Announcement Modal -->
            <div class="modal fade" id="previewAnnouncementModal" tabindex="-1" aria-labelledby="previewAnnouncementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="previewAnnouncementModalLabel">
                                <i class="bi bi-eye me-2"></i>
                                Announcement Preview
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                This is how your announcement will appear to users.
                            </div>
                            
                            <!-- Preview Content (mimics the actual announcement display) -->
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 text-danger" id="previewTitle">
                                        <i class="bi bi-bell-fill me-1"></i>
                                        [Title will appear here]
                                    </h6>
                                    <span class="badge bg-success">New</span>
                                </div>
                                <small class="text-muted d-block mb-2">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <span id="previewDate"><?php echo date('M d, Y h:i A'); ?></span>
                                    <span id="previewExpires" class="ms-2" style="display: none;">
                                        <i class="bi bi-clock me-1"></i>
                                        Expires: <span id="previewExpiresDate"></span>
                                    </span>
                                </small>
                                <p class="mb-0" id="previewBody">[Content will appear here]</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-pencil me-1"></i>
                                Edit
                            </button>
                            <button type="button" class="btn btn-primary" id="postFromPreview">
                                <i class="bi bi-send me-1"></i>
                                Post Announcement
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements Section -->
            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-megaphone-fill text-danger me-2"></i>
                        Recent Announcements
                    </h5>
                    <span class="badge bg-danger"><?php echo count($recent_announcements); ?></span>
                </div>
                <div class="list-group">
                    <?php if (empty($recent_announcements)): ?>
                        <div class="text-muted p-3 text-center">
                            <i class="bi bi-info-circle me-1"></i>
                            No recent announcements in the last 30 days.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_announcements as $a): ?>
                            <div class="mb-3 border rounded p-3 <?php echo $a['priority'] === 'high' ? 'border-warning bg-warning bg-opacity-10' : 'bg-light'; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 <?php echo $a['priority'] === 'high' ? 'text-warning' : 'text-danger'; ?>">
                                        <i class="bi bi-<?php echo get_announcement_icon($a['announcement_type']); ?> me-1"></i>
                                        <?php echo htmlspecialchars($a['title']); ?>
                                    </h6>
                                    <div class="d-flex gap-1">
                                        <span class="badge bg-<?php echo get_priority_class($a['priority']); ?>">
                                            <?php echo ucfirst($a['priority']); ?>
                                        </span>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $a['announcement_type'])); ?>
                                        </span>
                                        <?php if ($a['target_audience'] !== 'all'): ?>
                                            <span class="badge bg-success">
                                                <?php echo format_target_audience($a['target_audience']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-primary">New</span>
                                    </div>
                                </div>
                                <small class="text-muted d-block mb-2">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Created: <?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?>
                                    <?php if (!empty($a['expires_at'])): ?>
                                        <span class="ms-2">
                                            <i class="bi bi-clock me-1"></i>
                                            Expires: <?php echo date('M d, Y h:i A', strtotime($a['expires_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($a['updated_at'])): ?>
                                        <span class="ms-2">
                                            <i class="bi bi-pencil me-1"></i>
                                            Updated: <?php echo date('M d, Y h:i A', strtotime($a['updated_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </small>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($a['body'])); ?></p>
                                <small class="text-muted">
                                    ID: <?php echo $a['id']; ?> | 
                                    Status: <?php echo $a['is_active'] ? 'Active' : 'Inactive'; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Old Announcements Section -->
            <div class="recent-activity">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-archive-fill text-secondary me-2"></i>
                        Previous Announcements
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-secondary"><?php echo count($old_announcements); ?></span>
                        <?php if (!empty($old_announcements)): ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#oldAnnouncementsCollapse" aria-expanded="false">
                                <i class="bi bi-chevron-down me-1"></i>
                                Show/Hide
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($old_announcements)): ?>
                    <div class="text-muted p-3 text-center">
                        <i class="bi bi-archive me-1"></i>
                        No previous announcements found.
                    </div>
                <?php else: ?>
                    <div class="collapse" id="oldAnnouncementsCollapse">
                        <div class="list-group">
                            <?php foreach ($old_announcements as $a): ?>
                                <div class="mb-3 border rounded p-3 <?php echo $a['priority'] === 'high' ? 'border-warning bg-warning bg-opacity-5' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1 <?php echo $a['priority'] === 'high' ? 'text-warning' : 'text-secondary'; ?>">
                                            <i class="bi bi-<?php echo get_announcement_icon($a['announcement_type']); ?> me-1"></i>
                                            <?php echo htmlspecialchars($a['title']); ?>
                                        </h6>
                                        <div class="d-flex gap-1">
                                            <span class="badge bg-<?php echo get_priority_class($a['priority']); ?>">
                                                <?php echo ucfirst($a['priority']); ?>
                                            </span>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $a['announcement_type'])); ?>
                                            </span>
                                            <?php if ($a['target_audience'] !== 'all'): ?>
                                                <span class="badge bg-success">
                                                    <?php echo format_target_audience($a['target_audience']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Created: <?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?>
                                        <?php if (!empty($a['expires_at'])): ?>
                                            <span class="ms-2">
                                                <i class="bi bi-clock me-1"></i>
                                                Expires: <?php echo date('M d, Y h:i A', strtotime($a['expires_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($a['updated_at'])): ?>
                                            <span class="ms-2">
                                                <i class="bi bi-pencil me-1"></i>
                                                Updated: <?php echo date('M d, Y h:i A', strtotime($a['updated_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                    <p class="mb-2 text-muted"><?php echo nl2br(htmlspecialchars($a['body'])); ?></p>
                                    <small class="text-muted">
                                        ID: <?php echo $a['id']; ?> | 
                                        Status: <?php echo $a['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
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

            // Preview functionality
            const previewBtn = document.getElementById('previewBtn');
            const previewModal = new bootstrap.Modal(document.getElementById('previewAnnouncementModal'));
            const createModal = bootstrap.Modal.getInstance(document.getElementById('addAnnouncementModal'));
            const postFromPreviewBtn = document.getElementById('postFromPreview');
            
            // Form elements
            const titleInput = document.querySelector('input[name="title"]');
            const bodyInput = document.querySelector('textarea[name="body"]');
            const scheduleInput = document.querySelector('input[name="scheduled_at"]');
            const createForm = document.querySelector('#addAnnouncementModal form');
            
            // Preview elements
            const previewTitle = document.getElementById('previewTitle');
            const previewBody = document.getElementById('previewBody');
            const previewExpires = document.getElementById('previewExpires');
            const previewExpiresDate = document.getElementById('previewExpiresDate');

            previewBtn.addEventListener('click', function() {
                const title = titleInput.value.trim();
                const body = bodyInput.value.trim();
                const schedule = scheduleInput.value;

                // Validate required fields
                if (!title || !body) {
                    alert('Please fill in both title and content before previewing.');
                    return;
                }

                // Update preview content
                previewTitle.innerHTML = '<i class="bi bi-bell-fill me-1"></i>' + escapeHtml(title);
                previewBody.innerHTML = escapeHtml(body).replace(/\n/g, '<br>');
                
                // Handle expiration date
                if (schedule) {
                    const expireDate = new Date(schedule);
                    previewExpiresDate.textContent = expireDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    previewExpires.style.display = 'inline';
                } else {
                    previewExpires.style.display = 'none';
                }

                // Show preview modal
                previewModal.show();
            });

            // Post from preview
            postFromPreviewBtn.addEventListener('click', function() {
                previewModal.hide();
                createForm.submit();
            });

            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }


        });
    </script>
</body>
</html>