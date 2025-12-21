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
    // Get team share records for student products where user is creator OR group member
    $sql = "SELECT 
                sp.id,
                sp.product_id,
                sp.student_id,
                sp.product_name,
                sp.product_description,
                sp.product_price,
                sp.product_quantity,
                sp.product_image,
                sp.status,
                sp.created_at,
                sp.group_members,
                s.first_name,
                s.middle_name,
                s.last_name,
                CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as student_name
             FROM afprotech_student_products sp
             LEFT JOIN student s ON sp.student_id = s.id_number
             WHERE sp.status = 'approved' 
             AND (
                 (sp.student_id = ?) OR 
                 (sp.group_members IS NOT NULL AND sp.group_members != '' AND FIND_IN_SET(?, REPLACE(sp.group_members, ' ', '')))
             )
             ORDER BY sp.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('ss', $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $team_shares = [];
    while ($row = $result->fetch_assoc()) {
        // Get member names if group_members exist
        $member_names = [];
        if (!empty($row['group_members'])) {
            $member_ids = array_map('trim', explode(',', $row['group_members']));
            
            if (!empty($member_ids)) {
                $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
                $member_sql = "SELECT id_number, CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name 
                              FROM student WHERE id_number IN ($placeholders)";
                $member_stmt = $conn->prepare($member_sql);
                $member_stmt->bind_param(str_repeat('s', count($member_ids)), ...$member_ids);
                $member_stmt->execute();
                $member_result = $member_stmt->get_result();
                
                while ($member_row = $member_result->fetch_assoc()) {
                    $member_names[] = $member_row['full_name'];
                }
                $member_stmt->close();
            }
        }
        
        $row['member_names'] = $member_names;
        $team_shares[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $team_shares,
        'message' => 'Team shares loaded successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading team shares: ' . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>