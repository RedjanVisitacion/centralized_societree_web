<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    // Create orders table if it doesn't exist
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS afprotechs_orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            student_id VARCHAR(20) NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            delivery_location TEXT NOT NULL,
            message TEXT,
            order_status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($createTableSql);
    
    // Add order_number column if it doesn't exist (for existing tables)
    $checkColumnSql = "SHOW COLUMNS FROM afprotechs_orders LIKE 'order_number'";
    $columnResult = $conn->query($checkColumnSql);
    if ($columnResult->num_rows == 0) {
        $addColumnSql = "ALTER TABLE afprotechs_orders ADD COLUMN order_number VARCHAR(20) UNIQUE AFTER order_id";
        $conn->query($addColumnSql);
    }
    
    // Add message column if it doesn't exist (rename from special_instructions)
    $checkMessageSql = "SHOW COLUMNS FROM afprotechs_orders LIKE 'message'";
    $messageResult = $conn->query($checkMessageSql);
    if ($messageResult->num_rows == 0) {
        $checkSpecialSql = "SHOW COLUMNS FROM afprotechs_orders LIKE 'special_instructions'";
        $specialResult = $conn->query($checkSpecialSql);
        if ($specialResult->num_rows > 0) {
            $conn->query("ALTER TABLE afprotechs_orders CHANGE special_instructions message TEXT");
        } else {
            $conn->query("ALTER TABLE afprotechs_orders ADD COLUMN message TEXT AFTER delivery_location");
        }
    }
    
    // Get filter parameters
    $student_id = $_GET['student_id'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    // Build query with optional filters
    $sql = "SELECT * FROM afprotechs_orders";
    $params = [];
    $types = '';
    $whereConditions = [];
    
    if (!empty($student_id)) {
        $whereConditions[] = "student_id = ?";
        $params[] = $student_id;
        $types .= 's';
    }
    
    if (!empty($status_filter)) {
        $whereConditions[] = "order_status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM afprotechs_orders";
    if (!empty($whereConditions)) {
        $countSql .= " WHERE " . implode(" AND ", $whereConditions);
        // For count query, we need to escape the values manually since we can't use prepared statements easily here
        $countSql = str_replace('?', "'$student_id'", $countSql);
        if (!empty($status_filter)) {
            $countSql = str_replace('?', "'$status_filter'", $countSql);
        }
    }
    $countResult = $conn->query($countSql);
    $totalCount = $countResult->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'total_count' => $totalCount,
        'current_page' => floor($offset / $limit) + 1,
        'total_pages' => ceil($totalCount / $limit)
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>