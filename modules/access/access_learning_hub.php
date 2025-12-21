
<?php
require_once __DIR__ . '/../../db_connection.php';

$message = '';
$message_type = 'info';
$total_resources = 0;
$resources_by_type = [
    'tutorial' => ['name' => 'Tutorials', 'icon' => 'bi-journal-code', 'resources' => []],
    'video'    => ['name' => 'Videos',    'icon' => 'bi-play-circle',  'resources' => []],
    'document' => ['name' => 'Documents', 'icon' => 'bi-file-earmark-text', 'resources' => []],
    'quiz'     => ['name' => 'Quizzes',   'icon' => 'bi-question-circle',   'resources' => []],
];

// Decide a simple resource_type based on tags text
function access_guess_resource_type(array $row): string
{
    $tags = strtolower((string)($row['tags'] ?? ''));
    if (strpos($tags, 'video') !== false) {
        return 'video';
    }
    if (strpos($tags, 'quiz') !== false) {
        return 'quiz';
    }
    if (strpos($tags, 'doc') !== false || strpos($tags, 'pdf') !== false) {
        return 'document';
    }
    return 'tutorial';
}

/**
 * Resolve stored content_url (DB value) to a browser-usable URL.
 * - If it's already an absolute URL (http/https), return as-is.
 * - Otherwise treat it as a path under the project root and prefix ../../ for this module.
 */
function access_learning_public_url($path): string
{
    if ($path === null) {
        return '#';
    }

    $path = (string) $path;
    if ($path === '') {
        return '#';
    }

    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
        return $path;
    }

    // Stored as e.g. "uploads/access_learning/filename.ext"
    return '../../' . ltrim($path, '/');
}

