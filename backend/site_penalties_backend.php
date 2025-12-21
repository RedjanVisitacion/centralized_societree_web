<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Action parameter required']);
    exit;
}

switch ($_POST['action']) {
    case 'add':
        addPenalty();
        break;
    case 'edit':
        editPenalty();
        break;
    case 'delete':
        deletePenalty();
        break;
    case 'get':
        getPenalty();
        break;
    case 'list':
        listPenalties();
        break;
    case 'get_students':
        getStudents();
        break;
    case 'validate_student':
        validateStudent();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addPenalty() {
    global $pdo;
    
    // Validate required fields
    $required_fields = ['student_id', 'penalty_type', 'description', 'community_service_hours', 'status', 'date_issued'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate student exists and get student info
    try {
        $stmt = $pdo->prepare("SELECT id_number, first_name, last_name, course, year, section FROM student WHERE id_number = ?");
        $stmt->execute([$_POST['student_id']]);
        $student = $stmt->fetch();
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student ID ' . $_POST['student_id'] . ' not found in the system']);
            return;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO site_penalties (student_id, penalty_type, description, community_service_hours, amount, status, date_issued, due_date, service_location, supervisor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $_POST['student_id'],
            $_POST['penalty_type'],
            $_POST['description'],
            intval($_POST['community_service_hours']),
            0.00, // Default amount to 0 since we removed the field
            $_POST['status'],
            $_POST['date_issued'],
            !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            !empty($_POST['service_location']) ? $_POST['service_location'] : null,
            !empty($_POST['supervisor']) ? $_POST['supervisor'] : null
        ]);
        
        if ($result) {
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            echo json_encode([
                'success' => true, 
                'message' => "Penalty added successfully for {$student_name} (ID: {$student['id_number']})", 
                'id' => $pdo->lastInsertId(),
                'student_info' => $student
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function editPenalty() {
    global $pdo;
    
    // Validate required fields
    $required_fields = ['id', 'student_id', 'penalty_type', 'description', 'community_service_hours', 'status', 'date_issued'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate penalty exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM site_penalties WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Penalty not found']);
            return;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    // Validate student exists and get student info
    try {
        $stmt = $pdo->prepare("SELECT id_number, first_name, last_name, course, year, section FROM student WHERE id_number = ?");
        $stmt->execute([$_POST['student_id']]);
        $student = $stmt->fetch();
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student ID ' . $_POST['student_id'] . ' not found in the system']);
            return;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE site_penalties SET student_id = ?, penalty_type = ?, description = ?, community_service_hours = ?, amount = ?, status = ?, date_issued = ?, due_date = ?, service_location = ?, supervisor = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([
            $_POST['student_id'],
            $_POST['penalty_type'],
            $_POST['description'],
            intval($_POST['community_service_hours']),
            0.00, // Default amount to 0 since we removed the field
            $_POST['status'],
            $_POST['date_issued'],
            !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            !empty($_POST['service_location']) ? $_POST['service_location'] : null,
            !empty($_POST['supervisor']) ? $_POST['supervisor'] : null,
            $_POST['id']
        ]);
        
        if ($result) {
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            echo json_encode([
                'success' => true, 
                'message' => "Penalty updated successfully for {$student_name} (ID: {$student['id_number']})",
                'student_info' => $student
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deletePenalty() {
    global $pdo;
    
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Penalty ID is required']);
        return;
    }
    
    try {
        // Check if penalty exists
        $stmt = $pdo->prepare("SELECT id FROM site_penalties WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Penalty not found']);
            return;
        }
        
        // Delete penalty
        $stmt = $pdo->prepare("DELETE FROM site_penalties WHERE id = ?");
        $result = $stmt->execute([$_POST['id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Penalty deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete penalty']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getPenalty() {
    global $pdo;
    
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Penalty ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, s.first_name, s.last_name, s.course, s.year, s.section 
            FROM site_penalties p 
            LEFT JOIN student s ON p.student_id = s.id_number 
            WHERE p.id = ?
        ");
        $stmt->execute([$_POST['id']]);
        $penalty = $stmt->fetch();
        
        if ($penalty) {
            echo json_encode(['success' => true, 'data' => $penalty]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Penalty not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function listPenalties() {
    global $pdo;
    
    try {
        $where_clause = "";
        $params = [];
        
        // Add filters if provided
        if (isset($_POST['student_id']) && !empty($_POST['student_id'])) {
            $where_clause .= " WHERE p.student_id = ?";
            $params[] = $_POST['student_id'];
        }
        
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $where_clause .= ($where_clause ? " AND" : " WHERE") . " p.status = ?";
            $params[] = $_POST['status'];
        }
        
        $stmt = $pdo->prepare("
            SELECT p.*, s.first_name, s.last_name, s.course, s.year, s.section 
            FROM site_penalties p 
            LEFT JOIN student s ON p.student_id = s.id_number 
            $where_clause
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        $penalties = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $penalties]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudents() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id_number, first_name, last_name, course, year, section FROM student ORDER BY last_name, first_name");
        $students = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $students]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function validateStudent() {
    global $pdo;
    
    if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id_number, first_name, last_name, course, year, section FROM student WHERE id_number = ?");
        $stmt->execute([$_POST['student_id']]);
        $student = $stmt->fetch();
        
        if ($student) {
            echo json_encode(['success' => true, 'data' => $student, 'message' => 'Student found']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student ID ' . $_POST['student_id'] . ' not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>