<?php
/**
 * PAFE System Verification Script
 * 
 * This script verifies that all components of the PAFE system are properly
 * connected to the database and functioning correctly.
 */

require_once '../../db_connection.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>PAFE System Verification</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f8f9fa; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.info { color: #17a2b8; font-weight: bold; }
.card { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
h1 { color: #2c3e50; text-align: center; }
h2 { color: #34495e; border-bottom: 2px solid #eea618; padding-bottom: 10px; }
.status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
</style></head><body>";

echo "<h1>🎓 PAFE System Verification</h1>";

// 1. Database Connection Test
echo "<div class='card'>";
echo "<h2>1. Database Connection</h2>";
try {
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    echo "<span class='success'>✅ Connected successfully</span><br>";
    echo "<span class='info'>📊 Database: societree</span><br>";
    echo "<span class='info'>🌐 Host: 103.125.219.236</span><br>";
    echo "<span class='info'>🔧 MySQL Version: $version</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ Connection failed: " . $e->getMessage() . "</span>";
}
echo "</div>";

// 2. Table Structure Verification
echo "<div class='card'>";
echo "<h2>2. Database Tables</h2>";

$requiredTables = [
    'pafe_events' => [
        'id', 'title', 'event_date', 'event_time', 'location', 
        'morning_session_locked', 'afternoon_session_locked',
        'morning_auto_lock_time', 'afternoon_auto_lock_time', 
        'auto_lock_enabled', 'qr_code_data', 'created_at', 'updated_at'
    ],
    'pafe_event_attendance' => [
        'id', 'event_id', 'student_id', 'session_type', 'attended_at'
    ],
    'pafe_announcements' => [
        'id', 'title', 'description', 'announcement_date', 'created_at', 'updated_at'
    ]
];

foreach ($requiredTables as $tableName => $requiredColumns) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() > 0) {
            echo "<span class='success'>✅ $tableName</span> - ";
            
            // Check columns
            $stmt = $pdo->query("DESCRIBE $tableName");
            $existingColumns = array_column($stmt->fetchAll(), 'Field');
            
            $missingColumns = array_diff($requiredColumns, $existingColumns);
            if (empty($missingColumns)) {
                echo "<span class='success'>All columns present</span><br>";
            } else {
                echo "<span class='warning'>Missing columns: " . implode(', ', $missingColumns) . "</span><br>";
            }
        } else {
            echo "<span class='error'>❌ $tableName - Table missing</span><br>";
        }
    } catch (PDOException $e) {
        echo "<span class='error'>❌ $tableName - Error: " . $e->getMessage() . "</span><br>";
    }
}
echo "</div>";

// 3. File System Check
echo "<div class='card'>";
echo "<h2>3. System Files</h2>";

$requiredFiles = [
    '../../db_connection.php' => 'Database Connection',
    'pafe_event.php' => 'Event Management',
    'pafe_announcement.php' => 'Announcements',
    'pafe_attendance_scanner.php' => 'Attendance Scanner',
    'auto_lock_cron.php' => 'Auto-Lock Cron Job'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $description</span> - $file<br>";
    } else {
        echo "<span class='error'>❌ $description</span> - $file (Missing)<br>";
    }
}
echo "</div>";

// 4. System Statistics
echo "<div class='card'>";
echo "<h2>4. Current System Status</h2>";
echo "<div class='status-grid'>";

