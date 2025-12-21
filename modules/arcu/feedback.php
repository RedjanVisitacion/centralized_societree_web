<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM arts_feedback WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Feedback deleted!';
        $messageType = 'success';
    }
}

$feedbacks = $pdo->query("SELECT * FROM arts_feedback ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - ARCU Admin</title>
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
        .feedback-card { transition: transform 0.2s; }
        .feedback-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .rating-stars { color: #ffc107; }
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
            <a class="nav-link active" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-chat-dots me-2"></i>Student Feedback</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (empty($feedbacks)): ?>
            <div class="alert alert-info">No feedback submitted yet.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($feedbacks as $fb): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card feedback-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($fb['name'] ?? 'Anonymous') ?></h5>
                                <span class="badge bg-<?= ($fb['status'] ?? 'new') == 'new' ? 'primary' : 'secondary' ?>"><?= ucfirst($fb['status'] ?? 'new') ?></span>
                            </div>
                            <?php if (isset($fb['email']) && $fb['email']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($fb['email']) ?></p>
                            <?php endif; ?>
                            <p class="card-text"><?= nl2br(htmlspecialchars($fb['message'])) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">ID: <?= $fb['id'] ?></small>
                                <form method="POST" onsubmit="return confirm('Delete this feedback?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $fb['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
