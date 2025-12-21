<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Simple test endpoint for QR scanning without database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Try form data
        $input = $_POST;
    }

    $student_id = $input['student_id'] ?? '';
    $organization = $input['organization'] ?? 'afprotechs';

    if (empty($student_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID is required'
        ]);
        exit;
    }

    // Simulate successful attendance recording
    echo json_encode([
        'success' => true,
        'message' => 'Attendance recorded successfully (TEST MODE)',
        'student_id' => $student_id,
        'student_name' => 'Test Student ' . substr($student_id, -4),
        'attendance_date' => date('Y-m-d H:i:s'),
        'organization' => $organization,
        'note' => 'This is a test response. Database connection not required.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>