<?php
// SITE Reports CRUD for Officers
// Self-contained module using db_connection.php

require_once(__DIR__ . '/../../db_connection.php');

$db_error = null;
if (!isset($conn) || !$conn) {
    $db_error = 'Database connection is not initialized. Check db_connection.php.';
}

// Auto-create reports table if not exists (id, title, description, attachment optional, created_at)
if (empty($db_error)) {
    $createSql = "CREATE TABLE IF NOT EXISTS site_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_title VARCHAR(255) NOT NULL,
        report_description TEXT NOT NULL,
        officer_role VARCHAR(30) NOT NULL,
        attachment_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!$conn->query($createSql)) {
        $db_error = 'Failed creating site_reports table: ' . $conn->error;
    }
    // Ensure officer_role column exists for legacy tables
    if (empty($db_error)) {
        $colCheck = $conn->query("SHOW COLUMNS FROM site_reports LIKE 'officer_role'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE site_reports ADD COLUMN officer_role VARCHAR(30) NOT NULL DEFAULT 'president' AFTER report_description");
        }
        if ($colCheck) { $colCheck->free(); }
    }
}

$success = null;
$error = null;

// Handle download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    if (isset($_GET['report_id']) && !empty($_GET['report_id'])) {
        $report_id = (int)$_GET['report_id'];
        if (!empty($db_error) === false) {
            $stmt = $conn->prepare('SELECT * FROM site_reports WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $report_id);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $filename = 'report_' . $row['id'] . '_' . date('Y-m-d_His') . '.csv';
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        
                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['Report Title', 'Date', 'Description'], ',', '"');
                        
                        $desc = $row['report_description'] ?? 'N/A';
                        $desc = wordwrap($desc, 80, "\n", true);
                        
                        fputcsv($output, [
                            $row['report_title'] ?? 'N/A',
                            (!empty($row['created_at'])) ? date('F j, Y g:i A', strtotime($row['created_at'])) : 'N/A',
                            $desc
                        ], ',', '"');
                        fclose($output);
                        exit;
                    }
                }
                $stmt->close();
            }
        }
    } else {
        $role_filter_dl = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : '';
        $allowed_roles = ['president','vice','sec','tres','auditor'];
        
        if (empty($db_error) && !empty($role_filter_dl) && in_array($role_filter_dl, $allowed_roles, true)) {
            $reports_dl = [];
            
            $stmt = $conn->prepare('SELECT * FROM site_reports WHERE officer_role = ? ORDER BY created_at DESC, id DESC');
            if ($stmt) {
                $stmt->bind_param('s', $role_filter_dl);
                if ($stmt->execute()) { $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) { $reports_dl[] = $row; } }
                $stmt->close();
            }
            
            if (!empty($reports_dl)) {
                $filename = 'reports_' . $role_filter_dl . '_' . date('Y-m-d_His') . '.csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Report Title', 'Date', 'Description'], ',', '"');
                
                foreach ($reports_dl as $row) {
                    $desc = $row['report_description'] ?? 'N/A';
                    $desc = wordwrap($desc, 80, "\n", true);
                    
                    fputcsv($output, [
                        $row['report_title'] ?? 'N/A',
                        (!empty($row['created_at'])) ? date('F j, Y g:i A', strtotime($row['created_at'])) : 'N/A',
                        $desc
                    ], ',', '"');
                }
                
                fclose($output);
                exit;
            }
        }
    }
}

