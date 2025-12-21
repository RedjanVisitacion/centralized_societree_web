<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}

// Get student_id from POST or GET
$student_id = $_POST['student_id'] ?? $_GET['student_id'] ?? null;
$event_id = $_POST['event_id'] ?? $_GET['event_id'] ?? null;

if (!$student_id) {
    echo json_encode([
        "success" => false,
        "message" => "Student ID is required"
    ]);
    exit;
}

// If no event_id provided, try to get the current active event
if (!$event_id) {
    try {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // First try to find events happening today
        $event_sql = "
            SELECT event_id
            FROM afprotechs_events 
            WHERE DATE(start_date) <= '$today' AND DATE(end_date) >= '$today'
            ORDER BY start_date ASC 
            LIMIT 1
        ";
        $event_result = $conn->query($event_sql);
        
        if ($event_result && $event_result->num_rows > 0) {
            $event_row = $event_result->fetch_assoc();
            $event_id = $event_row['event_id'];
        } else {
            // If no events today, look for upcoming events (next 7 days)
            $upcoming_event_sql = "
                SELECT event_id
                FROM afprotechs_events 
                WHERE start_date > '$today' AND start_date <= DATE_ADD('$today', INTERVAL 7 DAY)
                ORDER BY start_date ASC 
                LIMIT 1
            ";
            $upcoming_result = $conn->query($upcoming_event_sql);
            
            if ($upcoming_result && $upcoming_result->num_rows > 0) {
                $upcoming_row = $upcoming_result->fetch_assoc();
                $event_id = $upcoming_row['event_id'];
            } else {
                // If no upcoming events, get the most recent event
                $recent_event_sql = "
                    SELECT event_id
                    FROM afprotechs_events 
                    ORDER BY ABS(DATEDIFF(start_date, '$today')) ASC 
                    LIMIT 1
                ";
                $recent_result = $conn->query($recent_event_sql);
                
                if ($recent_result && $recent_result->num_rows > 0) {
                    $recent_row = $recent_result->fetch_assoc();
                    $event_id = $recent_row['event_id'];
                }
            }
        }
    } catch (Exception $e) {
        // If there's an error getting events, continue with null event_id
        error_log("Error getting event_id: " . $e->getMessage());
    }
}

if (!$event_id) {
    echo json_encode([
        "success" => false,
        "message" => "No active or upcoming event. Attendance requires at least one valid event in the afprotechs_events table.",
        "student_id" => $student_id
    ]);
    exit;
}

