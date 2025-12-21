<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../db_connection.php';

try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    // If query is empty, return all students (limited to 100)
    if (empty($query)) {
        $stmt = $pdo->prepare("
            SELECT 
                id_number,
                first_name,
                middle_name,
                last_name,
                course,
                year,
                section
            FROM student
            ORDER BY 
                last_name ASC,
                first_name ASC
            LIMIT 100
        ");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Search in student table by first_name, middle_name, last_name, or id_number
        $searchTerm = '%' . $query . '%';
        
        $stmt = $pdo->prepare("
            SELECT 
                id_number,
                first_name,
                middle_name,
                last_name,
                course,
                year,
                section
            FROM student
            WHERE 
                first_name LIKE ? OR
                middle_name LIKE ? OR
                last_name LIKE ? OR
                id_number LIKE ?
            ORDER BY 
                last_name ASC,
                first_name ASC
            LIMIT 100
        ");
        
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Students found',
        'data' => $students
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => []
    ]);
}

