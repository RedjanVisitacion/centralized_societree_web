<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send') {
        $stmt = $pdo->prepare("INSERT INTO arts_messages (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'notification', 0)");
        $stmt->execute([$_POST['user_id'], $_POST['title'], $_POST['message']]);
        $message = 'Message sent successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_messages WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Message deleted!';
        $messageType = 'success';
    }
}

$messages = $pdo->query("SELECT * FROM arts_messages ORDER BY id DESC")->fetchAll();

// Get list of students for dropdown
$students = $pdo->query("SELECT id_number, first_name, last_name FROM student ORDER BY last_name, first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ARCU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
            <a class="nav-link" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link active" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-envelope me-2"></i>Messages Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendModal">
                <i class="bi bi-plus-lg me-1"></i> Send Message
            </button>
        </div>

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
                                <th>User ID</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Read</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= htmlspecialchars($msg['user_id']) ?></td>
                                <td><?= htmlspecialchars($msg['title']) ?></td>
                                <td><?= htmlspecialchars(substr($msg['message'] ?? '', 0, 50)) ?>...</td>
                                <td>
                                    <span class="badge bg-<?= $msg['is_read'] ? 'success' : 'warning' ?>">
                                        <?= $msg['is_read'] ? 'Read' : 'Unread' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewMessage(<?= htmlspecialchars(json_encode($msg)) ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this message?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
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

    <!-- Send Message Modal -->
    <div class="modal fade" id="sendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="send">
                    <div class="modal-header"><h5 class="modal-title">Send Message to Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Student *</label>
                            <select name="user_id" id="studentSelect" class="form-select" required>
                                <option value="">-- Type to search or select a student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= htmlspecialchars($student['id_number']) ?>">
                                        <?= htmlspecialchars($student['id_number']) ?> - <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Type student ID or name to search, or select from the list</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Message title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="4" required placeholder="Type your message here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Message Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>To (User ID):</strong> <span id="view_user"></span></p>
                    <p><strong>Title:</strong> <span id="view_title"></span></p>
                    <p><strong>Message:</strong></p>
                    <p id="view_message" class="bg-light p-3 rounded"></p>
                    <p><strong>Status:</strong> <span id="view_status"></span></p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for searchable dropdown
        $(document).ready(function() {
            $('#studentSelect').select2({
                theme: 'bootstrap-5',
                placeholder: 'Type to search student ID or name...',
                allowClear: true,
                tags: true,
                dropdownParent: $('#sendModal')
            });
        });
        
        function viewMessage(msg) {
            document.getElementById('view_user').textContent = msg.user_id;
            document.getElementById('view_title').textContent = msg.title;
            document.getElementById('view_message').textContent = msg.message || '';
            document.getElementById('view_status').textContent = msg.is_read ? 'Read' : 'Unread';
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
    </script>
</body>
</html>