try {
    // Clean the student_id (remove any whitespace or special characters)
    $student_id = trim($student_id);
    
    // First, look up the student in the student table (has names)
    $student_sql = "SELECT id_number, first_name, middle_name, last_name, course, year, section 
                    FROM student WHERE id_number = ?";
    $stmt = $conn->prepare($student_sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    
    // If not found, try users table as fallback
    if ($student_result->num_rows === 0) {
        $student_sql = "SELECT student_id as id_number, first_name, middle_name, last_name, course, year_level as year, section 
                        FROM users WHERE student_id = ?";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $student_result = $stmt->get_result();
    }
    
    if ($student_result->num_rows === 0) {
        // Return debug info to help troubleshoot
        echo json_encode([
            "success" => false,
            "message" => "Student not found",
            "student_id" => $student_id,
            "debug" => "No student with id_number matching '$student_id' in database"
        ]);
        exit;
    }
    
    $student = $student_result->fetch_assoc();
    // Build full name from first, middle, last
    $name_parts = array_filter([
        $student['first_name'] ?? '',
        $student['middle_name'] ?? '',
        $student['last_name'] ?? ''
    ]);
    $student_name = !empty($name_parts) ? implode(' ', $name_parts) : 'Unknown';
    
    // Create afprotechs_attendance table if it doesn't exist
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS afprotechs_attendance (
            afprotechs_id_attendance INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_number VARCHAR(64) NOT NULL,
            first_name VARCHAR(128) NULL,
            middle_name VARCHAR(128) NULL,
            last_name VARCHAR(128) NULL,
            course VARCHAR(64) NULL,
            year VARCHAR(16) NULL,
            section VARCHAR(16) NULL,
            event_id INT UNSIGNED NULL,
            morning_in TIME NULL,
            morning_out TIME NULL,
            afternoon_in TIME NULL,
            afternoon_out TIME NULL,
            attendance_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_date (id_number, attendance_date),
            INDEX idx_event (event_id),
            INDEX idx_attendance_date (attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $conn->query($create_table_sql);
    
    // Ensure columns allow NULL and add name columns (fix for existing tables)
    $conn->query("ALTER TABLE afprotechs_attendance MODIFY morning_in TIME NULL");
    $conn->query("ALTER TABLE afprotechs_attendance MODIFY morning_out TIME NULL");
    $conn->query("ALTER TABLE afprotechs_attendance MODIFY afternoon_in TIME NULL");
    $conn->query("ALTER TABLE afprotechs_attendance MODIFY afternoon_out TIME NULL");
    $conn->query("ALTER TABLE afprotechs_attendance MODIFY status VARCHAR(50) NULL");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN first_name VARCHAR(128) NULL AFTER id_number");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN middle_name VARCHAR(128) NULL AFTER first_name");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN last_name VARCHAR(128) NULL AFTER middle_name");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN course VARCHAR(64) NULL AFTER last_name");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN year VARCHAR(16) NULL AFTER course");
    $conn->query("ALTER TABLE afprotechs_attendance ADD COLUMN section VARCHAR(16) NULL AFTER year");

    // Check if student already has attendance for today (prevent duplicate scans)
    $today = date('Y-m-d');
    $check_sql = "SELECT afprotechs_id_attendance FROM afprotechs_attendance 
                  WHERE id_number = ? AND DATE(attendance_date) = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $student_id, $today);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $yr = $student['year'] ?? '';
        $sec = $student['section'] ?? '';
        echo json_encode([
            "success" => true,
            "message" => "Already recorded today",
            "already_recorded" => true,
            "student" => [
                "id_number" => $student['id_number'],
                "name" => $student_name,
                "course" => $student['course'] ?? '',
                "year" => $yr,
                "section" => trim($yr . $sec)
            ]
        ]);
        exit;
    }
    
    // Record attendance with Philippine time
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $current_hour = (int)date('H');
    
    // Get student details for insert
    $first_name = $student['first_name'] ?? '';
    $middle_name = $student['middle_name'] ?? '';
    $last_name = $student['last_name'] ?? '';
    $course = $student['course'] ?? '';
    $year = $student['year'] ?? '';
    $section = $student['section'] ?? '';
    
    // Determine if it's morning (8AM-12PM) or afternoon (1PM-5PM)
    if ($current_hour >= 8 && $current_hour < 12) {
        // Morning attendance
        $insert_sql = "INSERT INTO afprotechs_attendance (id_number, first_name, middle_name, last_name, course, year, section, event_id, morning_in, attendance_date) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE morning_in = VALUES(morning_in)";
    } else {
        // Afternoon attendance
        $insert_sql = "INSERT INTO afprotechs_attendance (id_number, first_name, middle_name, last_name, course, year, section, event_id, afternoon_in, attendance_date) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE afternoon_in = VALUES(afternoon_in)";
    }
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssssiss", $student_id, $first_name, $middle_name, $last_name, $course, $year, $section, $event_id, $current_time, $current_date);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Attendance recorded successfully",
            "already_recorded" => false,
            "student" => [
                "id_number" => $student['id_number'],
                "name" => $student_name,
                "course" => $student['course'] ?? '',
                "year" => $year,
                "section" => (strlen($section) > 1 && is_numeric(substr($section, 0, 1))) ? $section : trim($year . $section)
            ],
            "attendance_time" => date('g:i A'),
            "attendance_date" => date('M d, Y')
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to record attendance: " . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
