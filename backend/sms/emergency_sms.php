<?php
/**
 * SMS Chef - Emergency SMS with Location
 * Converts Flutter emergency SMS functionality to PHP web application
 */

header('Content-Type: application/json');

// Handle CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
require_once __DIR__ . '/config_emergency.php';

/**
 * Send emergency SMS with location via SMSChef API
 */
function sendEmergencySMS($phoneNumber, $name, $latitude, $longitude) {
    try {
        // Create Google Maps link
        $mapsLink = "https://maps.google.com/?q={$latitude},{$longitude}";
        
        // Create emergency message
        $deviceName = defined('SMSCHEF_DEVICE_NAME') ? SMSCHEF_DEVICE_NAME : 'Device';
        $message = "🚨 EMERGENCY ALERT - Saferide 🚨\n\n";
        $message .= "⚠️ I need help!\n";
        $message .= "📍 My current location:\n";
        $message .= "$mapsLink\n\n";
        $message .= "Please send assistance immediately!\n";
        $message .= "👤 From: $name\n";
        $message .= "📱 Sent via: $deviceName";
        
        // Prepare API request
        $url = SMSCHEF_API_URL;
        
        $postData = [
            'secret' => SMSCHEF_SECRET,
            'mode' => 'devices',
            'device' => SMSCHEF_DEVICE,
            'sim' => SMSCHEF_SIM,
            'priority' => SMSCHEF_PRIORITY,
            'phone' => $phoneNumber,
            'message' => $message
        ];
        
        // Send request using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => "cURL error: $curlError"
            ];
        }
        
        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully via SMSChef!',
                'response' => $response,
                'maps_link' => $mapsLink
            ];
        } else {
            return [
                'success' => false,
                'error' => "Failed to send SMS. HTTP Code: $httpCode",
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => "Exception: " . $e->getMessage()
        ];
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // If JSON decode failed, try form data
        if (!$input) {
            $input = $_POST;
        }
        
        // Validate required fields
        $phoneNumber = isset($input['phone']) ? trim($input['phone']) : '';
        $name = isset($input['name']) ? trim($input['name']) : '';
        $latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
        $longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
        
        $errors = [];
        
        if (empty($phoneNumber)) {
            $errors[] = 'Phone number is required';
        }
        
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if ($latitude === null || $longitude === null) {
            $errors[] = 'Location coordinates are required';
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'errors' => $errors
            ]);
            exit;
        }
        
        // Validate phone number format (basic)
        if (!preg_match('/^\+?[1-9]\d{9,14}$/', preg_replace('/[^0-9+]/', '', $phoneNumber))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid phone number format'
            ]);
            exit;
        }
        
        // Validate coordinates
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid coordinates'
            ]);
            exit;
        }
        
        // Send SMS
        $result = sendEmergencySMS($phoneNumber, $name, $latitude, $longitude);
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
}

