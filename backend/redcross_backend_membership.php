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
        return htmlspecialchars(trim($conn->real_escape_string((string)$value)), ENT_QUOTES, 'UTF-8');
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        // Map the existing form fields to the new schema in redcross_members
        $full_name_input = rc_clean($conn, $_POST['full_name'] ?? '');
        $id_number       = rc_clean($conn, $_POST['id_number'] ?? '');
        $course          = rc_clean($conn, $_POST['department'] ?? ''); // Department field in UI -> course in DB
        $year_section    = rc_clean($conn, $_POST['year_level'] ?? ''); // Year level in UI -> year_section in DB
        $email           = rc_clean($conn, $_POST['email'] ?? '');
        $phone           = rc_clean($conn, $_POST['phone'] ?? '');
        $campus          = 'USTP - CDO';
        $status          = 'active'; // new enum: active|inactive|suspended

        // Simple name splitter: first token = first_name, last token = last_name, middle = everything in between
        $first_name  = '';
        $middle_name = null;
        $last_name   = '';

        if ($full_name_input) {
            $parts = preg_split('/\s+/', $full_name_input);
            if (count($parts) === 1) {
                $first_name = $parts[0];
                $last_name  = $parts[0];
            } elseif (count($parts) === 2) {
                $first_name = $parts[0];
                $last_name  = $parts[1];
            } else {
                $first_name  = array_shift($parts);
                $last_name   = array_pop($parts);
                $middle_name = implode(' ', $parts);
            }
        }

        if ($first_name && $last_name && $id_number) {
            // Check if ID number already exists
            $check_stmt = $conn->prepare("SELECT id FROM redcross_members WHERE id_number = ?");
            $check_stmt->bind_param('s', $id_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'ID number already exists.';
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                // Generate member_id (e.g., RCY-2025-001)
                $year = date('Y');
                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM redcross_members WHERE member_id LIKE ?");
                $like_pattern = "RCY-{$year}-%";
                $count_stmt->bind_param('s', $like_pattern);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $count_row = $count_result->fetch_assoc();
                $next_number = str_pad($count_row['count'] + 1, 3, '0', STR_PAD_LEFT);
                $member_id = "RCY-{$year}-{$next_number}";
                $count_stmt->close();
                
                $stmt = $conn->prepare(
                    "INSERT INTO redcross_members (
                        id_number,
                        first_name,
                        middle_name,
                        last_name,
                        course,
                        year_section,
                        email,
                        phone,
                        campus,
                        member_id,
                        status,
                        created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt->bind_param(
                    'sssssssssss',
                    $id_number,
                    $first_name,
                    $middle_name,
                    $last_name,
                    $course,
                    $year_section,
                    $email,
                    $phone,
                    $campus,
                    $member_id,
                    $status
                );

                if ($stmt->execute()) {
                    $message = 'Member application submitted successfully. Member ID: ' . $member_id;
                } else {
                    $message = 'Error saving member: ' . $conn->error;
                }

                $stmt->close();
            }
        } else {
            $message = 'Full name and ID number are required.';
        }
    } elseif ($action === 'update') {
        $member_id       = intval($_POST['member_id'] ?? 0);
        $full_name_input = rc_clean($conn, $_POST['full_name'] ?? '');
        $course          = rc_clean($conn, $_POST['department'] ?? '');
        $year_section    = rc_clean($conn, $_POST['year_level'] ?? '');
        $email           = rc_clean($conn, $_POST['email'] ?? '');
        $phone           = rc_clean($conn, $_POST['phone'] ?? '');

        // Re-split full name for update
        $first_name  = '';
        $middle_name = null;
        $last_name   = '';
        if ($full_name_input) {
            $parts = preg_split('/\s+/', $full_name_input);
            if (count($parts) === 1) {
                $first_name = $parts[0];
                $last_name  = $parts[0];
            } elseif (count($parts) === 2) {
                $first_name = $parts[0];
                $last_name  = $parts[1];
            } else {
                $first_name  = array_shift($parts);
                $last_name   = array_pop($parts);
                $middle_name = implode(' ', $parts);
            }
        }

        if ($member_id > 0 && $first_name && $last_name) {
            $stmt = $conn->prepare(
                "UPDATE redcross_members
                 SET first_name = ?, middle_name = ?, last_name = ?, course = ?, year_section = ?, email = ?, phone = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('sssssssi', $first_name, $middle_name, $last_name, $course, $year_section, $email, $phone, $member_id);

            if ($stmt->execute()) {
                $message = 'Member updated.';
            } else {
                $message = 'Error updating member: ' . $conn->error;
            }

            $stmt->close();
        } else {
            $message = 'Member ID and full name are required.';
        }
    } elseif ($action === 'delete') {
        $member_id = intval($_POST['member_id'] ?? 0);
        if ($member_id > 0) {
            $stmt = $conn->prepare("DELETE FROM redcross_members WHERE id = ?");
            $stmt->bind_param('i', $member_id);

            if ($stmt->execute()) {
                $message = 'Member deleted.';
            } else {
                $message = 'Error deleting member: ' . $conn->error;
            }

            $stmt->close();
        }
    } elseif ($action === 'status') {
        $member_id = intval($_POST['member_id'] ?? 0);
        $status    = rc_clean($conn, $_POST['status'] ?? '');

        // New allowed statuses based on schema enum: active, inactive, suspended
        if ($member_id > 0 && in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $stmt = $conn->prepare("UPDATE redcross_members SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $member_id);

            if ($stmt->execute()) {
                $message = 'Member status updated to ' . $status . '.';
            } else {
                $message = 'Error updating status: ' . $conn->error;
            }

            $stmt->close();
        }
    }
}

$filter_status     = $_GET['status'] ?? '';
$filter_year       = $_GET['year'] ?? '';
$filter_department = $_GET['department'] ?? '';

$where = [];
if ($filter_status !== '') {
    $where[] = "status = '" . rc_clean($conn, $filter_status) . "'";
}
if ($filter_year !== '') {
    // UI "Year Level" filter -> year_section column
    $where[] = "year_section = '" . rc_clean($conn, $filter_year) . "'";
}
if ($filter_department !== '') {
    // UI "Department" filter -> course column
    $where[] = "course = '" . rc_clean($conn, $filter_department) . "'";
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$members = [];
$sql = "SELECT id,
               id_number,
               first_name,
               middle_name,
               last_name,
               full_name,
               course,
               year_section,
               email,
               phone,
               campus,
               member_id,
               status,
               created_at
        FROM redcross_members
        $where_sql
        ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $result->free();
}

