<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once __DIR__ . '/../db_connection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$student_id = isset($data['student_id']) ? trim((string)$data['student_id']) : '';
$current_password = isset($data['current_password']) ? (string)$data['current_password'] : '';
$new_password = isset($data['new_password']) ? (string)$data['new_password'] : '';

if ($student_id === '' || $current_password === '' || $new_password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

try {
    // Get user's current password hash
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE student_id = ? LIMIT 1');
    $stmt->execute([$student_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    $hash = (string)$user['password_hash'];
    $ok = false;

    // Verify current password
    if (preg_match('/^\$2[aby]\$/', $hash)) {
        $ok = password_verify($current_password, $hash);
    } else {
        $ok = hash_equals($hash, $current_password);
    }

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Hash new password and update
    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([$new_hash, $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

