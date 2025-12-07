<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/mailer/mailer_config.php';

function json_fail($msg, $code = 400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit(); }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { json_fail('Invalid request'); }
$identifier = trim((string)($data['identifier'] ?? '')); // email or student_id
$emailOverride = trim((string)($data['email'] ?? ''));
if ($identifier === '') { json_fail('Provide email or ID number'); }

try {
  // find user by email or student_id
  $stmt = $pdo->prepare('SELECT id, email, student_id, first_name, last_name FROM users WHERE (LOWER(email) = LOWER(?) AND email IS NOT NULL) OR student_id = ? LIMIT 1');
  $stmt->execute([$identifier, $identifier]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user && $emailOverride === '') { json_fail('User not found'); }

  // generate 6-digit OTP and store hash + expiry (10 minutes)
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $hash = password_hash($code, PASSWORD_BCRYPT);
  $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

  $up = $pdo->prepare('UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?');
  $up->execute([$hash, $expires_at, $user['id']]);

  // send email via PHPMailer
  $err = null; $mail = new_configured_mailer($err);
  if ($mail === null) { json_fail($err ?? 'Mailer not available', 500); }
  $toName = trim((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
  $sendTo = $emailOverride !== '' ? $emailOverride : ($user['email'] ?? '');
  if ($sendTo === '') { json_fail('No email available to send code'); }
  try {
    $mail->clearAllRecipients();
    $mail->addAddress($sendTo, $toName !== '' ? $toName : ($user['student_id'] ?? 'User'));
    $mail->Subject = 'SocieTree Password Reset Code';
    $mail->Body = '<p>Here is your one-time verification code:</p>'
      . '<p style="font-size:22px;font-weight:bold;letter-spacing:2px">' . htmlspecialchars($code) . '</p>'
      . '<p>This code will expire in 10 minutes. If you did not request this, you can ignore this email.</p>';
    $mail->AltBody = "Your SocieTree verification code: $code (expires in 10 minutes).";
    $mail->send();
  } catch (Throwable $e) {
    json_fail('Failed to send email. Please try again later.', 500);
  }

  echo json_encode(['success'=>true,'message'=>'OTP sent to your email','email'=>$sendTo]);
} catch (Throwable $e) {
  json_fail('Server error', 500);
}
