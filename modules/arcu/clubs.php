<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO arts_clubs (name, description, members_count, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['members_count'] ?: 0, $_POST['status']]);
        $message = 'Club added successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE arts_clubs SET name=?, description=?, members_count=?, status=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['members_count'] ?: 0, $_POST['status'], $_POST['id']]);
        $message = 'Club updated successfully!';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_clubs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Club deleted successfully!';
        $messageType = 'success';
    }
}

$clubs = $pdo->query("SELECT * FROM arts_clubs ORDER BY name")->fetchAll();

// Get announcements and events for each club
$clubAnnouncements = [];
$clubEvents = [];
foreach ($clubs as $club) {
    $clubName = $club['name'];
    
    // Get announcements for this club
    $annStmt = $pdo->prepare("SELECT * FROM arts_announcements WHERE club = ? ORDER BY id DESC");
    $annStmt->execute([$clubName]);
    $clubAnnouncements[$club['id']] = $annStmt->fetchAll();
    
    // Get events for this club
    $evtStmt = $pdo->prepare("SELECT * FROM arts_events WHERE location LIKE ? OR title LIKE ? ORDER BY date DESC");
    $evtStmt->execute(["%$clubName%", "%$clubName%"]);
    $clubEvents[$club['id']] = $evtStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubs - ARCU Admin</title>
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
        .club-card { transition: transform 0.2s; }
        .club-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h5><i class="bi bi-palette me-2"></i>ARCU Admin</h5></div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a class="nav-link" href="events.php"><i class="bi bi-calendar-event"></i> Events</a>
            <a class="nav-link" href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a class="nav-link active" href="clubs.php"><i class="bi bi-people"></i> Clubs</a>
            <a class="nav-link" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i>Clubs Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> Add Club
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($clubs as $club): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card club-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title"><?= htmlspecialchars($club['name']) ?></h5>
                            <span class="badge bg-<?= $club['status'] == 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($club['status']) ?></span>
                        </div>
                        <p class="card-text text-muted"><?= htmlspecialchars($club['description'] ?? 'No description') ?></p>
                        <p class="mb-2"><i class="bi bi-people me-1"></i> <?= $club['members_count'] ?> members</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-info" onclick="viewAnnouncements(<?= $club['id'] ?>, '<?= htmlspecialchars($club['name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-megaphone"></i> Announcements
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="viewEvents(<?= $club['id'] ?>, '<?= htmlspecialchars($club['name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-calendar-event"></i> Events
                            </button>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button class="btn btn-sm btn-warning" onclick="editItem(<?= htmlspecialchars(json_encode($club)) ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this club?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $club['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header"><h5 class="modal-title">Add New Club</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Club Name *</label><input type="text" name="name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Members Count</label><input type="number" name="members_count" class="form-control" value="0" min="0"></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Club</button></div>
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
                    <div class="modal-header"><h5 class="modal-title">Edit Club</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Club Name *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-control" rows="3"></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Members Count</label><input type="number" name="members_count" id="edit_members_count" class="form-control" min="0"></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Announcements Modal -->
    <div class="modal fade" id="announcementsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>Announcements - <span id="ann_club_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="announcements_list"></div>
                </div>
                <div class="modal-footer">
                    <a href="announcements.php" class="btn btn-info"><i class="bi bi-plus-lg me-1"></i> Add New Announcement</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Events Modal -->
    <div class="modal fade" id="eventsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Events - <span id="evt_club_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="events_list"></div>
                </div>
                <div class="modal-footer">
                    <a href="events.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add New Event</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store announcements and events data
        const clubAnnouncements = <?= json_encode($clubAnnouncements) ?>;
        const clubEvents = <?= json_encode($clubEvents) ?>;
        
        function editItem(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_members_count').value = item.members_count || 0;
            document.getElementById('edit_status').value = item.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        function viewAnnouncements(clubId, clubName) {
            document.getElementById('ann_club_name').textContent = clubName;
            const announcements = clubAnnouncements[clubId] || [];
            let html = '';
            
            if (announcements.length === 0) {
                html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No announcements found for this club.</div>';
            } else {
                html = '<div class="list-group">';
                announcements.forEach(ann => {
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1">${escapeHtml(ann.title)}</h6>
                                <small class="text-muted">${ann.date || ''}</small>
                            </div>
                            <p class="mb-1 text-muted">${escapeHtml(ann.body || ann.content || '')}</p>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            document.getElementById('announcements_list').innerHTML = html;
            new bootstrap.Modal(document.getElementById('announcementsModal')).show();
        }
        
        function viewEvents(clubId, clubName) {
            document.getElementById('evt_club_name').textContent = clubName;
            const events = clubEvents[clubId] || [];
            let html = '';
            
            if (events.length === 0) {
                html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No events found for this club.</div>';
            } else {
                html = '<div class="list-group">';
                events.forEach(evt => {
                    const statusBadge = evt.status === 'upcoming' ? 'primary' : (evt.status === 'ongoing' ? 'success' : 'secondary');
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1">${escapeHtml(evt.title)}</h6>
                                <span class="badge bg-${statusBadge}">${evt.status || 'upcoming'}</span>
                            </div>
                            <p class="mb-1">${escapeHtml(evt.description || '')}</p>
                            <small class="text-muted">
                                <i class="bi bi-calendar me-1"></i>${evt.date || ''} 
                                ${evt.time ? '<i class="bi bi-clock ms-2 me-1"></i>' + evt.time : ''}
                                ${evt.location ? '<i class="bi bi-geo-alt ms-2 me-1"></i>' + escapeHtml(evt.location) : ''}
                            </small>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            document.getElementById('events_list').innerHTML = html;
            new bootstrap.Modal(document.getElementById('eventsModal')).show();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
