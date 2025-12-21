<?php
// SITE Services CRUD (Create, Read, Update, Delete)
// This module is self-contained and uses db_connection.php

require_once(__DIR__ . '/../../db_connection.php');

// Ensure DB connection variable $conn exists (using mysqli)
if (!isset($conn) || !$conn) {
    $db_error = 'Database connection is not initialized. Check db_connection.php.';
}

// Expecting table `site_service` to already exist; no automatic creation here.

$success = null;
$error = null;

// Detect if optional image column exists
$hasImageCol = false;
if (empty($db_error)) {
    $colCheck = $conn->query("SHOW COLUMNS FROM site_service LIKE 'service_image'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasImageCol = true;
    }
}

// Handle create/update submit
if (empty($db_error) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $title = isset($_POST['service_title']) ? trim($_POST['service_title']) : '';
    $description = isset($_POST['service_description']) ? trim($_POST['service_description']) : '';

    // Handle image upload if provided
    $uploadedImageRelPath = null; // relative to web root (e.g., assets/img/services/filename.jpg)
    if ($hasImageCol && isset($_FILES['service_image']) && is_array($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['service_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!isset($allowed[$mime])) {
                $error = 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
                $error = 'Image too large. Max 2MB.';
            } else {
                $ext = $allowed[$mime];
                $uploadDir = __DIR__ . '/../../assets/img/services';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $basename = 'svc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destAbs = $uploadDir . '/' . $basename;
                if (move_uploaded_file($file['tmp_name'], $destAbs)) {
                    // store relative path from modules (page) perspective: ../../assets/img/services/...
                    $uploadedImageRelPath = 'assets/img/services/' . $basename;
                } else {
                    $error = 'Failed to save uploaded image.';
                }
            }
        } else {
            $error = 'Image upload error (code ' . (int)$file['error'] . ').';
        }
    } elseif (!$hasImageCol && isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Inform missing column if user tried to upload
        $error = 'Database column service_image is missing. Please add it: ALTER TABLE site_service ADD COLUMN service_image VARCHAR(255) NULL AFTER service_description;';
    }

    if ($title === '' || $description === '') {
        $error = 'Please provide a title and description.';
    } else {
        if ($service_id > 0) {
            // Update existing
            $oldImage = null;
            if ($hasImageCol) {
                $chk = $conn->prepare('SELECT service_image FROM site_service WHERE id = ?');
                if ($chk) {
                    $chk->bind_param('i', $service_id);
                    if ($chk->execute()) {
                        $res = $chk->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $oldImage = $row['service_image'] ?? null;
                        }
                    }
                    $chk->close();
                }
            }

            if ($hasImageCol && $uploadedImageRelPath !== null) {
                $stmt = $conn->prepare('UPDATE site_service SET service_title = ?, service_description = ?, service_image = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssi', $title, $description, $uploadedImageRelPath, $service_id);
                    if ($stmt->execute()) {
                        $success = 'Service updated successfully.';
                        // Delete old image file if replaced
                        if (!empty($oldImage)) {
                            $oldPathAbs = __DIR__ . '/../../' . $oldImage;
                            if (is_file($oldPathAbs)) { @unlink($oldPathAbs); }
                        }
                    } else {
                        $error = 'Failed to update service: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare update: ' . $conn->error;
                }
            } else {
                $stmt = $conn->prepare('UPDATE site_service SET service_title = ?, service_description = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('ssi', $title, $description, $service_id);
                    if ($stmt->execute()) {
                        $success = 'Service updated successfully.';
                    } else {
                        $error = 'Failed to update service: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare update: ' . $conn->error;
                }
            }
        } else {
            // Create new
            if ($hasImageCol) {
                $stmt = $conn->prepare('INSERT INTO site_service (service_title, service_description, service_image) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('sss', $title, $description, $uploadedImageRelPath);
                    if ($stmt->execute()) {
                        $success = 'Service created successfully.';
                    } else {
                        $error = 'Failed to create service: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare insert: ' . $conn->error;
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO site_service (service_title, service_description) VALUES (?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ss', $title, $description);
                    if ($stmt->execute()) {
                        $success = 'Service created successfully. (Image column not present)';
                    } else {
                        $error = 'Failed to create service: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare insert: ' . $conn->error;
                }
            }
        }
    }
}

// Handle delete by GET param
if (empty($db_error) && isset($_GET['delete_service_id'])) {
    $delete_id = (int)$_GET['delete_service_id'];
    if ($delete_id > 0) {
        // Remove associated image if present
        if ($hasImageCol) {
            $chk = $conn->prepare('SELECT service_image FROM site_service WHERE id = ?');
            if ($chk) {
                $chk->bind_param('i', $delete_id);
                if ($chk->execute()) {
                    $res = $chk->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $img = $row['service_image'] ?? null;
                        if (!empty($img)) {
                            $abs = __DIR__ . '/../../' . $img;
                            if (is_file($abs)) { @unlink($abs); }
                        }
                    }
                }
                $chk->close();
            }
        }

        $stmt = $conn->prepare('DELETE FROM site_service WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $success = 'Service deleted successfully.';
            } else {
                $error = 'Failed to delete service: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Failed to prepare delete: ' . $conn->error;
        }
    }
}

// Load services list
$services = [];
if (empty($db_error)) {
    $result = $conn->query('SELECT * FROM site_service ORDER BY created_at DESC, id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        $result->free();
    } else {
        $db_error = 'Failed to load services: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - SITE</title>
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
        .recent-activity { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .activity-item { padding: 15px 0; border-bottom: 1px solid #ecf0f1; display: flex; align-items: center; }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 40px; height: 40px; border-radius: 50%; background: #ecf0f1; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: #3498db; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: #1e174a; cursor: pointer; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .btn-close-sidebar { display: block; }
            .search-box { width: 200px; }
        }
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
                <li class="nav-item"><a class="nav-link active" href="site_service.php"><i class="bi bi-wrench-adjustable"></i><span>Services</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_penalties.php"><i class="bi bi-exclamation-triangle"></i><span>Penalties</span></a></li>
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
                <div class="user-details"><div class="user-name">Tim</div><div class="user-role">Student</div></div>
            </div>
        </nav>

        <div class="content-area">
            <h2 class="mb-4">Services</h2>

            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i> <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="content-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h2 class="mb-0">Services</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal" id="newServiceBtn">
                    <i class="bi bi-plus-circle"></i> New Service
                </button>
            </div>

            <div class="recent-activity">
                <div id="servicesList">
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $svc): ?>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="bi bi-wrench"></i></div>
                                <div class="flex-grow-1">
                                    <?php if (isset($svc['service_image']) && !empty($svc['service_image'])): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo '../../' . htmlspecialchars($svc['service_image']); ?>" alt="Service Image" style="max-height:120px;border-radius:8px;object-fit:cover;">
                                        </div>
                                    <?php endif; ?>
                                    <h6 class="announcement-title"><?php echo htmlspecialchars($svc['service_title']); ?></h6>
                                    <p class="announcement-content"><?php echo nl2br(htmlspecialchars($svc['service_description'])); ?></p>
                                    <small class="announcement-date d-block mb-2">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php if (!empty($svc['created_at'])) echo date('F j, Y g:i A', strtotime($svc['created_at'])); ?>
                                    </small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary editServiceBtn" data-service='<?php echo json_encode($svc, JSON_HEX_APOS|JSON_HEX_QUOT); ?>'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="?delete_service_id=<?php echo (int)$svc['id']; ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Delete this service?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-wrench-adjustable-circle display-1 text-muted mb-3"></i>
                            <p class="text-muted">No services yet. Create your first service!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Modal (create / edit) -->
    <div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalLabel"><i class="bi bi-wrench me-2"></i><span id="serviceModalTitle">Create New Service</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="serviceForm" method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="service_id" value="">
                        <div class="mb-3">
                            <label for="serviceTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="serviceTitle" name="service_title" placeholder="Enter service title" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="serviceDescription" class="form-label">Description *</label>
                            <textarea class="form-control" id="serviceDescription" name="service_description" rows="6" placeholder="Enter service description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="serviceImage" class="form-label">Image (optional)</label>
                            <input type="file" class="form-control" id="serviceImage" name="service_image" accept="image/*">
                            <div class="form-text">Max 2MB. Allowed: JPG, PNG, GIF, WEBP</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveServiceBtn"><i class="bi bi-save me-1"></i><span id="saveServiceText">Save Service</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');

            if (menuToggle) menuToggle.addEventListener('click', function() { sidebar.classList.add('active'); if (sidebarOverlay) { sidebarOverlay.style.display = 'block'; } });
            if (closeSidebar) closeSidebar.addEventListener('click', function() { sidebar.classList.remove('active'); if (sidebarOverlay) { sidebarOverlay.style.display = 'none'; } });
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', function() { sidebar.classList.remove('active'); sidebarOverlay.style.display = 'none'; });
            window.addEventListener('resize', function() { if (window.innerWidth > 992 && sidebarOverlay) sidebarOverlay.style.display = 'none'; });

            const serviceModalEl = document.getElementById('serviceModal');
            const serviceForm = document.getElementById('serviceForm');
            const serviceModalTitle = document.getElementById('serviceModalTitle');
            const saveServiceBtn = document.getElementById('saveServiceBtn');
            const saveServiceText = document.getElementById('saveServiceText');
            const serviceIdField = document.getElementById('service_id');
            const titleField = document.getElementById('serviceTitle');
            const descriptionField = document.getElementById('serviceDescription');

            const newServiceBtn = document.getElementById('newServiceBtn');
            if (newServiceBtn) {
                newServiceBtn.addEventListener('click', function() {
                    serviceIdField.value = '';
                    titleField.value = '';
                    descriptionField.value = '';
                    serviceModalTitle.textContent = 'Create New Service';
                    saveServiceText.textContent = 'Save Service';
                });
            }

            document.querySelectorAll('.editServiceBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sv = btn.getAttribute('data-service');
                    if (!sv) return;
                    try {
                        const data = JSON.parse(sv);
                        serviceIdField.value = data.id || '';
                        titleField.value = data.service_title || '';
                        descriptionField.value = data.service_description || '';
                        serviceModalTitle.textContent = 'Edit Service';
                        saveServiceText.textContent = 'Update Service';
                        const modal = new bootstrap.Modal(serviceModalEl);
                        modal.show();
                    } catch (e) { console.error('Failed to parse service data', e); }
                });
            });

            if (serviceForm) {
                serviceForm.addEventListener('submit', function() {
                    const original = saveServiceBtn.innerHTML;
                    saveServiceBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                    saveServiceBtn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>