<?php
// Script to update order statuses to only use 'pending' and 'completed'
require_once __DIR__ . '/config/config.php';

$conn = null;
try {
    $conn = getAfprotechDbConnection();
} catch (Throwable $t) {
    // Fallback to root db_connection.php if AFPROTECH config fails
    $rootDbPath = realpath(__DIR__ . '/../../db_connection.php');
    if ($rootDbPath && file_exists($rootDbPath)) {
        require_once $rootDbPath; // defines $pdo
        // If PDO is available, open a mysqli for the legacy code paths
        try {
            $conn = new mysqli(DB_HOST_PRIMARY, DB_USER_PRIMARY, DB_PASS_PRIMARY, DB_NAME_PRIMARY);
        } catch (Throwable $t2) {
            // final fallback handled below
        }
    }
}

if (!$conn || $conn->connect_error) {
    die('Database connection failed: ' . ($conn->connect_error ?? 'Connection not established'));
}

echo "🔄 Updating order statuses to only use 'pending' and 'completed'...\n\n";

// Update 'preparing' and 'ready' statuses to 'pending'
$update_to_pending = $conn->query("
    UPDATE afprotechs_food_orders 
    SET order_status = 'pending' 
    WHERE order_status IN ('preparing', 'ready')
");

if ($update_to_pending) {
    $affected_rows = $conn->affected_rows;
    echo "✅ Updated $affected_rows orders from 'preparing'/'ready' to 'pending'\n";
} else {
    echo "❌ Error updating orders to pending: " . $conn->error . "\n";
}

// Show current status distribution
echo "\n📊 Current order status distribution:\n";
$status_count = $conn->query("
    SELECT order_status, COUNT(*) as count 
    FROM afprotechs_food_orders 
    GROUP BY order_status 
    ORDER BY order_status
");

if ($status_count && $status_count->num_rows > 0) {
    while ($row = $status_count->fetch_assoc()) {
        $status_label = $row['order_status'] === 'completed' ? 'Delivered' : ucfirst($row['order_status']);
        echo "   {$status_label}: {$row['count']} orders\n";
    }
} else {
    echo "   No orders found\n";
}

echo "\n✅ Order status update complete!\n";
echo "📝 System now uses only:\n";
echo "   - Pending: New orders waiting to be delivered\n";
echo "   - Completed: Orders that have been delivered\n";

$conn->close();
?>