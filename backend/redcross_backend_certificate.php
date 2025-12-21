<?php
require_once __DIR__ . '/../db_connection.php';

// Ensure a mysqli connection named $conn exists for this backend
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        die('MySQLi connection failed: ' . $conn->connect_error);
    }
}

if (!function_exists('rc_clean')) {
    function rc_clean($conn, $value) {
        return htmlspecialchars(trim($conn->real_escape_string($value)), ENT_QUOTES, 'UTF-8');
    }
}

$certificate_data = null;
$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;

if ($member_id > 0) {
    // Get the latest certificate for this member
    $stmt = $conn->prepare(
        "SELECT c.id, c.member_id, c.total_hours, c.issued_at, m.full_name
         FROM redcross_certificates c
         JOIN redcross_members m ON m.id = c.member_id
         WHERE c.member_id = ?
         ORDER BY c.issued_at DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $certificate_data = $row;
    }
    
    $stmt->close();
}

