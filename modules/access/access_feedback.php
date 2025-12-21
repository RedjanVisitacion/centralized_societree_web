<?php
require_once __DIR__ . '/../../db_connection.php';

$total_feedback = 0;
$unresolved_count = 0;
$most_common_type = 'Suggestion';
$most_active_user = 'N/A';
$most_active_count = 0;
$all_feedback = [];

// Map numeric rating to a simple feedback_type label
function access_feedback_type_from_rating($rating)
{
    if ($rating >= 5) return 'Praise';
    if ($rating === 4) return 'Suggestion';
    if ($rating === 3) return 'Bug Report';
    return 'Complaint';
}

// PHP helpers used in the template
function getTypeEmoji($type)
{
    switch ($type) {
        case 'Praise': return '🎉';
        case 'Complaint': return '😤';
        case 'Bug Report': return '🐞';
        case 'Suggestion':
        default: return '💡';
    }
}

function getTypeBadge($type)
{
    switch ($type) {
        case 'Praise': return 'badge bg-success';
        case 'Complaint': return 'badge bg-danger';
        case 'Bug Report': return 'badge bg-warning text-dark';
        case 'Suggestion':
        default: return 'badge bg-primary';
    }
}

function getStatusClass($status)
{
    switch ($status) {
        case 'In Progress': return 'progress';
        case 'Resolved': return 'completed';
        case 'New':
        default: return 'pending';
    }
}

