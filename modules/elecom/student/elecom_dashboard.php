<?php
require_once '../../../db_connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../../index.php'); exit(); }
$role = isset($_SESSION['role']) ? strtolower((string)$_SESSION['role']) : '';
$full_name = trim($_SESSION['full_name'] ?? '');
$student_id = $_SESSION['student_id'] ?? '';
$display_name = $full_name !== '' ? $full_name : ($student_id !== '' ? $student_id : ($role !== '' ? ucfirst($role) : 'Student'));
$display_role = 'Student';
$icon_class = 'bi bi-person-circle';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ELECOM</title>
    <link rel="icon" href="../../../assets/logo/elecom_2.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../assets/css/app.css">
    <link rel="stylesheet" href="../css/elecom.css">


    <!-- Popup Modal for APK Download -->
    <style>
      .modal-header .app-logo { width: 36px; height: 36px; }
    </style>
    
</head>
<body class="theme-elecom">

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../../assets/logo/elecom_2.png" alt="ELECOM Logo">

                    <h4>Electoral Commission</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="elecom_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_election.php">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Election</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_results.php">
                        <i class="bi bi-file-bar-graph"></i>
                        <span>Results</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_status.php">
                        <i class="bi bi-check2"></i>
                        <span>Status</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../../dashboard.php">
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
                    <i class="<?php echo $icon_class; ?>"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($display_role); ?></div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-4">Student Dashboard</h2>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h5 class="mb-3">Announcements</h5>
                <div class="activity-list">
                    <div class="activity-item">
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- APK Download Modal -->
    <div class="modal fade" id="apkModal" tabindex="-1" aria-labelledby="apkModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-center gap-2">
              <img src="../../../assets/logo/elecom_2.png" alt="App" class="app-logo rounded-circle">
              <h5 class="modal-title" id="apkModalLabel">Welcome to ELECOM</h5>
            </div>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-start gap-3">
              <i class="bi bi-info-circle fs-3 text-primary"></i>
              <div>
                <div class="fw-semibold mb-1">Student web portal is under construction</div>
                <div class="mb-2">Please use the SocieTree mobile app for voting and student features in the meantime.</div>
<<<<<<< HEAD
<<<<<<< HEAD
                <a href="https://www.mediafire.com/file/chgia0y0n0jdglu/SocieTree_v4.apk/file" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
=======
                <a href="https://www.mediafire.com/file/pn8lujiic1i8jiv/SocieTree_v3.apk/file" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
>>>>>>> a5c928e3c2717702ca92849296cde29d3e48b423
=======
                <a href="https://www.mediafire.com/file/chgia0y0n0jdglu/SocieTree_v4.apk/file" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
>>>>>>> 613c140f78cf0217a162c48358b13beb65831940
                  <i class="bi bi-download me-1"></i> Download SocieTree Android APK
                </a>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <a href="../../../index.php" class="btn btn-danger">Logout</a>
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

            // 5. Show APK modal on load
            try {
                const modalEl = document.getElementById('apkModal');
                if (modalEl) {
                    const apkModal = new bootstrap.Modal(modalEl);
                    apkModal.show();
                }
            } catch (e) { /* ignore */ }
        });
    </script>
</body>
</html>
