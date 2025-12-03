<?php
session_start();
// If not logged in, send back to login
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit();
}
// Default to student if role missing
$role = isset($_SESSION['role']) ? strtolower((string)$_SESSION['role']) : 'student';

// Route based on role
if ($role === 'admin') {
  header('Location: admin/elecom_dashboard.php');
  exit();
}

header('Location: student/elecom_dashboard.php');
exit();