// AJAX endpoints for update/delete – keep it simple for now: delete is real, status is virtual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'delete_feedback') {
            $id = isset($_POST['feedback_id']) ? (int) $_POST['feedback_id'] : 0;
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM access_feedback WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        if ($action === 'update_status') {
            // Schema has no status column; acknowledge but do nothing persistent
            echo json_encode(['success' => true, 'message' => 'Status change noted (not stored in DB).']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Load feedback list and stats
try {
    $sql = '
        SELECT f.id, f.member_id, f.rating, f.comment, f.created_at,
               m.id_number, m.full_name
        FROM access_feedback f
        LEFT JOIN access_members m ON m.id = f.member_id
        ORDER BY f.created_at DESC, f.id DESC
    ';
    $stmt = $pdo->query($sql);

    $type_counts = [];
    $user_counts = [];

    while ($row = $stmt->fetch()) {
        $total_feedback++;
        $type = access_feedback_type_from_rating((int) $row['rating']);
        $status = 'New'; // no status column – treat all as new/unresolved

        $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;

        $user_name = $row['full_name'] ?: ('ID ' . $row['id_number']);
        if ($user_name) {
            $user_counts[$user_name] = ($user_counts[$user_name] ?? 0) + 1;
        }

        $all_feedback[] = [
            'feedback_id'   => (int) $row['id'],
            'feedback_type' => $type,
            'feedback_text' => $row['comment'],
            'user_name'     => $user_name ?: 'Unknown User',
            'user_id'       => $row['id_number'] ?? '',
            'status'        => $status,
            'date_created'  => $row['created_at'],
        ];
    }

    // For now unresolved = total (no dedicated status field)
    $unresolved_count = $total_feedback;

    if (!empty($type_counts)) {
        arsort($type_counts);
        $most_common_type = array_key_first($type_counts);
    }

    if (!empty($user_counts)) {
        arsort($user_counts);
        $most_active_user = array_key_first($user_counts);
        $most_active_count = $user_counts[$most_active_user];
    }
} catch (PDOException $e) {
    // Fail silently in UI, but keep zero stats
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - ACCESS</title>
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
            border: 1px solid #e0f2fe;
            box-shadow: 0 2px 10px rgba(186, 230, 253, 0.2);
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff, #f8fbff);
        }
        
        .card-feature:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(96, 165, 250, 0.2);
        }

        .fun-header {
            background: linear-gradient(120deg, #f7b733, #fc4a1a);
            color: white;
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 15px 30px rgba(252,74,26,0.35);
            position: relative;
            overflow: hidden;
        }

        .fun-header::after {
            content: '✨';
            font-size: 60px;
            position: absolute;
            right: 30px;
            bottom: 20px;
            opacity: 0.2;
        }

        .emoji-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6c757d;
        }

        .emoji-label span {
            margin-left: 6px;
        }

        .fun-progress {
            height: 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.4);
            overflow: hidden;
        }

        .fun-progress-bar {
            height: 100%;
            width: 45%;
            border-radius: 999px;
            background: white;
            animation: bounce 2.5s infinite;
        }

        @keyframes bounce {
            0% { width: 20%; }
            50% { width: 75%; }
            100% { width: 20%; }
        }

        .feedback-card {
            border-radius: 12px;
            border: 1px solid #e1f0ff;
            background: #f8fbff;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feedback-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #3b82f6;
            opacity: 0.8;
        }

        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(59, 130, 246, 0.15);
            border-color: #bfdbfe;
        }

        .reaction-badge {
            border-radius: 20px;
            background: #ebf5ff;
            color: #1d4ed8;
            padding: 4px 12px;
            font-size: 0.8rem;
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #dbeafe;
            font-weight: 500;
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

        .status-pill {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .status-pill.pending {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-pill.progress {
            background: #3b82f6;
            color: white;
            border: 1px solid #2563eb;
        }

        .status-pill.completed {
            background: #60a5fa;
            color: white;
            border: 1px solid #3b82f6;
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
                    <a class="nav-link" href="access_service_requests.php">
                        <i class="bi bi-tools"></i>
                        <span>Service Requests & Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="access_feedback.php">
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

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card-feature text-center">
                        <p class="text-muted mb-1">Total Feedback</p>
                        <h3><?php echo $total_feedback; ?></h3>
                        <div class="reaction-badge justify-content-center">🔥 Trendy</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-feature text-center">
                        <p class="text-muted mb-1">Unresolved</p>
                        <h3><?php echo $unresolved_count; ?></h3>
                        <div class="reaction-badge justify-content-center">⚠️ Needs attention</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-feature text-center">
                        <p class="text-muted mb-1">Most Common Type</p>
                        <h3><?php echo $most_common_type; ?></h3>
                        <div class="reaction-badge justify-content-center"><?php echo getTypeEmoji($most_common_type); ?> Keep them coming</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-feature text-center">
                        <p class="text-muted mb-1">Most Active User</p>
                        <h3><?php echo htmlspecialchars($most_active_user); ?></h3>
                        <div class="reaction-badge justify-content-center">📝 <?php echo $most_active_count; ?> submissions</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="card-feature mb-4">
                <form class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" placeholder="Search by user or feedback text">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Type</label>
                        <select class="form-select">
                            <option>All types</option>
                            <option>Suggestion</option>
                            <option>Complaint</option>
                            <option>Praise</option>
                            <option>Bug Report</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Status</label>
                        <select class="form-select">
                            <option>Any status</option>
                            <option>New</option>
                            <option>In Progress</option>
                            <option>Resolved</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Sort</label>
                        <select class="form-select">
                            <option>Newest</option>
                            <option>Oldest</option>
                            <option>Most reacted</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Feedback Grid -->
            <div class="row g-3 mb-4">
                <?php if (empty($all_feedback)): ?>
                    <div class="col-12">
                        <div class="card-feature text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-3">No feedback submitted yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_feedback as $feedback): 
                        $feedback_type = $feedback['feedback_type'] ?? 'Suggestion';
                        $feedback_text = htmlspecialchars($feedback['feedback_text'] ?? '');
                        $user_name = htmlspecialchars($feedback['user_name'] ?? 'Unknown User');
                        $status = $feedback['status'] ?? 'New';
                        $date_created = !empty($feedback['date_created']) ? date('M d, Y', strtotime($feedback['date_created'])) : 'N/A';
                        $date_created_full = !empty($feedback['date_created']) ? date('F d, Y \a\t h:i A', strtotime($feedback['date_created'])) : 'N/A';
                        $feedback_id = $feedback['feedback_id'] ?? 0;
                        $user_id = $feedback['user_id'] ?? '';
                    ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="feedback-card h-100" 
                                 data-feedback-id="<?php echo $feedback_id; ?>"
                                 data-feedback-type="<?php echo htmlspecialchars($feedback_type); ?>"
                                 data-feedback-text="<?php echo htmlspecialchars($feedback_text); ?>"
                                 data-user-name="<?php echo htmlspecialchars($user_name); ?>"
                                 data-user-id="<?php echo htmlspecialchars($user_id); ?>"
                                 data-status="<?php echo htmlspecialchars($status); ?>"
                                 data-date-created="<?php echo htmlspecialchars($date_created_full); ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge <?php echo getTypeBadge($feedback_type); ?>">
                                            <?php echo htmlspecialchars($feedback_type); ?> <?php echo getTypeEmoji($feedback_type); ?>
                                        </span>
                                        <small class="text-muted d-block"><?php echo $date_created; ?></small>
                                    </div>
                                    <span class="status-pill <?php echo getStatusClass($status); ?>"><?php echo htmlspecialchars($status); ?></span>
                                </div>
                                <h6 class="mb-1">User: <?php echo $user_name; ?></h6>
                                <p class="small text-muted mb-2">"<?php echo strlen($feedback_text) > 80 ? substr($feedback_text, 0, 80) . '...' : $feedback_text; ?>"</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewFeedback(<?php echo $feedback_id; ?>)">View Details</button>
                                    <?php if ($status != 'In Progress'): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(<?php echo $feedback_id; ?>, 'In Progress')">Mark In Progress</button>
                                    <?php endif; ?>
                                    <?php if ($status != 'Resolved'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?php echo $feedback_id; ?>, 'Resolved')">Mark Resolved</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteFeedback(<?php echo $feedback_id; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Feedback Details Modal -->
    <div class="modal fade" id="feedbackDetailsModal" tabindex="-1" aria-labelledby="feedbackDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="feedbackDetailsModalLabel">
                            <span id="modalFeedbackType"></span>
                            <span id="modalFeedbackEmoji"></span>
                        </h5>
                        <p class="mb-0 text-muted small">
                            Submitted by <span id="modalUserName"></span> · 
                            <span id="modalDateCreated"></span> · 
                            Status: <span id="modalStatus"></span>
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <h6 class="mb-2">Feedback Details</h6>
                            <div class="card-feature p-3">
                                <p class="mb-0" id="modalFeedbackText" style="white-space: pre-wrap;"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">User Information</h6>
                            <div class="card-feature p-3">
                                <p class="mb-1"><strong>Name:</strong> <span id="modalUserFullName"></span></p>
                                <p class="mb-0"><strong>ID Number:</strong> <span id="modalUserId"></span></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Feedback Information</h6>
                            <div class="card-feature p-3">
                                <p class="mb-1"><strong>Type:</strong> <span id="modalTypeBadge"></span></p>
                                <p class="mb-1"><strong>Status:</strong> <span id="modalStatusBadge"></span></p>
                                <p class="mb-0"><strong>Feedback ID:</strong> <span id="modalFeedbackId"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <span id="modalActionButtons"></span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        // Helper function to get emoji based on feedback type
        function getTypeEmoji(type) {
            const emojis = {
                'Suggestion': '💡',
                'Complaint': '😤',
                'Praise': '🎉',
                'Bug Report': '🐞'
            };
            return emojis[type] || '📝';
        }

        // Helper function to get badge class based on feedback type
        function getTypeBadgeClass(type) {
            const badges = {
                'Suggestion': 'bg-primary',
                'Complaint': 'bg-danger',
                'Praise': 'bg-success',
                'Bug Report': 'bg-warning'
            };
            return badges[type] || 'bg-secondary';
        }

        // Helper function to get status class
        function getStatusClass(status) {
            const classes = {
                'New': 'pending',
                'In Progress': 'progress',
                'Resolved': 'completed'
            };
            return classes[status] || 'pending';
        }

        // Function to view feedback details
        function viewFeedback(feedbackId) {
            // Find the feedback card with this ID
            const feedbackCard = document.querySelector(`[data-feedback-id="${feedbackId}"]`);
            if (!feedbackCard) {
                alert('Feedback not found');
                return;
            }

            // Get all feedback data from data attributes
            const feedbackData = {
                id: feedbackCard.getAttribute('data-feedback-id'),
                type: feedbackCard.getAttribute('data-feedback-type'),
                text: feedbackCard.getAttribute('data-feedback-text'),
                userName: feedbackCard.getAttribute('data-user-name'),
                userId: feedbackCard.getAttribute('data-user-id'),
                status: feedbackCard.getAttribute('data-status'),
                dateCreated: feedbackCard.getAttribute('data-date-created')
            };

            // Populate modal with feedback data
            document.getElementById('modalFeedbackType').textContent = feedbackData.type;
            document.getElementById('modalFeedbackEmoji').textContent = getTypeEmoji(feedbackData.type);
            document.getElementById('modalUserName').textContent = feedbackData.userName;
            document.getElementById('modalDateCreated').textContent = feedbackData.dateCreated;
            document.getElementById('modalStatus').textContent = feedbackData.status;
            document.getElementById('modalFeedbackText').textContent = feedbackData.text;
            document.getElementById('modalUserFullName').textContent = feedbackData.userName;
            document.getElementById('modalUserId').textContent = feedbackData.userId || 'N/A';
            document.getElementById('modalFeedbackId').textContent = feedbackData.id;

            // Set type badge
            const typeBadge = document.getElementById('modalTypeBadge');
            typeBadge.innerHTML = `<span class="badge ${getTypeBadgeClass(feedbackData.type)}">${feedbackData.type} ${getTypeEmoji(feedbackData.type)}</span>`;

            // Set status badge
            const statusBadge = document.getElementById('modalStatusBadge');
            const statusClass = getStatusClass(feedbackData.status);
            statusBadge.innerHTML = `<span class="status-pill ${statusClass}">${feedbackData.status}</span>`;

            // Set action buttons
            const actionButtons = document.getElementById('modalActionButtons');
            let buttonsHTML = '';
            if (feedbackData.status !== 'In Progress') {
                buttonsHTML += `<button class="btn btn-warning" onclick="updateStatus(${feedbackData.id}, 'In Progress'); bootstrap.Modal.getInstance(document.getElementById('feedbackDetailsModal')).hide();">Mark In Progress</button> `;
            }
            if (feedbackData.status !== 'Resolved') {
                buttonsHTML += `<button class="btn btn-success" onclick="updateStatus(${feedbackData.id}, 'Resolved'); bootstrap.Modal.getInstance(document.getElementById('feedbackDetailsModal')).hide();">Mark Resolved</button> `;
            }
            buttonsHTML += `<button class="btn btn-danger" onclick="deleteFeedback(${feedbackData.id}); bootstrap.Modal.getInstance(document.getElementById('feedbackDetailsModal')).hide();">Delete</button>`;
            actionButtons.innerHTML = buttonsHTML;

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('feedbackDetailsModal'));
            modal.show();
        }

        // Function to update feedback status
        function updateStatus(feedbackId, newStatus) {
            if (!confirm(`Are you sure you want to mark this feedback as "${newStatus}"?`)) {
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('feedback_id', feedbackId);
            formData.append('status', newStatus);

            // Send AJAX request
            fetch('access_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status. Please try again.');
            });
        }

        // Function to delete feedback
        function deleteFeedback(feedbackId) {
            if (!confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('action', 'delete_feedback');
            formData.append('feedback_id', feedbackId);

            // Send AJAX request
            fetch('access_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback deleted successfully!');
                    location.reload();
                } else {
                    alert('Error deleting feedback: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting feedback. Please try again.');
            });
        }

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
        });
    </script>
</body>
</html>