<?php
/**
 * Database Migration Script for PAFE Auto-Lock Features
 * 
 * This script adds the missing auto-lock columns to existing pafe_events table.
 * Run this once to update your existing database.
 */

require_once '../../db_connection.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>PAFE Database Migration</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f8f9fa; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.info { color: #17a2b8; font-weight: bold; }
.card { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
h1 { color: #2c3e50; text-align: center; }
h2 { color: #34495e; border-bottom: 2px solid #eea618; padding-bottom: 10px; }
</style></head><body>";

echo "<h1>🔧 PAFE Database Migration</h1>";

echo "<div class='card'>";
echo "<h2>Migration Progress</h2>";

try {
    // Check if pafe_events table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'pafe_events'");
    if ($stmt->rowCount() == 0) {
        echo "<span class='error'>❌ pafe_events table does not exist. Please create it first.</span><br>";
        echo "<p><a href='test_database_connection.php'>Run Database Test</a> to create all required tables.</p>";
        echo "</div></body></html>";
        exit;
    }
    
    echo "<span class='success'>✅ pafe_events table found</span><br>";
    
    // Check current table structure
    echo "<h3>Current Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE pafe_events");
    $columns = $stmt->fetchAll();
    
    $existingColumns = array_column($columns, 'Field');
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    // Required new columns
    $requiredColumns = [
        'auto_lock_enabled' => 'BOOLEAN DEFAULT FALSE',
        'morning_auto_lock_time' => 'TIME NULL',
        'afternoon_auto_lock_time' => 'TIME NULL'
    ];
    
    echo "<h3>Adding Missing Columns:</h3>";
    
    $columnsAdded = 0;
    foreach ($requiredColumns as $columnName => $columnDefinition) {
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE pafe_events ADD COLUMN $columnName $columnDefinition";
                $pdo->exec($sql);
                echo "<span class='success'>✅ Added column: $columnName</span><br>";
                $columnsAdded++;
            } catch (PDOException $e) {
                echo "<span class='error'>❌ Failed to add column $columnName: " . $e->getMessage() . "</span><br>";
            }
        } else {
            echo "<span class='info'>ℹ️ Column $columnName already exists</span><br>";
        }
    }
    
    if ($columnsAdded > 0) {
        echo "<br><span class='success'>🎉 Migration completed! Added $columnsAdded new columns.</span><br>";
    } else {
        echo "<br><span class='info'>ℹ️ No migration needed. All columns already exist.</span><br>";
    }
    
    // Verify final structure
    echo "<h3>Updated Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE pafe_events");
    $updatedColumns = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($updatedColumns as $column) {
        $isNew = in_array($column['Field'], array_keys($requiredColumns));
        $class = $isNew ? 'success' : 'info';
        echo "<li class='$class'><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . ($isNew ? ' (NEW)' : '') . "</li>";
    }
    echo "</ul>";
    
    // Test the auto-lock functionality
    echo "<h3>Testing Auto-Lock Functionality:</h3>";
    
    // Count events with auto-lock settings
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pafe_events WHERE auto_lock_enabled = 1");
    $autoLockCount = $stmt->fetch()['count'];
    
    echo "<span class='info'>📊 Events with auto-lock enabled: $autoLockCount</span><br>";
    
    // Show sample of events
    $stmt = $pdo->query("SELECT id, title, auto_lock_enabled, morning_auto_lock_time, afternoon_auto_lock_time FROM pafe_events LIMIT 5");
    $sampleEvents = $stmt->fetchAll();
    
    if (count($sampleEvents) > 0) {
        echo "<h4>Sample Events (showing auto-lock columns):</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Auto-Lock Enabled</th><th>Morning Lock Time</th><th>Afternoon Lock Time</th></tr>";
        foreach ($sampleEvents as $event) {
            echo "<tr>";
            echo "<td>" . $event['id'] . "</td>";
            echo "<td>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td>" . ($event['auto_lock_enabled'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($event['morning_auto_lock_time'] ?: 'Not set') . "</td>";
            echo "<td>" . ($event['afternoon_auto_lock_time'] ?: 'Not set') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<span class='error'>❌ Migration failed: " . $e->getMessage() . "</span><br>";
}

echo "</div>";

echo "<div class='card' style='text-align: center; background: #e8f5e8; border: 2px solid #28a745;'>";
echo "<h2 style='color: #28a745; margin: 0;'>🎉 Migration Complete!</h2>";
echo "<p>Your PAFE events table now supports auto-lock functionality.</p>";
echo "<div style='display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;'>";
echo "<a href='pafe_event.php' style='background: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📅 Go to Events</a>";
echo "<a href='verify_system.php' style='background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔧 Verify System</a>";
echo "</div>";
echo "</div>";

echo "</body></html>";
?>