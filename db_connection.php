<?php
// db_connection.php
// Primary database credentials (remote)
$host = '103.125.219.236';
$user = 'societree';
$password = 'socieTree12345';
$database = 'societree';

function connect_pdo($h, $u, $p, $db) {
    $dsn = "mysql:host={$h};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

try {
    // Try remote first
    $pdo = connect_pdo($host, $user, $password, $database);
} catch (PDOException $e) {
    // Fall back to local XAMPP defaults
    try {
        $pdo = connect_pdo('localhost', 'root', '', $database);
    } catch (PDOException $e2) {
        // Final failure
        http_response_code(500);
        die('Connection failed');
    }
}
?>