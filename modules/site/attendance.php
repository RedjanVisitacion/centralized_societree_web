<?php
require_once(__DIR__ . '/../../db_connection.php');

$db_error = null;
$success = null;
$error = null;
$students = [];
$attendance_records = [];

if (!isset($conn) || !$conn) {
    $db_error = 'Database connection failed.';
} else {
    $createTableSql = "CREATE TABLE IF NOT EXISTS site_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        morning_in TIME DEFAULT NULL,
        morning_out TIME DEFAULT NULL,
        afternoon_in TIME DEFAULT NULL,
        afternoon_out TIME DEFAULT NULL,
        status VARCHAR(20) DEFAULT NULL,
        morning_status VARCHAR(20) DEFAULT NULL,
        afternoon_status VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        event_id INT DEFAULT NULL,
        UNIQUE KEY unique_student_date (student_id, attendance_date),
        CONSTRAINT fk_event_id FOREIGN KEY (event_id) REFERENCES site_event(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!$conn->query($createTableSql)) {
        $db_error = 'Failed to create attendance table: ' . $conn->error;
    }
    
    if (empty($db_error)) {
        // Fetch students for dropdown (if needed)
        $result = $conn->query('SELECT id_number, CONCAT(first_name, " ", last_name) as name FROM student ORDER BY last_name ASC LIMIT 100');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $result->free();
        }
        
        $search_id = isset($_GET['search_id']) ? trim($_GET['search_id']) : '';
        
        if (!empty($search_id)) {
            $stmt = $conn->prepare(
                'SELECT a.*, CONCAT(s.first_name, " ", s.last_name) as student_name, s.id_number, e.event_title
                 FROM site_attendance a 
                 LEFT JOIN student s ON a.student_id = s.id_number 
                 LEFT JOIN site_event e ON a.event_id = e.id
                 WHERE s.id_number LIKE ? 
                 ORDER BY a.attendance_date DESC, a.morning_in DESC'
            );
            if ($stmt) {
                $search_pattern = '%' . $search_id . '%';
                $stmt->bind_param('s', $search_pattern);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $attendance_records[] = $row;
                }
                $stmt->close();
            }
        } else {
            // Fetch all records if no search
            $query = 'SELECT a.*, CONCAT(s.first_name, " ", s.last_name) as student_name, s.id_number, e.event_title
                      FROM site_attendance a 
                      LEFT JOIN student s ON a.student_id = s.id_number 
                      LEFT JOIN site_event e ON a.event_id = e.id
                      ORDER BY a.attendance_date DESC, a.morning_in DESC 
                      LIMIT 100';
            $result = $conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $attendance_records[] = $row;
                }
                $result->free();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - SITE</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        * { font-family: "Oswald", sans-serif; font-weight: 500; font-style: normal; }
        body { background-color: #f8f9fa; margin: 0; padding: 0; min-height: 100vh; display: flex; }
        .sidebar { background: #20a8f8; color: white; width: 260px; min-height: 100vh; transition: all 0.3s; box-shadow: 3px 0 10px rgba(0,0,0,0.1); position: fixed; z-index: 1000; overflow-y: auto; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo-container { display: flex; align-items: center; gap: 10px; }
        .sidebar-header img { height: 50px; }
        .sidebar-header h4 { margin: 0; font-size: 1rem; font-weight: 600; }
        .btn-close-sidebar { background: none; border: none; font-size: 1.5rem; color: white; cursor: pointer; padding: 5px; display: none; }
        .btn-close-sidebar:hover { opacity: 0.7; }
        .sidebar-menu { padding: 20px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 25px; margin: 5px 0; border-left: 3px solid transparent; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 5px solid #081b5b; }
        .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .main-content { flex: 1; display: flex; flex-direction: column; margin-left: 260px; transition: margin-left 0.3s; }
        .top-navbar { background: white; padding: 15px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
        .search-box { width: 300px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #3498db; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .content-area { padding: 30px; flex: 1; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .attendance-table { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: #1e174a; cursor: pointer; }
        @media (max-width: 992px) { .sidebar{transform:translateX(-100%);} .sidebar.active{transform:translateX(0);} .main-content{margin-left:0;} .menu-toggle{display:block;} .btn-close-sidebar{display:block;} .sidebar-overlay.active{display:block;} }
        .attendance-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.85rem; }
        .badge-marked { background-color: #d4edda; color: #155724; }
        .badge-unmarked { background-color: #f8d7da; color: #721c24; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
        .time-col { font-family: monospace; font-size: 0.9rem; }
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
                    <a class="nav-link" href="site_dashboard.php">
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
                    <a class="nav-link active" href="attendance.php">
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
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-4"><i class="bi bi-clipboard-check me-2"></i>Attendance Tracking</h2>

            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Attendance Records -->
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Attendance Records</h5>
                    <form method="GET" action="" class="d-flex gap-2">
                        <input type="text" class="form-control" name="search_id" placeholder="Search by Student ID" value="<?php echo isset($_GET['search_id']) ? htmlspecialchars($_GET['search_id']) : ''; ?>">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-2"></i>Search</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <th>ID Number</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Morning IN</th>
                                <th>Morning OUT</th>
                                <th>Afternoon IN</th>
                                <th>Afternoon OUT</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendance_records)): ?>
                                <?php foreach ($attendance_records as $att): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($att['student_name'] ?? 'Unknown Student'); ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($att['student_id'] ?? 'N/A'); ?></span></td>
                                        <td><span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo htmlspecialchars($att['event_title'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($att['event_title'] ?? 'N/A'); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($att['attendance_date'])); ?></td>
                                        <td class="time-col text-primary"><?php echo $att['morning_in'] ? date('h:i A', strtotime($att['morning_in'])) : '-'; ?></td>
                                        <td class="time-col text-secondary"><?php echo $att['morning_out'] ? date('h:i A', strtotime($att['morning_out'])) : '-'; ?></td>
                                        <td class="time-col text-primary"><?php echo $att['afternoon_in'] ? date('h:i A', strtotime($att['afternoon_in'])) : '-'; ?></td>
                                        <td class="time-col text-secondary"><?php echo $att['afternoon_out'] ? date('h:i A', strtotime($att['afternoon_out'])) : '-'; ?></td>
                                        <td>
                                            <?php 
                                                $status = $att['status'] ?? 'Absent';
                                                $badge_class = '';
                                                
                                                switch ($status) {
                                                    case 'Present':
                                                        $badge_class = 'bg-success text-white';
                                                        break;
                                                    case 'Absent':
                                                        $badge_class = 'bg-danger text-white';
                                                        break;
                                                    case 'Late':
                                                        $badge_class = 'bg-warning text-dark';
                                                        break;
                                                    default:
                                                        $badge_class = 'bg-secondary text-white';
                                                }
                                                
                                                echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($status) . '</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-5 me-2"></i>No attendance records found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            
            if (menuToggle) menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
            if (closeSidebar) closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', function() {
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
        });
    </script>
</body>
</html>
