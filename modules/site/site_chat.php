<?php
// Enhanced SITE chat module with student ID-based loading and chat engine
require_once(__DIR__ . '/../../db_connection.php');
require_once(__DIR__ . '/../../backend/chat_engine.php');

// Get current student ID from URL parameter
$current_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$selected_student_id = isset($_GET['selected_student']) ? (int)$_GET['selected_student'] : null;

// Load all students for selection
$students = [];
$current_student_info = null;
$selected_student_info = null;

$db_error = null;
if (!isset($conn) || !$conn) {
    $db_error = 'Database connection is not initialized. Check db_connection.php.';
}

// Create the enhanced chat table if it doesn't exist
if (empty($db_error)) {
    $createSql = "CREATE TABLE IF NOT EXISTS site_chat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        message TEXT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_admin TINYINT(1) DEFAULT 0,
        reply_to INT DEFAULT NULL,
        INDEX idx_student_timestamp (student_id, timestamp),
        FOREIGN KEY (student_id) REFERENCES student(id_number) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!$conn->query($createSql)) {
        $db_error = 'Failed creating chat table: ' . $conn->error;
    }
}

// Load students and current student info
if (empty($db_error)) {
    // Get all students
    $sql = "SELECT id_number, first_name, middle_name, last_name, course, year, section FROM student ORDER BY last_name, first_name";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) { 
            $students[] = $row; 
            if ($row['id_number'] == $current_student_id) {
                $current_student_info = $row;
            }
            if ($row['id_number'] == $selected_student_id) {
                $selected_student_info = $row;
            }
        }
        $res->free();
    }
}

