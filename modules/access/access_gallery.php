
<?php
// Backend for ACCESS Gallery using database schema (access_gallery_categories, access_gallery_photos)
require_once __DIR__ . '/../../db_connection.php';

// Initialize default variables used in the view
$message = '';
$message_type = '';
$db_error = '';
$albums_data = [];
$selected_album = null;
$selected_album_images = [];

/**
 * Helper to resolve the final image path/URL.
 * Accepts either a DB row array or a raw string.
 */
function get_gallery_image_path($img, $default = '')
{
    if (is_array($img)) {
        $url = $img['image_url'] ?? '';
    } else {
        $url = (string) $img;
    }

    if ($url === '' || $url === null) {
        return $default;
    }

    // If already absolute URL or absolute path, return as is
    if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0 || strpos($url, '/') === 0) {
        return $url;
    }

    // Otherwise, treat it as a file stored in the uploads directory
    return '../../uploads/access_gallery/' . ltrim($url, '/');
}

try {
    // Handle image upload (create album implicitly if needed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
        $album_name = isset($_POST['album']) ? trim($_POST['album']) : '';

        if ($album_name === '') {
            $message = 'Album name is required.';
            $message_type = 'danger';
        } elseif (empty($_FILES['images']['name'][0])) {
            $message = 'Please select at least one image to upload.';
            $message_type = 'danger';
        } else {
            // Ensure upload directory exists
            $upload_dir = __DIR__ . '/../../uploads/access_gallery/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            // Find or create album category
            $stmt = $pdo->prepare('SELECT id, name, created_at FROM access_gallery_categories WHERE name = ? LIMIT 1');
            $stmt->execute([$album_name]);
            $category = $stmt->fetch();

            if ($category) {
                $category_id = (int) $category['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO access_gallery_categories (name) VALUES (?)');
                $stmt->execute([$album_name]);
                $category_id = (int) $pdo->lastInsertId();
            }

            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $success_count = 0;
            $error_count = 0;

            foreach ($_FILES['images']['name'] as $index => $original_name) {
                $tmp_name = $_FILES['images']['tmp_name'][$index] ?? null;
                $size = $_FILES['images']['size'][$index] ?? 0;
                $error = $_FILES['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK || !$tmp_name || !is_uploaded_file($tmp_name)) {
                    $error_count++;
                    continue;
                }

                if ($size > $max_size) {
                    $error_count++;
                    continue;
                }

                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true)) {
                    $error_count++;
                    continue;
                }

                $safe_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
                $final_name = uniqid('img_', true) . '_' . $safe_name . '.' . $ext;
                $dest_path = $upload_dir . $final_name;

                if (!move_uploaded_file($tmp_name, $dest_path)) {
                    $error_count++;
                    continue;
                }

                $relative_url = $final_name; // stored as filename; resolved via helper
                $title = $safe_name !== '' ? $safe_name : null;

                $stmt = $pdo->prepare('INSERT INTO access_gallery_photos (category_id, title, image_url) VALUES (?, ?, ?)');
                $stmt->execute([$category_id, $title, $relative_url]);

                $success_count++;
            }

            if ($success_count > 0) {
                $message = "Successfully uploaded {$success_count} image(s)" . ($error_count > 0 ? " ({$error_count} failed validation)." : '.');
                $message_type = 'success';
                // After upload, default view to this album
                $selected_album = $album_name;
            } else {
                $message = 'No images were uploaded. Please check the file types and sizes.';
                $message_type = 'danger';
            }
        }
    }

    // Fetch all albums (categories) and their images
    $sql = "
        SELECT 
            c.id AS category_id,
            c.name AS album_name,
            c.created_at AS album_created_at,
            p.id AS photo_id,
            p.title,
            p.image_url,
            p.created_at AS photo_created_at
        FROM access_gallery_categories c
        LEFT JOIN access_gallery_photos p ON p.category_id = c.id
        ORDER BY c.created_at DESC, p.created_at DESC, p.id DESC
    ";

    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch()) {
        $album_name = $row['album_name'];

        if (!isset($albums_data[$album_name])) {
            $albums_data[$album_name] = [
                'created_at' => $row['album_created_at'],
                'cover_image' => null,
                'images' => [],
            ];
        }

        if (!empty($row['photo_id'])) {
            $image = [
                'id' => $row['photo_id'],
                'title' => $row['title'],
                'image_url' => $row['image_url'],
                'upload_date' => $row['photo_created_at'],
                'uploader_name' => 'Unknown', // Not stored in schema, placeholder
            ];

            // First image becomes cover if not set yet
            if ($albums_data[$album_name]['cover_image'] === null) {
                $albums_data[$album_name]['cover_image'] = get_gallery_image_path($image, '../../assets/img/sample_img.jpg');
            }

            $albums_data[$album_name]['images'][] = $image;
        }
    }

    // Determine which album is selected from query string if not already set by upload logic
    if ($selected_album === null && isset($_GET['album']) && $_GET['album'] !== '') {
        $selected_album = $_GET['album'];
    }

    if ($selected_album !== null && isset($albums_data[$selected_album])) {
        $selected_album_images = $albums_data[$selected_album]['images'];
    } else {
        $selected_album = null;
        $selected_album_images = [];
    }
} catch (PDOException $e) {
    $db_error = 'Database error: ' . $e->getMessage();
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
            width: 100%;
            max-width: 300px;
        }
        
        @media (max-width: 768px) {
            .search-box {
                max-width: 100%;
                margin: 10px 0;
            }
            .top-navbar {
                flex-wrap: wrap;
                gap: 10px;
            }
            .menu-toggle {
                order: 1;
            }
            .search-box {
                order: 3;
                width: 100%;
            }
            .user-info {
                order: 2;
                margin-left: auto;
            }
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
                    <a class="nav-link active" href="access_gallery.php">
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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="mb-1">Gallery Albums</h2>
                    <p class="text-muted mb-0">Organize documentation into albums for events, trainings, and campaigns.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newAlbumModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        New Album
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadImagesModal">
                        <i class="bi bi-cloud-upload me-1"></i>
                        Upload to Album
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Album</label>
                            <input type="text" class="form-control" placeholder="Search by album name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select">
                                <option selected>All Categories</option>
                                <option>Events</option>
                                <option>Training</option>
                                <option>Campaigns</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Created Date</label>
                            <input type="date" class="form-control">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Flash / DB messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info'); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($db_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Album Cards -->
            <div class="row g-3 mb-4">
                <?php
                $has_non_empty_album = false;
                if (!empty($albums_data)):
                    foreach ($albums_data as $albumName => $data):
                        $image_count = count($data['images']);
                        if ($image_count === 0) {
                            continue; // skip empty albums entirely
                        }
                        $has_non_empty_album = true;
                        $created_at = $data['created_at'] ?? null;
                        $created_text = $created_at ? date('M d, Y', strtotime($created_at)) : 'N/A';
                        $cover_src = htmlspecialchars($data['cover_image'] ?? '../../assets/img/sample_img.jpg', ENT_QUOTES);
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <img src="<?php echo $cover_src; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($albumName); ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($albumName); ?></h5>
                                        <span class="badge bg-primary"><?php echo $image_count; ?> images</span>
                                    </div>
                                    <p class="card-text text-muted mb-1">Created: <?php echo htmlspecialchars($created_text); ?></p>
                                    <p class="card-text text-muted mb-2"><?php echo $image_count; ?> images · Album</p>
                                    <a href="?album=<?php echo urlencode($albumName); ?>" class="btn btn-sm btn-outline-primary w-100">
                                        Open Album
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                endif;

                if (!$has_non_empty_album): ?>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            No images found in the gallery yet. Start by uploading images to create your first album.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Selected Album Content -->
            <?php if ($selected_album !== null): ?>
                <?php 
                    $selected_count = count($selected_album_images);
                    $selected_created = $albums_data[$selected_album]['created_at'] ?? null;
                    $selected_created_text = $selected_created ? date('M d, Y', strtotime($selected_created)) : 'N/A';
                ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Album: <?php echo htmlspecialchars($selected_album); ?></h5>
                            <small class="text-muted">
                                <?php echo $selected_count; ?> image(s) · Created <?php echo htmlspecialchars($selected_created_text); ?>
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadImagesModal">
                                <i class="bi bi-cloud-upload me-1"></i> Upload Images
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($selected_album_images)): ?>
                            <div class="row g-3">
                                <?php foreach ($selected_album_images as $img): 
                                    $img_src = htmlspecialchars(get_gallery_image_path($img, '../../assets/img/sample_img.jpg'), ENT_QUOTES);
                                    $title = $img['title'] ?? pathinfo(get_gallery_image_path($img, ''), PATHINFO_FILENAME);
                                    if ($title === '' || $title === null) {
                                        $title = 'Untitled';
                                    }
                                    $uploader = $img['uploader_name'] ?? 'Unknown';
                                    $uploaded_at = $img['upload_date'] ?? null;
                                    $uploaded_text = $uploaded_at ? date('M d, Y', strtotime($uploaded_at)) : 'N/A';
                                    ?>
                                    <div class="col-sm-6 col-md-4 col-lg-3">
                                        <div class="card h-100">
                                            <img src="<?php echo $img_src; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($title); ?>">
                                            <div class="card-body p-2">
                                                <h6 class="mb-1 small"><?php echo htmlspecialchars($title); ?></h6>
                                                <p class="mb-1 small text-muted">
                                                    By <?php echo htmlspecialchars($uploader); ?> · <?php echo htmlspecialchars($uploaded_text); ?>
                                                </p>
                                                <div class="d-flex gap-1">
                                                    <button 
                                                        class="btn btn-sm btn-outline-primary w-100 view-image-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#previewModal"
                                                        data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
                                                        data-src="<?php echo $img_src; ?>"
                                                        data-uploader="<?php echo htmlspecialchars($uploader, ENT_QUOTES); ?>"
                                                        data-date="<?php echo htmlspecialchars($uploaded_text, ENT_QUOTES); ?>"
                                                        data-album="<?php echo htmlspecialchars($selected_album, ENT_QUOTES); ?>"
                                                    >
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                This album does not have any images yet. Use the "Upload Images" button to add documentation.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Album Modal -->
    <div class="modal fade" id="newAlbumModal" tabindex="-1" aria-labelledby="newAlbumModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newAlbumModalLabel">Create New Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- This form is mainly for planning / label only.
                         An album is effectively created once images are uploaded with that name. -->
                    <form class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Album Title</label>
                            <input type="text" class="form-control" placeholder="e.g., ACCESS General Assembly 2025" id="newAlbumTitle">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" placeholder="Describe this album (event, purpose, etc.)"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cover Image</label>
                            <input type="file" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="useAlbumNameForUpload">Use Album Name</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Images Modal -->
    <div class="modal fade" id="uploadImagesModal" tabindex="-1" aria-labelledby="uploadImagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadImagesModalLabel">Upload Images to Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="row g-3" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_images" value="1">
                        <div class="col-12">
                            <label class="form-label">Album</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="album" 
                                id="uploadAlbumInput"
                                placeholder="Enter album name"
                                value="<?php echo htmlspecialchars($selected_album ?? '', ENT_QUOTES); ?>"
                                required
                            >
                            <div class="form-text">You can type a new album name or use an existing one.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Upload Images</label>
                            <div class="border rounded p-4 text-center bg-light">
                                <p class="mb-1">Drag and drop files here</p>
                                <p class="text-muted mb-2">or click to select from your device</p>
                                <input 
                                    type="file" 
                                    class="form-control" 
                                    name="images[]" 
                                    id="uploadImagesInput" 
                                    accept="image/*" 
                                    multiple 
                                    required
                                >
                                <div class="form-text">Supported formats: JPG, PNG, GIF, WEBP. Max size: 5MB per image.</div>
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload me-1"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img src="../../assets/img/sample_img.jpg" class="img-fluid rounded mb-3" alt="Preview" id="previewImage">
                    <p class="mb-1"><strong>Title:</strong> <span id="previewTitle">Opening Ceremony</span></p>
                    <p class="mb-1"><strong>Uploaded by:</strong> <span id="previewUploader">Unknown</span></p>
                    <p class="mb-1"><strong>Date:</strong> <span id="previewDate">N/A</span></p>
                    <p class="mb-0"><strong>Album:</strong> <span id="previewAlbum">N/A</span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Download</button>
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
            const newAlbumTitleInput = document.getElementById('newAlbumTitle');
            const useAlbumNameForUploadBtn = document.getElementById('useAlbumNameForUpload');
            const uploadAlbumInput = document.getElementById('uploadAlbumInput');
            const previewImage = document.getElementById('previewImage');
            const previewTitle = document.getElementById('previewTitle');
            const previewUploader = document.getElementById('previewUploader');
            const previewDate = document.getElementById('previewDate');
            const previewAlbum = document.getElementById('previewAlbum');
            
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

            // Use album name from "New Album" modal and copy into upload form
            if (useAlbumNameForUploadBtn && newAlbumTitleInput && uploadAlbumInput) {
                useAlbumNameForUploadBtn.addEventListener('click', function() {
                    const name = newAlbumTitleInput.value.trim();
                    if (name !== '') {
                        uploadAlbumInput.value = name;
                    }
                    // Close the new album modal and open upload modal
                    const newAlbumModalEl = document.getElementById('newAlbumModal');
                    const uploadImagesModalEl = document.getElementById('uploadImagesModal');
                    if (newAlbumModalEl && uploadImagesModalEl) {
                        const newAlbumModal = bootstrap.Modal.getInstance(newAlbumModalEl);
                        if (newAlbumModal) {
                            newAlbumModal.hide();
                        }
                        const uploadModal = new bootstrap.Modal(uploadImagesModalEl);
                        uploadModal.show();
                    }
                });
            }

            // Preview image in modal
            document.querySelectorAll('.view-image-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const src = this.dataset.src || '../../assets/img/sample_img.jpg';
                    const title = this.dataset.title || 'Untitled';
                    const uploader = this.dataset.uploader || 'Unknown';
                    const date = this.dataset.date || 'N/A';
                    const album = this.dataset.album || 'N/A';

                    if (previewImage) previewImage.src = src;
                    if (previewTitle) previewTitle.textContent = title;
                    if (previewUploader) previewUploader.textContent = uploader;
                    if (previewDate) previewDate.textContent = date;
                    if (previewAlbum) previewAlbum.textContent = album;
                });
            });
        });
    </script>
</body>
</html>