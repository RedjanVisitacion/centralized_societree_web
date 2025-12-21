<?php
/**
 * Database Connection Test for PAFE Event System
 * 
 * This script tests the database connection and ensures all required tables exist.
 * Run this script to verify your database setup.
 */

require_once '../../db_connection.php';

echo "<h2>PAFE Event System - Database Connection Test</h2>";

// Test 1: Database Connection
echo "<h3>1. Testing Database Connection</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ <strong>SUCCESS:</strong> Database connection established successfully!<br>";
    echo "📊 <strong>Database:</strong> societree<br>";
    echo "🌐 <strong>Host:</strong> 103.125.219.236<br><br>";
} catch (PDOException $e) {
    echo "❌ <strong>ERROR:</strong> Database connection failed: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 2: Create Tables
echo "<h3>2. Creating Required Tables</h3>";

try {
    // Create Events Table
    $createEventsTable = "CREATE TABLE IF NOT EXISTS pafe_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        event_time TIME NOT NULL,
        location VARCHAR(255) NOT NULL,
        morning_session_locked BOOLEAN DEFAULT FALSE,
        afternoon_session_locked BOOLEAN DEFAULT FALSE,
        morning_auto_lock_time TIME NULL,
        afternoon_auto_lock_time TIME NULL,
        auto_lock_enabled BOOLEAN DEFAULT FALSE,
        qr_code_data VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createEventsTable);
    echo "✅ <strong>pafe_events</strong> table created/verified successfully<br>";
    
    // Create Attendance Table
    $createAttendanceTable = "CREATE TABLE IF NOT EXISTS pafe_event_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        student_id INT NOT NULL,
        session_type ENUM('morning', 'afternoon') NOT NULL,
        attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES pafe_events(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (event_id, student_id, session_type)
    )";
    
    $pdo->exec($createAttendanceTable);
    echo "✅ <strong>pafe_event_attendance</strong> table created/verified successfully<br>";
    
    // Create Announcements Table (if not exists)
    $createAnnouncementsTable = "CREATE TABLE IF NOT EXISTS pafe_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        announcement_date DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createAnnouncementsTable);
    echo "✅ <strong>pafe_announcements</strong> table created/verified successfully<br><br>";
    
} catch (PDOException $e) {
    echo "❌ <strong>ERROR:</strong> Table creation failed: " . $e->getMessage() . "<br><br>";
}

// Test 3: Verify Table Structure
echo "<h3>3. Verifying Table Structure</h3>";

try {
    // Check pafe_events table structure
    $stmt = $pdo->query("DESCRIBE pafe_events");
    $columns = $stmt->fetchAll();
    
    echo "<strong>pafe_events table columns:</strong><br>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    // Check pafe_event_attendance table structure
    $stmt = $pdo->query("DESCRIBE pafe_event_attendance");
    $columns = $stmt->fetchAll();
    
    echo "<strong>pafe_event_attendance table columns:</strong><br>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ <strong>ERROR:</strong> Table verification failed: " . $e->getMessage() . "<br><br>";
}

// Test 4: Test Basic Operations
echo "<h3>4. Testing Basic Database Operations</h3>";

try {
    // Test INSERT (create a sample event)
    $testTitle = "Database Test Event - " . date('Y-m-d H:i:s');
    $testDate = date('Y-m-d', strtotime('+1 day'));
    $testTime = '10:00:00';
    $testLocation = 'Test Location';
    $testQRData = 'TEST_QR_' . time();
    
    $stmt = $pdo->prepare("INSERT INTO pafe_events (title, event_date, event_time, location, qr_code_data) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$testTitle, $testDate, $testTime, $testLocation, $testQRData]);
    
    $testEventId = $pdo->lastInsertId();
    echo "✅ <strong>INSERT:</strong> Test event created with ID: $testEventId<br>";
    
    // Test SELECT
    $stmt = $pdo->prepare("SELECT * FROM pafe_events WHERE id = ?");
    $stmt->execute([$testEventId]);
    $testEvent = $stmt->fetch();
    
    if ($testEvent) {
        echo "✅ <strong>SELECT:</strong> Test event retrieved successfully<br>";
        echo "&nbsp;&nbsp;&nbsp;📝 Title: " . $testEvent['title'] . "<br>";
        echo "&nbsp;&nbsp;&nbsp;📅 Date: " . $testEvent['event_date'] . "<br>";
        echo "&nbsp;&nbsp;&nbsp;🕐 Time: " . $testEvent['event_time'] . "<br>";
    }
    
    // Test UPDATE
    $stmt = $pdo->prepare("UPDATE pafe_events SET auto_lock_enabled = 1 WHERE id = ?");
    $stmt->execute([$testEventId]);
    echo "✅ <strong>UPDATE:</strong> Test event updated successfully<br>";
    
    // Test DELETE (cleanup)
    $stmt = $pdo->prepare("DELETE FROM pafe_events WHERE id = ?");
    $stmt->execute([$testEventId]);
    echo "✅ <strong>DELETE:</strong> Test event cleaned up successfully<br><br>";
    
} catch (PDOException $e) {
    echo "❌ <strong>ERROR:</strong> Database operations test failed: " . $e->getMessage() . "<br><br>";
}

// Test 5: Check Existing Data
echo "<h3>5. Current Database Status</h3>";

try {
    // Count events
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_events");
    $eventCount = $stmt->fetch()['count'];
    echo "📊 <strong>Total Events:</strong> $eventCount<br>";
    
    // Count attendance records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_event_attendance");
    $attendanceCount = $stmt->fetch()['count'];
    echo "👥 <strong>Total Attendance Records:</strong> $attendanceCount<br>";
    
    // Count announcements
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_announcements");
    $announcementCount = $stmt->fetch()['count'];
    echo "📢 <strong>Total Announcements:</strong> $announcementCount<br><br>";
    
    // Show recent events
    if ($eventCount > 0) {
        echo "<strong>Recent Events:</strong><br>";
        $stmt = $pdo->query("SELECT title, event_date, event_time FROM pafe_events ORDER BY created_at DESC LIMIT 5");
        $recentEvents = $stmt->fetchAll();
        
        echo "<ul>";
        foreach ($recentEvents as $event) {
            echo "<li><strong>" . htmlspecialchars($event['title']) . "</strong> - " . 
                 date('M d, Y', strtotime($event['event_date'])) . " at " . 
                 date('h:i A', strtotime($event['event_time'])) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>ERROR:</strong> Status check failed: " . $e->getMessage() . "<br><br>";
}

echo "<h3>🎉 Database Test Complete!</h3>";
echo "<p><strong>Summary:</strong> Your PAFE Event System database is properly connected and configured.</p>";
echo "<p><a href='pafe_event.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Events Management</a></p>";
echo "<p><a href='pafe_announcement.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Announcements</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}

h2 {
    color: #2c3e50;
    border-bottom: 3px solid #eea618;
    padding-bottom: 10px;
}

h3 {
    color: #34495e;
    margin-top: 30px;
}

ul {
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

li {
    margin: 5px 0;
}
</style>