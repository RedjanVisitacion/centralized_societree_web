<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/mailer/mailer_config.php';
require_once __DIR__ . '/sms/config_emergency.php';

function json_fail($msg, $code = 400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit(); }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { json_fail('Invalid request'); }
$identifier = trim((string)($data['identifier'] ?? '')); // email or student_id
$emailOverride = trim((string)($data['email'] ?? ''));
$channel = strtolower(trim((string)($data['channel'] ?? 'email')));
$phoneRaw = trim((string)($data['phone'] ?? ''));
if ($identifier === '') { json_fail('Provide email or ID number'); }

function normalize_ph_phone($phone){
  $p = preg_replace('/[^0-9+]/', '', (string)$phone);
  if ($p === '') return $p;
  if ($p[0] === '+') return $p;
  $digits = preg_replace('/\D/', '', $p);
  if (preg_match('/^0\d{10}$/', $digits)) { return '+63' . substr($digits, 1); }
  if (preg_match('/^9\d{9}$/', $digits)) { return '+63' . $digits; }
  return $p;
}
function same_phone($a, $b){
  $da = preg_replace('/\D/', '', normalize_ph_phone($a));
  $db = preg_replace('/\D/', '', normalize_ph_phone($b));
  if (strlen($da) >= 10) { $da = substr($da, -10); }
  if (strlen($db) >= 10) { $db = substr($db, -10); }
  return $da !== '' && $da === $db;
}

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

  if ($channel === 'sms') {
    if ($phoneRaw === '') { json_fail('Enter your phone number'); }
    if (!$user) { json_fail('User not found for SMS'); }
    $sid = (string)($user['student_id'] ?? '');
    if ($sid === '') { json_fail('No ID number on record for this user'); }
    $pstmt = $pdo->prepare('SELECT phone_number FROM student WHERE id_number = ? LIMIT 1');
    $pstmt->execute([$sid]);
    $row = $pstmt->fetch(PDO::FETCH_ASSOC);
    $dbPhone = '';
    if ($row && isset($row['phone_number']) && trim((string)$row['phone_number']) !== '') {
      $dbPhone = (string)$row['phone_number'];
    } else {
      // Fallback: try users.phone if available
      try {
        $uStmt = $pdo->prepare('SELECT phone FROM users WHERE student_id = ? LIMIT 1');
        $uStmt->execute([$sid]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow && isset($uRow['phone']) && trim((string)$uRow['phone']) !== '') {
          $dbPhone = (string)$uRow['phone'];
        }
      } catch (Throwable $e2) {
        // ignore if column doesn't exist
      }
    }
    if ($dbPhone === '') { json_fail('No phone number on record for this ID'); }
    if (!same_phone($phoneRaw, $dbPhone)) { json_fail('The phone number does not match the ID number on record'); }
    $toPhone = normalize_ph_phone($phoneRaw);
    $message = 'Your SocieTree password reset code is ' . $code . '. It expires in 10 minutes.';
    $url = SMSCHEF_API_URL;
    $postData = [
      'secret' => SMSCHEF_SECRET,
      'mode' => 'devices',
      'device' => SMSCHEF_DEVICE,
      'sim' => SMSCHEF_SIM,
      'priority' => SMSCHEF_PRIORITY,
      'phone' => $toPhone,
      'message' => $message
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError || $httpCode !== 200) {
      json_fail('Failed to send SMS. Please try again later.', 500);
    }
    echo json_encode(['success'=>true,'message'=>'OTP sent via SMS']);
    exit();
  }

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
