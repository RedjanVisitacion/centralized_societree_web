<?php
// Centralized SMTP configuration for PHPMailer
// Change credentials here only

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'socie.tre3@gmail.com');
define('SMTP_PASS', 'uhap lvrv uolm myzm'); // Gmail App Password
// Use 'ssl' on 465 or 'tls' on 587
define('SMTP_SECURE', 'ssl');
define('SMTP_PORT', 465);
// From address and name
define('SMTP_FROM', SMTP_USER);
define('SMTP_FROM_NAME', 'SocieTree');

// Resolve PHPMailer src directory from common locations
function phpmailer_src_path() {
  $candidates = [
    __DIR__ . '/phpmailer/src',          // backend/mailer/phpmailer/src
    dirname(__DIR__) . '/phpmailer/src', // backend/phpmailer/src
    dirname(__DIR__, 2) . '/phpmailer/src', // project-root/phpmailer/src
  ];
  foreach ($candidates as $p) {
    if (is_file($p . '/PHPMailer.php') && is_file($p . '/SMTP.php') && is_file($p . '/Exception.php')) {
      return $p;
    }
  }
  return null;
}

// Returns configured PHPMailer instance or null on failure; sets $err string
function new_configured_mailer(&$err) {
  $err = null;
  $src = phpmailer_src_path();
  if ($src === null) { $err = 'PHPMailer library missing. Place it under backend/mailer/phpmailer/src or project-root/phpmailer/src'; return null; }
  require_once $src . '/Exception.php';
  require_once $src . '/PHPMailer.php';
  require_once $src . '/SMTP.php';
  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  // SMTP setup
  $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port = SMTP_PORT;
  $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
  $mail->isHTML(true);
  return $mail;
}
