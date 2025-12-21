<?php
// usg_get_attendance.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once(__DIR__ . '/../db_connection.php');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Get filter parameters from POST request
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$course = isset($_POST['course']) ? trim($_POST['course']) : '';
$year = isset($_POST['year']) ? trim($_POST['year']) : '';
$section = isset($_POST['section']) ? trim($_POST['section']) : '';
$role = isset($_POST['role']) ? trim($_POST['role']) : '';

// Build SQL query with filters
$sql = "SELECT * FROM usg_attendace WHERE 1=1";


if (!empty($name)) {
    $sql .= " AND (first_name LIKE '%" . $conn->real_escape_string($name) . "%' 
                  OR last_name LIKE '%" . $conn->real_escape_string($name) . "%')";
}

if (!empty($id)) {
    $sql .= " AND id_number LIKE '%" . $conn->real_escape_string($id) . "%'";
}

if (!empty($course)) {
    $sql .= " AND course LIKE '%" . $conn->real_escape_string($course) . "%'";
}

// Add new filters for the additional columns
if (!empty($year)) {
    $sql .= " AND year = '" . $conn->real_escape_string($year) . "'";
}

if (!empty($section)) {
    $sql .= " AND section LIKE '%" . $conn->real_escape_string($section) . "%'";
}

if (!empty($role)) {
    $sql .= " AND role LIKE '%" . $conn->real_escape_string($role) . "%'";
}

$sql .= " ORDER BY id_number ASC";

$result = $conn->query($sql);

if ($result) {
    $data = [];
        while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error executing query: ' . $conn->error,
        'query' => $sql
    ]);
}

$conn->close();
?>