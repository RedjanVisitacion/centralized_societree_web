<?php
// Prefer AFPROTECH module config/connection (no edits to root db_connection.php)
require_once __DIR__ . '/config/config.php';
$conn = null;
try {
    $conn = getAfprotechDbConnection();
} catch (Throwable $t) {
    // Fallback to root db_connection.php if AFPROTECH config fails
    $rootDbPath = realpath(__DIR__ . '/../../db_connection.php');
    if ($rootDbPath && file_exists($rootDbPath)) {
        require_once $rootDbPath; // defines $pdo
        // If PDO is available, open a mysqli for the legacy code paths
        try {
            $conn = new mysqli(DB_HOST_PRIMARY, DB_USER_PRIMARY, DB_PASS_PRIMARY, DB_NAME_PRIMARY);
        } catch (Throwable $t2) {
            // final fallback handled below
        }
    }
}

if (!$conn || $conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection not established')
    ]));
}

// Ensure announcements table exists (prevents empty results on first load)
$createAnnouncementsTable = "
    CREATE TABLE IF NOT EXISTS afprotechs_announcements (
        announcement_id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_title VARCHAR(255) NOT NULL,
        announcement_content TEXT NOT NULL,
        announcement_datetime DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createAnnouncementsTable);

// Backfill missing audit columns for older tables
// Ignore errors if columns already exist
$conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
$conn->query("ALTER TABLE afprotechs_announcements ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

// Fetch announcements ordered by datetime (latest first)
$annSql = "
    SELECT 
        announcement_id,
        announcement_title,
        announcement_content,
        announcement_datetime,
        created_at
    FROM afprotechs_announcements
    ORDER BY announcement_datetime DESC, created_at DESC";

$annResult = $conn->query($annSql);
$announcements = [];
$annError = null;

if ($annResult && $annResult->num_rows > 0) {
    while ($row = $annResult->fetch_assoc()) {
        $announcements[] = $row;
    }
} elseif (!$annResult) {
    $annError = $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECHS</title>
        <link rel="icon" type="image/png" href="../../assets/logo/afprotech_1.png?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="afprotechs_styles.css?v=<?= time() ?>">
    
    <style>
    /* Fix dropdown z-index and positioning issues */
    .dropdown-menu {
        z-index: 1050 !important;
        position: absolute !important;
    }
    
    .dropdown {
        position: relative !important;
    }
    
    .event-action-toggle {
        z-index: 10 !important;
        position: relative !important;
    }
    
    /* Ensure dropdown button is clickable */
    .event-action-toggle:hover {
        background-color: #f8f9fa !important;
    }
    
    /* Fix any potential overlay issues */
    .announcement-item {
        position: relative;
        z-index: 1;
    }
    
    .announcement-item .dropdown {
        z-index: 10;
    }
    </style>
    
</head>
<body>

<div class="sidebar d-flex flex-column align-items-start pt-4 px-3">
    <div class="sidebar-brand d-flex align-items-center gap-3 mb-4 w-100">
        <div class="sidebar-logo">
            <img src="../../assets/logo/afprotech_1.png?v=<?= time() ?>" alt="logo" width="60" height="60">
        </div>
        <div class="sidebar-org text-start">
            <span class="sidebar-org-title">AFPROTECH</span>
        </div>
    </div>

    <a href="afprotechs_dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="afprotechs_events.php"><i class="fa-solid fa-calendar-days"></i><span>Event</span></a>
    <a href="afprotechs_attendance.php"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a>
    <a href="#" class="active"><i class="fa-solid fa-bullhorn"></i><span>Announcement</span></a>
    <a href="afprotechs_records.php"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="afprotechs_products.php"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="afprotechs_reports.php"><i class="fa-solid fa-file-lines"></i><span>Generate Reports</span></a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">

    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">
        
        <div>
            <h2 class="fw-bold text-dark mb-0" style="font-size: 24px;">Announcements Management</h2>
        </div>

        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <div class="rounded-circle dashboard-profile-avatar 
            d-flex align-items-center justify-content-center"
     style="width:40px;height:40px;background:#000080;
            color:#fff;font-weight:bold;font-size:14px; text-transform: uppercase;">
    LB
</div>

<span class="fw-semibold dashboard-admin-name">
    Lester Bulay<br>
    <span class="dashboard-role">ADMIN</span>
</span>

        </div>

    </div>

    <!-- SEARCH BAR AND CONTROLS -->
    <div class="container-fluid px-4 mb-4">
        <div class="row">
            <div class="col-12 d-flex justify-content-end align-items-center gap-3">
                <!-- SEARCH BAR -->
                <div style="max-width: 300px; width: 100%;">
                    <form class="announcements-search-form">
                        <div class="input-group">
                            <input type="search" class="form-control" id="announcementsSearchInput" placeholder="Search announcements..." aria-label="Search announcements">
                            <button class="btn btn-primary" type="submit" style="background-color: #000080; border-color: #000080;">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- CREATE ANNOUNCEMENT BUTTON -->
                <button class="btn btn-create-event d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="fa-solid fa-plus"></i>
                    Create Announcement
                </button>
            </div>
        </div>
    </div>

    <div class="section-box">
        <div class="section-title mb-3">
            <i class="fa-solid fa-bullhorn"></i>
            Recent Announcements
        </div>

        <?php if ($annError): ?>
            <p class="text-danger mb-0">Error loading announcements: <?= htmlspecialchars($annError) ?></p>
        <?php elseif (empty($announcements)): ?>
            <p class="mb-0 text-muted">No announcements yet.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($announcements as $a): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="me-3 flex-grow-1 announcement-view-item" 
                             style="cursor: pointer;"
                             data-id="<?= $a['announcement_id'] ?>"
                             data-title="<?= htmlspecialchars($a['announcement_title']) ?>"
                             data-content="<?= htmlspecialchars($a['announcement_content']) ?>"
                             data-datetime="<?= htmlspecialchars($a['announcement_datetime']) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <strong style="color: #000080;"><?= htmlspecialchars($a['announcement_title']) ?></strong>
                                <span class="badge bg-light text-dark border">
                                    Scheduled
                                </span>
                            </div>
                            <div class="mt-1" style="white-space: pre-line; line-height: 1.6;">
                                <?= htmlspecialchars($a['announcement_content']) ?>
                            </div>
                            <div class="small mt-2" style="color: #000080;">
                                <?= date('M d, Y g:i A', strtotime($a['announcement_datetime'])) ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm event-action-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 edit-announcement-btn"
                                           href="#"
                                           data-id="<?= $a['announcement_id'] ?>"
                                           data-title="<?= htmlspecialchars($a['announcement_title']) ?>"
                                           data-content="<?= htmlspecialchars($a['announcement_content']) ?>"
                                           data-datetime="<?= htmlspecialchars($a['announcement_datetime']) ?>">
                                            <i class="fa-regular fa-pen-to-square"></i> Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-announcement-btn"
                                           href="#"
                                           data-id="<?= $a['announcement_id'] ?>"
                                           data-title="<?= htmlspecialchars($a['announcement_title']) ?>">
                                            <i class="fa-regular fa-trash-can"></i> Delete
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementModalTitle">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="create_announcement.php" method="POST" id="createAnnouncementForm">
                <input type="hidden" name="announcement_id" id="announcementId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="announcement_title" class="form-control" placeholder="Title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content</label>
                        <textarea name="announcement_content" class="form-control" placeholder="Content" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date & Time</label>
                        <input type="datetime-local" name="announcement_datetime" class="form-control" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-save-event w-100" id="announcementFormSubmitBtn">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 900px;">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            
            <!-- App Bar Header -->
            <div class="announcement-app-bar d-flex align-items-center justify-content-between p-3" style="background: linear-gradient(135deg, #000080 0%, #1a4fa0 100%); color: white;">
                <div class="d-flex align-items-center gap-3">
                    <div class="announcement-app-icon d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; backdrop-filter: blur(10px);">
                        <i class="fa-solid fa-bullhorn" style="font-size: 18px;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="font-size: 16px;">Recent Announcement</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body with Scrollable Content -->
            <div class="modal-body p-0" style="max-height: 70vh; overflow-y: auto;">
                
                <!-- Announcement Content -->
                <div class="announcement-content p-4">
                    <!-- Announcement Title -->
                    <h4 id="viewAnnouncementModalTitle" class="mb-4 fw-bold" style="color: #000080; font-size: 24px; line-height: 1.3;"></h4>
                    
                    <div class="content-container">
                        <div id="viewAnnouncementModalContent" class="mb-3 content-text" style="line-height: 1.6; color: #2c2c2c; font-size: 16px; font-weight: 400; text-align: justify; max-height: 200px; overflow: hidden; transition: max-height 0.3s ease; word-wrap: break-word; white-space: pre-line;"></div>
                        <button id="seeMoreBtn" class="btn btn-link p-0 text-primary d-none" onclick="toggleSeeMore()" style="font-size: 14px; text-decoration: none; font-weight: 500;">
                            <i class="fa-solid fa-chevron-down me-1" id="seeMoreIcon"></i> See More
                        </button>
                    </div>
                </div>

                <!-- Date and Time Section -->
                <div class="announcement-datetime px-4 py-2">
                    <div class="d-flex align-items-center gap-3 text-muted">
                        <div class="d-flex align-items-center gap-1">
                            <i class="fa-solid fa-calendar-days" style="color: #000080; font-size: 14px;"></i>
                            <span id="viewAnnouncementModalDate" style="font-size: 14px; font-weight: normal;"></span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <i class="fa-solid fa-clock" style="color: #000080; font-size: 14px;"></i>
                            <span id="viewAnnouncementModalTime" style="font-size: 14px; font-weight: normal;"></span>
                        </div>
                    </div>
                </div>



                <!-- Comments Toggle Section -->
                <div class="comments-toggle-section px-4 py-2">
                    <button class="btn btn-sm btn-outline-dark" id="toggleCommentsBtn" onclick="toggleComments()" style="border-radius: 20px; padding: 6px 16px; font-size: 12px; border: 2px solid #000; color: #000;">
                        <i class="fa-solid fa-comment me-1"></i> Comments <span class="badge bg-light text-dark ms-1">3</span>
                    </button>
                </div>

                <!-- Comments Section (Initially Hidden) -->
                <div class="comments-section px-4 py-3 d-none" id="commentsSection">
                    <!-- Comments List -->
                    <div class="comments-list">
                        <!-- Sample Comment 1 -->
                        <div class="comment-item mb-3 pb-3" style="border-bottom: 1px solid #f0f0f0;">
                            <div class="w-100">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold" style="font-size: 14px; color: #2c2c2c;">John Kenneth</span>
                                    <span class="text-muted" style="font-size: 12px;">2 hours ago</span>
                                </div>
                                <p class="mb-2" style="font-size: 14px; line-height: 1.4; color: #4a4a4a;">Great announcement! Very informative and well-structured. Looking forward to more updates like this. The information provided is really helpful for our organization.</p>
                                <div class="comment-actions d-flex align-items-center gap-3">
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-thumbs-up me-1"></i> Like (2)
                                    </button>
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-comment me-1"></i> Reply
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sample Comment 2 -->
                        <div class="comment-item mb-3 pb-3" style="border-bottom: 1px solid #f0f0f0;">
                            <div class="w-100">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold" style="font-size: 14px; color: #2c2c2c;">Maria Santos</span>
                                    <span class="text-muted" style="font-size: 12px;">5 hours ago</span>
                                </div>
                                <p class="mb-2" style="font-size: 14px; line-height: 1.4; color: #4a4a4a;">Thanks for sharing this important information. Will there be a follow-up session? I'm really interested in learning more about this topic.</p>
                                <div class="comment-actions d-flex align-items-center gap-3">
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-thumbs-up me-1"></i> Like (1)
                                    </button>
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-comment me-1"></i> Reply
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sample Comment 3 (Admin) -->
                        <div class="comment-item mb-3">
                            <div class="w-100">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold" style="font-size: 14px; color: #2c2c2c;">Harold Coyoca</span>
                                    <span class="badge" style="background: linear-gradient(135deg, #000080 0%, #1a4fa0 100%); font-size: 10px;">ADMIN</span>
                                    <span class="text-muted" style="font-size: 12px;">1 day ago</span>
                                </div>
                                <p class="mb-2" style="font-size: 14px; line-height: 1.4; color: #4a4a4a;">Thank you all for your engagement! We'll be posting more updates soon. Your feedback is valuable to us and helps us improve our communication. Stay tuned!</p>
                                <div class="comment-actions d-flex align-items-center gap-3">
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-thumbs-up me-1"></i> Like (5)
                                    </button>
                                    <button class="btn btn-sm btn-link p-0 text-muted" style="font-size: 12px; text-decoration: none;">
                                        <i class="fa-regular fa-comment me-1"></i> Reply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const createAnnouncementForm = document.getElementById('createAnnouncementForm');
    const announcementModal = document.getElementById('createAnnouncementModal');
    const announcementModalTitle = document.getElementById('announcementModalTitle');
    const announcementSubmitBtn = document.getElementById('announcementFormSubmitBtn');
    const announcementIdInput = document.getElementById('announcementId');

    // Handle form submit (create/update)
    if (createAnnouncementForm) {
        createAnnouncementForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = announcementSubmitBtn;
            const originalText = submitBtn.textContent;
            const announcementId = formData.get('announcement_id');
            const isEdit = announcementId && announcementId !== '';

            submitBtn.disabled = true;
            submitBtn.textContent = isEdit ? 'Updating...' : 'Creating...';

            const apiUrl = isEdit ? 'update_announcement.php' : 'create_announcement.php';

            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                
                // Clean the response text by extracting JSON from it
                let cleanText = text.trim();
                
                // If response contains JSON, extract it
                const jsonMatch = cleanText.match(/\{.*\}/s);
                if (jsonMatch) {
                    cleanText = jsonMatch[0];
                }
                
                // Try to parse the cleaned JSON
                try {
                    const data = JSON.parse(cleanText);
                    // Trust the data.success value from the parsed JSON
                    return { success: data.success === true || data.status === 'success', data: data, rawText: text };
                } catch (e) {
                    // If JSON parsing still fails, check for success indicators in the text
                    const hasSuccessIndicator = text.includes('"success":true') || text.includes('"status":"success"') || text.includes('successfully') || text.includes('created') || text.includes('updated');
                    const hasErrorIndicator = text.includes('"success":false') || text.includes('"status":"error"') || text.includes('error') || text.includes('failed');
                    
                    // Prioritize success indicators over error indicators
                    const isSuccess = hasSuccessIndicator && !hasErrorIndicator;
                    
                    return { 
                        success: isSuccess, 
                        data: { 
                            success: isSuccess, 
                            status: isSuccess ? 'success' : 'error',
                            message: isSuccess ? 'Operation completed successfully' : 'Invalid response format' 
                        },
                        rawText: text 
                    };
                }
            })
            .then(result => {
                // Always check for success first, regardless of parsing issues
                const isActualSuccess = result.success === true || 
                                      (result.data && (result.data.success === true || result.data.status === 'success')) ||
                                      (result.data && result.data.message && result.data.message.toLowerCase().includes('success'));
                
                if (isActualSuccess) {
                    // Close modal first
                    const createModal = bootstrap.Modal.getInstance(announcementModal);
                    if (createModal) {
                        createModal.hide();
                    }
                    
                    // Reset form
                    createAnnouncementForm.reset();
                    announcementIdInput.value = '';
                    announcementModalTitle.textContent = 'Create Announcement';
                    announcementSubmitBtn.textContent = 'Create Announcement';
                    
                    // Show success message after modal closes
                    setTimeout(() => {
                        const successMessage = isEdit ? 'Announcement updated successfully!' : 'Announcement created successfully!';
                        alert(successMessage);
                        location.reload();
                    }, 300);
                } else {
                    // Only show error if it's genuinely an error (not a success message)
                    const errorMessage = result.data.message || 'Failed to save announcement';
                    
                    // Double-check: don't show error if message contains success indicators
                    if (!errorMessage.toLowerCase().includes('success') && 
                        !errorMessage.toLowerCase().includes('updated') && 
                        !errorMessage.toLowerCase().includes('created')) {
                        alert('Error: ' + errorMessage);
                    } else {
                        // It's actually a success, treat it as such
                        setTimeout(() => {
                            alert(errorMessage);
                            location.reload();
                        }, 300);
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                // Only show error for genuine network failures
                console.error('Network error:', error);
                alert('Network connection error. Please check your internet connection and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // Edit announcement
    document.querySelectorAll('.edit-announcement-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title') || '';
            const content = this.getAttribute('data-content') || '';
            const datetime = this.getAttribute('data-datetime') || '';

            // Populate form
            announcementIdInput.value = id;
            createAnnouncementForm.announcement_title.value = title;
            createAnnouncementForm.announcement_content.value = content;
            createAnnouncementForm.announcement_datetime.value = datetime.replace(' ', 'T');

            announcementModalTitle.textContent = 'Edit Announcement';
            announcementSubmitBtn.textContent = 'Update Announcement';

            const modal = new bootstrap.Modal(announcementModal);
            modal.show();
        });
    });

    // Delete announcement
    document.querySelectorAll('.delete-announcement-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title') || 'this announcement';

            if (!confirm(`Delete "${title}"? This action cannot be undone.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('announcement_id', id);

            fetch('delete_announcement.php', {
                method: 'POST',
                body: fd
            })
            .then(async response => {
                const text = await response.text();
                
                // Clean the response text by extracting JSON from it
                let cleanText = text.trim();
                
                // If response contains JSON, extract it
                const jsonMatch = cleanText.match(/\{.*\}/s);
                if (jsonMatch) {
                    cleanText = jsonMatch[0];
                }
                
                // Try to parse the cleaned JSON
                try {
                    const data = JSON.parse(cleanText);
                    return { success: data.status === 'success', data: data, rawText: text };
                } catch (e) {
                    // If JSON parsing still fails, check for success indicators in the text
                    const hasSuccessIndicator = text.includes('"status":"success"') || text.includes('deleted successfully');
                    const hasErrorIndicator = text.includes('"status":"error"') || text.includes('error') || text.includes('failed');
                    
                    const isSuccess = hasSuccessIndicator && !hasErrorIndicator;
                    
                    return { 
                        success: isSuccess, 
                        data: { 
                            status: isSuccess ? 'success' : 'error',
                            message: isSuccess ? 'Announcement deleted successfully' : 'Invalid response format' 
                        },
                        rawText: text 
                    };
                }
            })
            .then(result => {
                if (result.success) {
                    alert('Announcement deleted successfully!');
                    location.reload();
                } else {
                    const errorMessage = result.data.message || 'Failed to delete announcement';
                    if (!errorMessage.toLowerCase().includes('success')) {
                        alert('Error: ' + errorMessage);
                    } else {
                        alert(errorMessage);
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                alert('Network connection error. Please check your internet connection and try again.');
            });
        });
    });

    // Handle announcement view clicks
    const viewAnnouncementModalEl = document.getElementById('viewAnnouncementModal');
    if (viewAnnouncementModalEl && window.bootstrap) {
        const viewAnnouncementModal = new bootstrap.Modal(viewAnnouncementModalEl);
        
        document.querySelectorAll('.announcement-view-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Prevent event bubbling and check if click is on dropdown
                if (e.target.closest('.dropdown') || e.target.closest('.event-action-toggle')) {
                    return; // Don't open modal if clicking on dropdown
                }
                
                e.stopPropagation();
                console.log('Announcement clicked!'); // Debug log
                
                const id = this.getAttribute('data-id') || '1';
                const title = this.getAttribute('data-title') || 'Announcement';
                const content = this.getAttribute('data-content') || '';
                const datetime = this.getAttribute('data-datetime') || '';
                
                console.log('ID:', id, 'Title:', title, 'Content:', content, 'DateTime:', datetime); // Debug log
                
                // Store announcement ID in modal for comments link
                viewAnnouncementModalEl.setAttribute('data-announcement-id', id);
                
                // Set modal content
                const titleEl = viewAnnouncementModalEl.querySelector('#viewAnnouncementModalTitle');
                const contentEl = viewAnnouncementModalEl.querySelector('#viewAnnouncementModalContent');
                
                if (titleEl) titleEl.textContent = title;
                if (contentEl) contentEl.innerHTML = content.replace(/\n/g, '<br>');
                
                // Format and set date/time
                if (datetime) {
                    const dateObj = new Date(datetime);
                    const dateEl = viewAnnouncementModalEl.querySelector('#viewAnnouncementModalDate');
                    const timeEl = viewAnnouncementModalEl.querySelector('#viewAnnouncementModalTime');
                    
                    if (dateEl && timeEl) {
                        const formattedDate = dateObj.toLocaleDateString('en-US', { 
                            month: 'long', 
                            day: 'numeric', 
                            year: 'numeric'
                        });
                        const formattedTime = dateObj.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit'
                        });
                        
                        dateEl.textContent = formattedDate;
                        timeEl.textContent = formattedTime;
                    }
                }
                
                console.log('Showing modal...'); // Debug log
                viewAnnouncementModal.show();
                
                // Check if content needs "See More" functionality
                setTimeout(() => {
                    checkContentHeight();
                }, 100);
            });
        });
        

    } else {
        console.error('Modal element or Bootstrap not found!'); // Debug log
    }

    // Prevent dropdown clicks from triggering announcement modal
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    document.querySelectorAll('.event-action-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('Dropdown button clicked'); // Debug log
        });
    });
    
    // Also ensure dropdown menus work properly
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Initialize Bootstrap dropdowns manually
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(dropdownToggle => {
        if (window.bootstrap && bootstrap.Dropdown) {
            new bootstrap.Dropdown(dropdownToggle);
        }
    });
    
    // Add click event to dropdown toggles to ensure they work
    document.querySelectorAll('.event-action-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Find the dropdown menu
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                // Toggle the dropdown manually if Bootstrap doesn't work
                if (dropdownMenu.classList.contains('show')) {
                    dropdownMenu.classList.remove('show');
                } else {
                    // Hide all other dropdowns first
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                    dropdownMenu.classList.add('show');
                }
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

});

// Toggle comments visibility within modal
function toggleComments() {
    const commentsSection = document.getElementById('commentsSection');
    const toggleBtn = document.getElementById('toggleCommentsBtn');
    
    if (commentsSection && toggleBtn) {
        if (commentsSection.classList.contains('d-none')) {
            // Show comments
            commentsSection.classList.remove('d-none');
            toggleBtn.innerHTML = '<i class="fa-solid fa-comment me-1"></i> Comments <span class="badge bg-secondary text-white ms-1">3</span>';
            toggleBtn.style.background = '#000080';
            toggleBtn.style.color = 'white';
            toggleBtn.style.borderColor = '#000080';
        } else {
            // Hide comments
            commentsSection.classList.add('d-none');
            toggleBtn.innerHTML = '<i class="fa-solid fa-comment me-1"></i> Comments <span class="badge bg-light text-dark ms-1">3</span>';
            toggleBtn.style.background = 'transparent';
            toggleBtn.style.color = '#000';
            toggleBtn.style.borderColor = '#000';
        }
    }
}

// Check if content height exceeds container and show "See More" button
function checkContentHeight() {
    const contentText = document.getElementById('viewAnnouncementModalContent');
    const seeMoreBtn = document.getElementById('seeMoreBtn');
    
    if (contentText && seeMoreBtn) {
        // Temporarily remove height restriction to measure full content
        contentText.style.maxHeight = 'none';
        const fullHeight = contentText.scrollHeight;
        contentText.style.maxHeight = '200px';
        
        // Show "See More" button if content is taller than 200px
        if (fullHeight > 200) {
            seeMoreBtn.classList.remove('d-none');
        } else {
            seeMoreBtn.classList.add('d-none');
        }
    }
}

// Toggle "See More" / "See Less" functionality
function toggleSeeMore() {
    const contentText = document.getElementById('viewAnnouncementModalContent');
    const seeMoreBtn = document.getElementById('seeMoreBtn');
    const seeMoreIcon = document.getElementById('seeMoreIcon');
    
    if (contentText && seeMoreBtn && seeMoreIcon) {
        const isExpanded = contentText.style.maxHeight === 'none' || contentText.style.maxHeight === '';
        
        if (isExpanded) {
            // Collapse content
            contentText.style.maxHeight = '200px';
            seeMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-down me-1" id="seeMoreIcon"></i> See More';
            seeMoreBtn.style.color = '#0d6efd';
        } else {
            // Expand content
            contentText.style.maxHeight = 'none';
            seeMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-up me-1" id="seeMoreIcon"></i> See Less';
            seeMoreBtn.style.color = '#6c757d';
        }
    }
}
</script>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="color: #000080; font-weight: bold;">Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <i class="fa-solid fa-right-from-bracket" style="font-size: 48px; color: #000080; margin-bottom: 1rem;"></i>
                </div>
                <p class="mb-4" style="color: #2c2c2c; font-size: 16px;">Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-logout-yes" onclick="window.location.href='afprotech_logout.php'" style="background: #000080; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">Yes</button>
                <button type="button" class="btn btn-logout-no" data-bs-dismiss="modal" style="background: #6c757d; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">No</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Search Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('announcementsSearchInput');
    const searchForm = document.querySelector('.announcements-search-form');
    const announcementItems = document.querySelectorAll('.announcement-view-item');
    
    // Handle form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Handle real-time search as user types
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            performSearch();
        });
    }
    
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        announcementItems.forEach(item => {
            const title = item.getAttribute('data-title')?.toLowerCase() || '';
            const content = item.getAttribute('data-content')?.toLowerCase() || '';
            const parent = item.closest('.list-group-item');
            
            if (searchTerm === '' || 
                title.includes(searchTerm) || 
                content.includes(searchTerm)) {
                if (parent) parent.style.display = '';
            } else {
                if (parent) parent.style.display = 'none';
            }
        });
    }
});
</script>

</body>
</html>

