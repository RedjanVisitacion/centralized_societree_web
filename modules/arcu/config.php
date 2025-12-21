<?php
// Database configuration for ARCU Admin Panel
// Connects to the same database as the mobile app

// Use 'localhost' if running on the server (103.125.219.236)
// Use '103.125.219.236' if running locally on XAMPP
$host = 'localhost';  // Change to '103.125.219.236' if running on XAMPP locally
$dbname = 'societree';
$username = 'societree';
$password = 'socieTree12345';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
