<?php
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
    $conn = null;
}

// Initialize data arrays
$announcements = [];
$events = [];
$orders = [];
$student_products = [];
$attendance_records = [];
$summary_stats = [
    'total_announcements' => 0,
    'total_events' => 0,
    'total_orders' => 0,
    'total_student_products' => 0,
    'total_attendance' => 0,
    'total_sales' => 0,
    'delivered_orders' => 0
];

if ($conn) {
    // Get Announcements
    $sql = "SELECT * FROM afprotechs_announcements ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $summary_stats['total_announcements'] = count($announcements);
    }

    // Get Events
    $sql = "SELECT * FROM afprotechs_events ORDER BY start_date DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $summary_stats['total_events'] = count($events);
    }

    // Get Orders with product details
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
            $orders[] = $row;
            $summary_stats['total_sales'] += floatval($row['total_price']);
            if ($row['order_status'] === 'delivered') {
                $summary_stats['delivered_orders']++;
            }
        }
        $summary_stats['total_orders'] = count($orders);
    }

    // Get Student Products (products created by students)
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
            $student_products[] = $row;
        }
        $summary_stats['total_student_products'] = count($student_products);
    }

    // Get Attendance Records
    $sql = "SELECT 
                a.*,
                e.event_title,
                e.event_description,
                e.start_date as event_date
            FROM afprotechs_attendance a
            LEFT JOIN afprotechs_events e ON a.event_id = e.event_id
            ORDER BY a.attendance_date DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $summary_stats['total_attendance'] = count($attendance_records);
    }
}

