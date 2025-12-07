<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../db_connection.php';

function json_fail($msg, $code = 400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit(); }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { json_fail('Invalid request'); }
$identifier = trim((string)($data['identifier'] ?? ''));
$otp = trim((string)($data['otp'] ?? ''));
$new_password = (string)($data['new_password'] ?? '');
if ($identifier === '' || $otp === '' || $new_password === '') { json_fail('Missing fields'); }

try {
  $stmt = $pdo->prepare('SELECT id, otp_code, otp_expires_at FROM users WHERE (LOWER(email) = LOWER(?) AND email IS NOT NULL) OR student_id = ? LIMIT 1');
  $stmt->execute([$identifier, $identifier]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) { json_fail('User not found'); }

  if (empty($user['otp_code']) || empty($user['otp_expires_at'])) { json_fail('No active OTP. Request a new code.'); }
  $now = new DateTime('now');
  $expiry = new DateTime($user['otp_expires_at']);
  if ($now > $expiry) { json_fail('OTP expired. Request a new code.'); }
  if (!password_verify($otp, (string)$user['otp_code'])) { json_fail('Invalid OTP'); }

  $hash = password_hash($new_password, PASSWORD_BCRYPT);
  $up = $pdo->prepare('UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?');
  $up->execute([$hash, $user['id']]);

  echo json_encode(['success'=>true,'message'=>'Password reset successful. You can now sign in.']);
} catch (Throwable $e) {
  json_fail('Server error', 500);
}
