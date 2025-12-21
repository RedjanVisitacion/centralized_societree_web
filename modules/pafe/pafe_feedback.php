<?php
session_start();
require_once '../../db_connection.php';

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                try {
                    $feedback_id = $_POST['feedback_id'];
                    $stmt = $pdo->prepare("UPDATE pafe_feedback SET status = 'read' WHERE id = ?");
                    $stmt->execute([$feedback_id]);
                    $success_message = "Feedback marked as read successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating feedback: " . $e->getMessage();
                }
                break;
                
            case 'delete_feedback':
                try {
                    $feedback_id = $_POST['feedback_id'];
                    $stmt = $pdo->prepare("DELETE FROM pafe_feedback WHERE id = ?");
                    $stmt->execute([$feedback_id]);
                    $success_message = "Feedback deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting feedback: " . $e->getMessage();
                }
                break;
                
            case 'reply_feedback':
                try {
                    $feedback_id = $_POST['feedback_id'];
                    $reply_message = $_POST['reply_message'];
                    $stmt = $pdo->prepare("UPDATE pafe_feedback SET admin_reply = ?, status = 'replied', replied_at = NOW() WHERE id = ?");
                    $stmt->execute([$reply_message, $feedback_id]);
                    $success_message = "Reply sent successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error sending reply: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch feedback statistics
try {
    // Get total feedback count
    $total_feedback_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_feedback");
    $total_feedback = $total_feedback_stmt->fetch()['total'] ?? 0;
    
    // Get unread feedback count
    $unread_feedback_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_feedback WHERE status = 'unread'");
    $unread_feedback = $unread_feedback_stmt->fetch()['total'] ?? 0;
    
    // Get replied feedback count
    $replied_feedback_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_feedback WHERE status = 'replied'");
    $replied_feedback = $replied_feedback_stmt->fetch()['total'] ?? 0;
    
    // Get all feedback with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $where_clause = '';
    if ($filter !== 'all') {
        $where_clause = "WHERE status = :status";
    }
    
    $feedback_stmt = $pdo->prepare("
        SELECT f.*, s.first_name, s.last_name, s.email, s.course, s.year, s.section
        FROM pafe_feedback f
        LEFT JOIN student s ON f.student_id = s.id_number
        $where_clause
        ORDER BY f.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    if ($filter !== 'all') {
        $feedback_stmt->bindParam(':status', $filter);
    }
    $feedback_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $feedback_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $feedback_stmt->execute();
    $feedback_list = $feedback_stmt->fetchAll();
    
    // Get total pages for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pafe_feedback $where_clause");
    if ($filter !== 'all') {
        $count_stmt->bindParam(':status', $filter);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
} catch (PDOException $e) {
    $total_feedback = 0;
    $unread_feedback = 0;
    $replied_feedback = 0;
    $feedback_list = [];
    $total_pages = 1;
    $db_error = "Database error: " . $e->getMessage();
}

// Create feedback table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pafe_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            rating INT DEFAULT NULL,
            status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
            admin_reply TEXT NULL,
            replied_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES student(id_number) ON DELETE SET NULL
        )
    ");
} catch (PDOException $e) {
    // Table creation failed, but continue
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - PAFE</title>
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

        /* Feedback Management Styles */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(238, 166, 24, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #eea618, #f39c12);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #eea618;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card.total .stat-number { color: #17a2b8; }
        .stat-card.unread .stat-number { color: #dc3545; }
        .stat-card.replied .stat-number { color: #28a745; }

        .feedback-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .feedback-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .feedback-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .feedback-item.unread {
            border-left: 4px solid #dc3545;
            background: rgba(220, 53, 69, 0.02);
        }

        .feedback-item.read {
            border-left: 4px solid #6c757d;
        }

        .feedback-item.replied {
            border-left: 4px solid #28a745;
            background: rgba(40, 167, 69, 0.02);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .feedback-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .feedback-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .feedback-email {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .feedback-date {
            color: #95a5a6;
            font-size: 0.8rem;
        }

        .feedback-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unread {
            background: #dc3545;
            color: white;
        }

        .status-read {
            background: #6c757d;
            color: white;
        }

        .status-replied {
            background: #28a745;
            color: white;
        }

        .feedback-subject {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .feedback-message {
            color: #495057;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .rating-stars {
            color: #ffc107;
        }

        .feedback-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-reply {
            background: rgba(238, 166, 24, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #eea618;
        }

        .reply-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #eea618, #f39c12);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .btn-close {
            filter: invert(1);
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
                    <a class="nav-link" href="pafe_announcement.php">
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
                    <a class="nav-link active" href="pafe_feedback.php">
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
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($db_error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="bi bi-exclamation-triangle"></i> Database Connection Issue</h5>
                <p><?= htmlspecialchars($db_error) ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <h2 class="mb-4">
                <i class="bi bi-chat-square-text"></i> Feedback Management
            </h2>

            <!-- Statistics Row -->
            <div class="stats-row">
                <div class="stat-card total">
                    <div class="stat-number"><?= $total_feedback ?></div>
                    <div class="stat-label">
                        <i class="bi bi-chat-square-text"></i> Total Feedback
                    </div>
                </div>
                <div class="stat-card unread">
                    <div class="stat-number"><?= $unread_feedback ?></div>
                    <div class="stat-label">
                        <i class="bi bi-envelope"></i> Unread
                    </div>
                </div>
                <div class="stat-card replied">
                    <div class="stat-number"><?= $replied_feedback ?></div>
                    <div class="stat-label">
                        <i class="bi bi-reply"></i> Replied
                    </div>
                </div>
            </div>

            <!-- Feedback Section -->
            <div class="feedback-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul"></i> Feedback List
                    </h5>
                    <div class="feedback-filters">
                        <a href="?filter=all" class="btn btn-outline-secondary <?= (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : '' ?>">
                            All
                        </a>
                        <a href="?filter=unread" class="btn btn-outline-danger <?= (isset($_GET['filter']) && $_GET['filter'] === 'unread') ? 'active' : '' ?>">
                            Unread
                        </a>
                        <a href="?filter=read" class="btn btn-outline-secondary <?= (isset($_GET['filter']) && $_GET['filter'] === 'read') ? 'active' : '' ?>">
                            Read
                        </a>
                        <a href="?filter=replied" class="btn btn-outline-success <?= (isset($_GET['filter']) && $_GET['filter'] === 'replied') ? 'active' : '' ?>">
                            Replied
                        </a>
                    </div>
                </div>

                <?php if (empty($feedback_list)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-chat-square-text-fill text-muted" style="font-size: 3rem;"></i>
                    <h6 class="text-muted mt-3">No feedback found</h6>
                    <p class="text-muted">Feedback from users will appear here</p>
                </div>
                <?php else: ?>
                <div class="feedback-list">
                    <?php foreach ($feedback_list as $feedback): ?>
                    <div class="feedback-item <?= $feedback['status'] ?>">
                        <div class="feedback-header">
                            <div class="feedback-meta">
                                <div class="feedback-name">
                                    <?= htmlspecialchars($feedback['first_name'] && $feedback['last_name'] ? $feedback['first_name'] . ' ' . $feedback['last_name'] : 'Anonymous User') ?>
                                    <?php if ($feedback['first_name']): ?>
                                    <small class="text-muted">
                                        (<?= htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']) ?> - 
                                        <?= htmlspecialchars($feedback['course']) ?> <?= htmlspecialchars($feedback['year']) ?>-<?= htmlspecialchars($feedback['section']) ?>)
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="feedback-email"><?= htmlspecialchars($feedback['email']) ?></div>
                                <div class="feedback-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('M d, Y - H:i A', strtotime($feedback['created_at'])) ?>
                                </div>
                            </div>
                            <span class="feedback-status status-<?= $feedback['status'] ?>">
                                <?= ucfirst($feedback['status']) ?>
                            </span>
                        </div>

                        <div class="feedback-subject">
                            <i class="bi bi-chat-quote"></i> <?= htmlspecialchars($feedback['subject']) ?>
                        </div>

                        <div class="feedback-message">
                            <?= nl2br(htmlspecialchars($feedback['message'])) ?>
                        </div>

                        <?php if ($feedback['rating']): ?>
                        <div class="feedback-rating">
                            <span><strong>Rating:</strong></span>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= $feedback['rating'] ? '-fill' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-muted">(<?= $feedback['rating'] ?>/5)</span>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($feedback['admin_reply']) && $feedback['admin_reply']): ?>
                        <div class="admin-reply">
                            <h6><i class="bi bi-reply"></i> Admin Reply:</h6>
                            <p class="mb-2"><?= nl2br(htmlspecialchars($feedback['admin_reply'])) ?></p>
                            <small class="text-muted">
                                Replied on <?= date('M d, Y - H:i A', strtotime($feedback['replied_at'])) ?>
                            </small>
                        </div>
                        <?php endif; ?>

                        <div class="feedback-actions">
                            <?php if ($feedback['status'] === 'unread'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($feedback['status'] !== 'replied'): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="showReplyForm(<?= $feedback['id'] ?>)">
                                <i class="bi bi-reply"></i> Reply
                            </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="confirmDelete(<?= $feedback['id'] ?>)">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>

                        <!-- Reply Form (Hidden by default) -->
                        <div id="replyForm<?= $feedback['id'] ?>" class="reply-form" style="display: none;">
                            <form method="POST">
                                <input type="hidden" name="action" value="reply_feedback">
                                <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Reply Message:</label>
                                    <textarea name="reply_message" class="form-control" rows="3" required 
                                              placeholder="Type your reply here..."></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-send"></i> Send Reply
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" 
                                            onclick="hideReplyForm(<?= $feedback['id'] ?>)">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>">Previous</a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this feedback? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_feedback">
                        <input type="hidden" name="feedback_id" id="deleteFeedbackId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete
                        </button>
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
        });

        // Feedback Management Functions
        function showReplyForm(feedbackId) {
            const replyForm = document.getElementById('replyForm' + feedbackId);
            replyForm.style.display = 'block';
            replyForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideReplyForm(feedbackId) {
            const replyForm = document.getElementById('replyForm' + feedbackId);
            replyForm.style.display = 'none';
        }

        function confirmDelete(feedbackId) {
            document.getElementById('deleteFeedbackId').value = feedbackId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>