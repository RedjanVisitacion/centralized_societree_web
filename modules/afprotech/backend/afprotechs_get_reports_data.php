<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

// Database connection
$conn = null;
try {
    $host = '103.125.219.236';
    $user = 'societree';
    $password = 'socieTree12345';
    $database = 'societree';
    
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        $conn = @new mysqli('localhost', 'root', '', $database);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $report_type = $_GET['type'] ?? 'all';
    
    $response = [
        'success' => true,
        'data' => [],
        'summary' => []
    ];
    
    switch ($report_type) {
        case 'announcements':
            $sql = "SELECT * FROM afprotechs_announcements ORDER BY created_at DESC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $response['data'][] = $row;
                }
            }
            $response['summary'] = [
                'total_count' => count($response['data']),
                'type' => 'announcements'
            ];
            break;
            
        case 'events':
            $sql = "SELECT * FROM afprotechs_events ORDER BY start_date DESC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $response['data'][] = $row;
                }
            }
            $response['summary'] = [
                'total_count' => count($response['data']),
                'type' => 'events'
            ];
            break;
            
        case 'orders':
            $sql = "SELECT 
                        o.*,
                        COALESCE(p.product_name, sp.product_name) as product_name,
                        COALESCE(p.product_image, sp.product_image) as product_image,
                        COALESCE(p.product_price, sp.product_price) as product_price,
                        CASE 
                            WHEN p.product_id IS NOT NULL THEN 'admin'
                            WHEN sp.product_id IS NOT NULL THEN 'student'
                            ELSE 'unknown'
                        END as product_type,
                        sp.first_name,
                        sp.last_name,
                        sp.group_members
                    FROM afprotechs_orders o
                    LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                    LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
                    ORDER BY o.created_at DESC";
            $result = $conn->query($sql);
            
            $total_sales = 0;
            $delivered_count = 0;
            $status_counts = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Format image
                    if (!empty($row['product_image'])) {
                        $image = trim($row['product_image']);
                        if (!preg_match('/^(data:|http)/i', $image)) {
                            if (substr($image, 0, 4) === '/9j/') {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            } else {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            }
                        }
                    }
                    
                    $response['data'][] = $row;
                    $total_sales += floatval($row['total_price']);
                    
                    if ($row['order_status'] === 'delivered') {
                        $delivered_count++;
                    }
                    
                    $status = $row['order_status'];
                    $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
                }
            }
            
            $response['summary'] = [
                'total_count' => count($response['data']),
                'total_sales' => $total_sales,
                'delivered_count' => $delivered_count,
                'status_breakdown' => $status_counts,
                'type' => 'orders'
            ];
            break;
            
        case 'student_products':
            $sql = "SELECT * FROM afprotech_student_products ORDER BY created_at DESC";
            $result = $conn->query($sql);
            
            $total_earnings = 0;
            $approved_count = 0;
            $pending_count = 0;
            $rejected_count = 0;
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Format image
                    if (!empty($row['product_image'])) {
                        $image = trim($row['product_image']);
                        if (!preg_match('/^(data:|http)/i', $image)) {
                            if (substr($image, 0, 4) === '/9j/') {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            } else {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            }
                        }
                    }
                    
                    $response['data'][] = $row;
                    // Note: total_earnings would need to be calculated from orders table
                    
                    $status = $row['status'] ?? 'pending'; // Use 'status' field instead of 'approval_status'
                    switch ($status) {
                        case 'approved':
                            $approved_count++;
                            break;
                        case 'rejected':
                            $rejected_count++;
                            break;
                        default:
                            $pending_count++;
                            break;
                    }
                }
            }
            
            $response['summary'] = [
                'total_count' => count($response['data']),
                'total_earnings' => 0, // Would need separate calculation
                'approved_count' => $approved_count,
                'pending_count' => $pending_count,
                'rejected_count' => $rejected_count,
                'type' => 'student_products'
            ];
            break;
            
        case 'attendance':
            $sql = "SELECT 
                        a.id as afprotech_id_attendance,
                        a.*,
                        e.event_title,
                        e.event_description,
                        e.start_date as event_date,
                        CONCAT(a.first_name, ' ', a.last_name) as student_name,
                        a.id_number as student_id,
                        COALESCE(a.morning_in, a.afternoon_in, a.attendance_date) as attendance_time
                    FROM afprotechs_attendance a
                    LEFT JOIN afprotechs_events e ON a.event_id = e.event_id
                    ORDER BY a.attendance_date DESC";
            $result = $conn->query($sql);
            
            $unique_students = [];
            $events_attended = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $response['data'][] = $row;
                    $unique_students[$row['student_id']] = $row['student_name'];
                    
                    if ($row['event_id']) {
                        $events_attended[$row['event_id']] = $row['event_title'];
                    }
                }
            } else {
                // Add error info if query fails
                $response['error'] = $conn->error;
                $response['query'] = $sql;
            }
            
            $response['summary'] = [
                'total_count' => count($response['data']),
                'unique_students' => count($unique_students),
                'events_with_attendance' => count($events_attended),
                'student_list' => array_values($unique_students),
                'type' => 'attendance'
            ];
            break;
            
        case 'comprehensive':
            // Get all data types combined
            $comprehensive_data = [
                'announcements' => [],
                'events' => [],
                'orders' => [],
                'student_products' => [],
                'attendance' => []
            ];
            
            // Get announcements
            $sql = "SELECT * FROM afprotechs_announcements ORDER BY COALESCE(created_at, announcement_datetime) DESC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $comprehensive_data['announcements'][] = $row;
                }
            } else {
                error_log("Comprehensive announcements query failed: " . $conn->error);
            }
            
            // Get events
            $sql = "SELECT * FROM afprotechs_events ORDER BY start_date DESC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $comprehensive_data['events'][] = $row;
                }
            } else {
                error_log("Comprehensive events query failed: " . $conn->error);
            }
            
            // Get orders
            $sql = "SELECT 
                        o.*,
                        COALESCE(p.product_name, sp.product_name, o.product_name) as product_name,
                        COALESCE(p.product_image, sp.product_image) as product_image,
                        COALESCE(p.product_price, sp.product_price, o.unit_price) as product_price,
                        CASE 
                            WHEN p.product_id IS NOT NULL THEN 'admin'
                            WHEN sp.product_id IS NOT NULL THEN 'student'
                            ELSE 'unknown'
                        END as product_type,
                        COALESCE(o.customer_name, CONCAT(sp.first_name, ' ', sp.last_name), 'N/A') as customer_name,
                        sp.first_name,
                        sp.last_name,
                        sp.group_members
                    FROM afprotechs_orders o
                    LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                    LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
                    ORDER BY o.created_at DESC";
            $result = $conn->query($sql);
            $total_sales = 0;
            $delivered_count = 0;
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Format image
                    if (!empty($row['product_image'])) {
                        $image = trim($row['product_image']);
                        if (!preg_match('/^(data:|http)/i', $image)) {
                            if (substr($image, 0, 4) === '/9j/') {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            } else {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            }
                        }
                    }
                    $comprehensive_data['orders'][] = $row;
                    $total_sales += floatval($row['total_price']);
                    if ($row['order_status'] === 'delivered') {
                        $delivered_count++;
                    }
                }
            } else {
                error_log("Comprehensive orders query failed: " . $conn->error);
            }
            
            // Get student products
            $sql = "SELECT * FROM afprotech_student_products ORDER BY created_at DESC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Format image
                    if (!empty($row['product_image'])) {
                        $image = trim($row['product_image']);
                        if (!preg_match('/^(data:|http)/i', $image)) {
                            if (substr($image, 0, 4) === '/9j/') {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            } else {
                                $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                            }
                        }
                    }
                    $comprehensive_data['student_products'][] = $row;
                }
            } else {
                error_log("Comprehensive student_products query failed: " . $conn->error);
            }
            
            // Get attendance
            $sql = "SELECT 
                        a.afprotechs_id_attendance as afprotech_id_attendance,
                        a.*,
                        e.event_title,
                        e.event_description,
                        e.start_date as event_date,
                        CONCAT(a.first_name, ' ', a.last_name) as student_name,
                        a.id_number as student_id,
                        COALESCE(a.morning_in, a.afternoon_in, a.attendance_date) as attendance_time
                    FROM afprotechs_attendance a
                    LEFT JOIN afprotechs_events e ON a.event_id = e.event_id
                    ORDER BY a.attendance_date DESC";
            $result = $conn->query($sql);
            $unique_students = [];
            $events_attended = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $comprehensive_data['attendance'][] = $row;
                    $unique_students[$row['student_id']] = $row['student_name'];
                    if ($row['event_id']) {
                        $events_attended[$row['event_id']] = $row['event_title'];
                    }
                }
            } else {
                error_log("Comprehensive attendance query failed: " . $conn->error);
            }
            
            $response['data'] = $comprehensive_data;
            $response['summary'] = [
                'announcements_count' => count($comprehensive_data['announcements']),
                'events_count' => count($comprehensive_data['events']),
                'orders_count' => count($comprehensive_data['orders']),
                'student_products_count' => count($comprehensive_data['student_products']),
                'attendance_count' => count($comprehensive_data['attendance']),
                'total_sales' => $total_sales,
                'delivered_orders' => $delivered_count,
                'unique_students' => count($unique_students),
                'events_with_attendance' => count($events_attended),
                'type' => 'comprehensive'
            ];
            break;
            
        case 'all':
        default:
            // Get summary data for all categories
            $summary = [];
            
            // Announcements count
            $result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_announcements");
            $summary['announcements'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Events count
            $result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_events");
            $summary['events'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Orders summary
            $result = $conn->query("SELECT COUNT(*) as count, SUM(total_price) as total_sales, 
                                   SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered 
                                   FROM afprotechs_orders");
            if ($result) {
                $orders_data = $result->fetch_assoc();
                $summary['orders'] = $orders_data['count'];
                $summary['total_sales'] = floatval($orders_data['total_sales'] ?? 0);
                $summary['delivered_orders'] = $orders_data['delivered'];
            }
            
            // Student products count
            $result = $conn->query("SELECT COUNT(*) as count FROM afprotech_student_products");
            $summary['student_products'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Attendance count
            $result = $conn->query("SELECT COUNT(*) as count, COUNT(DISTINCT id_number) as unique_students 
                                   FROM afprotechs_attendance");
            if ($result) {
                $attendance_data = $result->fetch_assoc();
                $summary['attendance'] = $attendance_data['count'];
                $summary['unique_students'] = $attendance_data['unique_students'];
            }
            
            $response['summary'] = $summary;
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

$conn->close();
?>