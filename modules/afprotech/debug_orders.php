<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    // Check if orders table exists
    $tableExistsQuery = "SHOW TABLES LIKE 'afprotechs_orders'";
    $tableResult = $conn->query($tableExistsQuery);
    $tableExists = $tableResult->num_rows > 0;
    
    $debugInfo = [
        'success' => true,
        'database_connected' => true,
        'orders_table_exists' => $tableExists,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($tableExists) {
        // Get table structure
        $structureQuery = "DESCRIBE afprotechs_orders";
        $structureResult = $conn->query($structureQuery);
        $columns = [];
        while ($row = $structureResult->fetch_assoc()) {
            $columns[] = $row;
        }
        $debugInfo['table_structure'] = $columns;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM afprotechs_orders";
        $countResult = $conn->query($countQuery);
        $totalCount = $countResult->fetch_assoc()['total'];
        $debugInfo['total_orders'] = $totalCount;
        
        // Get sample orders
        $sampleQuery = "SELECT * FROM afprotechs_orders ORDER BY created_at DESC LIMIT 5";
        $sampleResult = $conn->query($sampleQuery);
        $sampleOrders = [];
        while ($row = $sampleResult->fetch_assoc()) {
            $sampleOrders[] = $row;
        }
        $debugInfo['sample_orders'] = $sampleOrders;
    } else {
        $debugInfo['message'] = 'Orders table does not exist yet';
    }
    
    echo json_encode($debugInfo);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Debug error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>