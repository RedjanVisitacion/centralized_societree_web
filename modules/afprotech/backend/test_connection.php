<?php
// Simple connection test endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Return server info and timestamp
echo json_encode([
    'success' => true,
    'message' => 'Server is online and responding',
    'data' => [
        'server_time' => date('Y-m-d H:i:s'),
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'php_version' => phpversion(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]
]);
?>