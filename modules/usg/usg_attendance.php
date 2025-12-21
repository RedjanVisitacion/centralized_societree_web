<?php
// Database connection and data retrieval at the TOP of the file
require_once(__DIR__ . '/../../db_connection.php');

// Check if $pdo exists (created in db_connection.php)
if (!isset($pdo)) {
    $db_error = "Database connection failed";
    $attendance_data = [];
} else {
    try {
        // Get filter parameters from GET/POST request
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $id = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
        $course = isset($_REQUEST['course']) ? trim($_REQUEST['course']) : '';
        $year = isset($_REQUEST['year']) ? trim($_REQUEST['year']) : '';
        $section = isset($_REQUEST['section']) ? trim($_REQUEST['section']) : '';
        $role = isset($_REQUEST['role']) ? trim($_REQUEST['role']) : '';

        // Build SQL query with filters using prepared statements
        $sql = "SELECT * FROM usg_attendace WHERE 1=1";
        $params = [];

        if (!empty($name)) {
            $sql .= " AND (first_name LIKE ? OR last_name LIKE ?)";
            $params[] = "%$name%";
            $params[] = "%$name%";
        }

        if (!empty($id)) {
            $sql .= " AND id_number LIKE ?";
            $params[] = "%$id%";
        }

        if (!empty($course)) {
            $sql .= " AND course LIKE ?";
            $params[] = "%$course%";
        }

        if (!empty($year)) {
            $sql .= " AND year = ?";
            $params[] = $year;
        }

        if (!empty($section)) {
            $sql .= " AND section LIKE ?";
            $params[] = "%$section%";
        }

        if (!empty($role)) {
            $sql .= " AND role LIKE ?";
            $params[] = "%$role%";
        }

        $sql .= " ORDER BY id_number ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendance_data = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $db_error = "Database error: " . $e->getMessage();
        $attendance_data = [];
    } catch (Exception $e) {
        $db_error = "Error: " . $e->getMessage();
        $attendance_data = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - USG</title>
    <link rel="icon" href="../../assets/logo/usg_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
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
            border-radius: 8px;
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

        .attendance-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        /* .btn-primary {
            padding: 10px 25px;
        }

        .btn-reset {
            background-color: #6c757d;
            color: white;
            padding: 10px 25px;
        }

        .btn-reset:hover {
            background-color: #5a6268;
            color: white;
        } */

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background-color: #1e174a;
            color: white;
        }

        .table th {
            border-bottom: none;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: rgba(30, 23, 74, 0.05);
        }

        .badge-student {
            background-color: #28a745;
        }

        .badge-officer {
            background-color: #007bff;
        }

        .badge-admin {
            background-color: #dc3545;
        }

        .stats-card {
            background: linear-gradient(135deg, #1e174a 0%, #2a2069 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .stats-card h3 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }

        #filterCourse.form-select,
        #filterYear.form-select,
        #filterSection.form-control {
            height: 50px;
            padding: 8px 12px;
            line-height: 1.2;
            box-sizing: border-box;
        }

        #filterSection.form-control {
            max-width: 265px;
        }

        .filter-row > div:not(.filter-actions) {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 78px;
        }

        .filter-row .form-label {
            margin-bottom: 8px;
            font-weight: 500;
        }

        .filter-row .btn {
            height: 45px;
            padding: 0 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-section .filter-row:last-child {
            display: grid;
            grid-template-columns: 265px 1fr;
            align-items: start;
            gap: 20px;
        }

        .filter-actions {
            display: flex;
            flex-direction: row;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 35px;
        }

        .filter-actions .btn,
        .filter-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 45px;
            min-width: 130px;
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
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .filter-section .filter-row:last-child {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                justify-content: flex-start;
                margin-top: 10px;
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
            
            .attendance-card {
                padding: 15px;
            }
            
            .stats-card h3 {
                font-size: 2rem;
            }
            
            .filter-actions {
                flex-wrap: wrap;
            }
            
            .filter-actions .btn {
                min-width: 120px;
                flex: 1;
            }
        }

        @media (max-width: 576px) {
            .sidebar-header h4 {
                font-size: 1rem;
            }
            
            .filter-actions .btn {
                min-width: 100px;
                padding: 0 15px;
                font-size: 0.9rem;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
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
                    <a class="nav-link" href="usg_announcement.php">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-wrench-adjustable"></i>
                        <span>Services</span>
                        <i class="bi bi-chevron-right chevron"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item active" href="usg_attendance.php">Attendance</a></li>
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
                <form method="GET" action="" class="input-group">
                    <input type="text" class="form-control" name="globalSearch" placeholder="Search..." value="<?php echo isset($_GET['globalSearch']) ? htmlspecialchars($_GET['globalSearch']) : ''; ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
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
            <h2 class="mb-4">Attendance</h2>
            
            <!-- Display error messages -->
            <?php if (isset($db_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Attendance Card -->
            <div class="attendance-card">
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <h5 class="mb-3">Records</h5>
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div>
                                <label for="filterId" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="filterId" name="id" placeholder="Search by ID..." value="<?php echo htmlspecialchars($id); ?>">
                            </div>
                            <div>
                                <label for="filterName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="filterName" name="name" placeholder="Search by name..." value="<?php echo htmlspecialchars($name); ?>">
                            </div>
                            <div>
                                <label for="filterCourse" class="form-label">Course</label>
                                <select class="form-select" id="filterCourse" name="course">
                                    <option value="">All Courses</option>
                                    <option value="BSIT" <?php echo ($course === 'BSIT') ? 'selected' : ''; ?>>BSIT</option>
                                    <option value="BTLED" <?php echo ($course === 'BTLED') ? 'selected' : ''; ?>>BTLED</option>
                                    <option value="BFPT" <?php echo ($course === 'BFPT') ? 'selected' : ''; ?>>BFPT</option>
                                </select>
                            </div>
                            <div>
                                <label for="filterYear" class="form-label">Year Level</label>
                                <select class="form-select" id="filterYear" name="year">
                                    <option value="">All Years</option>
                                    <option value="1" <?php echo ($year === '1') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2" <?php echo ($year === '2') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3" <?php echo ($year === '3') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4" <?php echo ($year === '4') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div>
                                <label for="filterSection" class="form-label">Section</label>
                                <input type="text" class="form-control" id="filterSection" name="section" placeholder="Enter section..." value="<?php echo htmlspecialchars($section); ?>">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" type="submit" id="applyFilters">
                                    <i class="bi bi-funnel"></i> Apply Filters
                                </button>
                                <a href="usg_attendance.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Attendance Table -->
                <div class="table-responsive" id="attendanceTableContainer">
                    <table class="table table-hover" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Full Name</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Section</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php if (isset($attendance_data) && !empty($attendance_data)): ?>
                                <?php foreach ($attendance_data as $item): 
                                    // Determine badge class based on role
                                    $badgeClass = 'badge-student';
                                    if (isset($item['role'])) {
                                        if ($item['role'] === 'officer') $badgeClass = 'badge-officer';
                                        if ($item['role'] === 'admin') $badgeClass = 'badge-admin';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo isset($item['id_number']) ? htmlspecialchars($item['id_number']) : ''; ?></td>
                                        <td><?php echo (isset($item['first_name']) && isset($item['last_name'])) ? htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) : ''; ?></td>
                                        <td><?php echo isset($item['course']) ? htmlspecialchars($item['course']) : ''; ?></td>
                                        <td><?php echo isset($item['year']) ? htmlspecialchars($item['year']) : ''; ?></td>
                                        <td><?php echo isset($item['section']) ? htmlspecialchars($item['section']) : ''; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="no-data">
                                            <i class="bi bi-inbox fs-1"></i>
                                            <h4 class="mt-3">No attendance records found</h4>
                                            <p class="text-muted">Try adjusting your filters or check back later.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
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
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });

            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });

            $('#attendanceTable').DataTable({
                searching: false,
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50, 100],
                order: [[0, 'asc']],
                language: {
                    info: "Showing _START_ to _END_ of _TOTAL_ records"
                }
            });
        });
    </script>
</body>
</html>