// Handle create/update
if (empty($db_error) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $title = isset($_POST['report_title']) ? trim($_POST['report_title']) : '';
    $description = isset($_POST['report_description']) ? trim($_POST['report_description']) : '';
    $officer_role = isset($_POST['officer_role']) ? strtolower(trim($_POST['officer_role'])) : '';
    $allowed_roles = ['president','vice','sec','tres','auditor'];
    if (!in_array($officer_role, $allowed_roles, true)) { $officer_role = 'president'; }

    // Optional attachment upload
    $uploadedPath = null; // relative to web root
    if (isset($_FILES['attachment']) && is_array($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'text/plain' => 'txt'
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $maxSize = 5 * 1024 * 1024; // 5MB
            if (!isset($allowed[$mime])) {
                $error = 'Invalid attachment type. Allowed: PDF, JPG, PNG, GIF, WEBP, TXT.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Attachment too large. Max 5MB.';
            } else {
                $ext = $allowed[$mime];
                $uploadDir = __DIR__ . '/../../assets/reports';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $basename = 'rep_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destAbs = $uploadDir . '/' . $basename;
                if (move_uploaded_file($file['tmp_name'], $destAbs)) {
                    $uploadedPath = 'assets/reports/' . $basename;
                } else {
                    $error = 'Failed to save attachment.';
                }
            }
        } else {
            $error = 'Attachment upload error (code ' . (int)$file['error'] . ').';
        }
    }

    if ($error === null) {
        if ($title === '' || $description === '') {
            $error = 'Please provide a title and description.';
        } else {
            if ($report_id > 0) {
                // Update
                $oldAttachment = null;
                if ($uploadedPath !== null) {
                    $chk = $conn->prepare('SELECT attachment_path FROM site_reports WHERE id = ?');
                    if ($chk) { $chk->bind_param('i', $report_id); if ($chk->execute()) { $res=$chk->get_result(); if ($row=$res->fetch_assoc()) { $oldAttachment = $row['attachment_path'] ?? null; } } $chk->close(); }
                    $stmt = $conn->prepare('UPDATE site_reports SET report_title = ?, report_description = ?, officer_role = ?, attachment_path = ? WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('ssssi', $title, $description, $officer_role, $uploadedPath, $report_id);
                        if ($stmt->execute()) {
                            $success = 'Report updated successfully.';
                            if (!empty($oldAttachment)) {
                                $abs = __DIR__ . '/../../' . $oldAttachment;
                                if (is_file($abs)) { @unlink($abs); }
                            }
                        } else { $error = 'Failed to update report: ' . $stmt->error; }
                        $stmt->close();
                    } else { $error = 'Failed to prepare update: ' . $conn->error; }
                } else {
                    $stmt = $conn->prepare('UPDATE site_reports SET report_title = ?, report_description = ?, officer_role = ? WHERE id = ?');
                    if ($stmt) { $stmt->bind_param('sssi', $title, $description, $officer_role, $report_id); if ($stmt->execute()) { $success = 'Report updated successfully.'; } else { $error = 'Failed to update report: ' . $stmt->error; } $stmt->close(); }
                    else { $error = 'Failed to prepare update: ' . $conn->error; }
                }
            } else {
                // Create
                $stmt = $conn->prepare('INSERT INTO site_reports (report_title, report_description, officer_role, attachment_path) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssss', $title, $description, $officer_role, $uploadedPath);
                    if ($stmt->execute()) { $success = 'Report created successfully.'; }
                    else { $error = 'Failed to create report: ' . $stmt->error; }
                    $stmt->close();
                } else { $error = 'Failed to prepare insert: ' . $conn->error; }
            }
        }
    }
}

