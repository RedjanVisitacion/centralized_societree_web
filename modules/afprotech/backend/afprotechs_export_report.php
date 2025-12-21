<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Include TCPDF library
require_once '../../../vendor/tcpdf/tcpdf.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Database connection
$conn = null;
try {
    $host = '103.125.219.236';
    $user = 'societree';
    $password = 'socieTree12345';
    $database = 'societree';

    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        // Try local connection
        $conn = @new mysqli('localhost', 'root', '', $database);
        if ($conn->connect_error) {
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
        }
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

if (!$type) {
    die(json_encode(['success' => false, 'message' => 'Report type is required']));
}

if (!in_array($format, ['csv', 'pdf'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid format. Use csv or pdf']));
}

// Function to clean data for CSV
function cleanForCSV($data) {
    if (is_string($data)) {
        // Remove newlines and escape quotes
        $data = str_replace(["\r\n", "\r", "\n"], " ", $data);
        $data = str_replace('"', '""', $data);
        return '"' . $data . '"';
    }
    return $data;
}

// Function to generate CSV content
function generateCSV($data, $headers, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, $headers);

    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// Comprehensive PDF generation using TCPDF
function generateComprehensivePDF($filename) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('AFPROTECHS System');
    $pdf->SetAuthor('AFPROTECHS');
    $pdf->SetTitle('AFPROTECHS Comprehensive Report');
    $pdf->SetSubject('Comprehensive Report');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'AFPROTECHS Comprehensive Report', 'Generated on ' . date('Y-m-d H:i:s'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set font
    $pdf->SetFont('helvetica', '', 9);

    // Database connection for comprehensive data
    global $conn;

    // Add a page
    $pdf->AddPage();

    $html = '<h1 style="color: #000080; text-align: center; border-bottom: 2px solid #000080; padding-bottom: 10px; margin-bottom: 30px;">AFPROTECHS Comprehensive Report</h1>';

    // Announcements Section
    $html .= '<h2 style="color: #000080; margin-top: 30px; border-bottom: 1px solid #000080; padding-bottom: 5px;">Announcements</h2>';
    $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">
        <th>ID</th>
        <th>Title</th>
        <th>Content</th>
        <th>Date Created</th>
        <th>Status</th>
    </tr></thead><tbody>';

    $sql = "SELECT announcement_id, title, content,
                   DATE_FORMAT(created_at, '%Y-%m-%d') as created_at,
                   status
            FROM afprotechs_announcements
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['announcement_id'] . '</td>
                <td>' . htmlspecialchars($row['title']) . '</td>
                <td>' . htmlspecialchars(substr($row['content'] ?? '', 0, 80)) . (strlen($row['content'] ?? '') > 80 ? '...' : '') . '</td>
                <td>' . $row['created_at'] . '</td>
                <td>' . ucfirst($row['status'] ?? 'active') . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="5" style="text-align: center;">No announcements found</td></tr>';
    }
    $html .= '</tbody></table>';

    // Events Section
    $html .= '<h2 style="color: #000080; margin-top: 30px; border-bottom: 1px solid #000080; padding-bottom: 5px;">Events</h2>';
    $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">
        <th>ID</th>
        <th>Title</th>
        <th>Description</th>
        <th>Start Date</th>
        <th>Location</th>
    </tr></thead><tbody>';

    $sql = "SELECT event_id, event_title, event_description,
                   DATE_FORMAT(start_date, '%Y-%m-%d') as start_date, location
            FROM afprotechs_events
            ORDER BY start_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['event_id'] . '</td>
                <td>' . htmlspecialchars($row['event_title']) . '</td>
                <td>' . htmlspecialchars(substr($row['event_description'] ?? '', 0, 80)) . (strlen($row['event_description'] ?? '') > 80 ? '...' : '') . '</td>
                <td>' . $row['start_date'] . '</td>
                <td>' . htmlspecialchars($row['location'] ?? '') . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="5" style="text-align: center;">No events found</td></tr>';
    }
    $html .= '</tbody></table>';

    // Orders Section
    $html .= '<h2 style="color: #000080; margin-top: 30px; border-bottom: 1px solid #000080; padding-bottom: 5px;">Orders</h2>';
    $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">
        <th>ID</th>
        <th>Product</th>
        <th>Customer</th>
        <th>Quantity</th>
        <th>Total Price</th>
        <th>Status</th>
        <th>Date</th>
    </tr></thead><tbody>';

    $sql = "SELECT
                o.order_id,
                COALESCE(p.product_name, sp.product_name, 'N/A') as product_name,
                o.customer_name,
                o.quantity,
                o.total_price,
                o.order_status,
                DATE_FORMAT(o.created_at, '%Y-%m-%d') as created_at
            FROM afprotechs_orders o
            LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
            LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
            ORDER BY o.created_at DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['order_id'] . '</td>
                <td>' . htmlspecialchars($row['product_name']) . '</td>
                <td>' . htmlspecialchars($row['customer_name'] ?? 'N/A') . '</td>
                <td>' . ($row['quantity'] ?? 1) . '</td>
                <td>₱' . number_format($row['total_price'], 2) . '</td>
                <td>' . ucfirst($row['order_status'] ?? 'pending') . '</td>
                <td>' . $row['created_at'] . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="7" style="text-align: center;">No orders found</td></tr>';
    }
    $html .= '</tbody></table>';

    // Student Products Section
    $html .= '<h2 style="color: #000080; margin-top: 30px; border-bottom: 1px solid #000080; padding-bottom: 5px;">Student Products</h2>';
    $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">
        <th>ID</th>
        <th>Product Name</th>
        <th>Student</th>
        <th>Price</th>
        <th>Status</th>
        <th>Date Created</th>
    </tr></thead><tbody>';

    $sql = "SELECT product_id, product_name,
                   CONCAT(first_name, ' ', last_name) as student_name,
                   product_price, status,
                   DATE_FORMAT(created_at, '%Y-%m-%d') as created_at
            FROM afprotech_student_products
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['product_id'] . '</td>
                <td>' . htmlspecialchars($row['product_name']) . '</td>
                <td>' . htmlspecialchars($row['student_name']) . '</td>
                <td>₱' . number_format($row['product_price'], 2) . '</td>
                <td>' . ucfirst($row['status'] ?? 'pending') . '</td>
                <td>' . $row['created_at'] . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="text-align: center;">No student products found</td></tr>';
    }
    $html .= '</tbody></table>';

    // Attendance Section
    $html .= '<h2 style="color: #000080; margin-top: 30px; border-bottom: 1px solid #000080; padding-bottom: 5px;">Attendance Records</h2>';
    $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">
        <th>ID</th>
        <th>Student Name</th>
        <th>Student ID</th>
        <th>Event</th>
        <th>Date</th>
        <th>Time</th>
    </tr></thead><tbody>';

    $sql = "SELECT
                a.id,
                CONCAT(a.first_name, ' ', a.last_name) as student_name,
                a.id_number as student_id,
                e.event_title,
                DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as attendance_date,
                COALESCE(TIME_FORMAT(a.morning_in, '%H:%i'), TIME_FORMAT(a.afternoon_in, '%H:%i'), '') as time_in
            FROM afprotechs_attendance a
            LEFT JOIN afprotechs_events e ON a.event_id = e.event_id
            ORDER BY a.attendance_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['id'] . '</td>
                <td>' . htmlspecialchars($row['student_name']) . '</td>
                <td>' . htmlspecialchars($row['student_id']) . '</td>
                <td>' . htmlspecialchars($row['event_title'] ?? 'N/A') . '</td>
                <td>' . $row['attendance_date'] . '</td>
                <td>' . $row['time_in'] . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="text-align: center;">No attendance records found</td></tr>';
    }
    $html .= '</tbody></table>';

    $html .= '<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666; line-height: 1.4;">
        <strong>Report generated by AFPROTECHS System on ' . date('Y-m-d H:i:s') . '</strong><br>
        <small>&copy; 2025 AFPROTECHS. All rights reserved.</small>
    </div>';

    // Print text using writeHTMLCell()
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output($filename, 'D');
    exit;
}

