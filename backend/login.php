<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once __DIR__ . '/../db_connection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['success'=>false,'message'=>'Invalid response']); exit(); }
$student_id = isset($data['student_id']) ? trim((string)$data['student_id']) : '';
$password   = isset($data['password']) ? (string)$data['password'] : '';
if ($student_id === '' || $password === '') { echo json_encode(['success'=>false,'message'=>'Missing credentials']); exit(); }

try {
  $stmt = $pdo->prepare('SELECT id, student_id, password_hash, role, department, position FROM users WHERE student_id = ? LIMIT 1');
  $stmt->execute([$student_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) { echo json_encode(['success'=>false,'message'=>'User not found']); exit(); }
  $hash = (string)$user['password_hash'];
  $ok = false;
  if (preg_match('/^\$2[aby]\$/', $hash)) {
    $ok = password_verify($password, $hash);
  } else {
    $ok = hash_equals($hash, $password);
    if ($ok) {
      $new = password_hash($password, PASSWORD_BCRYPT);
      $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
      $up->execute([$new, $user['id']]);
      $hash = $new;
    }
  }
  if (!$ok) { echo json_encode(['success'=>false,'message'=>'Invalid credentials']); exit(); }

  $_SESSION['user_id'] = (int)$user['id'];
  $_SESSION['student_id'] = $user['student_id'];
  $_SESSION['role'] = $user['role'] ?: 'student';
  $_SESSION['department'] = $user['department'] ?: '';
  $_SESSION['position'] = $user['position'] ?: '';

  echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user_id' => (int)$user['id'],
    'role' => $_SESSION['role'],
    'department' => $_SESSION['department'],
    'position' => $_SESSION['position'],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
