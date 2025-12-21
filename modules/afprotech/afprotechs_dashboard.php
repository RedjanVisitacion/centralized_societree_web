<?php
// Prefer AFPROTECH module config/connection (no edits to root db_connection.php)
require_once __DIR__ . '/config/config.php';
$conn = null;
try {
    $conn = getAfprotechDbConnection();
} catch (Throwable $t) {
    // Fallback to root db_connection.php if AFPROTECH config fails
    $rootDbPath = realpath(__DIR__ . '/../../db_connection.php');
    if ($rootDbPath && file_exists($rootDbPath)) {
        require_once $rootDbPath; // defines $pdo
        // If PDO is available, open a mysqli for the legacy code paths
        try {
            $conn = new mysqli(DB_HOST_PRIMARY, DB_USER_PRIMARY, DB_PASS_PRIMARY, DB_NAME_PRIMARY);
        } catch (Throwable $t2) {
            // final fallback handled below
        }
    }
}

if (!$conn || $conn->connect_error) {
    die('Database connection failed: ' . ($conn->connect_error ?? 'Connection not established'));
}

// Fetch counts from database
$total_events = 0;
$total_events_result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_events");
if ($total_events_result) {
    $row = $total_events_result->fetch_assoc();
    $total_events = $row ? $row['count'] : 0;
}

