<?php
require_once '../../db_connection.php';

// Create table if it doesn't exist
try {
    $createTable = "CREATE TABLE IF NOT EXISTS pafe_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        announcement_date DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($createTable);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle delete announcement
if (isset($_POST['delete_announcement'])) {
    $announcement_id = (int)$_POST['announcement_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pafe_announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Announcement deleted successfully!";
        } else {
            $error_message = "Announcement not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting announcement: " . $e->getMessage();
    }
}

// Handle edit announcement
if (isset($_POST['edit_announcement'])) {
    $announcement_id = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $announcement_date = $_POST['announcement_date'];
    
    if (!empty($title) && !empty($description) && !empty($announcement_date)) {
        try {
            $stmt = $pdo->prepare("UPDATE pafe_announcements SET title = ?, description = ?, announcement_date = ? WHERE id = ?");
            $stmt->execute([$title, $description, $announcement_date, $announcement_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Announcement updated successfully!";
            } else {
                $error_message = "No changes made or announcement not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Error updating announcement: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Handle create announcement
if (isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $announcement_date = $_POST['announcement_date'];
    
    if (!empty($title) && !empty($description) && !empty($announcement_date)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pafe_announcements (title, description, announcement_date) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $announcement_date]);
            
            $success_message = "Announcement created successfully!";
        } catch (PDOException $e) {
            $error_message = "Error creating announcement: " . $e->getMessage();
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
    <title>Dashboard - PAFE</title>
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

        /* Announcement item styling */
        .activity-item {
            position: relative;
        }

        .activity-item .ms-auto {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .activity-item:hover .ms-auto {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .activity-item .ms-auto {
                margin-top: 10px;
                margin-left: 0 !important;
                width: 100%;
                justify-content: flex-end;
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
                    <a class="nav-link active" href="pafe_announcement.php">
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
            <h2 class="mb-4">Announcements</h2>

            <!-- Create Announcement Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle"></i> Create New Announcement
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

            <!-- Announcements List -->
            <div class="recent-activity">
                <h5 class="mb-3">Recent Announcements</h5>
                <div class="activity-list" id="announcementsList">
                    <?php
                    require_once '../../db_connection.php';
                    
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM pafe_announcements ORDER BY created_at DESC");
                        $stmt->execute();
                        $announcements = $stmt->fetchAll();
                        
                        if (count($announcements) > 0) {
                            foreach ($announcements as $announcement) {
                                echo '<div class="activity-item">';
                                echo '<div class="activity-icon"><i class="bi bi-megaphone"></i></div>';
                                echo '<div class="flex-grow-1">';
                                echo '<h6 class="mb-1">' . htmlspecialchars($announcement['title']) . '</h6>';
                                echo '<p class="mb-1 text-muted">' . htmlspecialchars($announcement['description']) . '</p>';
                                echo '<small class="text-muted">Posted on: ' . date('M d, Y h:i A', strtotime($announcement['announcement_date'])) . '</small>';
                                echo '</div>';
                                echo '<div class="ms-auto">';
                                echo '<button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="editAnnouncement(' . $announcement['id'] . ', \'' . htmlspecialchars($announcement['title'], ENT_QUOTES) . '\', \'' . htmlspecialchars($announcement['description'], ENT_QUOTES) . '\', \'' . date('Y-m-d\TH:i', strtotime($announcement['announcement_date'])) . '\')">';
                                echo '<i class="bi bi-pencil"></i> Edit';
                                echo '</button>';
                                echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(' . $announcement['id'] . ', \'' . htmlspecialchars($announcement['title'], ENT_QUOTES) . '\')">';
                                echo '<i class="bi bi-trash"></i> Delete';
                                echo '</button>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="activity-item">';
                            echo '<div class="text-center w-100">';
                            echo '<p class="text-muted">No announcements yet. Create your first announcement!</p>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="activity-item">';
                        echo '<div class="text-center w-100">';
                        echo '<p class="text-danger">Error loading announcements. Please try again later.</p>';
                        echo '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAnnouncementModalLabel">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="announcementForm" method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="announcementTitle" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="announcementTitle" name="title" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="announcementDescription" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="announcementDescription" name="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="announcementDate" class="form-label">Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="announcementDate" name="announcement_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_announcement" class="btn btn-primary">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAnnouncementForm" method="POST" action="">
                    <input type="hidden" id="editAnnouncementId" name="announcement_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editAnnouncementTitle" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editAnnouncementTitle" name="title" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="editAnnouncementDescription" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="editAnnouncementDescription" name="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editAnnouncementDate" class="form-label">Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="editAnnouncementDate" name="announcement_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_announcement" class="btn btn-primary">Update Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-labelledby="deleteAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAnnouncementModalLabel">Delete Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this announcement?</p>
                    <p><strong id="deleteAnnouncementTitle"></strong></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" id="deleteAnnouncementId" name="announcement_id">
                        <button type="submit" name="delete_announcement" class="btn btn-danger">Delete</button>
                    </form>
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
            
            // Set default datetime to current date and time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('announcementDate').value = now.toISOString().slice(0, 16);
            
            // Form validation for create form
            document.getElementById('announcementForm').addEventListener('submit', function(e) {
                const title = document.getElementById('announcementTitle').value.trim();
                const description = document.getElementById('announcementDescription').value.trim();
                const date = document.getElementById('announcementDate').value;
                
                if (!title || !description || !date) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });

            // Form validation for edit form
            document.getElementById('editAnnouncementForm').addEventListener('submit', function(e) {
                const title = document.getElementById('editAnnouncementTitle').value.trim();
                const description = document.getElementById('editAnnouncementDescription').value.trim();
                const date = document.getElementById('editAnnouncementDate').value;
                
                if (!title || !description || !date) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });
        });

        // Function to open edit modal with announcement data
        function editAnnouncement(id, title, description, date) {
            document.getElementById('editAnnouncementId').value = id;
            document.getElementById('editAnnouncementTitle').value = title;
            document.getElementById('editAnnouncementDescription').value = description;
            document.getElementById('editAnnouncementDate').value = date;
            
            const editModal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
            editModal.show();
        }

        // Function to open delete confirmation modal
        function deleteAnnouncement(id, title) {
            document.getElementById('deleteAnnouncementId').value = id;
            document.getElementById('deleteAnnouncementTitle').textContent = title;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteAnnouncementModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>