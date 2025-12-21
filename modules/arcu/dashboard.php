<?php
require_once 'config.php';

// Get counts for dashboard
$eventsCount = $pdo->query("SELECT COUNT(*) FROM arts_events")->fetchColumn();
$clubsCount = $pdo->query("SELECT COUNT(*) FROM arts_clubs")->fetchColumn();
$announcementsCount = $pdo->query("SELECT COUNT(*) FROM arts_announcements")->fetchColumn();
$applicationsCount = $pdo->query("SELECT COUNT(*) FROM arts_club_applications WHERE status = 'pending'")->fetchColumn();
$feedbackCount = $pdo->query("SELECT COUNT(*) FROM arts_feedback")->fetchColumn();

// Get recent activities
$recentAnnouncements = $pdo->query("SELECT * FROM arts_announcements ORDER BY created_at DESC LIMIT 3")->fetchAll();
$recentApplications = $pdo->query("SELECT * FROM arts_club_applications ORDER BY id DESC LIMIT 3")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ARCU Admin</title>
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
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.events { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.clubs { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.announcements { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.applications { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.feedback { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card h3 { font-size: 2.5rem; margin: 0; }
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
            <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a class="nav-link" href="events.php"><i class="bi bi-calendar-event"></i> Events</a>
            <a class="nav-link" href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
            <a class="nav-link" href="clubs.php"><i class="bi bi-people"></i> Clubs</a>
            <a class="nav-link" href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
            <a class="nav-link" href="feedback.php"><i class="bi bi-chat-dots"></i> Feedback</a>
            <a class="nav-link" href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 class="mb-4">Dashboard</h2>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-lg">
                <div class="stat-card events">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?= $eventsCount ?></h3>
                            <p class="mb-0">Events</p>
                        </div>
                        <i class="bi bi-calendar-event" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg">
                <div class="stat-card clubs">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?= $clubsCount ?></h3>
                            <p class="mb-0">Clubs</p>
                        </div>
                        <i class="bi bi-people" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg">
                <div class="stat-card announcements">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?= $announcementsCount ?></h3>
                            <p class="mb-0">Announcements</p>
                        </div>
                        <i class="bi bi-megaphone" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg">
                <div class="stat-card applications">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?= $applicationsCount ?></h3>
                            <p class="mb-0">Pending Applications</p>
                        </div>
                        <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg">
                <div class="stat-card feedback">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?= $feedbackCount ?></h3>
                            <p class="mb-0">Feedback</p>
                        </div>
                        <i class="bi bi-chat-dots" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentAnnouncements)): ?>
                            <p class="text-muted">No announcements yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentAnnouncements as $ann): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                    <br><small class="text-muted"><?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Recent Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentApplications)): ?>
                            <p class="text-muted">No applications yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentApplications as $app): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($app['full_name']) ?></strong>
                                    <span class="badge bg-<?= $app['status'] == 'pending' ? 'warning' : ($app['status'] == 'approved' ? 'success' : 'danger') ?>"><?= ucfirst($app['status']) ?></span>
                                    <br><small class="text-muted">ID: <?= $app['id'] ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
