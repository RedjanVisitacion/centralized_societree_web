<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Get order ID from query parameter
$order_id = $_GET['order_id'] ?? '';

if (empty($order_id) || !is_numeric($order_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid order ID is required'
    ]);
    exit;
}

try {
    // Fetch order details from afprotechs_orders table
    $sql = "SELECT * FROM afprotechs_orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $order_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'order_id' => (int)$order['order_id'],
                    'order_number' => $order['order_number'] ?? '',
                    'student_id' => $order['student_id'],
                    'product_id' => (int)($order['product_id'] ?? 0),
                    'product_name' => $order['product_name'],
                    'quantity' => (int)($order['quantity'] ?? 1),
                    'total_price' => (float)($order['total_price'] ?? 0),
                    'delivery_location' => $order['delivery_location'] ?? '',
                    'message' => $order['message'] ?? '',
                    'order_status' => $order['order_status'],
                    'created_at' => $order['created_at'] ?? $order['order_date'] ?? null
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Order not found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch order details: ' . $stmt->error
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
