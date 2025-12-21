<?php
require_once '../db_connection.php';
require_once 'chat_engine.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize chat engine
$chatEngine = new ChatEngine();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_messages':
            getMessages();
            break;
        case 'send_message':
            sendMessage();
            break;
        case 'get_students':
            getStudents();
            break;
        case 'get_student_info':
            getStudentInfo();
            break;
        case 'delete_message':
            deleteMessage();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function getMessages() {
    global $pdo;
    
    $student_id = $_GET['student_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    
    if (!$student_id) {
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }
    
    $sql = "SELECT c.*, s.first_name, s.last_name, s.course, s.year, s.section 
            FROM site_chat c 
            JOIN student s ON c.student_id = s.id_number 
            WHERE c.student_id = ? OR c.is_admin = 1
            ORDER BY c.timestamp DESC 
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $limit]);
    $messages = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
}

function sendMessage() {
    global $pdo;
    
    $student_id = $_POST['student_id'] ?? null;
    $message = $_POST['message'] ?? null;
    $is_admin = $_POST['is_admin'] ?? 0;
    $reply_to = $_POST['reply_to'] ?? null;
    
    if (!$student_id || !$message) {
        echo json_encode(['error' => 'Student ID and message are required']);
        return;
    }
    
    // Verify student exists
    $stmt = $pdo->prepare("SELECT id_number FROM student WHERE id_number = ?");
    $stmt->execute([$student_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Student not found']);
        return;
    }
    
    $sql = "INSERT INTO site_chat (student_id, message, is_admin, reply_to) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $message, $is_admin, $reply_to]);
    
    $messageId = $pdo->lastInsertId();
    
    // Process message through chat engine
    global $chatEngine;
    $chatEngine->processMessage($messageId, $student_id, $message, $is_admin);
    
    echo json_encode(['success' => true, 'message_id' => $messageId]);
}

function getStudents() {
    global $pdo;
    
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT id_number, first_name, middle_name, last_name, course, year, section 
            FROM student 
            WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
               OR id_number LIKE ?
               OR course LIKE ?
            ORDER BY last_name, first_name
            LIMIT 20";
    
    $searchTerm = "%$search%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function getStudentInfo() {
    global $pdo;
    
    $student_id = $_GET['student_id'] ?? null;
    
    if (!$student_id) {
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM student WHERE id_number = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'student' => $student]);
}

function deleteMessage() {
    global $pdo;
    
    $message_id = $_POST['message_id'] ?? null;
    $student_id = $_POST['student_id'] ?? null;
    
    if (!$message_id || !$student_id) {
        echo json_encode(['error' => 'Message ID and Student ID are required']);
        return;
    }
    
    // Only allow deletion of own messages or admin messages
    $sql = "DELETE FROM site_chat WHERE id = ? AND (student_id = ? OR is_admin = 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$message_id, $student_id]);
    
    echo json_encode(['success' => true]);
}
?>