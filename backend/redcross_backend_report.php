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

if (!function_exists('get_member_details')) {
    function get_member_details($conn, $member_id) {
        $stmt = $conn->prepare(
            "SELECT id, full_name, member_id, course, year_section, email, phone, status, created_at
             FROM redcross_members 
             WHERE id = ?"
        );
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();
        $stmt->close();
        return $member;
    }
}

if (!function_exists('get_member_activities')) {
    function get_member_activities($conn, $member_id) {
        $stmt = $conn->prepare(
            "SELECT * FROM redcross_activities 
             WHERE member_id = ? 
             ORDER BY activity_date DESC"
        );
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        $stmt->close();
        return $activities;
    }
}

if (!function_exists('send_certificate_email')) {
    function send_certificate_email($member, $certificate) {
        // For development/testing - simulate email sending
        // In production, you would use proper SMTP configuration
        
        $to = $member['email'];
        $subject = 'Red Cross Youth Certificate - ' . $member['full_name'];
        
        // Create certificate content
        $certificate_content = generate_certificate_html($member, $certificate);
        
        // Check if we're in a development environment (XAMPP)
        $is_development = (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || 
                          strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false);
        
        if ($is_development) {
            // For development - save certificate to file instead of sending email
            $filename = 'certificate_' . $member['id'] . '_' . date('Ymd_His') . '.html';
            $filepath = __DIR__ . '/../certificates/' . $filename;
            
            // Create certificates directory if it doesn't exist
            $cert_dir = __DIR__ . '/../certificates/';
            if (!is_dir($cert_dir)) {
                mkdir($cert_dir, 0755, true);
            }
            
            // Email body
            $message = "
            <html>
            <head>
                <title>Red Cross Youth Certificate</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
                    .header { background-color: #f80305; color: white; padding: 20px; text-align: center; margin: -20px -20px 20px -20px; }
                    .content { padding: 20px; }
                    .certificate { border: 2px solid #f80305; padding: 20px; margin: 20px 0; text-align: center; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; margin: 20px -20px -20px -20px; }
                    .dev-notice { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='dev-notice'>
                    <strong>Development Mode:</strong> This certificate would be sent to: {$member['email']}<br>
                    <strong>Subject:</strong> {$subject}<br>
                    <strong>Generated:</strong> " . date('F d, Y H:i:s') . "
                </div>
                <div class='header'>
                    <h1>Red Cross Youth</h1>
                    <p>Certificate of Volunteer Service</p>
                </div>
                <div class='content'>
                    <p>Dear {$member['full_name']},</p>
                    <p>Congratulations! Your volunteer certificate is ready.</p>
                    
                    <div class='certificate'>
                        <h2>Certificate of Volunteer Service</h2>
                        <p><strong>This is to certify that</strong></p>
                        <h3>{$member['full_name']}</h3>
                        <p>Member ID: {$member['member_id']}</p>
                        <p>Has completed <strong>{$certificate['total_hours']} hours</strong> of volunteer service</p>
                        <p>Course: {$member['course']} - {$member['year_section']}</p>
                        <p>Issued on: " . date('F d, Y', strtotime($certificate['issued_at'])) . "</p>
                        <br>
                        <p><em>Thank you for your dedication to serving the community!</em></p>
                    </div>
                    
                    <p>You can print this email as your official certificate.</p>
                    <p>Thank you for your valuable contribution to our organization!</p>
                </div>
                <div class='footer'>
                    <p>Red Cross Youth - University of Science and Technology of Southern Philippines</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                </div>
            </body>
            </html>";
            
            // Save to file
            $saved = file_put_contents($filepath, $message);
            return $saved !== false;
            
        } else {
            // Production environment - use actual email sending
            $headers = array(
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: Red Cross Youth <noreply@redcross.org>',
                'Reply-To: noreply@redcross.org',
                'X-Mailer: PHP/' . phpversion()
            );
            
            $message = "<!-- Production email content here -->";
            
            // Use mail() function or integrate with PHPMailer/SMTP
            return mail($to, $subject, $message, implode("\r\n", $headers));
        }
    }
}

if (!function_exists('generate_certificate_html')) {
    function generate_certificate_html($member, $certificate) {
        // This function can be expanded to create more detailed certificate content
        return "
        <div style='border: 3px solid #f80305; padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
            <h1 style='color: #f80305; margin-bottom: 30px;'>Red Cross Youth</h1>
            <h2>Certificate of Volunteer Service</h2>
            <p style='font-size: 18px; margin: 30px 0;'>This is to certify that</p>
            <h3 style='font-size: 24px; color: #f80305; margin: 20px 0;'>{$member['full_name']}</h3>
            <p>Member ID: {$member['member_id']}</p>
            <p style='font-size: 16px; margin: 20px 0;'>Has successfully completed <strong>{$certificate['total_hours']} hours</strong> of volunteer service</p>
            <p>Course: {$member['course']} - {$member['year_section']}</p>
            <p style='margin-top: 40px;'>Issued on: " . date('F d, Y', strtotime($certificate['issued_at'])) . "</p>
        </div>";
    }
}

$report_message = '';

if (isset($_GET['export_patients']) && $_GET['export_patients'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=redcross_patients_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date of Service', 'Name', 'Age', 'Address', 'Case / Condition', 'Remarks']);

    $res = $conn->query(
        "SELECT date_of_service, name, age, address, case_description, remarks
         FROM redcross_patients
         ORDER BY date_of_service DESC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['date_of_service'],
                $row['name'],
                $row['age'],
                $row['address'],
                $row['case_description'],
                $row['remarks'],
            ]);
        }
        $res->free();
    }

    fclose($output);
    exit;
}

$members = [];
$resMembers = $conn->query(
    "SELECT id, full_name, member_id, course, year_section
     FROM redcross_members
     WHERE status = 'active'
     ORDER BY full_name ASC"
);
if ($resMembers) {
    while ($row = $resMembers->fetch_assoc()) {
        $members[] = $row;
    }
    $resMembers->free();
}

// Fetch campaigns for activity dropdown
$campaigns = [];
$resCampaigns = $conn->query(
    "SELECT id, title, description, event_date, location, status
     FROM redcross_campaigns
     ORDER BY 
        CASE status 
            WHEN 'ongoing' THEN 1 
            WHEN 'upcoming' THEN 2 
            WHEN 'completed' THEN 3 
            WHEN 'cancelled' THEN 4 
            ELSE 5 
        END,
        event_date DESC"
);
if ($resCampaigns) {
    while ($row = $resCampaigns->fetch_assoc()) {
        $campaigns[] = $row;
    }
    $resCampaigns->free();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_activity') {
    $member_id     = (int) ($_POST['member_id'] ?? 0);
    $campaign_id   = (int) ($_POST['campaign_id'] ?? 0);
    $activity_date = $_POST['activity_date'] ?? '';
    $hours         = (float) ($_POST['hours'] ?? 0);
    $remarks       = rc_clean($conn, $_POST['remarks'] ?? '');

    // Get campaign title for activity name
    $activity_name = '';
    if ($campaign_id > 0) {
        $campaignStmt = $conn->prepare("SELECT title FROM redcross_campaigns WHERE id = ?");
        $campaignStmt->bind_param('i', $campaign_id);
        $campaignStmt->execute();
        $campaignResult = $campaignStmt->get_result();
        if ($campaignRow = $campaignResult->fetch_assoc()) {
            $activity_name = $campaignRow['title'];
        }
        $campaignStmt->close();
    }

    if ($member_id > 0 && $campaign_id > 0 && $activity_name && $activity_date && $hours > 0) {
        // Check if redcross_activities table has campaign_id column, if not use activity_name
        $stmt = $conn->prepare(
            "INSERT INTO redcross_activities (member_id, activity_name, activity_date, hours, remarks)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issds', $member_id, $activity_name, $activity_date, $hours, $remarks);

        if ($stmt->execute()) {
            $report_message = 'Activity logged successfully for: ' . $activity_name;
        } else {
            $report_message = 'Error logging activity: ' . $conn->error;
        }

        $stmt->close();
    } else {
        $report_message = 'Please select a member, campaign, date and enter hours greater than zero.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_certificate') {
    $member_id   = (int) ($_POST['member_id'] ?? 0);
    $quota_hours = isset($_POST['quota_hours']) ? max(0, (int) $_POST['quota_hours']) : 20;
    $total_hours = 0;

    if ($member_id > 0) {
        $r = $conn->query("SELECT SUM(hours) AS h FROM redcross_activities WHERE member_id = {$member_id}");
        if ($r && $row = $r->fetch_assoc()) {
            $total_hours = (float) $row['h'];
        }

        if ($total_hours >= $quota_hours) {
            // Check if certificate already exists
            $checkStmt = $conn->prepare("SELECT id FROM redcross_certificates WHERE member_id = ? LIMIT 1");
            $checkStmt->bind_param('i', $member_id);
            $checkStmt->execute();
            $certExists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if (!$certExists) {
                $stmt = $conn->prepare(
                    "INSERT INTO redcross_certificates (member_id, total_hours, issued_at)
                     VALUES (?, ?, NOW())"
                );
                $stmt->bind_param('id', $member_id, $total_hours);

                if ($stmt->execute()) {
                    // Redirect to certificate page
                    header('Location: ../../modules/redcross/redcross_certificate.php?member_id=' . $member_id);
                    exit;
                } else {
                    $report_message = 'Error generating certificate: ' . $conn->error;
                }

                $stmt->close();
            } else {
                // Certificate already exists, redirect to view it
                header('Location: ../../modules/redcross/redcross_certificate.php?member_id=' . $member_id);
                exit;
            }
        } else {
            $report_message = 'Member has less than required ' . $quota_hours . ' hours.';
        }
    }
}

// Handle send certificate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_certificate') {
    $member_id = (int) ($_POST['member_id'] ?? 0);
    
    if ($member_id > 0) {
        // Get member details
        $member = get_member_details($conn, $member_id);
        
        if ($member && !empty($member['email'])) {
            // Check if certificate exists
            $certStmt = $conn->prepare("SELECT * FROM redcross_certificates WHERE member_id = ? LIMIT 1");
            $certStmt->bind_param('i', $member_id);
            $certStmt->execute();
            $certResult = $certStmt->get_result();
            
            if ($certificate = $certResult->fetch_assoc()) {
                // Send certificate via email
                $success = send_certificate_email($member, $certificate);
                
                // Check if we're in development mode
                $is_development = (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || 
                                  strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false);
                
                if ($success) {
                    if ($is_development) {
                        $report_message = 'Certificate generated successfully! (Development mode: Certificate saved to certificates folder instead of sending email to ' . $member['email'] . ')';
                    } else {
                        $report_message = 'Certificate sent successfully to ' . $member['email'];
                    }
                } else {
                    $report_message = 'Failed to generate/send certificate. Please check system configuration.';
                }
            } else {
                $report_message = 'No certificate found for this member. Please generate a certificate first.';
            }
            
            $certStmt->close();
        } else {
            if (!$member) {
                $report_message = 'Member not found.';
            } else {
                $report_message = 'No email address on file for ' . $member['full_name'] . '. Please update member information.';
            }
        }
    } else {
        $report_message = 'Invalid member ID.';
    }
}

$quota_hours = isset($_GET['quota']) ? max(0, (int) $_GET['quota']) : 20;

// Get member statistics
$member_stats = [
    'total_members' => 0,
    'active_members' => 0,
    'members_with_activities' => 0,
    'members_with_certificates' => 0
];

$stats_result = $conn->query(
    "SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
        SUM(CASE WHEN EXISTS(SELECT 1 FROM redcross_activities WHERE member_id = redcross_members.id) THEN 1 ELSE 0 END) as members_with_activities,
        SUM(CASE WHEN EXISTS(SELECT 1 FROM redcross_certificates WHERE member_id = redcross_members.id) THEN 1 ELSE 0 END) as members_with_certificates
     FROM redcross_members"
);

if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
    $member_stats = [
        'total_members' => (int)$stats_row['total_members'],
        'active_members' => (int)$stats_row['active_members'],
        'members_with_activities' => (int)$stats_row['members_with_activities'],
        'members_with_certificates' => (int)$stats_row['members_with_certificates']
    ];
}

$filter_member = (int) ($_GET['member_id'] ?? 0);
$filter_from   = $_GET['from'] ?? '';
$filter_to     = $_GET['to'] ?? '';

// Get selected member details if a member is selected
$selected_member = null;
$selected_member_activities = [];
if ($filter_member > 0) {
    $selected_member = get_member_details($conn, $filter_member);
    $selected_member_activities = get_member_activities($conn, $filter_member);
}

$where = [];
if ($filter_member > 0) {
    $where[] = "a.member_id = {$filter_member}";
}
if ($filter_from !== '') {
    $where[] = "a.activity_date >= '" . rc_clean($conn, $filter_from) . "'";
}
if ($filter_to !== '') {
    $where[] = "a.activity_date <= '" . rc_clean($conn, $filter_to) . "'";
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$activities = [];
$sql = "SELECT a.*, m.full_name
        FROM redcross_activities a
        JOIN redcross_members m ON m.id = a.member_id
        {$where_sql}
        ORDER BY a.activity_date DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $result->free();
}

$summary = [];
$resSum = $conn->query(
    "SELECT m.id, m.full_name, m.member_id, m.course, m.year_section,
            COALESCE(SUM(a.hours), 0) AS total_hours, 
            COUNT(DISTINCT a.id) AS activities,
            CASE WHEN EXISTS(SELECT 1 FROM redcross_certificates WHERE member_id = m.id) THEN 1 ELSE 0 END AS has_certificate
     FROM redcross_members m
     LEFT JOIN redcross_activities a ON a.member_id = m.id
     WHERE m.status = 'active'
     GROUP BY m.id, m.full_name, m.member_id, m.course, m.year_section
     ORDER BY m.full_name ASC"
);
if ($resSum) {
    while ($row = $resSum->fetch_assoc()) {
        $summary[] = $row;
    }
    $resSum->free();
}

$total_patients        = 0;
$patients_this_month   = 0;
$patients_by_condition = [];

$r = $conn->query("SELECT COUNT(*) AS c FROM redcross_patients");
if ($r && $row = $r->fetch_assoc()) {
    $total_patients = (int) $row['c'];
}

$r = $conn->query(
    "SELECT COUNT(*) AS c
     FROM redcross_patients
     WHERE YEAR(date_of_service) = YEAR(CURDATE())
       AND MONTH(date_of_service) = MONTH(CURDATE())"
);
if ($r && $row = $r->fetch_assoc()) {
    $patients_this_month = (int) $row['c'];
}

$r = $conn->query(
    "SELECT COALESCE(NULLIF(TRIM(case_description), ''), 'Unspecified') AS case_label, COUNT(*) AS c
     FROM redcross_patients
     GROUP BY case_label
     ORDER BY c DESC, case_label ASC"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $patients_by_condition[] = $row;
    }
    $r->free();
}

