<?php
session_start();
require_once '../../db_connection.php';
 
// Ensure necessary tables exist
try {
    // Event specific penalty hours configuration
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_event_penalty_hours (
            event_id INT PRIMARY KEY,
            hours INT NOT NULL,
            FOREIGN KEY (event_id) REFERENCES site_event(id) ON DELETE CASCADE
        )
    ");
    
    // Persistent student event penalties (for attendance absences)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_student_event_penalties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            event_id INT NOT NULL,
            service_hours INT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_event (student_id, event_id),
            FOREIGN KEY (event_id) REFERENCES site_event(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    $error_message = "Database initialization error: " . $e->getMessage();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add':
            addPenalty();
            break;
        case 'edit':
            editPenalty();
            break;
        case 'delete':
            deletePenalty();
            break;
        case 'get':
            getPenalty();
            break;
        case 'set_event_hours':
            setEventHours();
            break;
        case 'update_event_penalty_status':
            updateEventPenaltyStatus();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function addPenalty() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO site_penalties (student_id, penalty_type, description, community_service_hours, status, date_issued, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $_POST['student_id'],
            $_POST['penalty_type'],
            $_POST['description'],
            $_POST['community_service_hours'],
            $_POST['status'],
            $_POST['date_issued'],
            $_POST['due_date']
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Penalty added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function editPenalty() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE site_penalties SET student_id = ?, penalty_type = ?, description = ?, community_service_hours = ?, status = ?, date_issued = ?, due_date = ? WHERE id = ?");
        $result = $stmt->execute([
            $_POST['student_id'],
            $_POST['penalty_type'],
            $_POST['description'],
            $_POST['community_service_hours'],
            $_POST['status'],
            $_POST['date_issued'],
            $_POST['due_date'],
            $_POST['id']
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Penalty updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deletePenalty() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM site_penalties WHERE id = ?");
        $result = $stmt->execute([$_POST['id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Penalty deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getPenalty() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM site_penalties WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $penalty = $stmt->fetch();
        
        if ($penalty) {
            echo json_encode(['success' => true, 'data' => $penalty]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Penalty not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function setEventHours() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO site_event_penalty_hours (event_id, hours) VALUES (?, ?) ON DUPLICATE KEY UPDATE hours = ?");
        $result = $stmt->execute([$_POST['event_id'], $_POST['hours'], $_POST['hours']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Event penalty hours updated. Relading to sync...']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update event hours']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateEventPenaltyStatus() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE site_student_event_penalties SET status = ? WHERE id = ?");
        $result = $stmt->execute([$_POST['status'], $_POST['id']]);
        echo json_encode(['success' => $result, 'message' => $result ? 'Status updated' : 'Failed to update status']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function syncEventPenalties() {
    global $pdo;
    try {
        // Find all absences for past events that don't have a persistent record yet
        $stmt = $pdo->query("
            SELECT 
                s.id_number as student_id, 
                e.id as event_id, 
                IFNULL(eh.hours, 0) as event_penalty_hours
            FROM student s
            CROSS JOIN site_event e
            LEFT JOIN site_event_penalty_hours eh ON e.id = eh.event_id
            LEFT JOIN site_attendance a ON s.id_number = a.student_id AND e.id = a.event_id
            LEFT JOIN site_student_event_penalties sep ON s.id_number = sep.student_id AND e.id = sep.event_id
            WHERE (a.student_id IS NULL OR IFNULL(a.status, '') = '' OR a.status = 'Absent')
            AND e.event_datetime < NOW()
            AND sep.id IS NULL
            AND IFNULL(eh.hours, 0) > 0
        ");
        $new_penalties = $stmt->fetchAll();
        
        if (!empty($new_penalties)) {
            $insert_stmt = $pdo->prepare("INSERT IGNORE INTO site_student_event_penalties (student_id, event_id, service_hours) VALUES (?, ?, ?)");
            foreach ($new_penalties as $np) {
                $insert_stmt->execute([$np['student_id'], $np['event_id'], $np['event_penalty_hours']]);
            }
        }
    } catch (PDOException $e) {
        // Silently fail or log for sync
    }
}

// Run sync on load
syncEventPenalties();

// Get all penalties with student information
try {
    // Check if community_service_hours column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM site_penalties LIKE 'community_service_hours'");
    $has_community_hours = $stmt->rowCount() > 0;
    
    if ($has_community_hours) {
        $stmt = $pdo->query("
            SELECT p.*, s.first_name, s.last_name, s.course, s.year, s.section 
            FROM site_penalties p 
            LEFT JOIN student s ON p.student_id = s.id_number 
            ORDER BY p.created_at DESC
        ");
    } else {
        // Fallback query for old table structure
        $stmt = $pdo->query("
            SELECT p.*, s.first_name, s.last_name, s.course, s.year, s.section,
                   0 as community_service_hours, NULL as completion_date, 
                   NULL as supervisor, NULL as service_location
            FROM site_penalties p 
            LEFT JOIN student s ON p.student_id = s.id_number 
            ORDER BY p.created_at DESC
        ");
    }
    $penalties = $stmt->fetchAll();

    // Fetch persistent event penalties and merge them
    try {
        $stmt_sep = $pdo->query("
            SELECT 
                sep.*, 
                s.first_name, 
                s.last_name, 
                s.course, 
                s.year, 
                s.section,
                e.event_title, 
                e.event_datetime
            FROM site_student_event_penalties sep
            LEFT JOIN student s ON sep.student_id = s.id_number
            LEFT JOIN site_event e ON sep.event_id = e.id
            ORDER BY sep.created_at DESC
        ");
        $event_penalties = $stmt_sep->fetchAll();
        
        foreach ($event_penalties as $ep) {
            $penalties[] = [
                'id' => $ep['id'],
                'student_id' => $ep['student_id'],
                'first_name' => $ep['first_name'] ?? 'Unknown',
                'last_name' => $ep['last_name'] ?? 'Student',
                'course' => $ep['course'] ?? 'N/A',
                'year' => $ep['year'] ?? '?',
                'section' => $ep['section'] ?? '?',
                'penalty_type' => 'Attendance Violation',
                'description' => 'Absent in ' . ($ep['event_title'] ?: 'Event #' . $ep['event_id']) . ' on ' . date('M d, Y', strtotime($ep['event_datetime'])),
                'community_service_hours' => $ep['service_hours'],
                'status' => $ep['status'],
                'date_issued' => $ep['event_datetime'],
                'due_date' => null,
                'is_persistent_event_penalty' => true
            ];
        }
        
        // Sort merged list by date_issued DESC
        usort($penalties, function($a, $b) {
            $dateA = strtotime($a['date_issued'] ?? '0000-00-00');
            $dateB = strtotime($b['date_issued'] ?? '0000-00-00');
            return $dateB - $dateA;
        });
        
    } catch (PDOException $e) {
        $error_message = "Error fetching event penalties: " . $e->getMessage();
    }
} catch (PDOException $e) {
    $penalties = [];
    $error_message = "Database error: " . $e->getMessage();
}

// Fetch all events for reference display
try {
    $stmt = $pdo->query("
        SELECT e.*, eh.hours as penalty_hours 
        FROM site_event e 
        LEFT JOIN site_event_penalty_hours eh ON e.id = eh.event_id 
        ORDER BY event_datetime DESC
    ");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Get all students for dropdown
try {
    $stmt = $pdo->query("SELECT id_number, first_name, last_name, course, year, section FROM student ORDER BY last_name, first_name");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
}

// Fetch all events for reference display
try {
    $stmt = $pdo->query("
        SELECT e.*, eh.hours as penalty_hours 
        FROM site_event e 
        LEFT JOIN site_event_penalty_hours eh ON e.id = eh.event_id 
        ORDER BY event_datetime DESC
    ");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Aggregate hours per student
$student_summaries = [];
foreach ($penalties as $penalty) {
    $sid = $penalty['student_id'];
    if (!isset($student_summaries[$sid])) {
        $student_summaries[$sid] = [
            'student_id' => $sid,
            'name' => htmlspecialchars(($penalty['first_name'] ?? '') . ' ' . ($penalty['last_name'] ?? '')),
            'course' => htmlspecialchars(($penalty['course'] ?? '') . ' ' . ($penalty['year'] ?? '') . '-' . ($penalty['section'] ?? '')),
            'total_hours' => 0,
            'pending_hours' => 0,
            'completed_hours' => 0,
            'case_count' => 0
        ];
    }
    $hours = intval($penalty['community_service_hours'] ?? 0);
    $student_summaries[$sid]['total_hours'] += $hours;
    $student_summaries[$sid]['case_count']++;
    if ($penalty['status'] === 'completed') {
        $student_summaries[$sid]['completed_hours'] += $hours;
    } else {
        $student_summaries[$sid]['pending_hours'] += $hours;
    }
}
// Sort summaries by pending hours DESC
uasort($student_summaries, function($a, $b) {
    return $b['pending_hours'] - $a['pending_hours'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SITE - Community Service Penalties</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

        * {
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
            background: #20a8f8;
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
            gap: 15px;
        }

        .logo-container img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
        }

        .logo-container h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .btn-close-sidebar {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
            display: none;
        }

        .btn-close-sidebar:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .sidebar-menu .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.15);
            border-left-color: white;
        }

        .sidebar-menu .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
            display: none;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            margin: 0 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications {
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .notifications:hover {
            color: #20a8f8;
        }

        .user-avatar {
            font-size: 2rem;
            color: #20a8f8;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #333;
        }

        .user-role {
            font-size: 0.85rem;
            color: #666;
        }

        .content-area {
            padding: 30px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .penalty-card {
            transition: transform 0.2s;
        }
        .penalty-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8em;
        }
        .amount-display {
            font-weight: bold;
            color: #dc3545;
        }
        .paid-amount {
            color: #28a745;
        }
        .event-card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .event-card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            
            .sidebar.show {
                margin-left: 0;
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
            
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/site_2.png" alt="SITE Logo">
                    <h4>Society of Information Technology Enthusiasts</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="site_dashboard.php"><i class="bi bi-house-door"></i><span>Home</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_event.php"><i class="bi bi-calendar-event"></i><span>Event</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_service.php"><i class="bi bi-wrench-adjustable"></i><span>Services</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="site_penalties.php"><i class="bi bi-exclamation-triangle"></i><span>Penalties</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_balance.php"><i class="bi bi-wallet2"></i><span>Balance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_chat.php"><i class="bi bi-chat-dots"></i><span>Chat</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_report.php"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a></li>
                <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="bi bi-clipboard-check"></i><span>Attendance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../../dashboard.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search...">
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="user-info">
                <div class="notifications"><i class="bi bi-bell fs-5"></i></div>
                <div class="user-avatar"><i class="bi bi-person-circle"></i></div>
                <div class="user-details"><div class="user-name">Admin</div><div class="user-role">SITE Officer</div></div>
            </div>
        </nav>

        <div class="content-area">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-hands-helping text-primary"></i> Community Service Penalties</h2>
                    <div class="d-flex gap-2">
                        <div class="input-group" style="width: 300px;">
                            <input type="text" class="form-control" id="searchStudent" placeholder="Search by Student ID or Name...">
                            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#penaltyModal" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Community Service Penalty
                        </button>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Events Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0" style="background-color: #f1f8ff;">
                            <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background-color: transparent;">
                                <h5 class="mb-0 text-primary"><i class="bi bi-calendar3 me-2"></i>SITE Events Reference</h5>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#eventsCollapse">
                                    <i class="bi bi-arrows-expand me-1"></i> Toggle View
                                </button>
                            </div>
                            <div class="collapse show" id="eventsCollapse">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php if (empty($events)): ?>
                                            <div class="col-12 text-center text-muted py-3">No events recorded.</div>
                                        <?php else: ?>
                                            <?php foreach ($events as $event): ?>
                                                <div class="col-md-4 col-lg-3">
                                                    <div class="p-3 border rounded h-100 bg-white shadow-sm event-card-hover">
                                                        <h6 class="text-primary fw-bold text-truncate mb-2" title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                            <?php echo htmlspecialchars($event['event_title']); ?>
                                                        </h6>
                                                        <div class="small text-muted mb-1">
                                                            <i class="bi bi-clock me-1"></i> 
                                                            <?php echo $event['event_datetime'] ? date('M d, Y h:i A', strtotime($event['event_datetime'])) : 'TBA'; ?>
                                                        </div>
                                                        <div class="small text-muted mb-2">
                                                            <i class="bi bi-geo-alt me-1"></i> 
                                                            <?php echo htmlspecialchars($event['event_location'] ?: 'TBA'); ?>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-2">
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="bi bi-hourglass-split me-1"></i> <?php echo $event['penalty_hours'] ?: 0; ?> hrs
                                                            </span>
                                                            <button class="btn btn-sm btn-link p-0 text-decoration-none" onclick="promptSetHours(<?php echo $event['id']; ?>, '<?php echo addslashes($event['event_title']); ?>', <?php echo $event['penalty_hours'] ?: 0; ?>)">
                                                                Set Hours
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <?php
                    $total_penalties = count($penalties);
                    $pending_count = count(array_filter($penalties, function($p) { return $p['status'] === 'pending'; }));
                    $completed_count = count(array_filter($penalties, function($p) { return $p['status'] === 'completed'; }));
                    $in_progress_count = count(array_filter($penalties, function($p) { return $p['status'] === 'in_progress'; }));
                    $total_hours = array_sum(array_column($penalties, 'community_service_hours'));
                    ?>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo $total_penalties; ?></h5>
                                <p class="card-text">Total Cases</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning"><?php echo $pending_count; ?></h5>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo $completed_count; ?></h5>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-info"><?php echo $total_hours; ?> hrs</h5>
                                <p class="card-text">Total Service Hours</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs for different views -->
                <ul class="nav nav-tabs mb-3" id="penaltyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button" role="tab">
                            <i class="bi bi-person-lines-fill me-1"></i> Individual Violations
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                            <i class="bi bi-calculator-fill me-1"></i> Student Hour Summary
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="penaltyTabContent">
                    <!-- Individual Violations Tab -->
                    <div class="tab-pane fade show active" id="individual" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Community Service Penalties</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Violation Type</th>
                                        <th>Description</th>
                                        <th>Service Hours</th>
                                        <th>Status</th>
                                        <th>Date Issued</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($penalties)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No community service penalties found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($penalties as $penalty): ?>
                                            <tr>
                                                <td>
                                                    <?php if (isset($penalty['is_persistent_event_penalty'])): ?>
                                                        <span class="badge bg-secondary">AUTO-EVENT</span>
                                                        <small class="text-muted">#<?php echo htmlspecialchars($penalty['id']); ?></small>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($penalty['id']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($penalty['first_name'] . ' ' . $penalty['last_name']); ?></strong><br>
                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($penalty['student_id']); ?></small><br>
                                                    <small class="text-info"><?php echo htmlspecialchars($penalty['course'] . ' ' . $penalty['year'] . '-' . $penalty['section']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (isset($penalty['is_persistent_event_penalty'])): ?>
                                                        <span class="badge bg-danger">Attendance Violation</span>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($penalty['penalty_type']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($penalty['description']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-info"><?php echo isset($penalty['community_service_hours']) ? $penalty['community_service_hours'] : 0; ?> hrs</span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($penalty['status']) {
                                                        case 'completed':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'in_progress':
                                                            $statusClass = 'bg-primary';
                                                            break;
                                                        case 'waived':
                                                            $statusClass = 'bg-info';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-warning text-dark';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?> status-badge">
                                                        <?php echo ucfirst($penalty['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($penalty['date_issued'])); ?></td>
                                                <td><?php echo $penalty['due_date'] ? date('M d, Y', strtotime($penalty['due_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php if (!isset($penalty['is_persistent_event_penalty'])): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editPenalty(<?php echo $penalty['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deletePenalty(<?php echo $penalty['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <div class="btn-group">
                                                            <button class="btn btn-sm btn-outline-success" onclick="updateEventPenaltyStatus(<?php echo $penalty['id']; ?>, 'completed')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="updateEventPenaltyStatus(<?php echo $penalty['id']; ?>, 'pending')">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        </div>
                                                        <span class="text-muted small ms-2">Auto-Synced</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Student Hour Summary Tab -->
                    <div class="tab-pane fade" id="summary" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Course & Section</th>
                                        <th>Total Cases</th>
                                        <th>Total Service Hours</th>
                                        <th>Completed Hours</th>
                                        <th>Pending Service Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($student_summaries)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No penalty data available</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($student_summaries as $summary): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo $summary['student_id']; ?></td>
                                                <td><?php echo $summary['name']; ?></td>
                                                <td><small><?php echo $summary['course']; ?></small></td>
                                                <td class="text-center"><?php echo $summary['case_count']; ?></td>
                                                <td class="text-center"><?php echo $summary['total_hours']; ?> hrs</td>
                                                <td class="text-center text-success"><?php echo $summary['completed_hours']; ?> hrs</td>
                                                <td class="text-center fw-bold text-danger">
                                                    <?php echo $summary['pending_hours']; ?> hrs
                                                </td>
                                                <td>
                                                    <?php if ($summary['pending_hours'] <= 0): ?>
                                                        <span class="badge bg-success">Service Cleared</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Service Required</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Penalty Modal -->
    <div class="modal fade" id="penaltyModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Community Service Penalty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="penaltyForm">
                    <div class="modal-body">
                        <input type="hidden" id="penaltyId" name="id">
                        <input type="hidden" id="formAction" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="studentId" class="form-label">Student ID & Name *</label>
                                    <select class="form-select" id="studentId" name="student_id" required>
                                        <option value="">Select Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id_number']; ?>" data-student-info="<?php echo htmlspecialchars($student['course'] . ' ' . $student['year'] . '-' . $student['section']); ?>">
                                                <?php echo htmlspecialchars($student['id_number'] . ' - ' . $student['last_name'] . ', ' . $student['first_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="studentInfo"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="penaltyType" class="form-label">Violation Type *</label>
                                    <select class="form-select" id="penaltyType" name="penalty_type" required>
                                        <option value="">Select Violation Type</option>
                                        <option value="Attendance Violation">Attendance Violation</option>
                                        <option value="Equipment Damage">Equipment Damage</option>
                                        <option value="Conduct Violation">Conduct Violation</option>
                                        <option value="Community Service Absence">Community Service Absence</option>
                                        <option value="Event Disruption">Event Disruption</option>
                                        <option value="Property Damage">Property Damage</option>
                                        <option value="Other Violation">Other Violation</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Violation Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Describe the violation and circumstances..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="communityServiceHours" class="form-label">Community Service Hours *</label>
                                    <input type="number" class="form-control" id="communityServiceHours" name="community_service_hours" min="0" max="100" required>
                                    <div class="form-text">Required community service hours</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="waived">Waived</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="dateIssued" class="form-label">Date Issued *</label>
                                    <input type="date" class="form-control" id="dateIssued" name="date_issued" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="dueDate" class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="dueDate" name="due_date">
                                    <div class="form-text">Deadline for completion</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="serviceLocation" class="form-label">Service Location</label>
                                    <input type="text" class="form-control" id="serviceLocation" name="service_location" placeholder="e.g., Local Elementary School">
                                    <div class="form-text">Where the service will be performed</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="supervisor" class="form-label">Supervisor</label>
                                    <input type="text" class="form-control" id="supervisor" name="supervisor" placeholder="e.g., John Doe">
                                    <div class="form-text">Person overseeing the service</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Add Community Service Penalty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default
        document.getElementById('dateIssued').value = new Date().toISOString().split('T')[0];

        // Search functionality
        document.getElementById('searchStudent').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const studentCell = row.cells[1]; // Student column
                if (studentCell) {
                    const studentText = studentCell.textContent.toLowerCase();
                    if (studentText.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        function clearSearch() {
            document.getElementById('searchStudent').value = '';
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.style.display = '';
            });
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Community Service Penalty';
            document.getElementById('submitBtn').textContent = 'Add Community Service Penalty';
            document.getElementById('formAction').value = 'add';
            document.getElementById('penaltyForm').reset();
            document.getElementById('dateIssued').value = new Date().toISOString().split('T')[0];
        }

        function editPenalty(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Community Service Penalty';
                    document.getElementById('submitBtn').textContent = 'Update Community Service Penalty';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('penaltyId').value = data.data.id;
                    document.getElementById('studentId').value = data.data.student_id;
                    document.getElementById('penaltyType').value = data.data.penalty_type;
                    document.getElementById('description').value = data.data.description;
                    document.getElementById('communityServiceHours').value = data.data.community_service_hours || 0;
                    document.getElementById('status').value = data.data.status;
                    document.getElementById('dateIssued').value = data.data.date_issued;
                    document.getElementById('dueDate').value = data.data.due_date;
                    document.getElementById('serviceLocation').value = data.data.service_location || '';
                    document.getElementById('supervisor').value = data.data.supervisor || '';
                    
                    new bootstrap.Modal(document.getElementById('penaltyModal')).show();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching penalty data');
            });
        }

        function deletePenalty(id) {
            if (confirm('Are you sure you want to delete this penalty?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Penalty deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the penalty');
                });
            }
        }

        // Show student info when selected
        document.getElementById('studentId').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const studentInfo = document.getElementById('studentInfo');
            
            if (selectedOption.value) {
                const studentData = selectedOption.getAttribute('data-student-info');
                studentInfo.textContent = 'Course: ' + studentData;
                studentInfo.style.color = '#0d6efd';
            } else {
                studentInfo.textContent = '';
            }
        });

        document.getElementById('penaltyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate student ID is selected
            const studentId = document.getElementById('studentId').value;
            if (!studentId) {
                alert('Please select a student ID');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('penaltyModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the request');
            });
        });

        // Sidebar functionality
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('show');
            document.getElementById('sidebarOverlay').classList.add('show');
        });

        document.getElementById('closeSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        function promptSetHours(eventId, eventTitle, currentHours) {
            const hours = prompt(`Enter penalty hours for "${eventTitle}":`, currentHours);
            if (hours !== null && hours !== "") {
                const formData = new FormData();
                formData.append('action', 'set_event_hours');
                formData.append('event_id', eventId);
                formData.append('hours', hours);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating penalty hours');
                });
            }
        }

        function updateEventPenaltyStatus(id, status) {
            if (confirm(`Mark this attendance violation as ${status}?`)) {
                const formData = new FormData();
                formData.append('action', 'update_event_penalty_status');
                formData.append('id', id);
                formData.append('status', status);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating status');
                });
            }
        }

    </script>
</body>
</html>