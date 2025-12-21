<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection
$conn = null;
try {
    $host = '103.125.219.236';
    $user = 'societree';
    $password = 'socieTree12345';
    $database = 'societree';
    
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        $conn = @new mysqli('localhost', 'root', '', $database);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to database'
    ]);
    exit;
}

// Get student ID and type from query parameters
$student_id = $_GET['student_id'] ?? '';
$type = $_GET['type'] ?? 'student_made'; // 'student_made' or 'student_bought'

if (empty($student_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required'
    ]);
    exit;
}

try {
    $orders = [];
    
    // Debug logging
    error_log("AFProtech Orders API - Student ID: $student_id, Type: $type");
    
    if ($type === 'student_made') {
        // Get orders for products created by this student (showing buyers) - with better product matching
        $sql = "SELECT 
                    o.order_id,
                    o.order_number,
                    o.student_id,
                    o.product_id,
                    o.product_name,
                    o.quantity,
                    o.total_price,
                    o.delivery_location,
                    o.message,
                    o.order_status,
                    o.order_date,
                    o.created_at,
                    COALESCE(
                        o.product_name,
                        p.product_name, 
                        sp.product_name, 
                        CONCAT('Product #', o.product_id)
                    ) as display_product_name,
                    COALESCE(p.product_price, sp.product_price, o.total_price/o.quantity) as product_price,
                    COALESCE(p.product_image, sp.product_image) as product_image,
                    o.student_id as buyer_id,
                    sp.student_id as creator_id,
                    sp.group_members,
                    CONCAT(buyer.first_name, ' ', IFNULL(CONCAT(buyer.middle_name, ' '), ''), buyer.last_name) as buyer_name,
                    CONCAT(creator.first_name, ' ', IFNULL(CONCAT(creator.middle_name, ' '), ''), creator.last_name) as creator_name
                 FROM afprotechs_orders o
                 LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                 LEFT JOIN afprotech_student_products sp ON (o.product_id = sp.product_id OR o.product_name = sp.product_name)
                 LEFT JOIN student buyer ON o.student_id = buyer.id_number
                 LEFT JOIN student creator ON sp.student_id = creator.id_number
                 WHERE sp.student_id = ?
                 ORDER BY o.created_at DESC";
    } else {
        // Get orders made by this student (showing sellers) - with better product matching
        $sql = "SELECT 
                    o.order_id,
                    o.order_number,
                    o.student_id,
                    o.product_id,
                    o.product_name,
                    o.quantity,
                    o.total_price,
                    o.delivery_location,
                    o.message,
                    o.order_status,
                    o.order_date,
                    o.created_at,
                    COALESCE(
                        o.product_name,
                        p.product_name, 
                        sp.product_name, 
                        CONCAT('Product #', o.product_id)
                    ) as display_product_name,
                    COALESCE(p.product_price, sp.product_price, o.total_price/o.quantity) as product_price,
                    COALESCE(p.product_image, sp.product_image) as product_image,
                    o.student_id as buyer_id,
                    sp.student_id as creator_id,
                    sp.group_members,
                    CONCAT(buyer.first_name, ' ', IFNULL(CONCAT(buyer.middle_name, ' '), ''), buyer.last_name) as buyer_name,
                    CONCAT(creator.first_name, ' ', IFNULL(CONCAT(creator.middle_name, ' '), ''), creator.last_name) as creator_name
                 FROM afprotechs_orders o
                 LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                 LEFT JOIN afprotech_student_products sp ON (o.product_id = sp.product_id OR o.product_name = sp.product_name)
                 LEFT JOIN student buyer ON o.student_id = buyer.id_number
                 LEFT JOIN student creator ON sp.student_id = creator.id_number
                 WHERE o.student_id = ?
                 ORDER BY o.created_at DESC";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("AFProtech Orders API - SQL Error: " . $conn->error);
        error_log("AFProtech Orders API - Failed SQL: " . $sql);
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $student_id);
    if (!$stmt->execute()) {
        error_log("AFProtech Orders API - Execute Error: " . $stmt->error);
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    error_log("AFProtech Orders API - Query executed, rows found: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        // Debug log the product name and buyer information
        error_log("AFProtech Orders API - Order ID: " . $row['order_id'] . ", Product Name: '" . ($row['display_product_name'] ?? 'NULL') . "', Buyer Name: '" . ($row['buyer_name'] ?? 'NULL') . "', Student ID: '" . ($row['student_id'] ?? 'NULL') . "'");
        
        // Use display_product_name for the frontend
        $row['product_name'] = $row['display_product_name'] ?? $row['product_name'] ?? 'Product #' . $row['product_id'];
        
        // Ensure product_name is not null or empty
        if (empty($row['product_name'])) {
            $row['product_name'] = 'Product #' . $row['product_id'];
            error_log("AFProtech Orders API - Fallback product name set for order " . $row['order_id']);
        }
        
        // Ensure buyer_name is not null or empty - try to get it manually if JOIN failed
        if (empty($row['buyer_name'])) {
            // Try to get student name manually
            $student_lookup_sql = "SELECT CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name 
                                  FROM student 
                                  WHERE id_number = ? OR student_id = ? OR id = ?";
            $student_stmt = $conn->prepare($student_lookup_sql);
            if ($student_stmt) {
                $student_stmt->bind_param('sss', $row['student_id'], $row['student_id'], $row['student_id']);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                if ($student_result && $student_result->num_rows > 0) {
                    $student_data = $student_result->fetch_assoc();
                    $row['buyer_name'] = $student_data['full_name'];
                    error_log("AFProtech Orders API - Manual student lookup successful for " . $row['student_id']);
                } else {
                    $row['buyer_name'] = 'Student ' . $row['student_id'];
                    error_log("AFProtech Orders API - Manual student lookup failed for " . $row['student_id']);
                }
                $student_stmt->close();
            } else {
                $row['buyer_name'] = 'Student ' . $row['student_id'];
                error_log("AFProtech Orders API - Fallback buyer name set for order " . $row['order_id']);
            }
        }
        
        // Parse group members if they exist
        $group_members = [];
        if (!empty($row['group_members'])) {
            $member_ids = array_map('trim', explode(',', $row['group_members']));
            
            // Get member names
            if (!empty($member_ids)) {
                $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
                $member_sql = "SELECT id_number, CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name 
                              FROM student WHERE id_number IN ($placeholders)";
                $member_stmt = $conn->prepare($member_sql);
                $member_stmt->bind_param(str_repeat('s', count($member_ids)), ...$member_ids);
                $member_stmt->execute();
                $member_result = $member_stmt->get_result();
                
                while ($member_row = $member_result->fetch_assoc()) {
                    $group_members[] = [
                        'id' => $member_row['id_number'],
                        'name' => $member_row['full_name']
                    ];
                }
                $member_stmt->close();
            }
        }
        
        $row['group_members_details'] = $group_members;
        $orders[] = $row;
    }
    
    // If no orders found with the complex query, try a simple fallback
    if (empty($orders)) {
        error_log("AFProtech Orders API - No orders found with complex query, trying simple fallback");
        
        $simple_sql = "SELECT 
                        o.*,
                        CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as buyer_name
                       FROM afprotechs_orders o
                       LEFT JOIN student s ON o.student_id = s.id_number
                       WHERE o.student_id = ? 
                       ORDER BY o.created_at DESC";
        $simple_stmt = $conn->prepare($simple_sql);
        $simple_stmt->bind_param('s', $student_id);
        $simple_stmt->execute();
        $simple_result = $simple_stmt->get_result();
        
        while ($simple_row = $simple_result->fetch_assoc()) {
            // Add basic product info - use the product_name from orders table if available
            $simple_row['product_name'] = $simple_row['product_name'] ?? 'Product #' . $simple_row['product_id'];
            $simple_row['product_price'] = $simple_row['total_price'] / max(1, $simple_row['quantity']);
            $simple_row['buyer_name'] = $simple_row['buyer_name'] ?? 'Student ' . $simple_row['student_id'];
            $simple_row['creator_name'] = 'Unknown Creator';
            $simple_row['group_members_details'] = [];
            $orders[] = $simple_row;
        }
        $simple_stmt->close();
        
        error_log("AFProtech Orders API - Simple fallback found: " . count($orders) . " orders");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'message' => 'Orders loaded successfully',
        'debug_info' => [
            'student_id' => $student_id,
            'type' => $type,
            'orders_count' => count($orders)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading orders: ' . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>