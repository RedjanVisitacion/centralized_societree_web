<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO arts_announcements (title, body, club, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['body'], $_POST['club'] ?: null, $_POST['status']]);
        $message = 'Announcement added successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE arts_announcements SET title=?, body=?, club=?, status=? WHERE id=?");
        $stmt->execute([$_POST['title'], $_POST['body'], $_POST['club'] ?: null, $_POST['status'], $_POST['id']]);
        $message = 'Announcement updated successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_announcements WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Announcement deleted successfully!';
        $messageType = 'success';
    }
}

$announcements = $pdo->query("SELECT * FROM arts_announcements ORDER BY created_at DESC")->fetchAll();
$clubs = $pdo->query("SELECT * FROM arts_clubs ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ARCU Admin</title>
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
            <a class="nav-link active" href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a class="nav-link" href="clubs.php"><i class="bi bi-people"></i> Clubs</a>
            <a class="nav-link" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-megaphone me-2"></i>Announcements Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> Add Announcement
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
                            <tr><th>ID</th><th>Title</th><th>Club</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($announcements as $ann): ?>
                            <tr>
                                <td><?= $ann['id'] ?></td>
                                <td><?= htmlspecialchars($ann['title']) ?></td>
                                <td><?= htmlspecialchars($ann['club'] ?? 'General') ?></td>
                                <td><span class="badge bg-<?= $ann['status'] == 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($ann['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($ann['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editItem(<?= htmlspecialchars(json_encode($ann)) ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this announcement?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $ann['id'] ?>">
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

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header"><h5 class="modal-title">Add New Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Body *</label><textarea name="body" class="form-control" rows="5" required></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Club</label>
                                <select name="club" class="form-select">
                                    <option value="">-- General ARCU --</option>
                                    <?php foreach ($clubs as $club): ?><option value="<?= htmlspecialchars($club['name']) ?>"><?= htmlspecialchars($club['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select"><option value="active">Active</option><option value="archived">Archived</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Announcement</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" id="edit_title" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Body *</label><textarea name="body" id="edit_body" class="form-control" rows="5" required></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Club</label>
                                <select name="club" id="edit_club" class="form-select">
                                    <option value="">-- General ARCU --</option>
                                    <?php foreach ($clubs as $club): ?><option value="<?= htmlspecialchars($club['name']) ?>"><?= htmlspecialchars($club['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select"><option value="active">Active</option><option value="archived">Archived</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editItem(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_title').value = item.title;
            document.getElementById('edit_body').value = item.body;
            document.getElementById('edit_club').value = item.club || '';
            document.getElementById('edit_status').value = item.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>
