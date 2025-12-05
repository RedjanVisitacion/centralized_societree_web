<?php
require_once '../../../db_connection.php';
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : 'search';

try {
    if ($action === 'search') {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if ($q === '') {
            echo json_encode([]);
            exit;
        }
        $like = "%{$q}%";
        $stmt = $pdo->prepare(
            "SELECT id, student_id, first_name, middle_name, last_name, position, organization, photo_url
             FROM candidates_registration
             WHERE CONCAT(first_name,' ',middle_name,' ',last_name) LIKE :q
                OR student_id LIKE :q
                OR position LIKE :q
                OR organization LIKE :q
             ORDER BY last_name, first_name
             LIMIT 10"
        );
        $stmt->execute([':q' => $like]);
        $rows = $stmt->fetchAll();
        echo json_encode($rows);
        exit;
    }

    if ($action === 'detail') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM candidates_registration WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        echo json_encode($row);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
