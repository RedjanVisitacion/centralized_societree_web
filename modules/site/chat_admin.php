<?php
// Chat Engine Admin Dashboard
require_once(__DIR__ . '/../../db_connection.php');
require_once(__DIR__ . '/../../backend/chat_engine.php');

$engine = new ChatEngine();

// Handle admin actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'broadcast_notification':
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';
            
            if ($message) {
                // Get all students
                $stmt = $conn->prepare("SELECT id_number FROM student");
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $engine->createNotification($row['id_number'], $message, $type);
                }
                
                $success_message = "Notification sent to all students!";
            }
            break;
            
        case 'add_auto_response':
            $pattern = $_POST['pattern'] ?? '';
            $response = $_POST['response'] ?? '';
            $category = $_POST['category'] ?? 'general';
            
            if ($pattern && $response) {
                $stmt = $conn->prepare("INSERT INTO chat_auto_responses (trigger_pattern, response_text, category) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $pattern, $response, $category);
                $stmt->execute();
                $success_message = "Auto-response added successfully!";
            }
            break;
    }
}

// Get analytics data
$analytics = $engine->getMessageAnalytics(30);
$topStats = $engine->getChatStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Admin - SITE</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        * { font-family: "Oswald", sans-serif; font-weight: 500; font-style: normal; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .analytics-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="../../assets/logo/site_2.png" alt="SITE" height="30" class="me-2">
                Chat Engine Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="site_chat.php">Back to Chat</a>
                <a class="nav-link" href="site_dashboard.php">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Statistics Overview -->
            <div class="col-md-8">
                <div class="stats-card">
                    <h4><i class="bi bi-graph-up me-2"></i>Chat Analytics (Last 30 Days)</h4>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <h2><?php echo array_sum(array_column($analytics, 'message_count')); ?></h2>
                            <small>Total Messages</small>
                        </div>
                        <div class="col-md-3">
                            <h2><?php echo count($topStats); ?></h2>
                            <small>Active Users</small>
                        </div>
                        <div class="col-md-3">
                            <h2><?php echo array_sum(array_column($analytics, 'flagged_count')); ?></h2>
                            <small>Flagged Messages</small>
                        </div>
                        <div class="col-md-3">
                            <h2><?php echo number_format(array_sum(array_column($analytics, 'avg_sentiment')) / max(count($analytics), 1), 2); ?></h2>
                            <small>Avg Sentiment</small>
                        </div>
                    </div>
                </div>

                <!-- Top Active Users -->
                <div class="analytics-card">
                    <h5><i class="bi bi-people me-2"></i>Most Active Users</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Messages Sent</th>
                                    <th>Messages Received</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($topStats, 0, 10) as $stat): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars(($stat['first_name'] ?? '') . ' ' . ($stat['last_name'] ?? '')); ?>
                                            <br><small class="text-muted">ID: <?php echo $stat['student_id']; ?></small>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo $stat['messages_sent']; ?></span></td>
                                        <td><span class="badge bg-success"><?php echo $stat['messages_received']; ?></span></td>
                                        <td><small><?php echo date('M j, Y H:i', strtotime($stat['last_active'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Admin Controls -->
            <div class="col-md-4">
                <!-- Broadcast Notification -->
                <div class="analytics-card">
                    <h5><i class="bi bi-megaphone me-2"></i>Broadcast Notification</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="broadcast_notification">
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="3" required placeholder="Enter notification message..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send me-2"></i>Send to All
                        </button>
                    </form>
                </div>

                <!-- Add Auto Response -->
                <div class="analytics-card">
                    <h5><i class="bi bi-robot me-2"></i>Add Auto Response</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_auto_response">
                        <div class="mb-3">
                            <label class="form-label">Trigger Pattern</label>
                            <input type="text" class="form-control" name="pattern" required placeholder="e.g., hello, help, event">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Response</label>
                            <textarea class="form-control" name="response" rows="2" required placeholder="Bot response message..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="greeting">Greeting</option>
                                <option value="help">Help</option>
                                <option value="events">Events</option>
                                <option value="services">Services</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle me-2"></i>Add Response
                        </button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="analytics-card">
                    <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="sendWelcomeMessage()">
                            <i class="bi bi-hand-thumbs-up me-2"></i>Send Welcome
                        </button>
                        <button class="btn btn-outline-info" onclick="sendEventReminder()">
                            <i class="bi bi-calendar-event me-2"></i>Event Reminder
                        </button>
                        <button class="btn btn-outline-warning" onclick="sendMaintenanceNotice()">
                            <i class="bi bi-tools me-2"></i>Maintenance Notice
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Analytics Chart -->
        <div class="row">
            <div class="col-12">
                <div class="analytics-card">
                    <h5><i class="bi bi-bar-chart me-2"></i>Daily Message Activity</h5>
                    <canvas id="analyticsChart" width="400" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Analytics Chart
        const ctx = document.getElementById('analyticsChart').getContext('2d');
        const analyticsData = <?php echo json_encode($analytics); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: analyticsData.map(d => new Date(d.date).toLocaleDateString()),
                datasets: [{
                    label: 'Messages',
                    data: analyticsData.map(d => d.message_count),
                    borderColor: '#20a8f8',
                    backgroundColor: 'rgba(32, 168, 248, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Flagged',
                    data: analyticsData.map(d => d.flagged_count),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Quick action functions
        function sendWelcomeMessage() {
            sendQuickNotification('Welcome to SITE Chat! Feel free to ask questions and connect with fellow students.', 'info');
        }

        function sendEventReminder() {
            sendQuickNotification('Don\'t forget to check out our upcoming events in the Events section!', 'info');
        }

        function sendMaintenanceNotice() {
            sendQuickNotification('Scheduled maintenance will occur tonight from 11 PM to 1 AM. Chat may be temporarily unavailable.', 'warning');
        }

        function sendQuickNotification(message, type) {
            const form = new FormData();
            form.append('action', 'broadcast_notification');
            form.append('message', message);
            form.append('type', type);

            fetch('', {
                method: 'POST',
                body: form
            }).then(() => {
                location.reload();
            });
        }
    </script>
</body>
</html>