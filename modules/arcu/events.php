<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Handle Add Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO arts_events (title, description, date, time, location, club, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['date'],
            $_POST['time'],
            $_POST['location'],
            $_POST['club'] ?: null,
            $_POST['status']
        ]);
        $message = 'Event added successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE arts_events SET title=?, description=?, date=?, time=?, location=?, club=?, status=? WHERE id=?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['date'],
            $_POST['time'],
            $_POST['location'],
            $_POST['club'] ?: null,
            $_POST['status'],
            $_POST['id']
        ]);
        $message = 'Event updated successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_events WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Event deleted successfully!';
        $messageType = 'success';
    }
}

// Get all events
$events = $pdo->query("SELECT * FROM arts_events ORDER BY date DESC")->fetchAll();

// Get clubs for dropdown
$clubs = $pdo->query("SELECT * FROM arts_clubs ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - ARCU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        * { font-family: "Oswald", sans-serif; }
        body { background-color: #f8f9fa; }
        .sidebar {
            background: #911c2c;
            min-height: 100vh;
            position: fixed;
            width: 250px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left-color: #fff;
        }
        .sidebar .nav-link i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h5 { color: white; margin: 0; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h5><i class="bi bi-palette me-2"></i>ARCU Admin</h5>
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a class="nav-link active" href="events.php"><i class="bi bi-calendar-event"></i> Events</a>
            <a class="nav-link" href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a class="nav-link" href="clubs.php"><i class="bi bi-people"></i> Clubs</a>
            <a class="nav-link" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-calendar-event me-2"></i>Events Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> Add Event
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Club</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?= $event['id'] ?></td>
                                <td><?= htmlspecialchars($event['title']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($event['club'] ?? 'General') ?></span></td>
                                <td><?= $event['date'] ?></td>
                                <td><?= $event['time'] ?></td>
                                <td><?= htmlspecialchars($event['location'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $event['status'] == 'upcoming' ? 'primary' : ($event['status'] == 'ongoing' ? 'success' : 'secondary') ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editEvent(<?= htmlspecialchars(json_encode($event)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this event?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $event['id'] ?>">
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
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="time" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Club</label>
                            <select name="club" class="form-select">
                                <option value="">-- General (All Clubs) --</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?= htmlspecialchars($club['name']) ?>"><?= htmlspecialchars($club['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select a specific club or leave as General for all clubs</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" name="date" id="edit_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="time" id="edit_time" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Club</label>
                            <select name="club" id="edit_club" class="form-select">
                                <option value="">-- General (All Clubs) --</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?= htmlspecialchars($club['name']) ?>"><?= htmlspecialchars($club['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEvent(event) {
            document.getElementById('edit_id').value = event.id;
            document.getElementById('edit_title').value = event.title;
            document.getElementById('edit_description').value = event.description || '';
            document.getElementById('edit_date').value = event.date;
            document.getElementById('edit_time').value = event.time || '';
            document.getElementById('edit_location').value = event.location || '';
            document.getElementById('edit_club').value = event.club || '';
            document.getElementById('edit_status').value = event.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>
