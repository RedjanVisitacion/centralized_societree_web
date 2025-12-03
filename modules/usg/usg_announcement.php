<?php
// Database connection and form processing at the TOP of the file
require_once(__DIR__ . '/../../backend/usg_announcement_backend.php');
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
    <link rel="stylesheet" href="../../assets/css/app.css">
</head>
<body class="theme-usg">

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
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="usg_announcement.php">
                        <i class="bi bi-megaphone"></i>
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
                    <input type="text" class="form-control" placeholder="Search announcements...">
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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                    <i class="bi bi-plus-circle"></i> New Announcement
                </button>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Announcements</h5>
                </div>
                
                <!-- Announcements List -->
                <div id="announcementsList">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="bi bi-megaphone"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="announcement-title"><?php echo htmlspecialchars($announcement['announcement_title']); ?></h6>
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
                        <div class="mb-3">
                            <label for="announcementTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="announcementTitle" name="announcement_title" 
                                   placeholder="Enter announcement title" required maxlength="255">
                            <div class="form-text">Maximum 255 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="announcementContent" class="form-label">Content *</label>
                            <textarea class="form-control" id="announcementContent" name="announcement_content" 
                                      rows="6" placeholder="Enter announcement content..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="announcementDatetime" class="form-label">Date & Time</label>
                            <input type="datetime-local" class="form-control" id="announcementDatetime" 
                                   name="announcement_datetime">
                            <div class="form-text">Leave empty for current date and time</div>
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