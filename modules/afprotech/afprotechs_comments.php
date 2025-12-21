<?php
// Include database connection
$db_path = __DIR__ . '/../../db_connection.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    // Try alternative path
    require_once dirname(dirname(__DIR__)) . '/db_connection.php';
}

// Check if connection exists
if (!isset($conn) || $conn->connect_error) {
    die('Database connection failed: ' . ($conn->connect_error ?? 'Connection not established'));
}

// Get announcement ID from URL parameter
$announcement_id = $_GET['id'] ?? null;

if (!$announcement_id) {
    header('Location: afprotechs_Announcement.php');
    exit;
}

// Fetch the specific announcement
$sql = "SELECT * FROM afprotechs_announcements WHERE announcement_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: afprotechs_Announcement.php');
    exit;
}

$announcement = $result->fetch_assoc();
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
    <a href="afprotechs_Announcement.php" class="active"><i class="fa-solid fa-bullhorn"></i><span>Announcement</span></a>
    <a href="afprotechs_records.php"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="afprotechs_products.php"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="#"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">

    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">
        <div class="dashboard-search">
            <form>
                <div class="input-group">
                    <input class="form-control" type="search" placeholder="Search">
                    <button class="btn btn-outline-secondary"><i class="fa fa-search"></i></button>
                </div>
            </form>
        </div>

        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <img src="../../assets/img/profile.png"
                 alt="Profile"
                 class="rounded-circle dashboard-profile-avatar"
                 style="width:40px;height:40px;object-fit:cover;">
            <span class="fw-semibold dashboard-admin-name">
                Harold Coyoca  <br>
                <span class="dashboard-role">ADMIN</span>
            </span>
        </div>
    </div>

    <!-- Comments Page Content -->
    <div class="comments-page-container">
        
        <!-- Back Button -->
        <div class="d-flex align-items-center mb-4">
            <a href="afprotechs_Announcement.php" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Announcements
            </a>
        </div>

        <!-- Announcement Summary -->
        <div class="announcement-summary mb-4 p-4" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
            <h5 class="fw-bold mb-2" style="color: #000080;"><?= htmlspecialchars($announcement['announcement_title']) ?></h5>
            <div class="text-muted mb-2">
                <i class="fa-solid fa-calendar-days me-1"></i>
                <?= date('F d, Y g:i A', strtotime($announcement['announcement_datetime'])) ?>
            </div>
            <p class="mb-0" style="color: #6c757d; font-size: 14px;">
                <?= strlen($announcement['announcement_content']) > 150 
                    ? substr(htmlspecialchars($announcement['announcement_content']), 0, 150) . '...' 
                    : htmlspecialchars($announcement['announcement_content']) ?>
            </p>
        </div>

        <!-- Comments Section -->
        <div class="comments-full-section p-4" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
            
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 class="mb-0 fw-semibold" style="color: #000080;">
                    <i class="fa-solid fa-comments me-2"></i>
                    Comments <span class="badge bg-light text-dark ms-2">3</span>
                </h5>
            </div>

            <!-- Comments List -->
            <div class="comments-list">
                
                <!-- Sample Comment 1 -->
                <div class="comment-item mb-4 pb-4" style="border-bottom: 1px solid #f0f0f0;">
                    <div class="w-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-semibold" style="font-size: 15px; color: #2c2c2c;">John Kenneth</span>
                            <span class="text-muted" style="font-size: 13px;">2 hours ago</span>
                        </div>
                        <p class="mb-3" style="font-size: 15px; line-height: 1.5; color: #4a4a4a;">Great announcement! Very informative and well-structured. Looking forward to more updates like this. The information provided is really helpful for our organization and I appreciate the detailed explanation.</p>
                        <div class="comment-actions d-flex align-items-center gap-4">
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-thumbs-up"></i> Like (2)
                            </button>
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-comment"></i> Reply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sample Comment 2 -->
                <div class="comment-item mb-4 pb-4" style="border-bottom: 1px solid #f0f0f0;">
                    <div class="w-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-semibold" style="font-size: 15px; color: #2c2c2c;">Maria Santos</span>
                            <span class="text-muted" style="font-size: 13px;">5 hours ago</span>
                        </div>
                        <p class="mb-3" style="font-size: 15px; line-height: 1.5; color: #4a4a4a;">Thanks for sharing this important information. Will there be a follow-up session? I'm really interested in learning more about this topic and would love to participate in any upcoming activities.</p>
                        <div class="comment-actions d-flex align-items-center gap-4">
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-thumbs-up"></i> Like (1)
                            </button>
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-comment"></i> Reply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sample Comment 3 (Admin) -->
                <div class="comment-item mb-4">
                    <div class="w-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-semibold" style="font-size: 15px; color: #2c2c2c;">Harold Coyoca</span>
                            <span class="badge" style="background: linear-gradient(135deg, #000080 0%, #1a4fa0 100%); font-size: 10px;">ADMIN</span>
                            <span class="text-muted" style="font-size: 13px;">1 day ago</span>
                        </div>
                        <p class="mb-3" style="font-size: 15px; line-height: 1.5; color: #4a4a4a;">Thank you all for your engagement! We'll be posting more updates soon. Your feedback is valuable to us and helps us improve our communication. Stay tuned for more announcements and feel free to reach out if you have any questions.</p>
                        <div class="comment-actions d-flex align-items-center gap-4">
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-thumbs-up"></i> Like (5)
                            </button>
                            <button class="btn btn-sm btn-link p-0 text-muted d-flex align-items-center gap-1" style="font-size: 13px; text-decoration: none;">
                                <i class="fa-regular fa-comment"></i> Reply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- No More Comments Message -->
                <div class="text-center py-4">
                    <div class="text-muted">
                        <i class="fa-solid fa-comments fa-2x mb-2" style="color: #e9ecef;"></i>
                        <p class="mb-0" style="font-size: 14px;">End of comments</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>