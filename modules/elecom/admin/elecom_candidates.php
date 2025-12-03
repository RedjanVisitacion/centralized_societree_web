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
                    <a class="nav-link" href="elecom_register_candidate.php">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Register Candidate</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_election_date.php">
                        <i class="bi bi-file-bar-graph"></i>
                        <span>Set Election Dates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_candidates.php">
                        <i class="bi bi-check2"></i>
                        <span>Candidates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="elecom_reset.php">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Reset Votes</span>
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
            <h2 class="mb-4">Candidates</h2>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h5 class="mb-3"></h5>
                <div class="activity-list">
                    <div class="activity-item">
                        
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