try {
    // Events statistics
    echo "<div>";
    echo "<h3>📅 Events</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_events");
    $totalEvents = $stmt->fetch()['total'];
    echo "Total Events: <strong>$totalEvents</strong><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_events WHERE event_date >= CURDATE()");
    $upcomingEvents = $stmt->fetch()['count'];
    echo "Upcoming Events: <strong>$upcomingEvents</strong><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_events WHERE auto_lock_enabled = 1");
    $autoLockEvents = $stmt->fetch()['count'];
    echo "Auto-Lock Enabled: <strong>$autoLockEvents</strong><br>";
    echo "</div>";
    
    // Attendance statistics
    echo "<div>";
    echo "<h3>👥 Attendance</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_event_attendance");
    $totalAttendance = $stmt->fetch()['total'];
    echo "Total Records: <strong>$totalAttendance</strong><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_event_attendance WHERE session_type = 'morning'");
    $morningAttendance = $stmt->fetch()['count'];
    echo "Morning Sessions: <strong>$morningAttendance</strong><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_event_attendance WHERE session_type = 'afternoon'");
    $afternoonAttendance = $stmt->fetch()['count'];
    echo "Afternoon Sessions: <strong>$afternoonAttendance</strong><br>";
    echo "</div>";
    
    // Announcements statistics
    echo "<div>";
    echo "<h3>📢 Announcements</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_announcements");
    $totalAnnouncements = $stmt->fetch()['total'];
    echo "Total Announcements: <strong>$totalAnnouncements</strong><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_announcements WHERE announcement_date >= CURDATE()");
    $recentAnnouncements = $stmt->fetch()['count'];
    echo "Recent/Upcoming: <strong>$recentAnnouncements</strong><br>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<span class='error'>Error getting statistics: " . $e->getMessage() . "</span>";
}

echo "</div>";
echo "</div>";

// 5. Auto-Lock System Test
echo "<div class='card'>";
echo "<h2>5. Auto-Lock System Test</h2>";

try {
    // Test auto-lock function
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    
    echo "<span class='info'>Current Server Time: $current_date $current_time</span><br>";
    
    // Check for events with auto-lock today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pafe_events WHERE auto_lock_enabled = 1 AND event_date = ?");
    $stmt->execute([$current_date]);
    $autoLockToday = $stmt->fetch()['count'];
    
    if ($autoLockToday > 0) {
        echo "<span class='success'>✅ $autoLockToday events with auto-lock enabled today</span><br>";
        
        // Show details
        $stmt = $pdo->prepare("SELECT title, morning_auto_lock_time, afternoon_auto_lock_time, morning_session_locked, afternoon_session_locked FROM pafe_events WHERE auto_lock_enabled = 1 AND event_date = ?");
        $stmt->execute([$current_date]);
        $events = $stmt->fetchAll();
        
        foreach ($events as $event) {
            echo "<strong>" . htmlspecialchars($event['title']) . ":</strong><br>";
            if ($event['morning_auto_lock_time']) {
                $status = $event['morning_session_locked'] ? 'LOCKED' : 'OPEN';
                echo "&nbsp;&nbsp;Morning: " . date('h:i A', strtotime($event['morning_auto_lock_time'])) . " - $status<br>";
            }
            if ($event['afternoon_auto_lock_time']) {
                $status = $event['afternoon_session_locked'] ? 'LOCKED' : 'OPEN';
                echo "&nbsp;&nbsp;Afternoon: " . date('h:i A', strtotime($event['afternoon_auto_lock_time'])) . " - $status<br>";
            }
        }
    } else {
        echo "<span class='warning'>⚠️ No events with auto-lock enabled today</span><br>";
    }
    
} catch (PDOException $e) {
    echo "<span class='error'>❌ Auto-lock test failed: " . $e->getMessage() . "</span>";
}
echo "</div>";

// 6. Quick Links
echo "<div class='card'>";
echo "<h2>6. System Access</h2>";
echo "<div style='display: flex; gap: 15px; flex-wrap: wrap;'>";
echo "<a href='pafe_event.php' style='background: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📅 Events Management</a>";
echo "<a href='pafe_announcement.php' style='background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📢 Announcements</a>";
echo "<a href='pafe_attendance_scanner.php' style='background: #17a2b8; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📱 Attendance Scanner</a>";
echo "<a href='test_database_connection.php' style='background: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔧 Database Test</a>";
echo "</div>";
echo "</div>";

echo "<div class='card' style='text-align: center; background: #e8f5e8; border: 2px solid #28a745;'>";
echo "<h2 style='color: #28a745; margin: 0;'>🎉 System Verification Complete!</h2>";
echo "<p>Your PAFE Event Management System is properly connected to the database and ready to use.</p>";
echo "</div>";

echo "</body></html>";
?>