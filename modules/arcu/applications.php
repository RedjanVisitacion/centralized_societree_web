<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        $appId = $_POST['id'];
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        
        // Update application status
        $stmt = $pdo->prepare("UPDATE arts_club_applications SET status=? WHERE id=?");
        $stmt->execute([$newStatus, $appId]);
        
        // Get application details to send message
        $appStmt = $pdo->prepare("SELECT a.*, c.name as club_name FROM arts_club_applications a LEFT JOIN arts_clubs c ON a.club_id = c.id WHERE a.id = ?");
        $appStmt->execute([$appId]);
        $appData = $appStmt->fetch();
        
        if ($appData) {
            // Create message for the student
            $msgTitle = $newStatus == 'approved' ? 'Application Approved!' : 'Application Update';
            $msgBody = $newStatus == 'approved' 
                ? "Congratulations! Your application to join {$appData['club_name']} has been approved. Welcome to the club!"
                : "Your application to join {$appData['club_name']} has been {$newStatus}.";
            
            if ($comment) {
                $msgBody .= "\n\nAdmin Comment: " . $comment;
            }
            
            $msgStmt = $pdo->prepare("INSERT INTO arts_messages (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'notification', 0)");
            $msgStmt->execute([$appData['user_id'], $msgTitle, $msgBody]);
        }
        
        $message = 'Application status updated and notification sent!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_club_applications WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Application deleted!';
        $messageType = 'success';
    }
}

$applications = $pdo->query("
    SELECT a.*, c.name as club_name 
    FROM arts_club_applications a 
    LEFT JOIN arts_clubs c ON a.club_id = c.id 
    ORDER BY a.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - ARCU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        * { font-family: "Oswald", sans-serif; }
        body { background-color: #f8f9fa; }
        .sidebar { background: #911c2c; min-height: 100vh; position: fixed; width: 250px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left-color: #fff; }
        .sidebar .nav-link i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h5 { color: white; margin: 0; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h5><i class="bi bi-palette me-2"></i>ARCU Admin</h5></div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a class="nav-link" href="events.php"><i class="bi bi-calendar-event"></i> Events</a>
            <a class="nav-link" href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a class="nav-link" href="clubs.php"><i class="bi bi-people"></i> Clubs</a>
            <a class="nav-link active" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-file-earmark-text me-2"></i>Club Applications</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Club</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?= $app['id'] ?></td>
                                <td><?= htmlspecialchars($app['full_name']) ?></td>
                                <td><?= htmlspecialchars($app['email']) ?></td>
                                <td><?= htmlspecialchars($app['club_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($app['phone'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $app['status'] == 'pending' ? 'warning' : ($app['status'] == 'approved' ? 'success' : 'danger') ?>">
                                        <?= ucfirst($app['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?= htmlspecialchars(json_encode($app)) ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($app['status'] == 'pending'): ?>
                                    <button class="btn btn-sm btn-success" onclick="showStatusModal(<?= $app['id'] ?>, 'approved')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="showStatusModal(<?= $app['id'] ?>, 'rejected')" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this application?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Application Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Full Name:</strong> <span id="view_name"></span></p>
                    <p><strong>Email:</strong> <span id="view_email"></span></p>
                    <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                    <p><strong>Club:</strong> <span id="view_club"></span></p>
                    <p><strong>Reason:</strong></p>
                    <p id="view_reason" class="bg-light p-2 rounded"></p>
                    <p><strong>Video/Audition Link:</strong></p>
                    <p id="view_video_url" class="bg-light p-2 rounded"></p>
                    <p><strong>Status:</strong> <span id="view_status"></span></p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="status_app_id">
                    <input type="hidden" name="status" id="status_value">
                    <div class="modal-header">
                        <h5 class="modal-title" id="status_modal_title">Update Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="status_message"></p>
                        <div class="mb-3">
                            <label class="form-label">Comment (optional - will be sent to student)</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Add a message for the student..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="status_btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(app) {
            document.getElementById('view_name').textContent = app.full_name;
            document.getElementById('view_email').textContent = app.email;
            document.getElementById('view_phone').textContent = app.phone || 'N/A';
            document.getElementById('view_club').textContent = app.club_name || 'Unknown';
            document.getElementById('view_reason').textContent = app.reason || 'No reason provided';
            
            // Handle video URL
            var videoContainer = document.getElementById('view_video_url');
            if (app.video_url && app.video_url.trim() !== '') {
                videoContainer.innerHTML = '<a href="' + app.video_url + '" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-play-circle me-1"></i>View Video/Link</a>';
            } else {
                videoContainer.textContent = 'No video provided';
            }
            
            document.getElementById('view_status').textContent = app.status;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
        
        function showStatusModal(appId, status) {
            document.getElementById('status_app_id').value = appId;
            document.getElementById('status_value').value = status;
            
            if (status === 'approved') {
                document.getElementById('status_modal_title').textContent = 'Approve Application';
                document.getElementById('status_message').textContent = 'Are you sure you want to approve this application? A notification will be sent to the student.';
                document.getElementById('status_btn').className = 'btn btn-success';
                document.getElementById('status_btn').textContent = 'Approve';
            } else {
                document.getElementById('status_modal_title').textContent = 'Reject Application';
                document.getElementById('status_message').textContent = 'Are you sure you want to reject this application? A notification will be sent to the student.';
                document.getElementById('status_btn').className = 'btn btn-danger';
                document.getElementById('status_btn').textContent = 'Reject';
            }
            
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
    </script>
</body>
</html>
