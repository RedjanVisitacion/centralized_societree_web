<?php
// AFPROTECHS Countdown API
// Handles getting and setting attendance countdown timer
// Uses file storage instead of database

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$countdownFile = __DIR__ . '/countdown.json';

function readCountdown() {
    global $countdownFile;
    if (file_exists($countdownFile)) {
        $data = json_decode(file_get_contents($countdownFile), true);
        return $data;
    }
    return null;
}

function writeCountdown($data) {
    global $countdownFile;
    $result = file_put_contents($countdownFile, json_encode($data));
    return $result !== false;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get current countdown
    $data = readCountdown();
    
    if ($data && isset($data['endTime']) && $data['endTime'] > time() * 1000) {
        echo json_encode([
            'success' => true,
            'countdown' => [
                'endTime' => $data['endTime'],
                'isActive' => true
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'countdown' => null
        ]);
    }
    
} elseif ($method === 'POST') {
    // Set new countdown
    $rawInput = file_get_contents('php://input');
    error_log('Countdown POST received: ' . $rawInput);
    $input = json_decode($rawInput, true);
    
    if (!isset($input['hours']) || !isset($input['minutes']) || !isset($input['seconds'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: hours, minutes, seconds']);
        exit();
    }
    
    $hours = intval($input['hours']);
    $minutes = intval($input['minutes']);
    $seconds = intval($input['seconds']);
    
    if ($hours < 0 || $minutes < 0 || $seconds < 0 || ($hours == 0 && $minutes == 0 && $seconds == 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid countdown values']);
        exit();
    }
    
    // Calculate end time in milliseconds
    $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
    $endTimeMs = (time() + $totalSeconds) * 1000;
    
    // Write to file
    if (writeCountdown(['endTime' => $endTimeMs, 'active' => true])) {
        error_log('Countdown saved successfully');
        echo json_encode([
            'success' => true,
            'message' => 'Countdown started successfully',
            'countdown' => [
                'endTime' => $endTimeMs,
                'isActive' => true
            ]
        ]);
    } else {
        error_log('Failed to write countdown file');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save countdown']);
    }
    
} elseif ($method === 'DELETE') {
    // Stop countdown
    if (file_exists($countdownFile)) {
        unlink($countdownFile);
    }
    echo json_encode(['success' => true, 'message' => 'Countdown stopped']);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>