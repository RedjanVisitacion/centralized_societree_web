<?php
// Database connection and service requests fetching
require_once(__DIR__ . '/../../db_connection.php');

// Handle AJAX requests for status updates and task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        
        // Map UI statuses to DB enum values (Pending, Approved, Completed, Rejected)
        $valid_statuses = ['Pending', 'Approved', 'Completed', 'Rejected'];

        if ($request_id > 0 && in_array($status, $valid_statuses, true)) {
            try {
                $sql = "UPDATE access_service_requests SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$status, $request_id]);
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating status']);
            }
        }
        exit;
    }
    
    if ($action === 'update_task_status') {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        
        $valid_task_statuses = ['To Do', 'In Progress', 'Completed'];

        if ($task_id > 0 && in_array($status, $valid_task_statuses, true)) {
            try {
                $progress = ($status === 'Completed') ? 100 : null;
                $sql = "UPDATE access_tasks SET status = ?" . ($progress !== null ? ", progress = ?" : "") . " WHERE id = ?";
                $params = [$status];
                if ($progress !== null) $params[] = $progress;
                $params[] = $task_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating task status']);
            }
        }
        exit;
    }
    
    if ($action === 'delete_task') {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        if ($task_id > 0) {
            try {
                $sql = "DELETE FROM access_tasks WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$task_id]);
                
                echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting task']);
            }
        }
        exit;
    }
    
    if ($action === 'create_task') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $assigned_to = isset($_POST['assigned_to']) ? trim($_POST['assigned_to']) : '';
        $priority = isset($_POST['priority']) ? $_POST['priority'] : 'Medium';
        $status = isset($_POST['status']) ? $_POST['status'] : 'To Do';
        $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
        
        $valid_priorities = ['Low', 'Medium', 'High', 'Urgent'];
        $valid_statuses = ['To Do', 'In Progress', 'Completed'];

        if (!empty($title) && !empty($assigned_to) && in_array($priority, $valid_priorities) && in_array($status, $valid_statuses)) {
            try {
                $sql = "INSERT INTO access_tasks (title, description, assigned_to, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $description, $assigned_to, $priority, $status, $due_date]);
                
                echo json_encode(['success' => true, 'message' => 'Task created successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error creating task']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        }
        exit;
    }
}

// Fetch service requests from database
try {
    $sql = "SELECT sr.*,
                   m.full_name AS requester_name
            FROM access_service_requests sr
            LEFT JOIN access_members m ON m.id = sr.member_id
            ORDER BY sr.created_at DESC";
    $stmt = $pdo->query($sql);
    $service_requests = $stmt->fetchAll();
} catch(PDOException $e) {
    $service_requests = [];
    $db_error = 'Error loading service requests: ' . $e->getMessage();
}

// Fetch tasks from database
try {
    $sql = "SELECT * FROM access_tasks ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $tasks = [];
    $task_error = 'Error loading tasks: ' . $e->getMessage();
}

// Calculate statistics
$total_requests = count($service_requests);
$open_count = 0;
$in_progress_count = 0;
$completed_count = 0;