// Simple PDF generation using TCPDF
function generateSimplePDF($title, $headers, $data, $filename) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('AFPROTECHS System');
    $pdf->SetAuthor('AFPROTECHS');
    $pdf->SetTitle($title);
    $pdf->SetSubject($title);

    // Set default header data
    $pdf->SetHeaderData('', 0, $title, 'Generated on ' . date('Y-m-d H:i:s'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();

    // Create the table
    $html = '<h1 style="color: #000080; text-align: center; border-bottom: 2px solid #000080; padding-bottom: 10px;">' . htmlspecialchars($title) . '</h1>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
    $html .= '<thead><tr style="background-color: #f8f9fa; font-weight: bold; color: #000080;">';

    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    if (empty($data)) {
        $html .= '<tr><td colspan="' . count($headers) . '" style="text-align: center; padding: 20px; color: #666;">No data available for this report.</td></tr>';
    } else {
        $rowCount = 0;
        foreach ($data as $row) {
            $bgColor = ($rowCount % 2 == 0) ? '#ffffff' : '#f9f9f9';
            $html .= '<tr style="background-color: ' . $bgColor . ';">';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
            $rowCount++;
        }
    }

    $html .= '</tbody></table>';
    $html .= '<div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666; line-height: 1.4;">';
    $html .= 'Report generated by AFPROTECHS System on ' . date('Y-m-d H:i:s') . '<br>';
    $html .= '<small>&copy; 2025 AFPROTECHS. All rights reserved.</small>';
    $html .= '</div>';

    // Print text using writeHTMLCell()
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output($filename, 'D');
    exit;
}

try {
    $data = [];
    $headers = [];
    $filename = '';

    switch ($type) {
        case 'announcements':
            $filename = 'afprotech_announcements_report_' . date('Y-m-d');
            $headers = ['ID', 'Title', 'Content', 'Date Created', 'Status'];

            // Check which columns exist in the table (same logic as dashboard)
            $has_status = false;
            $has_created_at = false;
            $columns_result = $conn->query("SHOW COLUMNS FROM afprotechs_announcements");
            if ($columns_result) {
                while ($col = $columns_result->fetch_assoc()) {
                    if ($col['Field'] === 'status') $has_status = true;
                    if ($col['Field'] === 'created_at') $has_created_at = true;
                }
            }

            // Build query based on available columns
            $date_column = $has_created_at ? 'created_at' : 'announcement_datetime';
            $status_column = $has_status ? 'status' : "'draft' as status";

            $sql = "SELECT announcement_id, announcement_title, announcement_content,
                           DATE_FORMAT($date_column, '%Y-%m-%d %H:%i:%s') as created_at,
                           $status_column
                    FROM afprotechs_announcements
                    ORDER BY $date_column DESC";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        $row['announcement_id'],
                        $row['announcement_title'],
                        strip_tags($row['announcement_content']),
                        $row['created_at'],
                        ucfirst($row['status'])
                    ];
                }
            } else {
                // Debug: Log SQL error
                error_log("Announcements SQL Error: " . $conn->error);
            }
            break;

        case 'events':
            $filename = 'afprotech_events_report_' . date('Y-m-d');
            $headers = ['ID', 'Title', 'Start Date', 'End Date', 'Location', 'Description'];

            $sql = "SELECT event_id, event_title,
                           DATE_FORMAT(start_date, '%Y-%m-%d %H:%i:%s') as start_date,
                           DATE_FORMAT(end_date, '%Y-%m-%d %H:%i:%s') as end_date,
                           event_location, event_description
                    FROM afprotechs_events
                    ORDER BY start_date DESC";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        $row['event_id'],
                        $row['event_title'],
                        $row['start_date'],
                        $row['end_date'],
                        $row['event_location'],
                        strip_tags($row['event_description'])
                    ];
                }
            }
            break;

        case 'orders':
            $filename = 'afprotech_orders_report_' . date('Y-m-d');
            $headers = ['Order ID', 'Customer Name', 'Product', 'Quantity', 'Total Price', 'Status', 'Order Date'];

            $sql = "SELECT
                        o.order_id,
                        CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, '')) as customer_name,
                        COALESCE(p.product_name, sp.product_name, o.product_name) as product_name,
                        o.quantity,
                        o.total_price,
                        o.order_status,
                        DATE_FORMAT(COALESCE(o.order_date, o.created_at), '%Y-%m-%d %H:%i:%s') as order_date
                    FROM afprotechs_orders o
                    LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                    LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
                    ORDER BY COALESCE(o.order_date, o.created_at) DESC";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        $row['order_id'],
                        trim($row['customer_name']) ?: 'Customer',
                        $row['product_name'],
                        $row['quantity'],
                        '₱' . number_format($row['total_price'], 2),
                        ucfirst($row['order_status']),
                        $row['order_date']
                    ];
                }
            } else {
                // Debug: Log SQL error
                error_log("Orders SQL Error: " . $conn->error);
            }
            break;

        case 'student_products':
            $filename = 'afprotech_student_products_report_' . date('Y-m-d');
            $headers = ['ID', 'Student Name', 'Product Name', 'Price', 'Quantity', 'Status', 'Created Date'];

            $sql = "SELECT
                        sp.product_id,
                        CONCAT(sp.first_name, ' ', COALESCE(sp.middle_name, ''), ' ', sp.last_name) as student_name,
                        sp.product_name,
                        sp.product_price,
                        sp.product_quantity,
                        sp.status,
                        DATE_FORMAT(sp.created_at, '%Y-%m-%d %H:%i:%s') as created_at
                    FROM afprotech_student_products sp
                    ORDER BY sp.created_at DESC";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        $row['product_id'],
                        $row['student_name'],
                        $row['product_name'],
                        '₱' . number_format($row['product_price'], 2),
                        $row['product_quantity'],
                        ucfirst($row['status']),
                        $row['created_at']
                    ];
                }
            }
            break;

        case 'attendance':
            $filename = 'afprotech_attendance_report_' . date('Y-m-d');
            $headers = ['ID', 'Student ID', 'Student Name', 'Section', 'Time In', 'Date'];

            $sql = "SELECT
                        a.afprotech_id_attendance as id,
                        a.id_number as student_id,
                        CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name) as student_name,
                        CONCAT(a.year, a.section) as section,
                        COALESCE(a.morning_in, a.afternoon_in) as time_in,
                        DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as attendance_date
                    FROM afprotechs_attendance a
                    ORDER BY a.attendance_date DESC, a.created_at DESC";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        $row['id'],
                        $row['student_id'],
                        $row['student_name'],
                        $row['section'],
                        $row['time_in'] ? date('g:i A', strtotime($row['time_in'])) : 'N/A',
                        $row['attendance_date']
                    ];
                }
            }
            break;

        case 'summary':
            // Return quick counts for UI/dashboard usage
            $stats = [];
            $res = $conn->query("SELECT COUNT(*) as c FROM afprotechs_announcements");
            $stats['announcements'] = $res ? (int)$res->fetch_assoc()['c'] : 0;

            $res = $conn->query("SELECT COUNT(*) as c FROM afprotechs_events");
            $stats['events'] = $res ? (int)$res->fetch_assoc()['c'] : 0;

            $res = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(total_price),0) as total FROM afprotechs_orders");
            if ($res) {
                $r = $res->fetch_assoc();
                $stats['orders'] = (int)$r['c'];
                $stats['total_sales'] = (float)$r['total'];
            } else {
                $stats['orders'] = 0;
                $stats['total_sales'] = 0.0;
            }

            $res = $conn->query("SELECT COUNT(*) as c FROM afprotech_student_products");
            $stats['student_products'] = $res ? (int)$res->fetch_assoc()['c'] : 0;

            $res = $conn->query("SELECT COUNT(*) as c FROM afprotechs_attendance");
            $stats['attendance'] = $res ? (int)$res->fetch_assoc()['c'] : 0;

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $stats]);
            exit;

        case 'comprehensive':
            $filename = 'afprotech_comprehensive_report_' . date('Y-m-d');

            if ($format === 'csv') {
                // For CSV, create a structured format with sections
                $headers = ['Section', 'ID', 'Details'];

                $data[] = ['ANNOUNCEMENTS', '', ''];
                $data[] = ['ID', 'Title', 'Content', 'Date Created'];

                $sql = "SELECT announcement_id, announcement_title, announcement_content,
                               DATE_FORMAT(COALESCE(created_at, announcement_datetime), '%Y-%m-%d %H:%i:%s') as created_at
                        FROM afprotechs_announcements
                        ORDER BY COALESCE(created_at, announcement_datetime) DESC";
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = [
                            $row['announcement_id'],
                            $row['announcement_title'],
                            strip_tags($row['announcement_content']),
                            $row['created_at']
                        ];
                    }
                }

                // Events section
                $data[] = ['', '', ''];
                $data[] = ['EVENTS', '', ''];
                $data[] = ['ID', 'Title', 'Description', 'Start Date', 'Location'];

                $sql = "SELECT event_id, event_title, event_description,
                               DATE_FORMAT(start_date, '%Y-%m-%d') as start_date, location
                        FROM afprotechs_events
                        ORDER BY start_date DESC";
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = [
                            $row['event_id'],
                            $row['event_title'],
                            strip_tags($row['event_description'] ?? ''),
                            $row['start_date'],
                            $row['location'] ?? ''
                        ];
                    }
                }

                // Orders section
                $data[] = ['', '', ''];
                $data[] = ['ORDERS', '', ''];
                $data[] = ['ID', 'Product', 'Customer', 'Quantity', 'Total Price', 'Status', 'Date'];

                $sql = "SELECT
                            o.order_id,
                            COALESCE(p.product_name, sp.product_name, 'N/A') as product_name,
                            o.customer_name,
                            o.quantity,
                            o.total_price,
                            o.order_status,
                            DATE_FORMAT(o.created_at, '%Y-%m-%d') as created_at
                        FROM afprotechs_orders o
                        LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
                        LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
                        ORDER BY o.created_at DESC";
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = [
                            $row['order_id'],
                            $row['product_name'],
                            $row['customer_name'] ?? 'N/A',
                            $row['quantity'] ?? 1,
                            $row['total_price'],
                            $row['order_status'] ?? 'pending',
                            $row['created_at']
                        ];
                    }
                }

                // Student Products section
                $data[] = ['', '', ''];
                $data[] = ['STUDENT PRODUCTS', '', ''];
                $data[] = ['ID', 'Product Name', 'Student', 'Price', 'Status', 'Date Created'];

                $sql = "SELECT product_id, product_name,
                               CONCAT(first_name, ' ', last_name) as student_name,
                               product_price, status,
                               DATE_FORMAT(created_at, '%Y-%m-%d') as created_at
                        FROM afprotech_student_products
                        ORDER BY created_at DESC";
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = [
                            $row['product_id'],
                            $row['product_name'],
                            $row['student_name'],
                            $row['product_price'],
                            $row['status'] ?? 'pending',
                            $row['created_at']
                        ];
                    }
                }

                // Attendance section
                $data[] = ['', '', ''];
                $data[] = ['ATTENDANCE', '', ''];
                $data[] = ['ID', 'Student Name', 'Student ID', 'Event', 'Date', 'Time'];

                $sql = "SELECT
                            a.id,
                            CONCAT(a.first_name, ' ', a.last_name) as student_name,
                            a.id_number as student_id,
                            e.event_title,
                            DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as attendance_date,
                            COALESCE(TIME_FORMAT(a.morning_in, '%H:%i'), TIME_FORMAT(a.afternoon_in, '%H:%i'), '') as time_in
                        FROM afprotechs_attendance a
                        LEFT JOIN afprotechs_events e ON a.event_id = e.event_id
                        ORDER BY a.attendance_date DESC";
                $result = $conn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = [
                            $row['id'],
                            $row['student_name'],
                            $row['student_id'],
                            $row['event_title'] ?? 'N/A',
                            $row['attendance_date'],
                            $row['time_in']
                        ];
                    }
                }
            } else {
                // For PDF, generate a custom comprehensive HTML
                generateComprehensivePDF($filename . '.pdf');
                exit;
            }
            break;

        default:
            die(json_encode(['success' => false, 'message' => 'Invalid report type']));
    }

    if ($format === 'csv') {
        if (empty($data)) {
            // For empty data, still create CSV with headers
            generateCSV($data, $headers, $filename . '.csv');
        } else {
            generateCSV($data, $headers, $filename . '.csv');
        }
    } else {
        generateSimplePDF(ucfirst($type) . ' Report', $headers, $data, $filename . '.pdf');
    }

} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]));
}

$conn->close();
?>