// Handle report generation
$generate_report = isset($_POST['generate_report']) ? $_POST['generate_report'] : '';
$date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
$date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECHS - Generate Reports</title>
    <link rel="icon" type="image/png" href="../../assets/logo/afprotech_1.png?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="afprotechs_styles.css?v=<?= time() ?>">
    <style>
        .report-card {
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .stats-card {
            background: #000080;
            color: white;
        }
        .print-section {
            display: none;
        }
        .dropdown-menu {
            z-index: 1050;
        }
        .btn-group .dropdown-toggle {
            border-left: 1px solid rgba(255, 255, 255, 0.2);
        }
        @media print {
            .no-print { display: none !important; }
            .print-section { display: block !important; }
            .content { margin-left: 0 !important; }
            .sidebar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="sidebar d-flex flex-column align-items-start pt-4 px-3 no-print">
    <div class="sidebar-brand d-flex align-items-center gap-3 mb-4 w-100">
        <div class="sidebar-logo">
            <img src="../../assets/logo/afprotech_1.png?v=<?= time() ?>" alt="logo" width="60" height="60">
        </div>
        <div class="sidebar-org text-start">
            <span class="sidebar-org-title">AFPROTECH</span>
        </div>
    </div>
    <a href="afprotechs_dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="afprotechs_events.php"><i class="fa-solid fa-calendar-days"></i><span>Event</span></a>
    <a href="afprotechs_attendance.php"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a>
    <a href="afprotechs_Announcement.php"><i class="fa-solid fa-bullhorn"></i><span>Announcement</span></a>
    <a href="afprotechs_records.php"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="afprotechs_products.php"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="#" class="active"><i class="fa-solid fa-file-lines"></i><span>Generate Reports</span></a>
    <a href="#"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">
    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center no-print">
        <div><h2 class="fw-bold text-dark mb-0" style="font-size: 24px;">Generate Reports</h2></div>
        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:#000080;color:#fff;font-weight:bold;font-size:14px;">LB</div>
            <span class="fw-semibold">Lester Bulay<br><span class="dashboard-role">ADMIN</span></span>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="container-fluid px-4 mb-4 no-print">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-4"><i class="fa-solid fa-chart-pie me-2"></i>System Overview</h5>
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1"><?= $summary_stats['total_announcements'] ?></h3>
                            <small>Announcements</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1"><?= $summary_stats['total_events'] ?></h3>
                            <small>Events</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1"><?= $summary_stats['total_orders'] ?></h3>
                            <small>Total Orders</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1"><?= $summary_stats['total_student_products'] ?></h3>
                            <small>Student Products</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1"><?= $summary_stats['total_attendance'] ?></h3>
                            <small>Attendance Records</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-1">₱<?= number_format($summary_stats['total_sales'], 0) ?></h3>
                            <small>Total Sales</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Generation Options -->
    <div class="container-fluid px-4 mb-4 no-print">
        <div class="row g-4">
            <!-- Announcements Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-bullhorn text-primary fa-2x"></i>
                        </div>
                        <h5 class="card-title">Announcements Report</h5>
                        <p class="card-text text-muted">Generate report of all announcements with dates and content</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-primary" onclick="generateReport('announcements')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('announcements', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('announcements', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-calendar-days text-success fa-2x"></i>
                        </div>
                        <h5 class="card-title">Events Report</h5>
                        <p class="card-text text-muted">Generate report of all events with schedules and details</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-success" onclick="generateReport('events')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('events', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('events', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-shopping-cart text-warning fa-2x"></i>
                        </div>
                        <h5 class="card-title">Orders Report</h5>
                        <p class="card-text text-muted">Generate report of all orders with sales and status details</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-warning" onclick="generateReport('orders')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-warning dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('orders', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('orders', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Products Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-users text-info fa-2x"></i>
                        </div>
                        <h5 class="card-title">Student Products Report</h5>
                        <p class="card-text text-muted">Generate report of products created by students with earnings</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-info" onclick="generateReport('student_products')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('student_products', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('student_products', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-clipboard-check text-danger fa-2x"></i>
                        </div>
                        <h5 class="card-title">Attendance Report</h5>
                        <p class="card-text text-muted">Generate report of attendance records with student names</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-danger" onclick="generateReport('attendance')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('attendance', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('attendance', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comprehensive Report -->
            <div class="col-md-6 col-lg-4">
                <div class="card report-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-dark bg-opacity-10 p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-file-lines text-dark fa-2x"></i>
                        </div>
                        <h5 class="card-title">Comprehensive Report</h5>
                        <p class="card-text text-muted">Generate complete report with all data combined</p>
                        <div class="btn-group" role="group">
                            <button class="btn btn-dark" onclick="generateReport('comprehensive')">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>
                            <div class="btn-group dropend" role="group">
                                <button type="button" class="btn btn-dark dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('comprehensive', 'pdf')"><i class="fa-solid fa-file-pdf me-2"></i>Export for PDF</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport('comprehensive', 'csv')"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Report Display Area -->
    <div class="container-fluid px-4" id="reportDisplay" style="display: none;">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center no-print">
                <h5 class="mb-0" id="reportTitle">Report</h5>
                <div>
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="printReport()">
                        <i class="fa-solid fa-print me-1"></i>Print
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="hideReport()">
                        <i class="fa-solid fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
            <div class="card-body" id="reportContent">
                <!-- Report content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Print Header (only visible when printing) -->
    <div class="print-section">
        <div class="text-center mb-4">
            <img src="../../assets/logo/afprotech_1.png" alt="AFPROTECH Logo" style="width: 80px; height: 80px;">
            <h2 class="mt-2">AFPROTECH</h2>
            <h4 id="printReportTitle">Report</h4>
            <p class="text-muted">Generated on <?= date('F d, Y g:i A') ?></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Reports functionality

function generateReport(type) {
    const reportDisplay = document.getElementById('reportDisplay');
    const reportTitle = document.getElementById('reportTitle');
    const printReportTitle = document.getElementById('printReportTitle');
    const reportContent = document.getElementById('reportContent');
    
    // Show loading
    reportContent.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Loading report...</div>';
    reportDisplay.style.display = 'block';
    reportDisplay.scrollIntoView({ behavior: 'smooth' });
    
    // Build API URL
    let apiUrl = 'backend/afprotechs_get_reports_data.php?type=' + type;
    
    // Fetch data from API
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data); // Debug log
            if (data.success) {
                let title = '';
                let content = '';
                
                switch(type) {
                    case 'announcements':
                        title = 'Announcements Report';
                        content = generateAnnouncementsReportFromData(data.data);
                        break;
                    case 'events':
                        title = 'Events Report';
                        content = generateEventsReportFromData(data.data);
                        break;
                    case 'orders':
                        title = 'Orders Report';
                        content = generateOrdersReportFromData(data.data, data.summary);
                        break;
                    case 'student_products':
                        title = 'Student Products Report';
                        content = generateStudentProductsReportFromData(data.data);
                        break;
                    case 'attendance':
                        title = 'Attendance Report';
                        console.log('Attendance data received:', data.data);
                        console.log('Attendance count:', data.data ? data.data.length : 0);
                        content = generateAttendanceReportFromData(data.data);
                        break;
                    case 'comprehensive':
                        title = 'Comprehensive Report';
                        content = generateComprehensiveReportFromData(data);
                        break;
                }
                
                reportTitle.textContent = title;
                printReportTitle.textContent = title;
                reportContent.innerHTML = content;
            } else {
                let errorMsg = 'Error loading report: ' + (data.message || 'Unknown error');
                if (data.error) errorMsg += '<br>SQL Error: ' + data.error;
                if (data.query) errorMsg += '<br>Query: ' + data.query;
                reportContent.innerHTML = '<div class="alert alert-danger">' + errorMsg + '</div>';
            }
        })
        .catch(error => {
            reportContent.innerHTML = '<div class="alert alert-danger">Error loading report: ' + error.message + '</div>';
        });
}

// Export report function for PDF and CSV
function exportReport(type, format) {
    // Build export URL
    const exportUrl = `backend/afprotechs_export_report.php?type=${type}&format=${format}`;

    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.style.display = 'none';
    document.body.appendChild(link);

    // Trigger download
    link.click();

    // Clean up
    document.body.removeChild(link);
}

function generateAnnouncementsReportFromData(announcements) {
    let html = `
        <div class="mb-4">
            <h6 class="text-muted">Total Announcements: ${announcements.length}</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Content</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (announcements.length === 0) {
        html += '<tr><td colspan="4" class="text-center text-muted py-4">No announcements found for the selected period</td></tr>';
    } else {
        announcements.forEach(announcement => {
            const date = new Date(announcement.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            html += `
                <tr>
                    <td>${announcement.announcement_id}</td>
                    <td><strong>${announcement.announcement_title}</strong></td>
                    <td>${announcement.announcement_content.substring(0, 100)}${announcement.announcement_content.length > 100 ? '...' : ''}</td>
                    <td>${date}</td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateEventsReport() {
    const events = <?= json_encode($events) ?>;
    
    let html = `
        <div class="mb-4">
            <h6 class="text-muted">Total Events: ${events.length}</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Event Title</th>
                        <th>Description</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    events.forEach(event => {
        const startDate = new Date(event.start_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const endDate = new Date(event.end_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        html += `
            <tr>
                <td>${event.event_id}</td>
                <td><strong>${event.event_title}</strong></td>
                <td>${event.event_description.substring(0, 80)}${event.event_description.length > 80 ? '...' : ''}</td>
                <td>${startDate}</td>
                <td>${endDate}</td>
                <td>${event.event_location || 'N/A'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateOrdersReport() {
    const orders = <?= json_encode($orders) ?>;
    const totalSales = <?= $summary_stats['total_sales'] ?>;
    const deliveredOrders = <?= $summary_stats['delivered_orders'] ?>;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4>₱${totalSales.toLocaleString()}</h4>
                        <small>Total Sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4>${deliveredOrders}</h4>
                        <small>Delivered Orders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4>${orders.length}</h4>
                        <small>Total Orders</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Order ID</th>
                        <th>Student ID</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Creator</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    orders.forEach(order => {
        const date = new Date(order.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const statusBadge = getStatusBadge(order.order_status);
        
        html += `
            <tr>
                <td>${order.order_id}</td>
                <td>${order.student_id}</td>
                <td>${order.product_name}</td>
                <td><span class="badge ${order.product_type === 'student' ? 'bg-info' : 'bg-secondary'}">${order.product_type}</span></td>
                <td>${order.quantity}</td>
                <td>₱${parseFloat(order.total_price).toLocaleString()}</td>
                <td>${statusBadge}</td>
                <td>${date}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateStudentProductsReportFromData(studentProducts) {
    let html = `
        <div class="mb-4">
            <h6 class="text-muted">Total Student Products: ${studentProducts.length}</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Group Members</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Total Orders</th>
                        <th>Total Earnings</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (studentProducts.length === 0) {
        html += '<tr><td colspan="8" class="text-center text-muted py-4">No student products found for the selected period</td></tr>';
    } else {
        studentProducts.forEach(product => {
            const date = new Date(product.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const groupMembers = 'N/A'; // Group members not available in current table structure
            
            const statusBadge = product.status === 'approved' ? 
                '<span class="badge bg-success">Approved</span>' :
                product.status === 'rejected' ?
                '<span class="badge bg-danger">Rejected</span>' :
                '<span class="badge bg-warning">Pending</span>';
            
            html += `
                <tr>
                    <td>${product.product_id}</td>
                    <td><strong>${product.product_name}</strong></td>
                    <td><small>${groupMembers}</small></td>
                    <td>₱${parseFloat(product.product_price).toLocaleString()}</td>
                    <td>${statusBadge}</td>
                    <td>${product.total_orders || 0}</td>
                    <td>₱${parseFloat(product.total_earnings || 0).toLocaleString()}</td>
                    <td>${date}</td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateAttendanceReportFromData(attendance) {
    let html = `
        <div class="mb-4">
            <h6 class="text-muted">Total Attendance Records: ${attendance.length}</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Event</th>
                        <th>Event Date</th>
                        <th>Attendance Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (attendance.length === 0) {
        html += '<tr><td colspan="7" class="text-center text-muted py-4">No attendance records found</td></tr>';
    } else {
        attendance.forEach(record => {
            const attendanceTime = new Date(record.attendance_time).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const eventDate = record.event_date ? 
                new Date(record.event_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) : 'N/A';
            
            html += `
                <tr>
                    <td>${record.afprotech_id_attendance || 'N/A'}</td>
                    <td>${record.student_id}</td>
                    <td>${record.student_name}</td>
                    <td>${record.event_title || 'N/A'}</td>
                    <td>${eventDate}</td>
                    <td>${attendanceTime}</td>
                    <td><span class="badge bg-success">Present</span></td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateComprehensiveReport() {
    const stats = <?= json_encode($summary_stats) ?>;
    
    let html = `
        <div class="row mb-4">
            <div class="col-12">
                <h5>System Overview</h5>
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-primary">${stats.total_announcements}</h4>
                                <small>Announcements</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-success">${stats.total_events}</h4>
                                <small>Events</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-warning">${stats.total_orders}</h4>
                                <small>Orders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-info">${stats.total_student_products}</h4>
                                <small>Student Products</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-danger">${stats.total_attendance}</h4>
                                <small>Attendance</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-dark">₱${stats.total_sales.toLocaleString()}</h4>
                                <small>Total Sales</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-4">
            <h6>Recent Announcements</h6>
            ${generateAnnouncementsReport()}
        </div>
        
        <div class="mb-4">
            <h6>Recent Events</h6>
            ${generateEventsReport()}
        </div>
        
        <div class="mb-4">
            <h6>Orders Summary</h6>
            ${generateOrdersReport()}
        </div>
        
        <div class="mb-4">
            <h6>Student Products</h6>
            ${generateStudentProductsReport()}
        </div>
        
        <div class="mb-4">
            <h6>Attendance Records</h6>
            ${generateAttendanceReport()}
        </div>
    `;
    
    return html;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning">Pending</span>',
        'confirmed': '<span class="badge bg-info">Confirmed</span>',
        'preparing': '<span class="badge bg-primary">Preparing</span>',
        'ready': '<span class="badge bg-success">Ready</span>',
        'delivered': '<span class="badge bg-success">Delivered</span>',
        'cancelled': '<span class="badge bg-danger">Cancelled</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function printReport() {
    window.print();
}

function hideReport() {
    document.getElementById('reportDisplay').style.display = 'none';
}

function generateEventsReportFromData(events) {
    let html = `
        <div class="mb-4">
            <h6 class="text-muted">Total Events: ${events.length}</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Event Title</th>
                        <th>Description</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (events.length === 0) {
        html += '<tr><td colspan="6" class="text-center text-muted py-4">No events found for the selected period</td></tr>';
    } else {
        events.forEach(event => {
            const startDate = new Date(event.start_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const endDate = new Date(event.end_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            html += `
                <tr>
                    <td>${event.event_id}</td>
                    <td><strong>${event.event_title}</strong></td>
                    <td>${event.event_description.substring(0, 80)}${event.event_description.length > 80 ? '...' : ''}</td>
                    <td>${startDate}</td>
                    <td>${endDate}</td>
                    <td>${event.event_location || 'N/A'}</td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateOrdersReportFromData(orders, summary) {
    const totalSales = summary.total_sales || 0;
    const deliveredOrders = summary.delivered_count || 0;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4>₱${totalSales.toLocaleString()}</h4>
                        <small>Total Sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4>${deliveredOrders}</h4>
                        <small>Delivered Orders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4>${orders.length}</h4>
                        <small>Total Orders</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Order ID</th>
                        <th>Student ID</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (orders.length === 0) {
        html += '<tr><td colspan="8" class="text-center text-muted py-4">No orders found for the selected period</td></tr>';
    } else {
        orders.forEach(order => {
            const date = new Date(order.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const statusBadge = getStatusBadge(order.order_status);
            
            html += `
                <tr>
                    <td>${order.order_id}</td>
                    <td>${order.student_id}</td>
                    <td>${order.product_name}</td>
                    <td><span class="badge ${order.product_type === 'student' ? 'bg-info' : 'bg-secondary'}">${order.product_type}</span></td>
                    <td>${order.quantity}</td>
                    <td>₱${parseFloat(order.total_price).toLocaleString()}</td>
                    <td>${statusBadge}</td>
                    <td>${date}</td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function generateComprehensiveReportFromData(data) {
    if (!data || !data.data) {
        return '<div class="alert alert-warning">No comprehensive data available.</div>';
    }

    const comprehensiveData = data.data;
    const summary = data.summary;

    let html = `
        <div class="mb-4">
            <h4 class="text-center mb-4">AFPROTECHS Comprehensive Report</h4>
            <div class="row text-center">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>${summary.announcements_count || 0}</h5>
                            <small>Announcements</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>${summary.events_count || 0}</h5>
                            <small>Events</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>${summary.orders_count || 0}</h5>
                            <small>Orders</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5>${summary.student_products_count || 0}</h5>
                            <small>Student Products</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5>${summary.attendance_count || 0}</h5>
                            <small>Attendance Records</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <h5>₱${(summary.total_sales || 0).toLocaleString()}</h5>
                            <small>Total Sales</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="mb-5">
            <h5 class="text-primary mb-3"><i class="fa-solid fa-bullhorn me-2"></i>Announcements</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    if (comprehensiveData.announcements && comprehensiveData.announcements.length > 0) {
        comprehensiveData.announcements.forEach(announcement => {
            const date = new Date(announcement.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            html += `
                <tr>
                    <td>${announcement.announcement_id}</td>
                    <td><strong>${announcement.announcement_title}</strong></td>
                    <td>${announcement.announcement_content.substring(0, 50)}${announcement.announcement_content.length > 50 ? '...' : ''}</td>
                    <td>${date}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="4" class="text-center text-muted">No announcements found</td></tr>';
    }

    html += `
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Events Section -->
        <div class="mb-5">
            <h5 class="text-success mb-3"><i class="fa-solid fa-calendar-days me-2"></i>Events</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Event Title</th>
                            <th>Description</th>
                            <th>Start Date</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    if (comprehensiveData.events && comprehensiveData.events.length > 0) {
        comprehensiveData.events.forEach(event => {
            const startDate = new Date(event.start_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            html += `
                <tr>
                    <td>${event.event_id}</td>
                    <td><strong>${event.event_title}</strong></td>
                    <td>${event.event_description ? event.event_description.substring(0, 50) + (event.event_description.length > 50 ? '...' : '') : ''}</td>
                    <td>${startDate}</td>
                    <td>${event.event_location || ''}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center text-muted">No events found</td></tr>';
    }

    html += `
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="mb-5">
            <h5 class="text-warning mb-3"><i class="fa-solid fa-shopping-cart me-2"></i>Orders</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    if (comprehensiveData.orders && comprehensiveData.orders.length > 0) {
        comprehensiveData.orders.forEach(order => {
            const date = new Date(order.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            const customerName = order.customer_name || `${order.first_name || ''} ${order.last_name || ''}`.trim() || 'N/A';
            html += `
                <tr>
                    <td>${order.order_id}</td>
                    <td>${order.product_name || 'N/A'}</td>
                    <td>${customerName}</td>
                    <td>${order.quantity || 1}</td>
                    <td>₱${parseFloat(order.total_price || 0).toLocaleString()}</td>
                    <td><span class="badge bg-${order.order_status === 'delivered' ? 'success' : order.order_status === 'pending' ? 'warning' : 'secondary'}">${order.order_status || 'pending'}</span></td>
                    <td>${date}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="7" class="text-center text-muted">No orders found</td></tr>';
    }

    html += `
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Student Products Section -->
        <div class="mb-5">
            <h5 class="text-info mb-3"><i class="fa-solid fa-users me-2"></i>Student Products</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Student</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    if (comprehensiveData.student_products && comprehensiveData.student_products.length > 0) {
        comprehensiveData.student_products.forEach(product => {
            const date = new Date(product.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            html += `
                <tr>
                    <td>${product.product_id}</td>
                    <td><strong>${product.product_name}</strong></td>
                    <td>${product.first_name} ${product.last_name}</td>
                    <td>₱${parseFloat(product.product_price || 0).toLocaleString()}</td>
                    <td><span class="badge bg-${product.status === 'approved' ? 'success' : product.status === 'rejected' ? 'danger' : 'warning'}">${product.status || 'pending'}</span></td>
                    <td>${date}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="6" class="text-center text-muted">No student products found</td></tr>';
    }

    html += `
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Attendance Section -->
        <div class="mb-5">
            <h5 class="text-danger mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Attendance Records</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Event</th>
                            <th>Attendance Date</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    if (comprehensiveData.attendance && comprehensiveData.attendance.length > 0) {
        comprehensiveData.attendance.forEach(record => {
            const date = new Date(record.attendance_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            const time = record.attendance_time ? new Date(record.attendance_time).toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            }) : '';
            html += `
                <tr>
                    <td>${record.afprotech_id_attendance}</td>
                    <td>${record.student_name || `${record.first_name} ${record.last_name}`}</td>
                    <td>${record.student_id || record.id_number}</td>
                    <td>${record.event_title || 'N/A'}</td>
                    <td>${date}</td>
                    <td>${time}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="6" class="text-center text-muted">No attendance records found</td></tr>';
    }

    html += `
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="text-center mb-0">Report Generated on ${new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}</h6>
        </div>
    `;

    return html;
}
</script>

</body>
</html>
<?php if ($conn) $conn->close(); ?>