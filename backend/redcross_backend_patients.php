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

$patient_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name         = rc_clean($conn, $_POST['name'] ?? '');
        $age          = (int) ($_POST['age'] ?? 0);
        $address      = rc_clean($conn, $_POST['address'] ?? '');
        $case_desc    = rc_clean($conn, $_POST['case_description'] ?? '');
        $service_date = $_POST['date_of_service'] ?? '';
        $remarks      = rc_clean($conn, $_POST['remarks'] ?? '');

        if ($name && $service_date) {
            $stmt = $conn->prepare(
                "INSERT INTO redcross_patients (name, age, address, case_description, date_of_service, remarks, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sissss', $name, $age, $address, $case_desc, $service_date, $remarks);

            if ($stmt->execute()) {
                $patient_message = 'Patient record added.';
            } else {
                $patient_message = 'Error saving patient: ' . $conn->error;
            }

            $stmt->close();
        } else {
            $patient_message = 'Name and date of service are required.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['patient_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM redcross_patients WHERE id = ?");
            $stmt->bind_param('i', $id);

            if ($stmt->execute()) {
                $patient_message = 'Patient record deleted.';
            } else {
                $patient_message = 'Error deleting patient: ' . $conn->error;
            }

            $stmt->close();
        }
    }
}

$patients = [];
$res = $conn->query("SELECT * FROM redcross_patients ORDER BY date_of_service DESC, created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $patients[] = $row;
    }
    $res->free();
}