foreach ($service_requests as $req) {
    switch($req['status']) {
        case 'Open':
            $open_count++;
            break;
        case 'In Progress':
            $in_progress_count++;
            break;
        case 'Completed':
            $completed_count++;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests & Tasks - ACCESS</title>
    <link rel="icon" href="../../assets/logo/access_2.png" type="image/png">
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
            background: #25bcd9;
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
            border-left: 5px solid #430fa0;
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

        .card-feature {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .status-pill.pending {
            background: rgba(255,193,7,0.2);
            color: #b8860b;
        }

        .status-pill.approved {
            background: rgba(13,110,253,0.2);
            color: #0a58ca;
        }

        .status-pill.rejected {
            background: rgba(220,53,69,0.2);
            color: #b02a37;
        }

        .status-pill.in-progress {
            background: rgba(255,152,0,0.2);
            color: #e65100;
        }

        .status-pill.completed {
            background: rgba(25,135,84,0.2);
            color: #146c43;
        }

        .status-pill.todo {
            background: rgba(108,117,125,0.2);
            color: #6c757d;
        }

        .status-pill.progress {
            background: rgba(13,110,253,0.2);
            color: #0d6efd;
        }

        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }

        .kanban-column {
            background: #f8f9fb;
            border-radius: 12px;
            padding: 16px;
            min-height: 320px;
            border: 1px dashed #dfe3eb;
        }

        .kanban-column h6 {
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            color: #6c757d;
            margin-bottom: 12px;
        }

        .kanban-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            margin-bottom: 12px;
        }

        .kanban-card:last-child {
            margin-bottom: 0;
        }

        .progress-bar {
            height: 6px;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 12px 20px;
            margin-right: 5px;
        }

        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
        }

        .nav-tabs .nav-link.active {
            color: #25bcd9;
            border-bottom-color: #25bcd9;
            background: transparent;
        }

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

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #1e174a;
            cursor: pointer;
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
        }

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

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/access_2.png" alt="ACCESS Logo">
                    <h4>Active Certified Computer Enhance Student Society</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="access_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_learning_hub.php">
                        <i class="bi bi-lightbulb"></i>
                        <span>Learning Hub</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_gallery.php">
                        <i class="bi bi-file-earmark-image"></i>
                        <span>Gallery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="access_service_requests.php">
                        <i class="bi bi-tools"></i>
                        <span>Service Requests & Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="access_feedback.php">
                        <i class="bi bi-chat-dots"></i>
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

    <div class="main-content">
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

        <div class="content-area">
            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="service-requests-tab" data-bs-toggle="tab" data-bs-target="#service-requests" type="button" role="tab">
                        <i class="bi bi-tools me-2"></i>Service Requests & Tasks
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab">
                        <i class="bi bi-list-check me-2"></i>Tasks Management
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="mainTabContent">
                <!-- Service Requests Tab -->
                <div class="tab-pane fade show active" id="service-requests" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h2 class="mb-1">Service Requests & Tasks</h2>
                            <p class="text-muted mb-0">Monitor user submissions and keep approvals coordinated.</p>
                        </div>
                        <div class="search-box" style="max-width:320px;">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search by Request ID or requester">
                                <button class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card-feature mb-4">
                        <form class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Category / Type</label>
                                <select class="form-select">
                                    <option selected>All Categories</option>
                                    <option>Documentation</option>
                                    <option>Media Release</option>
                                    <option>Training Support</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select">
                                    <option selected>All Statuses</option>
                                    <option>Pending</option>
                                    <option>Approved</option>
                                    <option>Rejected</option>
                                    <option>In Progress</option>
                                    <option>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date Submitted</label>
                                <input type="date" class="form-control">
                            </div>
                        </form>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <!-- Requests Table -->
                            <div class="card-feature h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">All Requests</h5>
                                    <small class="text-muted">Track approvals and pending items</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle table-striped">
                                        <thead>
                                            <tr>
                                                <th>Request ID</th>
                                                <th>Requester Name</th>
                                                <th>Category / Type</th>
                                                <th>Date Submitted</th>
                                                <th>Status</th>
                                                <th>Priority</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($service_requests)): ?>
                                                <?php foreach ($service_requests as $req): 
                                                    $display_id = 'SR-' . str_pad($req['id'], 4, '0', STR_PAD_LEFT);
                                                    $req_name = $req['requester_name'] ?: 'Unknown Member';
                                                    $category = $req['category'];
                                                    $priority = $req['priority'];
                                                    $created = !empty($req['created_at']) ? date('M d, Y', strtotime($req['created_at'])) : 'N/A';
                                                    $status = $req['status'];

                                                    $status_class = 'pending';
                                                    if ($status === 'Approved') $status_class = 'approved';
                                                    if ($status === 'Completed') $status_class = 'completed';
                                                    if ($status === 'Rejected') $status_class = 'rejected';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($display_id); ?></td>
                                                    <td><?php echo htmlspecialchars($req_name); ?></td>
                                                    <td><?php echo htmlspecialchars($category); ?></td>
                                                    <td><?php echo htmlspecialchars($created); ?></td>
                                                    <td><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                                    <td>
                                                        <?php if ($priority === 'High' || $priority === 'Urgent'): ?>
                                                            <span class="badge bg-danger"><?php echo htmlspecialchars($priority); ?></span>
                                                        <?php elseif ($priority === 'Medium'): ?>
                                                            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($priority); ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?php echo htmlspecialchars($priority); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <button 
                                                            class="btn btn-sm btn-outline-primary me-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#requestDetailsModal"
                                                            onclick="setModalRequestId(<?php echo $req['id']; ?>)"
                                                            title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($status === 'Pending'): ?>
                                                            <button 
                                                                class="btn btn-sm btn-success me-1" 
                                                                onclick="updateRequestStatus(<?php echo $req['id']; ?>, 'Approved')"
                                                                title="Approve">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="updateRequestStatus(<?php echo $req['id']; ?>, 'Rejected')"
                                                                title="Decline">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">No service requests found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks Management Tab -->
                <div class="tab-pane fade" id="tasks" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h2 class="mb-1">Task Management</h2>
                            <p class="text-muted mb-0">Assign, track, and visualize documentation tasks across ACCESS initiatives.</p>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="bi bi-plus-lg me-1"></i>
                            Create Task
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="card-feature mb-4">
                        <form class="row gy-3 gx-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select">
                                    <option selected>All Priorities</option>
                                    <option>High</option>
                                    <option>Medium</option>
                                    <option>Low</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label">Status</label>
                                <select class="form-select">
                                    <option selected>All Statuses</option>
                                    <option>To Do</option>
                                    <option>In Progress</option>
                                    <option>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control">
                            </div>
                            <div class="col-lg-3 d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary w-100">Reset</button>
                                <button type="button" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>

                    <!-- Task List -->
                    <div class="card-feature mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Task List</h5>
                            <small class="text-muted">Monitor current assignments</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle table-striped">
                                <thead>
                                    <tr>
                                        <th>Task Title</th>
                                        <th>Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($tasks)): ?>
                                        <?php foreach ($tasks as $task): 
                                            $priority = $task['priority'];
                                            $status = $task['status'];
                                            $due_date = !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'No due date';
                                            
                                            // Priority badge classes
                                            $priority_class = 'bg-success';
                                            if ($priority === 'High' || $priority === 'Urgent') $priority_class = 'bg-danger';
                                            elseif ($priority === 'Medium') $priority_class = 'bg-warning text-dark';
                                            
                                            // Status badge classes
                                            $status_class = 'bg-secondary';
                                            if ($status === 'In Progress') $status_class = 'bg-primary';
                                            elseif ($status === 'Completed') $status_class = 'bg-success';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td><?php echo htmlspecialchars($task['assigned_to']); ?></td>
                                            <td><span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                            <td><?php echo htmlspecialchars($due_date); ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#taskDetailsModal" onclick="setTaskModalData(<?php echo $task['id']; ?>, '<?php echo addslashes($task['title']); ?>', '<?php echo addslashes($task['assigned_to']); ?>', '<?php echo $due_date; ?>', '<?php echo $priority; ?>', '<?php echo addslashes($task['description']); ?>')" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success me-1" <?php echo $status === 'Completed' ? 'disabled' : ''; ?> onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'Completed')" data-bs-toggle="tooltip" title="Mark Complete">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(<?php echo $task['id']; ?>)" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No tasks found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Kanban Board -->
                    <div class="card-feature mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Kanban Board</h5>
                            <small class="text-muted">Visualize task workflow</small>
                        </div>
                        <div class="kanban-board">
                            <?php 
                            $kanban_columns = ['To Do', 'In Progress', 'Completed'];
                            foreach ($kanban_columns as $column_status): 
                                $column_tasks = array_filter($tasks, function($task) use ($column_status) {
                                    return $task['status'] === $column_status;
                                });
                            ?>
                            <div class="kanban-column">
                                <h6><?php echo $column_status; ?></h6>
                                <?php if (!empty($column_tasks)): ?>
                                    <?php foreach ($column_tasks as $task): 
                                        $priority = $task['priority'];
                                        $due_date = !empty($task['due_date']) ? date('M d', strtotime($task['due_date'])) : 'No due date';
                                        
                                        // Priority badge classes
                                        $priority_class = 'bg-success';
                                        if ($priority === 'High' || $priority === 'Urgent') $priority_class = 'bg-danger';
                                        elseif ($priority === 'Medium') $priority_class = 'bg-warning text-dark';
                                        
                                        $description = !empty($task['description']) ? $task['description'] : 'No description available.';
                                        if (strlen($description) > 80) {
                                            $description = substr($description, 0, 80) . '...';
                                        }
                                    ?>
                                    <div class="kanban-card">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            <span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span>
                                        </div>
                                        <p class="mb-2 small text-muted"><?php echo htmlspecialchars($description); ?></p>
                                        <?php if ($task['status'] === 'In Progress' && $task['progress'] > 0): ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $task['progress']; ?>%;"></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-light text-dark">
                                                <?php echo $task['status'] === 'Completed' ? 'Completed ' . $due_date : 'Due: ' . $due_date; ?>
                                            </span>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskDetailsModal" onclick="setTaskModalData(<?php echo $task['id']; ?>, '<?php echo addslashes($task['title']); ?>', '<?php echo addslashes($task['assigned_to']); ?>', '<?php echo $due_date; ?>', '<?php echo $priority; ?>', '<?php echo addslashes($task['description']); ?>')">Open</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <small>No tasks in this status</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="requestDetailsModalLabel">SR-0124 · Event Coverage</h5>
                        <p class="mb-0 text-muted small">Submitted by Kim Santos · Dec 03, 2025 · Priority High</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <h6>Request Details</h6>
                            <p class="text-muted">
                                Need full coverage for the city outreach program—photo team, video highlights,
                                and final publication layout for social channels.
                            </p>
                            <div class="mb-3">
                                <h6>Attachments</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="bi bi-paperclip me-1"></i> BriefingDocument.pdf</li>
                                    <li><i class="bi bi-paperclip me-1"></i> Schedule.xlsx</li>
                                </ul>
                            </div>
                            <div>
                                <h6>Activity Timeline</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">Dec 03 · Request submitted by Kim Santos</li>
                                    <li class="list-group-item">Dec 04 · Reviewed by Admin ACCESS</li>
                                    <li class="list-group-item">Dec 05 · Awaiting assignment</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6>Request Status</h6>
                            <div class="border rounded p-3 mb-3">
                                <div class="mb-3">
                                    <label class="form-label">Current Status</label>
                                    <select class="form-select" id="statusSelect" disabled>
                                        <option value="Pending">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                                <div class="d-grid gap-2" id="statusActions">
                                    <button class="btn btn-success" onclick="updateModalStatus('Approved')">
                                        <i class="bi bi-check-lg me-1"></i>Approve Request
                                    </button>
                                    <button class="btn btn-danger" onclick="updateModalStatus('Rejected')">
                                        <i class="bi bi-x-lg me-1"></i>Decline Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">View Full Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTaskModalLabel">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createTaskForm" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Task Title</label>
                            <input type="text" id="taskTitle" class="form-control" placeholder="Enter task or event focus" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select id="taskPriority" class="form-select" required>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Low">Low</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Users</label>
                            <input type="text" id="taskAssignedTo" class="form-control" placeholder="e.g., Alyssa Dizon, Team ACCESS" required>
                            <div class="form-text">Separate multiple members with commas.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select id="taskStatus" class="form-select" required>
                                <option value="To Do" selected>To Do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" id="taskDueDate" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea id="taskDescription" class="form-control" rows="3" placeholder="Add task instructions, references, or objectives..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createTask()">Save Task</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="taskDetailsModalLabel">City Outreach Coverage</h5>
                        <p class="text-muted mb-0 small">Assigned to Alyssa Dizon · Due Dec 10, 2025 · Priority High</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <h6>Description</h6>
                            <p class="text-muted">Coordinate documentation for the upcoming city outreach, capture interviews, gather raw assets, and submit highlight drafts to the editing team.</p>
                            <div class="mb-3">
                                <h6>Attachments</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="bi bi-paperclip me-1"></i> Shotlist.pdf</li>
                                    <li><i class="bi bi-paperclip me-1"></i> EventBrief.docx</li>
                                </ul>
                            </div>
                            <div>
                                <h6>Activity Updates</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>Nov 30:</strong> Task assigned to Alyssa D.
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Dec 02:</strong> Progress updated to 55%.
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Dec 04:</strong> Additional volunteers added for coverage.
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6>Comments</h6>
                            <div class="border rounded p-2 mb-3" style="max-height: 200px; overflow-y: auto;">
                                <div class="mb-2">
                                    <strong>Mika</strong>
                                    <p class="mb-0 small text-muted">Please include shots of the onsite workshop.</p>
                                </div>
                                <div class="mb-2">
                                    <strong>Ralph</strong>
                                    <p class="mb-0 small text-muted">Uploading reference graphics later today.</p>
                                </div>
                            </div>
                            <textarea class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                            <button class="btn btn-primary w-100 mt-2">Post Comment</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Edit Task</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
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

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Function to update request status
        function updateRequestStatus(requestId, status) {
            if (confirm('Are you sure you want to ' + (status === 'Approved' ? 'approve' : 'decline') + ' this request?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_status&request_id=' + requestId + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Request status updated successfully!');
                        location.reload(); // Reload page to show updated status
                    } else {
                        alert('Error updating status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the status.');
                });
            }
        }

        // Function to update status from modal
        function updateModalStatus(status) {
            // This would need the current request ID - you can set this when opening the modal
            const requestId = document.getElementById('requestDetailsModal').dataset.requestId;
            if (requestId) {
                updateRequestStatus(requestId, status);
            }
        }

        // Function to set request ID for modal
        function setModalRequestId(requestId) {
            document.getElementById('requestDetailsModal').dataset.requestId = requestId;
        }

        // Function to set task modal data
        function setTaskModalData(taskId, title, assignedTo, dueDate, priority, description) {
            document.getElementById('taskDetailsModalLabel').textContent = title;
            document.querySelector('#taskDetailsModal .text-muted.small').textContent = 
                'Assigned to ' + assignedTo + ' · Due ' + dueDate + ' · Priority ' + priority;
            document.querySelector('#taskDetailsModal .col-lg-7 p.text-muted').textContent = description;
        }

        // Function to update task status
        function updateTaskStatus(taskId, status) {
            if (confirm('Are you sure you want to mark this task as ' + status.toLowerCase() + '?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_task_status&task_id=' + taskId + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error updating task status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the task status.');
                });
            }
        }

        // Function to delete task
        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_task&task_id=' + taskId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting task: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the task.');
                });
            }
        }

        // Function to create new task
        function createTask() {
            const title = document.getElementById('taskTitle').value.trim();
            const description = document.getElementById('taskDescription').value.trim();
            const assignedTo = document.getElementById('taskAssignedTo').value.trim();
            const priority = document.getElementById('taskPriority').value;
            const status = document.getElementById('taskStatus').value;
            const dueDate = document.getElementById('taskDueDate').value;

            if (!title || !assignedTo || !dueDate) {
                alert('Please fill in all required fields (Title, Assigned To, and Due Date)');
                return;
            }

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=create_task&title=' + encodeURIComponent(title) + 
                      '&description=' + encodeURIComponent(description) + 
                      '&assigned_to=' + encodeURIComponent(assignedTo) + 
                      '&priority=' + encodeURIComponent(priority) + 
                      '&status=' + encodeURIComponent(status) + 
                      '&due_date=' + encodeURIComponent(dueDate)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task created successfully!');
                    document.getElementById('createTaskForm').reset();
                    bootstrap.Modal.getInstance(document.getElementById('createTaskModal')).hide();
                    location.reload();
                } else {
                    alert('Error creating task: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the task.');
            });
        }
    </script>
</body>
</html>