// Ensure announcements table exists and count records
$conn->query("
    CREATE TABLE IF NOT EXISTS afprotechs_announcements (
        announcement_id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_title VARCHAR(255) NOT NULL,
        announcement_content TEXT NOT NULL,
        announcement_datetime DATETIME NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$total_announcements = 0;
$total_announcements_result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_announcements");
if ($total_announcements_result) {
    $row = $total_announcements_result->fetch_assoc();
    $total_announcements = $row ? $row['count'] : 0;
}

$recent_announcements = [];

// Check which columns exist in the table
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
$select_cols = "announcement_id, announcement_title, announcement_content, announcement_datetime";
if ($has_status) $select_cols .= ", status";

$order_by = "announcement_datetime DESC";
if ($has_created_at) $order_by .= ", created_at DESC";

$recent_announcements_sql = "SELECT $select_cols FROM afprotechs_announcements ORDER BY $order_by LIMIT 5";

$recent_announcements_result = $conn->query($recent_announcements_sql);
if ($recent_announcements_result && $recent_announcements_result->num_rows > 0) {
    while ($row = $recent_announcements_result->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
}

// Get total products count
$total_products = 0;
$total_products_result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_products");
if ($total_products_result) {
    $row = $total_products_result->fetch_assoc();
    $total_products = $row ? $row['count'] : 0;
}
// Get total attendance count
$total_attendance = 0;
$attendance_count_result = $conn->query("SELECT COUNT(*) as count FROM afprotechs_attendance");
if ($attendance_count_result) {
    $row = $attendance_count_result->fetch_assoc();
    $total_attendance = $row ? $row['count'] : 0;
}

// Fetch all upcoming events (for localStorage pin functionality)
$events_sql = "
    SELECT 
        event_id,
        event_title,
        event_description,
        start_date AS event_date,
        start_date,
        end_date,
        event_location
    FROM afprotechs_events 
    ORDER BY start_date ASC";
$events_result = $conn->query($events_sql);
$upcoming_events = [];

if ($events_result && $events_result->num_rows > 0) {
    while ($row = $events_result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECHS</title>
    <link rel="icon" type="image/png" href="../../assets/logo/afprotech_1.png?v=<?= time() ?>">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="afprotechs_styles.css?v=<?= time() ?>">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar d-flex flex-column align-items-start pt-4 px-3">

    <div class="sidebar-brand d-flex align-items-center gap-3 mb-4 w-100">
        <div class="sidebar-logo">
            <img src="../../assets/logo/afprotech_1.png?v=<?= time() ?>" alt="logo" width="60" height="60">
        </div>
        <div class="sidebar-org text-start">
            <span class="sidebar-org-title">
                AFPROTECHS
            </span>
        </div>
    </div>

    <a href="afprotechs_dashboard.php" class="active">
        <i class="fa-solid fa-house"></i><span>Home</span>
    </a>
    <a href="afprotechs_events.php">
        <i class="fa-solid fa-calendar-days"></i><span>Event</span>
    </a>
    <a href="afprotechs_attendance.php">
        <i class="fa-solid fa-clipboard-check"></i><span>Attendance</span>
    </a>
    <a href="afprotechs_announcement.php">
        <i class="fa-solid fa-bullhorn"></i><span>Announcement</span>
    </a>
    <a href="afprotechs_records.php">
        <i class="fa-solid fa-chart-bar"></i><span>Records</span>
    </a>
    <a href="afprotechs_products.php">
        <i class="fa-solid fa-cart-shopping"></i><span>Product</span>
    </a>
    <a href="afprotechs_reports.php">
        <i class="fa-solid fa-file-lines"></i><span>Generate Reports</span>
    </a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
    </a>

</div>

<!-- MAIN CONTENT -->
<div class="content">

    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">

        <div class="dashboard-search">
            <form>
                <div class="input-group">
                    <input class="form-control" type="search" placeholder="Search">
                    <button class="btn btn-outline-secondary"><i class="fa fa-search"></i></button>
                </div>
            </form>
        </div>

        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <div class="rounded-circle dashboard-profile-avatar 
            d-flex align-items-center justify-content-center"
     style="width:40px;height:40px;background:#000080;
            color:#fff;font-weight:bold;font-size:14px; text-transform: uppercase;">
    LB
</div>

<span class="fw-semibold dashboard-admin-name">
    Lester Bulay<br>
    <span class="dashboard-role">ADMIN</span>
</span>

        </div>

    </div>

    <!-- ATTENDANCE COUNTDOWN SECTION -->
    <div class="attendance-count-section bg-white shadow-sm rounded p-3 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <div class="attendance-icon d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #000080; border-radius: 12px; color: white;">
                        <i class="fa-solid fa-clipboard-check" style="font-size: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold" style="color: #000080;">
                            Attendance Countdown
                            <span id="countdownStatus" class="badge bg-secondary ms-2" style="font-size: 10px;">Inactive</span>
                        </h5>
                        <p class="mb-0 text-muted">Student Attendance Timer</p>
                        <small class="text-muted" style="font-size: 11px;">Attendance will close soon!</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex justify-content-end align-items-center gap-3">
                    <div class="attendance-count">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <div class="countdown-square d-flex align-items-center justify-content-center" id="hours" style="width: 50px; height: 50px; background: #000080; border-radius: 8px; color: white; font-size: 24px; font-weight: bold; font-family: monospace;">00</div>
                            <span style="font-size: 24px; color: #6c757d;">:</span>
                            <div class="countdown-square d-flex align-items-center justify-content-center" id="minutes" style="width: 50px; height: 50px; background: #000080; border-radius: 8px; color: white; font-size: 24px; font-weight: bold; font-family: monospace;">00</div>
                            <span style="font-size: 24px; color: #6c757d;">:</span>
                            <div class="countdown-square d-flex align-items-center justify-content-center" id="seconds" style="width: 50px; height: 50px; background: #000080; border-radius: 8px; color: white; font-size: 24px; font-weight: bold; font-family: monospace;">00</div>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#countdownModal" style="border-color: #000080; color: #000080;">
                        <i class="fa-solid fa-gear me-1"></i>Manage
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="summary-text">
                    <div class="summary-label">TOTAL EVENTS</div>
                    <div class="summary-value"><?= $total_events ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="summary-text">
                    <div class="summary-label">TOTAL ANNOUNCEMENTS</div>
                    <div class="summary-value"><?= $total_announcements ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-box"></i></div>
                <div class="summary-text">
                    <div class="summary-label">TOTAL PRODUCTS</div>
                    <div class="summary-value"><?= $total_products ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-id-card"></i></div>
                <div class="summary-text">
                    <div class="summary-label">TOTAL ATTENDANCE</div>
                    <div class="summary-value"><?= $total_attendance ?></div>
                </div>
            </div>
        </div>
    </div>



    <!-- MAIN PANELS -->
    <div class="row g-3 align-items-stretch">

        <!-- Recent Announcement -->
        <div class="col-lg-8">
            <div class="section-box section-side w-100 h-100">
                <div class="section-title mb-3">
                    <i class="fa-solid fa-bullhorn"></i>
                    Recent Announcements
                </div>
                <?php if (empty($recent_announcements)): ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-bullhorn text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mb-0 text-muted">No announcements yet.</p>
                        <small class="text-muted">Announcements will appear here when they are created.</small>
                    </div>
                <?php else: ?>
                    <div class="dashboard-announcements-list">
                        <?php foreach ($recent_announcements as $index => $a): ?>
                            <div class="dashboard-announcement-item mb-3 <?= $index < count($recent_announcements) - 1 ? 'pb-3 border-bottom' : '' ?> announcement-item" 
                                 style="cursor: pointer;"
                                 data-title="<?= htmlspecialchars($a['announcement_title']) ?>"
                                 data-content="<?= htmlspecialchars($a['announcement_content']) ?>"
                                 data-datetime="<?= htmlspecialchars($a['announcement_datetime']) ?>">
                                <div class="d-flex flex-column">
                                    <div class="dashboard-announcement-title"><?= htmlspecialchars($a['announcement_title']) ?></div>
                                    <div class="dashboard-announcement-content text-muted mt-2">
                                        <?= htmlspecialchars($a['announcement_content']) ?>
                                    </div>
                                    <div class="dashboard-announcement-date text-muted mt-auto pt-3" style="font-size: 11px; border-top: 1px dashed #e0e0e0; margin-top: 12px !important; padding-top: 8px !important;">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <?= date('M d, Y', strtotime($a['announcement_datetime'])) ?> at <?= date('g:i A', strtotime($a['announcement_datetime'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right column: Upcoming + Available -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="section-box section-side flex-fill">
                <div class="section-title mb-3">
                    <i class="fa-solid fa-calendar-days"></i>
                    Upcoming Events
                </div>
                <?php if (empty($upcoming_events)): ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-calendar-days text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mb-0 text-muted">No events have been announced.</p>
                        <small class="text-muted">Upcoming events will appear here when they are created.</small>
                    </div>
                <?php else: ?>
                    <div class="dashboard-events-list">
                        <?php foreach ($upcoming_events as $index => $event): ?>
                            <div class="dashboard-event-item mb-3 <?= $index < count($upcoming_events) - 1 ? 'pb-3 border-bottom' : '' ?>"
                                 data-event-id="<?= $event['event_id'] ?>"
                                 data-title="<?= htmlspecialchars($event['event_title']) ?>"
                                 data-desc="<?= htmlspecialchars($event['event_description']) ?>"
                                 data-location="<?= htmlspecialchars($event['event_location'] ?? '') ?>"
                                 data-date="<?= htmlspecialchars($event['event_date']) ?>"
                                 data-start-date="<?= htmlspecialchars($event['start_date'] ?? $event['event_date']) ?>"
                                 data-end-date="<?= htmlspecialchars($event['end_date'] ?? $event['event_date']) ?>"
                                 style="cursor: pointer;">
                                <?php
                                    $startDate = $event['start_date'] ?? $event['event_date'];
                                    $endDate   = $event['end_date']   ?? $event['event_date'];
                                    $isSameDay = $startDate === $endDate;
                                ?>
                                <div class="d-flex align-items-start gap-2">
                                    <div class="dashboard-event-icon">
                                        <i class="fa-solid fa-calendar-days"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="dashboard-event-title"><?= htmlspecialchars($event['event_title']) ?></div>
                                        <div class="dashboard-event-date text-muted">
                                            <?php if ($isSameDay): ?>
                                                <?= date('M d, Y', strtotime($startDate)) ?>
                                            <?php else: ?>
                                                <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-box section-side flex-fill">
                <div class="section-title mb-3">
                    <i class="fa-solid fa-box"></i>
                    Available Products
                </div>
                <?php
                // Fetch recent products
                $products_sql = "SELECT product_id, product_name, product_price, product_quantity FROM afprotechs_products ORDER BY created_at DESC LIMIT 5";
                $products_result = $conn->query($products_sql);
                
                if ($products_result && $products_result->num_rows > 0):
                ?>
                    <div class="dashboard-products-list">
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <div class="dashboard-product-item mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="dashboard-product-icon">
                                        <i class="fa-solid fa-box" style="color: #000080;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="dashboard-product-title fw-semibold"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <div class="dashboard-product-details text-muted small">
                                            <span class="me-3">₱<?= number_format($product['product_price'], 2) ?></span>
                                            <span class="badge bg-light text-dark">Stock: <?= $product['product_quantity'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-box text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mb-0 text-muted">No products have been listed.</p>
                        <small class="text-muted">Products will appear here when they are added.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Recent Orders Section -->
    <div class="row g-3 mt-4">
        <div class="col-12">
            <div class="section-box section-side w-100">
                <div class="section-title mb-3">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Recent Orders
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Student ID</th>
                                <th>Product</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentOrdersTable">
                            <?php
                            // Get recent orders (exclude delivered - those go to Records page)
                            $recent_orders_sql = "SELECT order_id, student_id, product_name, total_price, order_status, order_date 
                                                 FROM afprotechs_orders 
                                                 WHERE order_status != 'delivered' OR order_status IS NULL
                                                 ORDER BY order_date DESC 
                                                 LIMIT 5";
                            
                            // Check if order_number column exists
                            $column_check = $conn->query("SHOW COLUMNS FROM afprotechs_orders LIKE 'order_number'");
                            if ($column_check && $column_check->num_rows > 0) {
                                $recent_orders_sql = "SELECT order_id, order_number, student_id, product_name, total_price, order_status, order_date 
                                                     FROM afprotechs_orders 
                                                     WHERE order_status != 'delivered' OR order_status IS NULL
                                                     ORDER BY order_date DESC 
                                                     LIMIT 5";
                            }
                            
                            $recent_orders_result = $conn->query($recent_orders_sql);
                            
                            if ($recent_orders_result && $recent_orders_result->num_rows > 0):
                                while ($order = $recent_orders_result->fetch_assoc()):
                                    $status_class = '';
                                    $status_text = '';
                                    switch($order['order_status']) {
                                        case 'pending': 
                                            $status_class = 'bg-warning text-dark'; 
                                            $status_text = 'Pending';
                                            break;
                                        case 'confirmed': 
                                            $status_class = 'bg-info text-white'; 
                                            $status_text = 'Confirmed';
                                            break;
                                        case 'preparing': 
                                            $status_class = 'bg-primary text-white'; 
                                            $status_text = 'Preparing';
                                            break;
                                        case 'ready': 
                                            $status_class = 'bg-success text-white'; 
                                            $status_text = 'Ready';
                                            break;
                                        case 'delivered': 
                                            $status_class = 'bg-secondary text-white'; 
                                            $status_text = 'Delivered';
                                            break;
                                        case 'cancelled': 
                                            $status_class = 'bg-danger text-white'; 
                                            $status_text = 'Cancelled';
                                            break;
                                        case '':
                                        case null:
                                            $status_class = 'bg-light text-muted'; 
                                            $status_text = 'Awaiting Confirmation';
                                            break;
                                        default: 
                                            $status_class = 'bg-light text-dark';
                                            $status_text = ucfirst($order['order_status']);
                                    }
                            ?>
                            <tr>
                                <td><strong><?php 
                                    if (isset($order['order_number']) && !empty($order['order_number'])) {
                                        echo htmlspecialchars($order['order_number']);
                                    } else {
                                        // Generate order number format for display
                                        $year = date('Y', strtotime($order['order_date'] ?? 'now'));
                                        echo "ORDER{$year}" . str_pad($order['order_id'], 2, '0', STR_PAD_LEFT);
                                    }
                                ?></strong></td>
                                <td><?= htmlspecialchars($order['student_id']) ?></td>
                                <td><?= htmlspecialchars($order['product_name']) ?></td>
                                <td>₱<?= number_format($order['total_price'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $status_class ?> rounded-pill">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= date('M d, g:i A', strtotime($order['order_date'])) ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $order_number_display = isset($order['order_number']) && !empty($order['order_number']) 
                                        ? htmlspecialchars($order['order_number']) 
                                        : "ORDER" . date('Y', strtotime($order['order_date'] ?? 'now')) . str_pad($order['order_id'], 2, '0', STR_PAD_LEFT);
                                    
                                    if (empty($order['order_status']) || $order['order_status'] === null): ?>
                                        <button class="btn btn-sm btn-success confirm-order-btn" 
                                                data-order-id="<?= $order['order_id'] ?>"
                                                data-order-number="<?= $order_number_display ?>">
                                            <i class="fa-solid fa-check me-1"></i>Confirm
                                        </button>
                                    <?php elseif ($order['order_status'] === 'delivered'): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item view-order-btn" href="#" 
                                                       data-order-id="<?= $order['order_id'] ?>"
                                                       data-order-number="<?= $order_number_display ?>">
                                                        <i class="fa-solid fa-eye me-2"></i>View Order Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item mark-delivered-btn text-success" href="#"
                                                       data-order-id="<?= $order['order_id'] ?>"
                                                       data-order-number="<?= $order_number_display ?>">
                                                        <i class="fa-solid fa-check-double me-2"></i>Mark as Delivered
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item delete-order-btn text-danger" href="#"
                                                       data-order-id="<?= $order['order_id'] ?>"
                                                       data-order-number="<?= $order_number_display ?>">
                                                        <i class="fa-solid fa-trash me-2"></i>Delete Order
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-inbox mb-2" style="font-size: 24px; opacity: 0.5;"></i>
                                    <br>No orders yet.
                                    <br><small>Orders will appear here when customers place them.</small>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="eventModalTitle">Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="event-modal-content">
                        <p id="eventModalDesc" class="mb-2" style="white-space: pre-line; line-height: 1.8;"></p>
                        <div id="eventModalLocation" class="text-muted mb-2"></div>
                        <div id="eventModalDateTime" class="text-muted"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="announcementModalTitle">Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="announcement-modal-content">
                        <p id="announcementModalContent" class="mb-3"></p>
                        <div id="announcementModalDateTime" class="small" style="color: #000080;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Countdown Management Modal -->
    <div class="modal fade" id="countdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Countdown Timer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Set Countdown Duration</label>
                        <div class="row g-3">
                            <div class="col-4">
                                <label for="setHours" class="form-label">Hours</label>
                                <input type="number" class="form-control" id="setHours" min="0" max="23" value="0">
                            </div>
                            <div class="col-4">
                                <label for="setMinutes" class="form-label">Minutes</label>
                                <input type="number" class="form-control" id="setMinutes" min="0" max="59" value="30">
                            </div>
                            <div class="col-4">
                                <label for="setSeconds" class="form-label">Seconds</label>
                                <input type="number" class="form-control" id="setSeconds" min="0" max="59" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Note:</strong> The countdown will persist even after page refresh. Students will see the same countdown on their mobile devices.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="startCountdown">
                        <i class="fa-solid fa-play me-1"></i>Start Countdown
                    </button>
                    <button type="button" class="btn btn-danger" id="stopCountdown">
                        <i class="fa-solid fa-stop me-1"></i>Stop Countdown
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" style="color: #000080; font-weight: bold;">Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fa-solid fa-right-from-bracket" style="font-size: 48px; color: #000080; margin-bottom: 1rem;"></i>
                    </div>
                    <p class="mb-4" style="color: #2c2c2c; font-size: 16px;">Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-logout-yes" id="confirmLogoutBtn" style="background: #000080; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">Yes</button>
                    <button type="button" class="btn btn-logout-no" data-bs-dismiss="modal" style="background: #6c757d; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">No</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('eventModal');
    if (!modalEl || !window.bootstrap) return;
    const modal = new bootstrap.Modal(modalEl);

    // Handle dashboard event item clicks
    document.querySelectorAll('.dashboard-event-item').forEach(item => {
        item.addEventListener('click', function() {
            const title = this.getAttribute('data-title') || 'Event';
            const desc = this.getAttribute('data-desc') || '';
            const location = this.getAttribute('data-location') || '';
            const date = this.getAttribute('data-date') || '';
            const startDate = this.getAttribute('data-start-date') || date;
            const endDate = this.getAttribute('data-end-date') || date;
            const time = this.getAttribute('data-time') || '';
            
            // Set modal content
            modalEl.querySelector('#eventModalTitle').textContent = title;
            modalEl.querySelector('#eventModalDesc').textContent = desc;
            
            // Handle location
            const locationEl = modalEl.querySelector('#eventModalLocation');
            if (location) {
                locationEl.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + location;
                locationEl.classList.remove('d-none');
            } else {
                locationEl.classList.add('d-none');
            }
            
            // Handle date and time
            const dateTimeEl = modalEl.querySelector('#eventModalDateTime');
            if (startDate) {
                if (startDate === endDate) {
                    // Single day event
                    const dateObj = new Date(startDate);
                    const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    dateTimeEl.innerHTML = '<i class="fa-solid fa-calendar-days" style="color: #000080;"></i> <span style="color: #000080;">' + formattedDate + '</span>';
                } else {
                    // Multi-day event
                    const startDateObj = new Date(startDate);
                    const endDateObj = new Date(endDate);
                    const formattedStartDate = startDateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    const formattedEndDate = endDateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    dateTimeEl.innerHTML = '<i class="fa-solid fa-calendar-days" style="color: #000080;"></i> <span style="color: #000080;">' + formattedStartDate + ' - ' + formattedEndDate + '</span>';
                }
                dateTimeEl.classList.remove('d-none');
            } else {
                dateTimeEl.classList.add('d-none');
            }
            
            modal.show();
        });
    });



    // Handle logout confirmation
    document.getElementById('confirmLogoutBtn').addEventListener('click', function() {
        window.location.href = 'afprotech_logout.php';
    });

    // Pure LocalStorage Countdown (No Database/Server)
    let countdownInterval = null;
    let countdownInitialized = false;

    function updateCountdownDisplay(hours = 0, minutes = 0, seconds = 0) {
        document.getElementById('hours').textContent = Math.max(0, hours).toString().padStart(2, '0');
        document.getElementById('minutes').textContent = Math.max(0, minutes).toString().padStart(2, '0');
        document.getElementById('seconds').textContent = Math.max(0, seconds).toString().padStart(2, '0');
    }

    function getCountdownFromServer() {
        return fetch('backend/afprotechs_countdown.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.countdown) {
                    const endTime = new Date(data.countdown.endTime);
                    const now = new Date();
                    if (endTime > now) {
                        const timeDiff = endTime - now;
                        const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                        const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                        return {
                            hours, minutes, seconds,
                            endTime: endTime,
                            isActive: true,
                            expired: false
                        };
                    } else {
                        return { expired: true, isActive: false };
                    }
                } else {
                    return null;
                }
            })
            .catch(error => {
                console.error('Error fetching countdown:', error);
                return null;
            });
    }

    function saveCountdownToServer(hours, minutes, seconds) {
        return fetch('backend/afprotechs_countdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ hours, minutes, seconds })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => data.success)
        .catch(error => {
            console.error('Error saving countdown:', error);
            return false;
        });
    }

    function stopCountdownOnServer() {
        return fetch('backend/afprotechs_countdown.php', {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => data.success)
        .catch(error => {
            console.error('Error stopping countdown:', error);
            return false;
        });
    }

    async function startCountdownTimer() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        countdownInterval = setInterval(async () => {
            const countdownData = await getCountdownFromServer();
            
            if (countdownData && countdownData.isActive && !countdownData.expired) {
                updateCountdownDisplay(countdownData.hours, countdownData.minutes, countdownData.seconds);
                updateStatusIndicator(true);
            } else if (countdownData && countdownData.expired) {
                // Countdown finished
                updateCountdownDisplay(0, 0, 0);
                updateStatusIndicator(false);
                clearInterval(countdownInterval);
                countdownInterval = null;
                
                // Show completion message
                showNotification('Countdown Finished!', 'Attendance time has ended.', 'warning');
            } else if (countdownInitialized) {
                // Only reset display if countdown was already initialized
                updateCountdownDisplay(0, 0, 0);
                updateStatusIndicator(false);
            }
        }, 1000);
    }

    function updateStatusIndicator(isActive) {
        const statusEl = document.getElementById('countdownStatus');
        if (statusEl) {
            if (isActive) {
                statusEl.textContent = 'Active';
                statusEl.className = 'badge bg-success ms-2';
            } else {
                statusEl.textContent = 'Inactive';
                statusEl.className = 'badge bg-secondary ms-2';
            }
        }
    }

    function createNewCountdown(hours, minutes, seconds) {
        return saveCountdownToServer(hours, minutes, seconds);
    }

    async function stopCountdownTimer() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        
        await stopCountdownOnServer();
        updateCountdownDisplay(0, 0, 0);
        updateStatusIndicator(false);
    }

    function showNotification(title, message, type = 'success') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <strong>${title}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        // Auto-remove alert after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    // Handle countdown modal buttons
    document.getElementById('startCountdown').addEventListener('click', async function() {
        const hours = parseInt(document.getElementById('setHours').value) || 0;
        const minutes = parseInt(document.getElementById('setMinutes').value) || 0;
        const seconds = parseInt(document.getElementById('setSeconds').value) || 0;

        if (hours > 0 || minutes > 0 || seconds > 0) {
            const success = await createNewCountdown(hours, minutes, seconds);
            
            if (success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('countdownModal'));
                modal.hide();
                
                // Immediately show the countdown values
                updateCountdownDisplay(hours, minutes, seconds);
                updateStatusIndicator(true);
                
                // Start the timer immediately
                startCountdownTimer();
                
                // Show success message
                showNotification('Countdown Started!', `Timer is now active for ${hours}h ${minutes}m ${seconds}s`);
            } else {
                alert('Failed to start countdown. Please check the console for details.');
            }
        } else {
            alert('Please set a valid time for the countdown.');
        }
    });

    document.getElementById('stopCountdown').addEventListener('click', function() {
        stopCountdownTimer();
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('countdownModal'));
        modal.hide();
        
        // Show success message
        showNotification('Countdown Stopped!', 'Attendance timer has been stopped.', 'warning');
    });



    // Handle announcement item clicks
    const announcementModalEl = document.getElementById('announcementModal');
    if (announcementModalEl && window.bootstrap) {
        const announcementModal = new bootstrap.Modal(announcementModalEl);
        
        document.querySelectorAll('.announcement-item').forEach(item => {
            item.addEventListener('click', function() {
                const title = this.getAttribute('data-title') || 'Announcement';
                const content = this.getAttribute('data-content') || '';
                const datetime = this.getAttribute('data-datetime') || '';
                
                // Set modal content
                announcementModalEl.querySelector('#announcementModalTitle').textContent = title;
                announcementModalEl.querySelector('#announcementModalContent').innerHTML = content.replace(/\n/g, '<br>');
                
                // Format and set date/time
                if (datetime) {
                    const dateObj = new Date(datetime);
                    const formattedDateTime = dateObj.toLocaleDateString('en-US', { 
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    announcementModalEl.querySelector('#announcementModalDateTime').textContent = formattedDateTime;
                }
                
                announcementModal.show();
            });
        });
    }

    // Handle confirm order button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('confirm-order-btn') || e.target.closest('.confirm-order-btn')) {
            const button = e.target.classList.contains('confirm-order-btn') ? e.target : e.target.closest('.confirm-order-btn');
            const orderId = button.getAttribute('data-order-id');
            const orderNumber = button.getAttribute('data-order-number');
            
            if (confirm(`Confirm order ${orderNumber}?`)) {
                // Disable button and show loading
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Confirming...';
                
                // Send AJAX request to update status
                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('new_status', 'pending');
                
                fetch('backend/afprotechs_update_order_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the page to show updated status
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        // Re-enable button
                        button.disabled = false;
                        button.innerHTML = '<i class="fa-solid fa-check me-1"></i>Confirm';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while confirming the order.');
                    // Re-enable button
                    button.disabled = false;
                    button.innerHTML = '<i class="fa-solid fa-check me-1"></i>Confirm';
                });
            }
        }
    });

    // Handle Mark as Delivered button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('mark-delivered-btn') || e.target.closest('.mark-delivered-btn')) {
            e.preventDefault();
            const link = e.target.classList.contains('mark-delivered-btn') ? e.target : e.target.closest('.mark-delivered-btn');
            const orderId = link.getAttribute('data-order-id');
            const orderNumber = link.getAttribute('data-order-number');
            
            if (confirm(`Mark order ${orderNumber} as delivered?`)) {
                // Send AJAX request to update status
                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('new_status', 'delivered');
                
                fetch('backend/afprotechs_update_order_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the order.');
                });
            }
        }
    });

    // Handle Delete Order button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-order-btn') || e.target.closest('.delete-order-btn')) {
            e.preventDefault();
            const link = e.target.classList.contains('delete-order-btn') ? e.target : e.target.closest('.delete-order-btn');
            const orderId = link.getAttribute('data-order-id');
            const orderNumber = link.getAttribute('data-order-number');
            
            if (confirm(`Are you sure you want to delete order ${orderNumber}? This action cannot be undone.`)) {
                // Send AJAX request to delete order
                const formData = new FormData();
                formData.append('order_id', orderId);
                
                fetch('backend/afprotechs_delete_order.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the order.');
                });
            }
        }
    });

    // Handle View Order Details button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-order-btn') || e.target.closest('.view-order-btn')) {
            e.preventDefault();
            const link = e.target.classList.contains('view-order-btn') ? e.target : e.target.closest('.view-order-btn');
            const orderId = link.getAttribute('data-order-id');
            const orderNumber = link.getAttribute('data-order-number');
            
            // Fetch order details
            fetch(`backend/afprotechs_get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.data;
                        const modalContent = `
                            <div class="modal fade" id="orderDetailsModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Order Details - ${orderNumber}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <strong>Student ID:</strong> ${order.student_id || 'N/A'}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Product:</strong> ${order.product_name || 'N/A'}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Quantity:</strong> ${order.quantity || 1}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Total Price:</strong> ₱${parseFloat(order.total_price || 0).toFixed(2)}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Pickup Location:</strong> ${order.delivery_location || 'N/A'}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Message:</strong> ${order.message || 'No message'}
                                            </div>
                                            <div class="mb-3">
                                                <strong>Status:</strong> <span class="badge ${getStatusClass(order.order_status)}">${order.order_status || 'Awaiting Confirmation'}</span>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Order Date:</strong> ${order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A'}
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Remove existing modal if any
                        const existingModal = document.getElementById('orderDetailsModal');
                        if (existingModal) existingModal.remove();
                        
                        // Add modal to body and show
                        document.body.insertAdjacentHTML('beforeend', modalContent);
                        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                        modal.show();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching order details.');
                });
        }
    });

    // Initialize countdown on page load
    (async () => {
        const existingCountdown = await getCountdownFromServer();
        
        if (existingCountdown && existingCountdown.isActive && !existingCountdown.expired) {
            updateCountdownDisplay(existingCountdown.hours, existingCountdown.minutes, existingCountdown.seconds);
            updateStatusIndicator(true);
        } else {
            updateCountdownDisplay(0, 0, 0);
            updateStatusIndicator(false);
        }
        
        // Mark as initialized
        countdownInitialized = true;
        
        // Start the countdown timer
        startCountdownTimer();
    })();
});
</script>

</body>
</html>