try {
    // Handle add / update / delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
        $action = $_POST['action'];

        // Ensure upload dir exists (for any file-based resource)
        $upload_dir = __DIR__ . '/../../uploads/access_learning/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        if ($action === 'add_resource') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $resource_type = $_POST['resource_type'] ?? 'tutorial';
            $content_url = trim($_POST['content_url'] ?? '');
            $uploaded_path = null;

            if ($title === '' || $description === '') {
                $message = 'Title and description are required.';
                $message_type = 'danger';
            } else {
                // If a file is uploaded, prefer that over URL
                if (!empty($_FILES['resource_file']['name'])) {
                    $file = $_FILES['resource_file'];
                    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $max_size = 20 * 1024 * 1024; // 20MB
                        if ($file['size'] <= $max_size) {
                            $safe_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                            $final_name = uniqid('res_', true) . '_' . $safe_name . '.' . $ext;
                            $dest_path = $upload_dir . $final_name;
                            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                                $uploaded_path = 'uploads/access_learning/' . $final_name;
                                $content_url = $uploaded_path;
                            }
                        }
                    }
                }

                $tags = ucfirst($resource_type);
                $stmt = $pdo->prepare('
                    INSERT INTO access_learning_materials (title, description, tags, content_url)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([$title, $description, $tags, $content_url !== '' ? $content_url : null]);
                $message = 'Learning resource added successfully.';
                $message_type = 'success';
            }
        } elseif ($action === 'update_resource') {
            $id = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $resource_type = $_POST['resource_type'] ?? 'tutorial';
            $content_url = trim($_POST['content_url'] ?? '');
            $uploaded_path = null;

            if ($id <= 0 || $title === '' || $description === '') {
                $message = 'Invalid resource data.';
                $message_type = 'danger';
            } else {
                // Optional new file on update; if provided, override URL
                if (!empty($_FILES['resource_file']['name'])) {
                    $file = $_FILES['resource_file'];
                    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $max_size = 20 * 1024 * 1024; // 20MB
                        if ($file['size'] <= $max_size) {
                            $safe_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                            $final_name = uniqid('res_', true) . '_' . $safe_name . '.' . $ext;
                            $dest_path = $upload_dir . $final_name;
                            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                                $uploaded_path = 'uploads/access_learning/' . $final_name;
                                $content_url = $uploaded_path;
                            }
                        }
                    }
                }

                $tags = ucfirst($resource_type);
                $stmt = $pdo->prepare('
                    UPDATE access_learning_materials
                    SET title = ?, description = ?, tags = ?, content_url = ?
                    WHERE id = ?
                ');
                $stmt->execute([$title, $description, $tags, $content_url !== '' ? $content_url : null, $id]);
                $message = 'Learning resource updated successfully.';
                $message_type = 'success';
            }
        } elseif ($action === 'delete' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM access_learning_materials WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Learning resource deleted successfully.';
                $message_type = 'success';
            }
        }
    }

    // Load resources
    $stmt = $pdo->query('
        SELECT id, title, description, tags, image_url, content_url, is_active, created_at
        FROM access_learning_materials
        WHERE is_active = 1
        ORDER BY created_at DESC, id DESC
    ');

    while ($row = $stmt->fetch()) {
        $total_resources++;
        $type = access_guess_resource_type($row);
        if (!isset($resources_by_type[$type])) {
            $resources_by_type[$type] = ['name' => ucfirst($type), 'icon' => 'bi-collection', 'resources' => []];
        }

        $resources_by_type[$type]['resources'][] = [
            'resource_id'   => (int) $row['id'],
            'title'         => $row['title'],
            'description'   => $row['description'] ?? '',
            'resource_type' => $type,
            'content_url'   => $row['content_url'] ?? '#',
            'creator_name'  => 'ACCESS Admin',
            'created_date'  => $row['created_at'],
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
    <title>Learning Hub - ACCESS</title>
    <link rel="icon" href="../../assets/logo/access_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

        .badge-type {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
        }

        .badge-type.video {
            background: rgba(13,110,253,0.1);
            color: #0d6efd;
        }

        .badge-type.document {
            background: rgba(25,135,84,0.1);
            color: #198754;
        }

        .badge-type.quiz {
            background: rgba(255,193,7,0.1);
            color: #c59000;
        }

        .badge-type.tutorial {
            background: rgba(111,66,193,0.1);
            color: #6f42c1;
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
                    <a class="nav-link" href="access_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="access_learning_hub.php">
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="mb-1">Learning Hub</h2>
                    <p class="text-muted mb-0">Access tutorials, documents, quizzes, and training materials for your development.</p>
                </div>
                <button type="button"
                        class="btn btn-primary"
                        id="openResourceModalBtn"
                        data-bs-toggle="modal"
                        data-bs-target="#newResourceModal">
                    <i class="bi bi-plus-lg me-2"></i>Add New Resource
                </button>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-collection text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Total Resources</h6>
                                    <h3 class="mb-0"><?php echo $total_resources; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php foreach ($resources_by_type as $type => $data): ?>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi <?php echo $data['icon']; ?> text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $data['name']; ?></h6>
                                    <h3 class="mb-0"><?php echo count($data['resources']); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters and Search -->
            <div class="card-feature mb-4">
                <form class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" placeholder="Search by title or description">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Type</label>
                        <select class="form-select">
                            <option>All types</option>
                            <option>Video</option>
                            <option>Document</option>
                            <option>Quiz</option>
                            <option>Tutorial</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Category</label>
                        <select class="form-select">
                            <option>All categories</option>
                            <option>Documentation</option>
                            <option>IT Skills</option>
                            <option>Orientation</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Uploader</label>
                        <select class="form-select">
                            <option>Any</option>
                            <option>ACCESS Admin</option>
                            <option>Training Team</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Sort By</label>
                        <select class="form-select">
                            <option>Newest</option>
                            <option>Oldest</option>
                            <option>Most Accessed</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Resource Categories -->
            <?php foreach ($resources_by_type as $type => $data): 
                if (!empty($data['resources'])): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi <?php echo $data['icon']; ?> me-2 text-primary"></i>
                            <?php echo $data['name']; ?>
                            <span class="badge bg-primary ms-2"><?php echo count($data['resources']); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Added By</th>
                                        <th>Date Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['resources'] as $resource): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo htmlspecialchars(access_learning_public_url($resource['content_url'])); ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($resource['title']); ?>
                                            </a>
                                        </td>
                                        <td class="text-muted"><?php echo substr(htmlspecialchars($resource['description']), 0, 80); ?>...</td>
                                        <td><?php echo htmlspecialchars($resource['creator_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($resource['created_date'])); ?></td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary me-1"
                                                    title="View"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewResourceModal"
                                                    data-title="<?php echo htmlspecialchars($resource['title']); ?>"
                                                    data-description="<?php echo htmlspecialchars($resource['description']); ?>"
                                                    data-type="<?php echo htmlspecialchars($resource['resource_type']); ?>"
                                                    data-url="<?php echo htmlspecialchars(access_learning_public_url($resource['content_url'])); ?>"
                                                    data-creator="<?php echo htmlspecialchars($resource['creator_name'] ?? 'N/A'); ?>"
                                                    data-date="<?php echo date('M d, Y', strtotime($resource['created_date'])); ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary me-1"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editResourceModal" 
                                               data-id="<?php echo $resource['resource_id']; ?>"
                                               data-title="<?php echo htmlspecialchars($resource['title']); ?>"
                                               data-description="<?php echo htmlspecialchars($resource['description']); ?>"
                                               data-type="<?php echo htmlspecialchars($resource['resource_type']); ?>"
                                               data-url="<?php echo htmlspecialchars($resource['content_url']); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?action=delete&id=<?php echo $resource['resource_id']; ?>" class="btn btn-sm btn-outline-danger delete-resource" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; 
            endforeach; ?>
        </div>
    </div>

    <!-- View Resource Modal -->
    <div class="modal fade" id="viewResourceModal" tabindex="-1" aria-labelledby="viewResourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewResourceModalLabel">Resource Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4 id="view_resource_title" class="mb-3"></h4>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <small class="text-muted d-block">Type</small>
                                <span id="view_resource_type" class="fw-semibold text-capitalize"></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <small class="text-muted d-block">Added By</small>
                                <span id="view_resource_creator" class="fw-semibold"></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <small class="text-muted d-block">Date Added</small>
                                <span id="view_resource_date" class="fw-semibold"></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <small class="text-muted d-block mb-2">Description</small>
                        <p id="view_resource_description" class="mb-0"></p>
                    </div>
                    <div id="view_resource_link_wrapper" class="d-none">
                        <a id="view_resource_link" href="#" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Resource
                        </a>
                    </div>
                    <div id="view_resource_no_link" class="text-muted d-none">
                        No resource link provided.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Resource Modal -->
    <div class="modal fade" id="newResourceModal" tabindex="-1" aria-labelledby="newResourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_resource">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newResourceModalLabel">Add New Learning Resource</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="resource_type" class="form-label">Resource Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="resource_type" name="resource_type" required>
                                    <option value="tutorial">Tutorial</option>
                                    <option value="video">Video</option>
                                    <option value="document">Document</option>
                                    <option value="quiz">Quiz</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="content_url" class="form-label">Resource URL (optional)</label>
                                <input type="url" class="form-control" id="content_url" name="content_url" placeholder="https://example.com/resource or video link">
                                <div class="form-text">Use this for YouTube links, external docs, etc.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="resource_file" class="form-label">Upload File (optional)</label>
                                <input type="file" class="form-control" id="resource_file" name="resource_file">
                                <div class="form-text">Upload videos, documents, or quiz files (max 20MB).</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Resource</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Resource Modal -->
    <div class="modal fade" id="editResourceModal" tabindex="-1" aria-labelledby="editResourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_resource">
                <input type="hidden" name="resource_id" id="edit_resource_id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editResourceModalLabel">Edit Learning Resource</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_resource_type" class="form-label">Resource Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_resource_type" name="resource_type" required>
                                    <option value="tutorial">Tutorial</option>
                                    <option value="video">Video</option>
                                    <option value="document">Document</option>
                                    <option value="quiz">Quiz</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_content_url" class="form-label">Resource URL (optional)</label>
                                <input type="url" class="form-control" id="edit_content_url" name="content_url" placeholder="https://example.com/resource">
                                <div class="form-text">Leave as is to keep the current link.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_resource_file" class="form-label">Replace File (optional)</label>
                                <input type="file" class="form-control" id="edit_resource_file" name="resource_file">
                                <div class="form-text">Upload a new file to replace the existing one.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger me-auto" id="deleteResourceBtn">
                            <i class="bi bi-trash me-1"></i> Delete
                        </button>
                        <button type="submit" class="btn btn-primary">Update Resource</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    
    <!-- Custom JavaScript -->
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const menuToggle = document.getElementById('menuToggle');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Close sidebar methods:
            
            // 0. Menu toggle click
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
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
            
            // Edit Modal - populate fields
            const editResourceModal = document.getElementById('editResourceModal');
            editResourceModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('edit_resource_id').value = button.dataset.id;
                document.getElementById('edit_title').value = button.dataset.title;
                document.getElementById('edit_description').value = button.dataset.description;
                document.getElementById('edit_resource_type').value = button.dataset.type;
                document.getElementById('edit_content_url').value = button.dataset.url;
            });
            
            // Delete resource from edit modal
            const deleteResourceBtn = document.getElementById('deleteResourceBtn');
            deleteResourceBtn.addEventListener('click', function() {
                const resourceId = document.getElementById('edit_resource_id').value;
                Swal.fire({
                    title: 'Delete Resource?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '?action=delete&id=' + resourceId;
                    }
                });
            });
            
            // Delete resource confirmation from table
            document.querySelectorAll('.delete-resource').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Delete Resource?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });

            // View resource modal
            const viewResourceModal = document.getElementById('viewResourceModal');
            if (viewResourceModal) {
                viewResourceModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    document.getElementById('view_resource_title').textContent = button.dataset.title || 'Untitled';
                    document.getElementById('view_resource_type').textContent = button.dataset.type || 'N/A';
                    document.getElementById('view_resource_creator').textContent = button.dataset.creator || 'N/A';
                    document.getElementById('view_resource_date').textContent = button.dataset.date || 'N/A';
                    document.getElementById('view_resource_description').textContent = button.dataset.description || 'No description provided.';

                    const resourceUrl = button.dataset.url || '';
                    const linkWrapper = document.getElementById('view_resource_link_wrapper');
                    const noLinkNotice = document.getElementById('view_resource_no_link');
                    const linkAnchor = document.getElementById('view_resource_link');

                    if (resourceUrl && resourceUrl.trim() !== '') {
                        linkAnchor.href = resourceUrl;
                        linkWrapper.classList.remove('d-none');
                        noLinkNotice.classList.add('d-none');
                    } else {
                        linkWrapper.classList.add('d-none');
                        noLinkNotice.classList.remove('d-none');
                    }
                });
            }

            // Ensure the "Add New Resource" modal opens even if data attributes fail
            const addResourceBtn = document.getElementById('openResourceModalBtn');
            const newResourceModalEl = document.getElementById('newResourceModal');
            if (addResourceBtn && newResourceModalEl) {
                const newResourceModal = new bootstrap.Modal(newResourceModalEl);
                addResourceBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    newResourceModal.show();
                });
            }
        });
    </script>
</body>
</html>