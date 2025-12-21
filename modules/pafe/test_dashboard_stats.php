<?php
// Test script to verify dashboard statistics work
require_once '../../db_connection.php';

echo "<h2>Testing PAFE Dashboard Statistics</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // Check if tables exist
    $tables_to_check = ['pafe_events', 'pafe_announcements'];
    
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Table '$table' exists</p>";
            
            // Get count
            $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $count = $count_stmt->fetch()['total'];
            echo "<p>📊 Total records in '$table': $count</p>";
        } else {
            echo "<p>❌ Table '$table' does not exist</p>";
            echo "<p>💡 You may need to create the table first</p>";
        }
    }
    
    echo "<hr>";
    echo "<p><strong>Dashboard should work properly if all tables exist.</strong></p>";
    echo "<p><a href='pafe_dashboard.php'>Go to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>