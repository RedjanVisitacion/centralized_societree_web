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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get form data
$student_id = $_POST['student_id'] ?? '';
$product_id = $_POST['product_id'] ?? '';
$product_name = $_POST['product_name'] ?? '';
$quantity = intval($_POST['quantity'] ?? 1);
$total_price = floatval($_POST['total_price'] ?? 0);
$delivery_location = $_POST['delivery_location'] ?? '';
$message = $_POST['message'] ?? '';

// Validate required fields
if (empty(trim($student_id))) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

if (empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
    exit;
}

if (empty(trim($delivery_location))) {
    echo json_encode(['success' => false, 'message' => 'Delivery location is required']);
    exit;
}

try {
    // Generate unique order number
    $year = date('Y');
    $prefix = "ORD{$year}";
    $sql = "SELECT order_number FROM afprotechs_orders WHERE order_number LIKE '{$prefix}%' ORDER BY order_number DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $lastOrder = $result->fetch_assoc();
        $lastNumber = intval(substr($lastOrder['order_number'], strlen($prefix)));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    $order_number = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    
    // Insert new order with order_number (status is 'pending' until admin confirms)
    $sql = "INSERT INTO afprotechs_orders (order_number, student_id, product_id, product_name, quantity, total_price, delivery_location, message, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'ssisidss',
        $order_number,
        $student_id,
        $product_id,
        $product_name,
        $quantity,
        $total_price,
        $delivery_location,
        $message
    );
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Verify the order was created correctly
        $verifyStmt = $conn->prepare("SELECT * FROM afprotechs_orders WHERE order_id = ?");
        $verifyStmt->bind_param('i', $order_id);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        $orderData = $verifyResult->fetch_assoc();
        $verifyStmt->close();

        // Also store a snapshot into afprotechs_order_history (for Records > Order History & Sales)
        // This will record everyone who buys a product (including student-created products)
        try {
            $historySql = "INSERT INTO afprotechs_order_history 
                (order_id, product_id, student_id, customer_name, quantity, total_price, 
                 order_status, payment_method, payment_status, order_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $histStmt = $conn->prepare($historySql);
            if ($histStmt) {
                // For now, use student_id as customer_name; you can join to the student table later if needed
                $customer_name = $student_id;
                $order_status_hist = $orderData['order_status'] ?? 'pending';
                $payment_method = 'Cash';
                $payment_status = 'unpaid';
                $order_notes = $message;
                
                $histStmt->bind_param(
                    'iissidssss',
                    $order_id,
                    $product_id,
                    $student_id,
                    $customer_name,
                    $quantity,
                    $total_price,
                    $order_status_hist,
                    $payment_method,
                    $payment_status,
                    $order_notes
                );
                $histStmt->execute();
                $histStmt->close();
            }
        } catch (Exception $historyEx) {
            // Don't break the main order creation if history logging fails
        }

        // If the product is a student-created product with group members,
        // also record it in a team share table for "Team Product Share"
        try {
            // Check if this product exists in afprotech_student_products
            $studentProdSql = "SELECT student_id, group_members, product_price 
                               FROM afprotech_student_products 
                               WHERE product_id = ? LIMIT 1";
            $spStmt = $conn->prepare($studentProdSql);
            if ($spStmt) {
                $spStmt->close();
            }
        } catch (Exception $teamEx) {
            // Ignore errors here; team share is optional and should not block orders
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'data' => [
                'order_id' => $order_id,
                'order_number' => $order_number,
                'student_id' => $student_id,
                'product_id' => (int)$product_id,
                'product_name' => $product_name,
                'quantity' => $quantity,
                'total_price' => $total_price,
                'delivery_location' => $delivery_location,
                'message' => $message,
                'order_status' => $orderData['order_status'] ?? 'pending',
                'order_date' => $orderData['order_date'] ?? date('Y-m-d H:i:s'),
                'created_at' => $orderData['created_at'] ?? date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to place order: ' . $stmt->error
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
