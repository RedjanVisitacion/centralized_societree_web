
<?php
require_once __DIR__ . '/../../db_connection.php';

$message = '';
$message_type = '';
$announcements = [];

// Simple helper to map visibility to an icon/color for notifications
function access_visibility_to_icon($visibility)
{
    $visibility = strtolower((string) $visibility);
    switch ($visibility) {
        case 'officers':
            return ['shield-lock', '#0d6efd'];
        case 'public':
            return ['megaphone', '#198754'];
        default:
            return ['people', '#6c757d'];
    }
}

try {
    // Handle create
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $visibility = $_POST['visibility'] ?? 'all';

        if ($title === '' || $content === '') {
            $message = 'Title and content are required.';
            $message_type = 'danger';
        } else {
            [$icon_name, $icon_color] = access_visibility_to_icon($visibility);

            $stmt = $pdo->prepare(
                'INSERT INTO access_notifications (member_id, title, message, icon_name, icon_color_hex, is_read)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            // For now, store as a broadcast/admin notification with member_id = 1
            $stmt->execute([1, $title, $content, $icon_name, $icon_color]);

            $message = 'Announcement created successfully.';
            $message_type = 'success';
        }
    }

    // Handle update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
        $id = isset($_POST['announcement_id']) ? (int) $_POST['announcement_id'] : 0;
        $title = trim($_POST['edit_title'] ?? '');
        $content = trim($_POST['edit_content'] ?? '');

        if ($id <= 0 || $title === '' || $content === '') {
            $message = 'Invalid announcement data.';
            $message_type = 'danger';
        } else {
            $stmt = $pdo->prepare('UPDATE access_notifications SET title = ?, message = ? WHERE id = ?');
            $stmt->execute([$title, $content, $id]);
            $message = 'Announcement updated successfully.';
            $message_type = 'success';
        }
    }

    // Handle "delete" (mark as inactive by setting is_read = 1)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
        $id = isset($_POST['announcement_id']) ? (int) $_POST['announcement_id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE access_notifications SET is_read = 1 WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Announcement marked as inactive.';
            $message_type = 'success';
        } else {
            $message = 'Invalid announcement selected.';
            $message_type = 'danger';
        }
    }

    // Load announcements from notifications table
    $sql = '
        SELECT id, title, message, icon_name, icon_color_hex, is_read, created_at
        FROM access_notifications
        ORDER BY created_at DESC, id DESC
    ';
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $status = $row['is_read'] ? 'Inactive' : 'Active';
        $announcements[] = [
            'announcement_id' => (int) $row['id'],
            'announcement_title' => $row['title'],
            'announcement_content' => $row['message'],
            'announcement_datetime' => $row['created_at'],
            'author_name' => 'ACCESS Admin',
            'visibility' => 'All Members',
            'status' => $status,
        ];
    }
} catch (PDOException $e) {
    $message = 'Database error: ' . $e->getMessage();
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ACCESS</title>
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
                    <a class="nav-link active" href="access_announcement.php">
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
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h2 class="mb-1">Announcements</h2>
                    <p class="text-muted mb-0">Create, publish, and manage announcements for ACCESS members across the system.</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-lg me-1"></i>
                    Create Announcement
                </button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info'); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Existing Announcements List -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Existing Announcements</h5>
                    <small class="text-muted">Overview of all posts</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date Posted</th>
                                    <th>Posted By</th>
                                    <th>Visibility</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($announcements)): ?>
                                    <?php foreach ($announcements as $ann): ?>
                                        <?php
                                            $title = htmlspecialchars($ann['announcement_title'] ?? 'No Title', ENT_QUOTES);
                                            $author = htmlspecialchars($ann['author_name'] ?? 'Unknown', ENT_QUOTES);
                                            $rawDatetime = $ann['announcement_datetime'] ?? '';
                                            $tableDate = !empty($rawDatetime) ? date('M d, Y', strtotime($rawDatetime)) : '—';
                                            $displayDatetime = !empty($rawDatetime) ? date('M d, Y · g:i A', strtotime($rawDatetime)) : '';
                                            $rawContent = $ann['announcement_content'] ?? '';
                                            $contentAttr = htmlspecialchars($rawContent, ENT_QUOTES);
                                            $visibilityValue = $ann['visibility'] ?? 'All Members';
                                            $visibility = htmlspecialchars($visibilityValue, ENT_QUOTES);
                                            $status = $ann['status'] ?? 'Unknown';
                                            $badgeClass = $status === 'Active' ? 'bg-success' : 'bg-secondary';
                                        ?>
                                        <tr>
                                            <td><?php echo $title; ?></td>
                                            <td><?php echo htmlspecialchars($tableDate, ENT_QUOTES); ?></td>
                                            <td><?php echo $author; ?></td>
                                            <td><?php echo $visibility; ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                            <td class="text-end">
                                                <button 
                                                    type="button" 
                                                    class="btn btn-sm btn-outline-primary me-1 view-announcement" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#previewAnnouncementModal" 
                                                    title="View"
                                                    data-id="<?php echo (int)$ann['announcement_id']; ?>"
                                                    data-title="<?php echo $title; ?>"
                                                    data-content="<?php echo $contentAttr; ?>"
                                                    data-author="<?php echo $author; ?>"
                                                    data-datetime="<?php echo htmlspecialchars($displayDatetime, ENT_QUOTES); ?>"
                                                    data-visibility="<?php echo $visibility; ?>"
                                                    data-status="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary edit-announcement" 
                                                        title="Edit"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editAnnouncementModal"
                                                        data-id="<?php echo $ann['announcement_id']; ?>"
                                                        data-title="<?php echo $title; ?>"
                                                        data-content="<?php echo $contentAttr; ?>">
                                                    Edit
                                                </button>
                                                <form method="POST" onsubmit="return confirmDelete();" style="display:inline-block;">
                                                    <input type="hidden" name="announcement_id" value="<?php echo (int)$ann['announcement_id']; ?>">
                                                    <button type="submit" name="delete_announcement" value="1" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No announcements found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            <!-- Edit Announcement Modal -->
            <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" id="editAnnouncementForm">
                            <div class="modal-body">
                                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                                <div class="mb-3">
                                    <label for="edit_title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_title" name="edit_title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_content" class="form-label">Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="edit_content" name="edit_content" rows="8" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_announcement" class="btn btn-primary">Update Announcement</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Create Announcement Modal -->
            <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createAnnouncementModalLabel">Create Announcement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" placeholder="Enter announcement title" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Visibility</label>
                                        <select name="visibility" class="form-select">
                                            <option value="all" selected>All Members</option>
                                            <option value="officers">Officers Only</option>
                                            <option value="public">Public</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Body / Description</label>
                                        <textarea name="content" class="form-control" rows="4" placeholder="Describe the announcement details, instructions, or reminders..." required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Image Upload (optional)</label>
                                        <input type="file" name="image" class="form-control">
                                        <div class="form-text">Supported formats: JPG, PNG. Max 5MB.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Schedule Date</label>
                                        <input type="date" name="schedule_date" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Schedule Time</label>
                                        <input type="time" name="schedule_time" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="publishNowCheck" name="publish_now" value="1" checked>
                                            <label class="form-check-label" for="publishNowCheck">
                                                Publish immediately
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                                    <button type="submit" name="create_announcement" value="1" class="btn btn-primary">Save Announcement</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Modal -->
            <div class="modal fade" id="previewAnnouncementModal" tabindex="-1" aria-labelledby="previewAnnouncementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="previewAnnouncementModalLabel">Announcement Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-2">
                                <span class="badge me-2" id="previewStatusBadge">Status</span>
                                <span class="badge bg-primary" id="previewVisibilityBadge">Visibility</span>
                            </div>
                            <h4 class="mb-1" id="previewAnnouncementTitle">Announcement Title</h4>
                            <p class="text-muted mb-3" id="previewAnnouncementMeta">By Author · Date</p>
                            <div id="previewAnnouncementContent" class="mb-0"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="previewEditButton">Edit Announcement</button>
                        </div>
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
            const editAnnouncementModal = document.getElementById('editAnnouncementModal');
            const editAnnouncementIdInput = document.getElementById('edit_announcement_id');
            const editTitleInput = document.getElementById('edit_title');
            const editContentInput = document.getElementById('edit_content');
            const previewModal = document.getElementById('previewAnnouncementModal');
            const previewTitleEl = document.getElementById('previewAnnouncementTitle');
            const previewMetaEl = document.getElementById('previewAnnouncementMeta');
            const previewContentEl = document.getElementById('previewAnnouncementContent');
            const previewStatusBadge = document.getElementById('previewStatusBadge');
            const previewVisibilityBadge = document.getElementById('previewVisibilityBadge');
            const previewEditButton = document.getElementById('previewEditButton');
            let previewContext = null;

            const escapeHtml = (unsafeText = '') => {
                const div = document.createElement('div');
                div.textContent = unsafeText;
                return div.innerHTML;
            };

            const populateEditForm = (data = {}) => {
                if (!editAnnouncementIdInput || !editTitleInput || !editContentInput) {
                    return;
                }
                editAnnouncementIdInput.value = data.id || '';
                editTitleInput.value = data.title || '';
                editContentInput.value = data.content || '';
            };
            
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

            // Populate edit modal when clicking direct edit buttons
            document.querySelectorAll('.edit-announcement').forEach(button => {
                button.addEventListener('click', function() {
                    populateEditForm({
                        id: this.dataset.id,
                        title: this.dataset.title,
                        content: this.dataset.content
                    });
                });
            });

            // Populate preview modal
            document.querySelectorAll('.view-announcement').forEach(button => {
                button.addEventListener('click', function() {
                    const status = this.dataset.status || 'Active';
                    previewContext = {
                        id: this.dataset.id,
                        title: this.dataset.title || 'No Title',
                        content: this.dataset.content || '',
                        author: this.dataset.author || 'Unknown',
                        datetime: this.dataset.datetime || '',
                        visibility: this.dataset.visibility || 'All Members',
                        status
                    };

                    previewTitleEl.textContent = previewContext.title;
                    previewMetaEl.textContent = previewContext.datetime
                        ? `${previewContext.author} · ${previewContext.datetime}`
                        : previewContext.author;

                    const statusClass = previewContext.status === 'Active' ? 'bg-success' : 'bg-secondary';
                    previewStatusBadge.className = `badge me-2 ${statusClass}`;
                    previewStatusBadge.textContent = previewContext.status;

                    previewVisibilityBadge.textContent = previewContext.visibility;
                    previewVisibilityBadge.className = 'badge bg-primary';

                    previewContentEl.innerHTML = escapeHtml(previewContext.content).replace(/\n/g, '<br>');
                });
            });

            if (previewEditButton) {
                previewEditButton.addEventListener('click', function() {
                    if (!previewContext) {
                        return;
                    }

                    populateEditForm(previewContext);

                    const previewModalInstance = bootstrap.Modal.getInstance(previewModal);
                    if (previewModalInstance) {
                        previewModalInstance.hide();
                    }

                    const editModalInstance = new bootstrap.Modal(editAnnouncementModal);
                    editModalInstance.show();
                });
            }
        });

        function confirmDelete() {
            return confirm('Are you sure you want to delete this announcement? This will mark it inactive.');
        }
    </script>
</body>
</html>