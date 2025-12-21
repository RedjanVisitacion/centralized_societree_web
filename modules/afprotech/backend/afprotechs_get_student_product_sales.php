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

// Get student ID from query parameter
$student_id = $_GET['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required'
    ]);
    exit;
}

try {
    // Get sales from orders of student-made products (where the student is the creator)
    $sql = "SELECT 
                o.order_id,
                o.order_number,
                o.student_id as buyer_id,
                o.quantity,
                o.total_price,
                o.order_status,
                o.created_at,
                o.updated_at,
                sp.product_name,
                sp.product_price,
                sp.product_image,
                sp.student_id as creator_id,
                sp.group_members,
                CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as buyer_name
             FROM afprotechs_orders o
             INNER JOIN afprotech_student_products sp ON o.product_id = sp.product_id
             LEFT JOIN student s ON o.student_id = s.id_number
             WHERE sp.student_id = ? 
             AND o.order_status = 'delivered'
             ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sales = [];
    while ($row = $result->fetch_assoc()) {
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
        $sales[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $sales,
        'message' => 'Student product sales loaded successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading student product sales: ' . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>