// Handle delete
if (empty($db_error) && isset($_GET['delete_report_id'])) {
    $delete_id = (int)$_GET['delete_report_id'];
    if ($delete_id > 0) {
        // Remove attachment if exists
        $chk = $conn->prepare('SELECT attachment_path FROM site_reports WHERE id = ?');
        if ($chk) { $chk->bind_param('i', $delete_id); if ($chk->execute()) { $res=$chk->get_result(); if ($row=$res->fetch_assoc()) { $att=$row['attachment_path'] ?? null; if (!empty($att)) { $abs=__DIR__ . '/../../' . $att; if (is_file($abs)) { @unlink($abs); } } } } $chk->close(); }
        $stmt = $conn->prepare('DELETE FROM site_reports WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $delete_id); if ($stmt->execute()) { $success = 'Report deleted successfully.'; } else { $error = 'Failed to delete report: ' . $stmt->error; } $stmt->close(); }
        else { $error = 'Failed to prepare delete: ' . $conn->error; }
    }
}

// Load list
$reports = [];
$role_filter = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : 'all';
$allowed_roles = ['president','vice','sec','tres','auditor'];
if (empty($db_error)) {
    if ($role_filter !== 'all' && in_array($role_filter, $allowed_roles, true)) {
        $stmt = $conn->prepare('SELECT * FROM site_reports WHERE officer_role = ? ORDER BY created_at DESC, id DESC');
        if ($stmt) {
            $stmt->bind_param('s', $role_filter);
            if ($stmt->execute()) { $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) { $reports[] = $row; } }
            else { $db_error = 'Failed to load reports: ' . $stmt->error; }
            $stmt->close();
        } else { $db_error = 'Failed to prepare load: ' . $conn->error; }
    } else {
        $result = $conn->query('SELECT * FROM site_reports ORDER BY created_at DESC, id DESC');
        if ($result) { while ($row = $result->fetch_assoc()) { $reports[] = $row; } $result->free(); }
        else { $db_error = 'Failed to load reports: ' . $conn->error; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - SITE</title>
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
        .recent-activity { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .menu-toggle { display:none; background:none; border:none; font-size:1.5rem; color:#1e174a; cursor:pointer; }
        @media (max-width: 992px) { .sidebar{transform:translateX(-100%);} .sidebar.active{transform:translateX(0);} .main-content{margin-left:0;} .menu-toggle{display:block;} .btn-close-sidebar{display:block;} }
    /* Role filter pill styles for better visibility */
        .role-pill { border: 2px solid transparent; font-weight: 600; margin-right: 6px; }
        .role-all { border-color: #6c757d; color: #6c757d; }
        .role-president { border-color: #0d6efd; color: #0d6efd; }
        .role-vice { border-color: #6610f2; color: #6610f2; }
        .role-sec { border-color: #198754; color: #198754; }
        .role-tres { border-color: #ffc107; color: #b8860b; }
        .role-auditor { border-color: #dc3545; color: #dc3545; }

        .nav-pills .role-pill { background: #fff; }
        .nav-pills .role-pill:hover { filter: brightness(0.95); }

        .nav-pills .role-pill.active.role-all { background-color: #6c757d; color: #fff; }
        .nav-pills .role-pill.active.role-president { background-color: #0d6efd; color: #fff; }
        .nav-pills .role-pill.active.role-vice { background-color: #6610f2; color: #fff; }
        .nav-pills .role-pill.active.role-sec { background-color: #198754; color: #fff; }
        .nav-pills .role-pill.active.role-tres { background-color: #ffc107; color: #212529; }
        .nav-pills .role-pill.active.role-auditor { background-color: #dc3545; color: #fff; }
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
                <li class="nav-item"><a class="nav-link" href="site_chat.php"><i class="bi bi-chat-dots"></i><span>Chat</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="site_report.php"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a></li>
                <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="bi bi-clipboard-check"></i><span>Attendance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../../dashboard.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
            <div><strong>Reports</strong></div>
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-person-circle"></i></div>
            </div>
        </nav>

        <div class="content-area">
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
                <h2 class="mb-0">Officer Reports</h2>
                <div style="display:flex; gap:0.5rem;">
                    <?php if (!empty($reports) && $role_filter !== 'all'): ?>
                        <a href="?role=<?php echo urlencode($role_filter); ?>&download=csv" class="btn btn-success">
                            <i class="bi bi-download me-1"></i> Download Reports
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal" id="newReportBtn">
                        <i class="bi bi-plus-circle"></i> New Report
                    </button>
                </div>
            </div>

            <!-- Role Filter Tabs -->
            <ul class="nav nav-pills mb-3">
                <?php 
                    $roles = ['all' => 'All', 'president' => 'President', 'vice' => 'Vice', 'sec' => 'Secretary', 'tres' => 'Treasurer', 'auditor' => 'Auditor'];
                    foreach ($roles as $key => $label):
                        $active = ($role_filter === $key) ? 'active' : '';
                        $href = 'site_report.php?role=' . urlencode($key);
                        $roleClass = 'role-pill role-' . $key;
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $roleClass . ' ' . $active; ?>" href="<?php echo htmlspecialchars($href); ?>"><?php echo htmlspecialchars($label); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="recent-activity">
                <div id="reportsList">
                    <?php if (!empty($reports)): ?>
                        <?php foreach ($reports as $rep): ?>
                            <div class="activity-item d-flex">
                                <div class="activity-icon"><i class="bi bi-file-earmark-text"></i></div>
                                <div class="flex-grow-1">
                                    <h6 class="announcement-title mb-1">
                                        <?php echo htmlspecialchars($rep['report_title']); ?>
                                        <?php if (!empty($rep['officer_role'])): ?>
                                            <span class="badge bg-info text-dark ms-2" style="font-weight:600; text-transform:capitalize;">
                                                <?php echo htmlspecialchars($rep['officer_role']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="announcement-content mb-1"><?php echo nl2br(htmlspecialchars($rep['report_description'])); ?></p>
                                    <small class="announcement-date d-block mb-2">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php if (!empty($rep['created_at'])) echo date('F j, Y g:i A', strtotime($rep['created_at'])); ?>
                                    </small>
                                    <?php if (!empty($rep['attachment_path'])): ?>
                                        <div class="mb-2">
                                            <a href="<?php echo '../../' . htmlspecialchars($rep['attachment_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-paperclip"></i> View Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="?download=csv&report_id=<?php echo (int)$rep['id']; ?>" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary ms-2 editReportBtn" data-report='<?php echo json_encode($rep, JSON_HEX_APOS|JSON_HEX_QUOT); ?>'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="?delete_report_id=<?php echo (int)$rep['id']; ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Delete this report?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text display-1 text-muted mb-3"></i>
                            <p class="text-muted">No reports yet. Create your first report!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Modal (create / edit) -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel"><i class="bi bi-file-earmark-text me-2"></i><span id="reportModalTitle">Create New Report</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="reportForm" method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="report_id" value="">
                        <div class="mb-3">
                            <label for="reportTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="reportTitle" name="report_title" placeholder="Enter report title" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="reportDescription" class="form-label">Description *</label>
                            <textarea class="form-control" id="reportDescription" name="report_description" rows="6" placeholder="Enter report description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="officerRole" class="form-label">Officer Role *</label>
                            <select class="form-select" id="officerRole" name="officer_role" required>
                                <option value="president">President</option>
                                <option value="vice">Vice</option>
                                <option value="sec">Secretary</option>
                                <option value="tres">Treasurer</option>
                                <option value="auditor">Auditor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="reportAttachment" class="form-label">Attachment (optional)</label>
                            <input type="file" class="form-control" id="reportAttachment" name="attachment" accept=".pdf,image/*,.txt">
                            <div class="form-text">Max 5MB. Allowed: PDF, JPG, PNG, GIF, WEBP, TXT.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveReportBtn"><i class="bi bi-save me-1"></i><span id="saveReportText">Save Report</span></button>
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
            if (menuToggle) menuToggle.addEventListener('click', ()=>{ sidebar.classList.add('active'); if (sidebarOverlay) sidebarOverlay.style.display='block'; });
            if (closeSidebar) closeSidebar.addEventListener('click', ()=>{ sidebar.classList.remove('active'); if (sidebarOverlay) sidebarOverlay.style.display='none'; });
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', ()=>{ sidebar.classList.remove('active'); sidebarOverlay.style.display='none'; });

            const reportModalEl = document.getElementById('reportModal');
            const reportForm = document.getElementById('reportForm');
            const reportModalTitle = document.getElementById('reportModalTitle');
            const saveReportBtn = document.getElementById('saveReportBtn');
            const saveReportText = document.getElementById('saveReportText');
            const reportIdField = document.getElementById('report_id');
            const titleField = document.getElementById('reportTitle');
            const descriptionField = document.getElementById('reportDescription');
            const officerRoleField = document.getElementById('officerRole');

            const newReportBtn = document.getElementById('newReportBtn');
            if (newReportBtn) {
                newReportBtn.addEventListener('click', function() {
                    reportIdField.value = '';
                    titleField.value = '';
                    descriptionField.value = '';
                    const att = document.getElementById('reportAttachment');
                    if (att) att.value = '';
                    if (officerRoleField) officerRoleField.value = 'president';
                    reportModalTitle.textContent = 'Create New Report';
                    saveReportText.textContent = 'Save Report';
                });
            }

            document.querySelectorAll('.editReportBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const rep = btn.getAttribute('data-report');
                    if (!rep) return;
                    try {
                        const data = JSON.parse(rep);
                        reportIdField.value = data.id || '';
                        titleField.value = data.report_title || '';
                        descriptionField.value = data.report_description || '';
                        if (officerRoleField) officerRoleField.value = (data.officer_role || 'president');
                        reportModalTitle.textContent = 'Edit Report';
                        saveReportText.textContent = 'Update Report';
                        const modal = new bootstrap.Modal(reportModalEl);
                        modal.show();
                    } catch (e) { console.error('Failed to parse report data', e); }
                });
            });

            if (reportForm) {
                reportForm.addEventListener('submit', function() {
                    const original = saveReportBtn.innerHTML;
                    saveReportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                    saveReportBtn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>