// Handle AJAX actions
if (empty($db_error) && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $is_admin = isset($_POST['is_admin']) ? (int)$_POST['is_admin'] : 0;
        
        if (!$student_id || $message === '') {
            echo json_encode(['ok' => false, 'error' => 'Student ID and message are required']);
            exit;
        }
        
        // Verify student exists
        $checkStmt = $conn->prepare('SELECT id_number FROM student WHERE id_number = ?');
        if ($checkStmt) {
            $checkStmt->bind_param('i', $student_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if (!$result->fetch_assoc()) {
                echo json_encode(['ok' => false, 'error' => 'Student not found']);
                $checkStmt->close();
                exit;
            }
            $checkStmt->close();
        }
        
        // Insert message into database
        $stmt = $conn->prepare('INSERT INTO site_chat (student_id, message, is_admin) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('isi', $student_id, $message, $is_admin);
            if ($stmt->execute()) {
                $messageId = $conn->insert_id;
                
                // Process message through chat engine if available
                if (class_exists('ChatEngine')) {
                    try {
                        $engine = new ChatEngine();
                        $engine->processMessage($messageId, $student_id, $message, $is_admin);
                    } catch (Exception $e) {
                        // Continue even if engine fails
                        error_log('Chat engine error: ' . $e->getMessage());
                    }
                }
                
                echo json_encode(['ok' => true, 'message_id' => $messageId]);
            } else {
                echo json_encode(['ok' => false, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        exit;
    }

    if ($_GET['action'] === 'fetch') {
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
        
        if (!$student_id) {
            echo json_encode(['ok' => false, 'error' => 'Student ID is required']);
            exit;
        }
        
        // Fetch messages for the specific student and admin messages
        $sql = 'SELECT c.id, c.student_id, c.message, c.timestamp, c.is_admin, 
                       s.first_name, s.last_name, s.course, s.year, s.section
                FROM site_chat c 
                JOIN student s ON c.student_id = s.id_number 
                WHERE (c.student_id = ? OR c.is_admin = 1) AND c.id > ?
                ORDER BY c.timestamp ASC
                LIMIT 50';
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $student_id, $since_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $messages = [];
                while ($row = $res->fetch_assoc()) { 
                    $messages[] = $row; 
                }
                echo json_encode(['ok' => true, 'messages' => $messages]);
            } else {
                echo json_encode(['ok' => false, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        exit;
    }

    if ($_GET['action'] === 'get_student_info') {
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        
        if (!$student_id) {
            echo json_encode(['ok' => false, 'error' => 'Student ID is required']);
            exit;
        }
        
        $stmt = $conn->prepare('SELECT * FROM student WHERE id_number = ?');
        if ($stmt) {
            $stmt->bind_param('i', $student_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $student = $res->fetch_assoc();
                if ($student) {
                    echo json_encode(['ok' => true, 'student' => $student]);
                } else {
                    echo json_encode(['ok' => false, 'error' => 'Student not found']);
                }
            } else {
                echo json_encode(['ok' => false, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        exit;
    }

    // Database test action
    if ($_GET['action'] === 'test_database') {
        $test_results = [];
        
        // Test 1: Connection
        $test_results['connection'] = isset($conn) && $conn ? 'OK' : 'FAILED';
        
        // Test 2: Student table
        $result = $conn->query("SELECT COUNT(*) as count FROM student");
        $test_results['student_table'] = $result ? 'OK (' . $result->fetch_assoc()['count'] . ' students)' : 'FAILED';
        
        // Test 3: Chat table
        $result = $conn->query("SELECT COUNT(*) as count FROM site_chat");
        $test_results['chat_table'] = $result ? 'OK (' . $result->fetch_assoc()['count'] . ' messages)' : 'FAILED';
        
        echo json_encode(['ok' => true, 'tests' => $test_results]);
        exit;
    }

    // Chat Engine Actions
    if ($_GET['action'] === 'get_notifications') {
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        if (!$student_id) {
            echo json_encode(['ok' => false, 'error' => 'Student ID is required']);
            exit;
        }
        
        $engine = new ChatEngine();
        $notifications = $engine->getNotifications($student_id, $unread_only);
        echo json_encode(['ok' => true, 'notifications' => $notifications]);
        exit;
    }

    if ($_GET['action'] === 'get_statistics') {
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
        
        $engine = new ChatEngine();
        $stats = $engine->getChatStatistics($student_id);
        echo json_encode(['ok' => true, 'statistics' => $stats]);
        exit;
    }

    if ($_GET['action'] === 'mark_notification_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        
        if (!$notification_id) {
            echo json_encode(['ok' => false, 'error' => 'Notification ID is required']);
            exit;
        }
        
        $engine = new ChatEngine();
        $engine->markNotificationRead($notification_id);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - SITE</title>
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
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 25px; margin: 5px 0; border-left: 3px solid transparent; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 5px solid #081b5b; }
        .main-content { flex: 1; display: flex; flex-direction: column; margin-left: 260px; }
        .top-navbar { background: white; padding: 15px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
        .content-area { padding: 30px; flex: 1; }
        .chat-wrapper { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display:flex; height: calc(100vh - 160px); }
        .chat-people { width: 300px; border-right:1px solid #ecf0f1; display:flex; flex-direction:column; }
        .chat-people-header { padding: 12px 16px; border-bottom:1px solid #ecf0f1; font-weight:600; background:#f7f9fc; }
        .chat-people-list { flex:1; overflow:auto; }
        .chat-people-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; text-decoration:none; color:inherit; border-left: 3px solid transparent; }
        .chat-people-item:hover { background:#f1f3f5; }
        .chat-people-item.active { background:#e3f2fd; border-left: 3px solid #20a8f8; }
        .chat-panel { flex:1; display:flex; flex-direction:column; }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #ecf0f1; display:flex; align-items:center; gap:10px; }
        .chat-body { flex:1; padding: 20px; overflow-y:auto; background:#f5f7fb; }
        .chat-input { border-top: 1px solid #ecf0f1; padding: 12px; display:flex; gap:10px; background:white; }
        .msg { max-width: 70%; padding: 10px 14px; border-radius: 14px; margin: 6px 0; font-size: 0.95rem; line-height: 1.25rem; word-wrap: break-word; }
        .msg.me { background:#d1ecf1; color:#0c5460; margin-left:auto; border-bottom-right-radius: 4px; }
        .msg.other { background:#e9ecef; color:#212529; margin-right:auto; border-bottom-left-radius: 4px; }
        .msg .meta { display:block; font-size:0.75rem; color:#6c757d; margin-top:4px; }
        .msg.bot { background:#fff3cd; color:#856404; border-left: 3px solid #ffc107; }
        .notifications-panel { position: fixed; top: 80px; right: 20px; width: 300px; max-height: 400px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1001; display: none; }
        .notifications-header { padding: 12px 16px; border-bottom: 1px solid #ecf0f1; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .notifications-body { max-height: 300px; overflow-y: auto; }
        .notification-item { padding: 12px 16px; border-bottom: 1px solid #f8f9fa; cursor: pointer; }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread { background: #e3f2fd; border-left: 3px solid #20a8f8; }
        .notification-badge { background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; min-width: 18px; text-align: center; }
        .stats-widget { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .menu-toggle { display:none; background:none; border:none; font-size:1.5rem; color:#1e174a; cursor:pointer; }
        @media (max-width: 992px) { .sidebar{transform:translateX(-100%);} .sidebar.active{transform:translateX(0);} .main-content{margin-left:0;} .menu-toggle{display:block;} .btn-close-sidebar{display:block;} }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" style="display:none;"></div>
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
                <li class="nav-item"><a class="nav-link" href="site_penalties.php"><i class="bi bi-exclamation-triangle"></i><span>Penalties</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_balance.php"><i class="bi bi-wallet2"></i><span>Balance</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="site_chat.php"><i class="bi bi-chat-dots"></i><span>Chat</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_report.php"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a></li>
                <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="bi bi-clipboard-check"></i><span>Attendance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../../dashboard.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
            <div><strong>Chat</strong></div>
            <div class="user-info d-flex align-items-center gap-3">
                <?php if ($current_student_id): ?>
                    <button class="btn btn-outline-success btn-sm" id="dbTestBtn" title="Test Database Connection">
                        <i class="bi bi-database-check"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="statsBtn">
                        <i class="bi bi-graph-up"></i> Stats
                    </button>
                    <button class="btn btn-outline-secondary btn-sm position-relative" id="notificationsBtn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge position-absolute top-0 start-100 translate-middle" id="notificationCount" style="display: none;">0</span>
                    </button>
                <?php endif; ?>
                <div class="user-avatar"><i class="bi bi-person-circle"></i></div>
            </div>
        </nav>

        <!-- Notifications Panel -->
        <div class="notifications-panel" id="notificationsPanel">
            <div class="notifications-header">
                <span>Notifications</span>
                <button class="btn btn-sm btn-outline-secondary" id="closeNotifications">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="notifications-body" id="notificationsBody">
                <div class="text-center p-3 text-muted">Loading...</div>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i> <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Modal -->
            <div class="modal fade" id="statsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-graph-up me-2"></i>Chat Statistics</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="statsModalBody">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$current_student_id): ?>
                <!-- Student Selection Screen -->
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-person-check me-2"></i>Select Your Student ID</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="studentSearch" class="form-label">Search for your Student ID or Name:</label>
                                    <input type="text" class="form-control" id="studentSearch" placeholder="Enter Student ID or Name...">
                                </div>
                                <div id="studentList" class="list-group" style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($students as $student): ?>
                                        <?php 
                                            $fullName = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']);
                                            $link = 'site_chat.php?student_id=' . $student['id_number'];
                                        ?>
                                        <a href="<?php echo htmlspecialchars($link); ?>" class="list-group-item list-group-item-action student-item" 
                                           data-id="<?php echo $student['id_number']; ?>" 
                                           data-name="<?php echo htmlspecialchars($fullName); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($fullName); ?></h6>
                                                    <small class="text-muted">ID: <?php echo $student['id_number']; ?> | <?php echo htmlspecialchars($student['course'] . ' ' . $student['year'] . '-' . $student['section']); ?></small>
                                                </div>
                                                <i class="bi bi-arrow-right"></i>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Chat Interface -->
                <div class="chat-wrapper">
                    <div class="chat-people">
                        <div class="chat-people-header">
                            <i class="bi bi-people me-2"></i>Students
                            <button class="btn btn-sm btn-outline-light ms-2" onclick="window.location.href='site_chat.php'">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                        </div>
                        <div class="chat-people-list">
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $s): ?>
                                    <?php 
                                        $name = trim($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']);
                                        $link = 'site_chat.php?student_id=' . $current_student_id . '&selected_student=' . $s['id_number'];
                                        $isActive = ($s['id_number'] == $selected_student_id) ? 'active' : '';
                                    ?>
                                    <a class="chat-people-item <?php echo $isActive; ?>" href="<?php echo htmlspecialchars($link); ?>">
                                        <i class="bi bi-person-circle fs-5"></i>
                                        <div>
                                            <div style="font-weight:600; font-size:0.95rem;"><?php echo htmlspecialchars($name); ?></div>
                                            <div style="font-size:0.8rem; color:#6c757d;">ID: <?php echo $s['id_number']; ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-3 text-muted">No students found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chat-panel">
                        <?php if ($selected_student_id && $selected_student_info): ?>
                            <div class="chat-header">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                <div>
                                    <div><strong><?php echo htmlspecialchars(trim($selected_student_info['first_name'] . ' ' . $selected_student_info['last_name'])); ?></strong></div>
                                    <small class="text-muted">
                                        ID: <?php echo $selected_student_info['id_number']; ?> | 
                                        <?php echo htmlspecialchars($selected_student_info['course'] . ' ' . $selected_student_info['year'] . '-' . $selected_student_info['section']); ?>
                                    </small>
                                </div>
                            </div>
                            <div id="chatBody" class="chat-body"></div>
                            <div class="chat-input">
                                <div class="d-flex gap-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="adminMode">
                                        <label class="form-check-label" for="adminMode">
                                            <small>Send as SITE Admin</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <input id="msgInput" type="text" class="form-control" placeholder="Type a message..." maxlength="1000">
                                    <button id="sendBtn" class="btn btn-primary"><i class="bi bi-send"></i></button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="chat-panel d-flex align-items-center justify-content-center">
                                <div class="text-center text-muted">
                                    <i class="bi bi-chat-dots fs-1 mb-3"></i>
                                    <h5>Select a student to start chatting</h5>
                                    <p>Choose a student from the list to view their chat history and send messages.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            // Sidebar toggle functionality
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            if (menuToggle) menuToggle.addEventListener('click', ()=>{ sidebar.classList.add('active'); overlay.style.display='block'; });
            if (closeSidebar) closeSidebar.addEventListener('click', ()=>{ sidebar.classList.remove('active'); overlay.style.display='none'; });
            if (overlay) overlay.addEventListener('click', ()=>{ sidebar.classList.remove('active'); overlay.style.display='none'; });

            // Student search functionality
            const studentSearch = document.getElementById('studentSearch');
            if (studentSearch) {
                studentSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const studentItems = document.querySelectorAll('.student-item');
                    
                    studentItems.forEach(item => {
                        const name = item.dataset.name.toLowerCase();
                        const id = item.dataset.id.toLowerCase();
                        
                        if (name.includes(searchTerm) || id.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Chat functionality
            const chatBody = document.getElementById('chatBody');
            const msgInput = document.getElementById('msgInput');
            const sendBtn = document.getElementById('sendBtn');

            if (chatBody && msgInput && sendBtn) {
                let lastId = 0;
                const params = new URLSearchParams(window.location.search);
                const currentStudentId = params.get('student_id');
                const selectedStudentId = params.get('selected_student');

                function appendMessage(m){
                    const div = document.createElement('div');
                    const isCurrentUser = m.student_id == currentStudentId;
                    const isAdmin = m.is_admin == 1;
                    const isBot = isAdmin && m.message.includes('SITE chat assistant');
                    
                    let className = 'msg ';
                    if (isBot) {
                        className += 'bot';
                    } else if (isCurrentUser) {
                        className += 'me';
                    } else {
                        className += 'other';
                    }
                    
                    div.className = className;
                    
                    const ts = new Date(m.timestamp.replace(' ', 'T'));
                    const time = isNaN(ts) ? '' : ts.toLocaleString();
                    
                    let senderName = '';
                    if (isBot) {
                        senderName = '🤖 SITE Assistant';
                    } else if (isAdmin) {
                        senderName = 'SITE Admin';
                    } else if (isCurrentUser) {
                        senderName = 'You';
                    } else {
                        senderName = (m.first_name + ' ' + m.last_name).trim();
                    }
                    
                    div.innerHTML = `${escapeHtml(m.message)}<span class="meta">${senderName} • ${time}</span>`;
                    chatBody.appendChild(div);
                    chatBody.scrollTop = chatBody.scrollHeight;
                }

                function escapeHtml(str){
                    return (str||'').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));
                }

                async function fetchMessages(){
                    if (!selectedStudentId) return;
                    
                    try {
                        const res = await fetch(`?action=fetch&student_id=${selectedStudentId}&since_id=${lastId}`);
                        const data = await res.json();
                        if (data.ok && Array.isArray(data.messages)){
                            console.log('📥 Loaded', data.messages.length, 'messages from database');
                            data.messages.forEach(m => { 
                                appendMessage(m); 
                                lastId = Math.max(lastId, m.id); 
                            });
                        } else if (data.error) {
                            console.error('❌ Database fetch error:', data.error);
                        }
                    } catch(e){ 
                        console.error('Network error:', e); 
                    }
                }

                async function sendMessage(){
                    const text = msgInput.value.trim();
                    if (!text || !selectedStudentId) return;
                    
                    const adminMode = document.getElementById('adminMode');
                    const isAdmin = adminMode && adminMode.checked;
                    const sendAsStudentId = isAdmin ? selectedStudentId : currentStudentId;
                    
                    sendBtn.disabled = true;
                    sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                    try {
                        const form = new FormData();
                        form.append('student_id', sendAsStudentId);
                        form.append('message', text);
                        form.append('is_admin', isAdmin ? '1' : '0');
                        
                        const res = await fetch('?action=send', { 
                            method: 'POST', 
                            body: form 
                        });
                        
                        console.log('Response status:', res.status);
                        const data = await res.json();
                        console.log('Response data:', data);
                        
                        if (data.ok) { 
                            msgInput.value = ''; 
                            fetchMessages(); 
                            console.log('✅ Message saved to database successfully, ID:', data.message_id);
                            
                            // Show success indicator
                            showStatusMessage('Message saved to database ✅', 'success');
                        } else {
                            console.error('Send error:', data.error);
                            alert('Error sending message: ' + (data.error || 'Unknown error'));
                        }
                    } catch(e){ 
                        console.error('Send error:', e);
                        alert('Network error occurred');
                    } finally { 
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="bi bi-send"></i>';
                    }
                }

                sendBtn.addEventListener('click', sendMessage);
                msgInput.addEventListener('keydown', (e) => { 
                    if (e.key === 'Enter') { 
                        e.preventDefault(); 
                        sendMessage(); 
                    } 
                });

                // Initial load and polling for selected student
                if (selectedStudentId) {
                    fetchMessages();
                    setInterval(fetchMessages, 3000);
                }
            }

            // Chat Engine Features
            const notificationsBtn = document.getElementById('notificationsBtn');
            const notificationsPanel = document.getElementById('notificationsPanel');
            const closeNotifications = document.getElementById('closeNotifications');
            const statsBtn = document.getElementById('statsBtn');
            const notificationCount = document.getElementById('notificationCount');

            // Notifications functionality
            if (notificationsBtn && currentStudentId) {
                notificationsBtn.addEventListener('click', toggleNotifications);
                closeNotifications.addEventListener('click', hideNotifications);
                
                // Load notifications on page load
                loadNotifications();
                
                // Check for new notifications periodically
                setInterval(checkNotifications, 30000);
            }

            // Database test functionality
            const dbTestBtn = document.getElementById('dbTestBtn');
            if (dbTestBtn) {
                dbTestBtn.addEventListener('click', testDatabase);
            }

            // Statistics functionality
            if (statsBtn && currentStudentId) {
                statsBtn.addEventListener('click', showStatistics);
            }

            function toggleNotifications() {
                const isVisible = notificationsPanel.style.display === 'block';
                if (isVisible) {
                    hideNotifications();
                } else {
                    showNotifications();
                }
            }

            function showNotifications() {
                notificationsPanel.style.display = 'block';
                loadNotifications();
            }

            function hideNotifications() {
                notificationsPanel.style.display = 'none';
            }

            async function loadNotifications() {
                if (!currentStudentId) return;
                
                try {
                    const res = await fetch(`?action=get_notifications&student_id=${currentStudentId}`);
                    const data = await res.json();
                    
                    if (data.ok) {
                        displayNotifications(data.notifications);
                    }
                } catch (e) {
                    console.error('Error loading notifications:', e);
                }
            }

            async function checkNotifications() {
                if (!currentStudentId) return;
                
                try {
                    const res = await fetch(`?action=get_notifications&student_id=${currentStudentId}&unread_only=true`);
                    const data = await res.json();
                    
                    if (data.ok) {
                        const unreadCount = data.notifications.length;
                        updateNotificationBadge(unreadCount);
                    }
                } catch (e) {
                    console.error('Error checking notifications:', e);
                }
            }

            function displayNotifications(notifications) {
                const body = document.getElementById('notificationsBody');
                
                if (notifications.length === 0) {
                    body.innerHTML = '<div class="text-center p-3 text-muted">No notifications</div>';
                    return;
                }

                body.innerHTML = notifications.map(notification => `
                    <div class="notification-item ${notification.is_read == 0 ? 'unread' : ''}" 
                         onclick="markNotificationRead(${notification.id})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold text-${getNotificationColor(notification.type)}">
                                    ${getNotificationIcon(notification.type)} ${notification.type.toUpperCase()}
                                </div>
                                <div class="small">${escapeHtml(notification.message)}</div>
                                <div class="text-muted small">${new Date(notification.created_at).toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            function getNotificationColor(type) {
                const colors = {
                    'info': 'primary',
                    'warning': 'warning', 
                    'error': 'danger',
                    'success': 'success'
                };
                return colors[type] || 'secondary';
            }

            function getNotificationIcon(type) {
                const icons = {
                    'info': '🔔',
                    'warning': '⚠️',
                    'error': '❌',
                    'success': '✅'
                };
                return icons[type] || '📢';
            }

            function updateNotificationBadge(count) {
                if (count > 0) {
                    notificationCount.textContent = count;
                    notificationCount.style.display = 'inline';
                } else {
                    notificationCount.style.display = 'none';
                }
            }

            async function markNotificationRead(notificationId) {
                try {
                    const form = new FormData();
                    form.append('notification_id', notificationId);
                    
                    await fetch('?action=mark_notification_read', {
                        method: 'POST',
                        body: form
                    });
                    
                    loadNotifications();
                    checkNotifications();
                } catch (e) {
                    console.error('Error marking notification as read:', e);
                }
            }

            async function showStatistics() {
                const modal = new bootstrap.Modal(document.getElementById('statsModal'));
                const modalBody = document.getElementById('statsModalBody');
                
                modal.show();
                
                try {
                    const res = await fetch(`?action=get_statistics&student_id=${currentStudentId}`);
                    const data = await res.json();
                    
                    if (data.ok && data.statistics.length > 0) {
                        const stats = data.statistics[0];
                        modalBody.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stats-widget">
                                        <h6><i class="bi bi-chat-left-text me-2"></i>Messages Sent</h6>
                                        <h3 class="text-primary">${stats.messages_sent || 0}</h3>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-widget">
                                        <h6><i class="bi bi-chat-right-text me-2"></i>Messages Received</h6>
                                        <h3 class="text-success">${stats.messages_received || 0}</h3>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-widget">
                                        <h6><i class="bi bi-clock me-2"></i>Last Active</h6>
                                        <p class="mb-0">${new Date(stats.last_active).toLocaleString()}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-widget">
                                        <h6><i class="bi bi-type me-2"></i>Total Words</h6>
                                        <h3 class="text-info">${stats.total_words || 0}</h3>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        modalBody.innerHTML = '<div class="text-center p-3 text-muted">No statistics available yet. Start chatting to see your stats!</div>';
                    }
                } catch (e) {
                    console.error('Error loading statistics:', e);
                    modalBody.innerHTML = '<div class="text-center p-3 text-danger">Error loading statistics</div>';
                }
            }

            // Status message function
            async function testDatabase() {
                const btn = document.getElementById('dbTestBtn');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                btn.disabled = true;
                
                try {
                    const res = await fetch('?action=test_database');
                    const data = await res.json();
                    
                    if (data.ok) {
                        let message = '🗄️ Database Status:\n';
                        for (const [test, result] of Object.entries(data.tests)) {
                            const status = result.includes('OK') ? '✅' : '❌';
                            message += `${status} ${test.replace('_', ' ')}: ${result}\n`;
                        }
                        
                        showStatusMessage(message.replace(/\n/g, '<br>'), 'info');
                        console.log('Database test results:', data.tests);
                    } else {
                        showStatusMessage('❌ Database test failed', 'danger');
                    }
                } catch (e) {
                    console.error('Database test error:', e);
                    showStatusMessage('❌ Could not test database', 'danger');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            }

            function showStatusMessage(message, type = 'info') {
                const statusDiv = document.createElement('div');
                statusDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                statusDiv.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
                statusDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                `;
                document.body.appendChild(statusDiv);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    if (statusDiv.parentElement) {
                        statusDiv.remove();
                    }
                }, 3000);
            }

            // Close notifications when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationsPanel && !notificationsPanel.contains(e.target) && !notificationsBtn.contains(e.target)) {
                    hideNotifications();
                }
            });
        })();
    </script>
</body>
</html>