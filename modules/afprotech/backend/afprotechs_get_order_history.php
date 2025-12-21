<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use AFPROTECH's own database connection
require_once __DIR__ . '/../config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Fetch completed orders (order history)
    $sql = "
        SELECT 
            order_id,
            customer_name,
            food_name,
            quantity,
            total_price,
            order_status,
            order_datetime,
            created_at,
            updated_at
        FROM afprotechs_food_orders 
        WHERE order_status = 'completed'
        ORDER BY updated_at DESC, order_datetime DESC
    ";
    
    $result = $conn->query($sql);
    $orders = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = [
                'order_id' => (int)$row['order_id'],
                'customer_name' => $row['customer_name'],
                'food_name' => $row['food_name'],
                'quantity' => (int)$row['quantity'],
                'total_price' => (float)$row['total_price'],
                'order_status' => $row['order_status'],
                'order_datetime' => $row['order_datetime'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'count' => count($orders),
        'message' => 'Order history retrieved successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>