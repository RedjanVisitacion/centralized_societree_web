<?php
require_once '../../db_connection.php';

// Handle QR code scan attendance
if (isset($_POST['mark_attendance'])) {
    $qr_data = trim($_POST['qr_data']);
    $student_id = (int)$_POST['student_id'];
    $session_type = $_POST['session_type'];
    
    // Parse QR code data to get event ID
    if (preg_match('/PAFE_EVENT_ID:(\d+):/', $qr_data, $matches)) {
        $event_id = (int)$matches[1];
        
        try {
            // Check if event exists and session is not locked
            $stmt = $pdo->prepare("SELECT * FROM pafe_events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch();
            
            if ($event) {
                $session_locked = $session_type === 'morning' ? $event['morning_session_locked'] : $event['afternoon_session_locked'];
                
                if (!$session_locked) {
                    // Check if already attended this session
                    $check_stmt = $pdo->prepare("SELECT id FROM pafe_event_attendance WHERE event_id = ? AND student_id = ? AND session_type = ?");
                    $check_stmt->execute([$event_id, $student_id, $session_type]);
                    
                    if (!$check_stmt->fetch()) {
                        // Mark attendance
                        $insert_stmt = $pdo->prepare("INSERT INTO pafe_event_attendance (event_id, student_id, session_type) VALUES (?, ?, ?)");
                        $insert_stmt->execute([$event_id, $student_id, $session_type]);
                        
                        $success_message = "✓ Attendance marked successfully!";
                        $event_details = [
                            'title' => $event['title'],
                            'date' => date('M d, Y', strtotime($event['event_date'])),
                            'time' => date('h:i A', strtotime($event['event_time'])),
                            'location' => $event['location'],
                            'session' => ucfirst($session_type)
                        ];
                    } else {
                        $error_message = "You have already marked attendance for the " . ucfirst($session_type) . " session of this event.";
                    }
                } else {
                    $error_message = "The " . ucfirst($session_type) . " session is currently locked. Please contact the administrator.";
                }
            } else {
                $error_message = "Invalid event QR code. Please scan a valid PAFE event QR code.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid QR code format. Please scan a PAFE event QR code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Scanner - PAFE</title>
    <link rel="icon" href="../../assets/logo/pafe_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #eea618 0%, #f4c430 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Oswald", sans-serif;
        }
        
        .scanner-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        
        .scanner-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .scanner-header img {
            height: 60px;
            margin-bottom: 15px;
        }
        
        .qr-scanner {
            border: 3px dashed #eea618;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        #qr-video {
            width: 100%;
            max-width: 300px;
            border-radius: 8px;
        }
        
        .manual-entry {
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-header">
            <img src="../../assets/logo/pafe_2.png" alt="PAFE Logo">
            <h3>Event Attendance Scanner</h3>
            <p class="text-muted">Scan QR code to mark your attendance instantly</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="bi bi-check-circle"></i> <?php echo $success_message; ?></h5>
                <?php if (isset($event_details)): ?>
                    <hr>
                    <div class="mb-2"><strong>Event:</strong> <?php echo htmlspecialchars($event_details['title']); ?></div>
                    <div class="mb-2"><strong>Date:</strong> <?php echo $event_details['date']; ?></div>
                    <div class="mb-2"><strong>Time:</strong> <?php echo $event_details['time']; ?></div>
                    <div class="mb-2"><strong>Location:</strong> <?php echo htmlspecialchars($event_details['location']); ?></div>
                    <div class="mb-0"><strong>Session:</strong> <span class="badge bg-primary"><?php echo $event_details['session']; ?></span></div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- QR Scanner -->
        <div class="qr-scanner">
            <div id="scanner-area">
                <i class="bi bi-qr-code-scan display-1 text-muted mb-3"></i>
                <p>Click "Start Scanner" to begin</p>
                <button type="button" class="btn btn-primary" onclick="startScanner()">
                    <i class="bi bi-camera"></i> Start Scanner
                </button>
            </div>
            <video id="qr-video" style="display: none;"></video>
        </div>

        <!-- Manual Entry Form -->
        <div class="manual-entry">
            <h6>Manual Entry</h6>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="student_id" class="form-label">Student ID</label>
                    <input type="number" class="form-control" id="student_id" name="student_id" required>
                </div>
                <div class="mb-3">
                    <label for="qr_data" class="form-label">QR Code Data</label>
                    <input type="text" class="form-control" id="qr_data" name="qr_data" placeholder="Paste QR code data here" required>
                </div>
                <div class="mb-3">
                    <label for="session_type" class="form-label">Session</label>
                    <select class="form-select" id="session_type" name="session_type" required>
                        <option value="">Select Session</option>
                        <option value="morning">Morning Session</option>
                        <option value="afternoon">Afternoon Session</option>
                    </select>
                </div>
                <button type="submit" name="mark_attendance" class="btn btn-success w-100">
                    <i class="bi bi-check-circle"></i> Mark Attendance
                </button>
            </form>
        </div>

        <div class="text-center mt-3">
            <a href="pafe_dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let html5QrcodeScanner = null;
        
        // Auto-suggest session based on current time
        function suggestSession() {
            const currentHour = new Date().getHours();
            const sessionSelect = document.getElementById('session_type');
            
            // Morning: before 12 PM, Afternoon: 12 PM and after
            if (currentHour < 12) {
                sessionSelect.value = 'morning';
            } else {
                sessionSelect.value = 'afternoon';
            }
        }
        
        // Call on page load
        document.addEventListener('DOMContentLoaded', function() {
            suggestSession();
        });
        
        function startScanner() {
            const scannerArea = document.getElementById('scanner-area');
            const video = document.getElementById('qr-video');
            
            scannerArea.style.display = 'none';
            video.style.display = 'block';
            
            html5QrcodeScanner = new Html5QrcodeScanner(
                "qr-video",
                { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanning
            html5QrcodeScanner.clear();
            
            // Fill the manual form with scanned data
            document.getElementById('qr_data').value = decodedText;
            
            // Show success message and scroll to form
            alert('QR Code scanned successfully! Please verify your Student ID and session type, then click "Mark Attendance".');
            
            // Focus on student ID field
            document.getElementById('student_id').focus();
            
            // Reset scanner area
            resetScanner();
        }
        
        function onScanFailure(error) {
            // Handle scan failure - usually just ignore
        }
        
        function resetScanner() {
            const scannerArea = document.getElementById('scanner-area');
            const video = document.getElementById('qr-video');
            
            scannerArea.style.display = 'block';
            video.style.display = 'none';
        }
    </script>
</body>
</html>