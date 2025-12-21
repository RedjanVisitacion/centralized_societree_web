<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection - direct connection for reliability
$conn = null;
try {
    $host = '103.125.219.236';
    $user = 'societree';
    $password = 'socieTree12345';
    $database = 'societree';
    
    // Try remote connection first
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        // Fallback to local
        $conn = @new mysqli('localhost', 'root', '', $database);
        if ($conn->connect_error) {
            throw new Exception('Both remote and local connections failed: ' . $conn->connect_error);
        }
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get form data
$order_id = $_POST['order_id'] ?? '';
$new_status = $_POST['new_status'] ?? '';

// Validate required fields
if (empty(trim($order_id))) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

if (empty(trim($new_status))) {
    echo json_encode([
        'success' => false,
        'message' => 'New status is required'
    ]);
    exit;
}

// Validate status value
$valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit;
}

try {
    // Update order status
    $sql = "UPDATE afprotechs_orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $new_status, $order_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order_id' => $order_id,
                'new_status' => $new_status
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Order not found or status unchanged'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order status: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>