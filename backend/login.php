<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once __DIR__ . '/../db_connection.php';

// Ensure new profile columns exist for compatibility
try {
  $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(128) NULL");
} catch (Throwable $e) {}
try {
  $pdo->exec("ALTER TABLE users ADD COLUMN middle_name VARCHAR(128) NULL");
} catch (Throwable $e) {}
try {
  $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(128) NULL");
} catch (Throwable $e) {}
try {
  $pdo->exec("ALTER TABLE users ADD COLUMN year_level VARCHAR(32) NULL");
} catch (Throwable $e) {}
try {
  $pdo->exec("ALTER TABLE users ADD COLUMN section VARCHAR(64) NULL");
} catch (Throwable $e) {}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['success'=>false,'message'=>'Invalid response']); exit(); }
$student_id = isset($data['student_id']) ? trim((string)$data['student_id']) : '';
$password   = isset($data['password']) ? (string)$data['password'] : '';
if ($student_id === '' || $password === '') { echo json_encode(['success'=>false,'message'=>'Missing credentials']); exit(); }

try {
  $stmt = $pdo->prepare('SELECT id, student_id, password_hash, role, department, position, first_name, middle_name, last_name, year_level, section FROM users WHERE student_id = ? LIMIT 1');
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
  $_SESSION['first_name'] = $user['first_name'] ?? '';
  $_SESSION['middle_name'] = $user['middle_name'] ?? '';
  $_SESSION['last_name'] = $user['last_name'] ?? '';
  $_SESSION['year_level'] = $user['year_level'] ?? '';
  $_SESSION['section'] = $user['section'] ?? '';
  $name_parts = array_filter([$_SESSION['first_name'], $_SESSION['middle_name'], $_SESSION['last_name']]);
  $_SESSION['full_name'] = $name_parts ? implode(' ', $name_parts) : '';

  echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user_id' => (int)$user['id'],
    'role' => $_SESSION['role'],
    'department' => $_SESSION['department'],
    'position' => $_SESSION['position'],
    'first_name' => $_SESSION['first_name'],
    'middle_name' => $_SESSION['middle_name'],
    'last_name' => $_SESSION['last_name'],
    'full_name' => $_SESSION['full_name'],
    'year_level' => $_SESSION['year_level'],
    'section' => $_SESSION['section'],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
