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

// Get delivered orders (sales records)
$sales_records = [];
$total_orders = 0; // Changed from $total_delivered to $total_orders
$total_sales = 0;

// Get all transactions
$all_transactions = [];
$total_transactions = 0;

if ($conn) {
    // Sales History - Only delivered orders (actual sales)
    $sql = "SELECT 
                o.*, 
                COALESCE(p.product_name, sp.product_name) as product_name,
                COALESCE(p.product_image, sp.product_image) as product_image,
                o.order_number,
                o.delivery_location
            FROM afprotechs_orders o
            LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
            LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
            WHERE o.order_status = 'delivered'
            ORDER BY o.updated_at DESC, o.created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format image
            if (!empty($row['product_image'])) {
                $image = trim($row['product_image']);
                // Handle base64 or paths
                if (!preg_match('/^(data:|http)/i', $image)) {
                    if (preg_match('/^(uploads|images|img)\//i', $image)) {
                        // It's a path, keep as is
                    } else {
                        // Likely base64 without prefix
                        if (substr($image, 0, 4) === '/9j/') {
                            $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                        } else {
                            $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                        }
                    }
                }
            }
            
            $sales_records[] = $row;
            $total_orders++;
        }
    }
    
    // Get total sales from orders table where status is delivered
    $total_sales_query = "SELECT SUM(total_price) as total FROM afprotechs_orders WHERE order_status = 'delivered'";
    $total_result = $conn->query($total_sales_query);
    if ($total_result) {
        $total_row = $total_result->fetch_assoc();
        $total_sales = floatval($total_row['total'] ?? 0);
    }
    
    // All transactions (from orders table - includes all orders)
    $sql2 = "SELECT
                o.*,
                COALESCE(p.product_name, sp.product_name) as product_name,
                COALESCE(p.product_image, sp.product_image) as product_image,
                o.order_number,
                o.order_status,
                o.total_price,
                DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') as created_at
            FROM afprotechs_orders o
            LEFT JOIN afprotechs_products p ON o.product_id = p.product_id
            LEFT JOIN afprotech_student_products sp ON o.product_id = sp.product_id
            ORDER BY o.created_at DESC";
    $result2 = $conn->query($sql2);
    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            $all_transactions[] = $row;
            $total_transactions++;
        }
    }
    
    // Student sales summary (delivered orders grouped by student)
    $student_sales = [];
    $sql3 = "SELECT student_id, COUNT(*) as total_orders, SUM(total_price) as total_spent 
             FROM afprotechs_orders WHERE order_status = 'delivered' 
             GROUP BY student_id ORDER BY total_spent DESC";
    $result3 = $conn->query($sql3);
    if ($result3) {
        while ($row = $result3->fetch_assoc()) {
            $student_sales[] = $row;
        }
    }

    // Team Share - Student Products with creator information
    $team_shares = [];
    $sql4 = "SELECT 
                sp.id,
                sp.product_id,
                sp.student_id,
                sp.product_name,
                sp.product_description,
                sp.product_price,
                sp.product_quantity,
                sp.product_image,
                sp.status,
                sp.created_at,
                sp.group_members,
                s.first_name,
                s.middle_name,
                s.last_name,
                CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as student_name
             FROM afprotech_student_products sp
             LEFT JOIN student s ON sp.student_id = s.id_number
             WHERE sp.group_members IS NOT NULL AND sp.group_members != ''
             ORDER BY sp.created_at DESC";
    
    $result4 = $conn->query($sql4);
    if ($result4) {
        while ($row = $result4->fetch_assoc()) {
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
            $team_shares[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECHS - Records</title>
    <link rel="icon" type="image/png" href="../../assets/logo/afprotech_1.png?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="afprotechs_styles.css?v=<?= time() ?>">
</head>
<body>

<div class="sidebar d-flex flex-column align-items-start pt-4 px-3">
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
    <a href="#" class="active"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="afprotechs_products.php"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="afprotechs_reports.php"><i class="fa-solid fa-file-lines"></i><span>Generate Reports</span></a>
    <a href="#"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">
    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">
        <div><h2 class="fw-bold text-dark mb-0" style="font-size: 24px;">Records</h2></div>
        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:#000080;color:#fff;font-weight:bold;font-size:14px;">LB</div>
            <span class="fw-semibold">Lester Bulay<br><span class="dashboard-role">ADMIN</span></span>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="container-fluid px-4 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fa-solid fa-peso-sign text-success fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Sales</h6>
                            <h3 class="mb-0 fw-bold text-success">₱<?= number_format($total_sales, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fa-solid fa-truck text-primary fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Orders</h6>
                            <h3 class="mb-0 fw-bold text-primary"><?= $total_orders ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="fa-solid fa-exchange-alt text-info fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Transactions</h6>
                            <h3 class="mb-0 fw-bold text-info"><?= $total_transactions ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="container-fluid px-4">
        <ul class="nav nav-tabs" id="recordsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                    <i class="fa-solid fa-receipt me-2"></i>Sales History (Delivered Orders)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                    <i class="fa-solid fa-exchange-alt me-2"></i>All Transactions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                    <i class="fa-solid fa-users me-2"></i>Students
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="team-tab" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab">
                    <i class="fa-solid fa-people-group me-2"></i>Team Share
                </button>
            </li>
        </ul>

        <div class="tab-content bg-white border border-top-0 rounded-bottom p-4" id="recordsTabContent">
            <!-- Sales History Tab -->
            <div class="tab-pane fade show active" id="sales" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="salesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Student ID</th>
                                <th>Product Name</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Location</th>
                                <th>Date Delivered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sales_records) > 0): ?>
                                <?php foreach ($sales_records as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['order_number'] ?? 'ORD'.str_pad($r['order_id'],4,'0',STR_PAD_LEFT)) ?></strong></td>
                                    <td><?= htmlspecialchars($r['student_id']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?= htmlspecialchars($r['product_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= $r['quantity'] ?? 1 ?></td>
                                    <td class="text-success fw-bold">₱<?= number_format($r['total_price'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['delivery_location'] ?? 'N/A') ?></td>
                                    <td><small><?= date('M d, Y g:i A', strtotime($r['updated_at'] ?? $r['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fa-solid fa-inbox mb-2" style="font-size:24px;opacity:0.5;"></i><br>No orders yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- All Transactions Tab -->
            <div class="tab-pane fade" id="transactions" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="transactionsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Product Name</th>
                                <th>Student ID</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_transactions) > 0): ?>
                                <?php foreach ($all_transactions as $t): 
                                    $status = $t['order_status'];
                                    $badge = 'bg-light text-muted';
                                    $statusText = 'Awaiting';
                                    if ($status == 'pending') { $badge = 'bg-warning text-dark'; $statusText = 'Pending'; }
                                    elseif ($status == 'confirmed') { $badge = 'bg-info text-white'; $statusText = 'Confirmed'; }
                                    elseif ($status == 'preparing') { $badge = 'bg-primary text-white'; $statusText = 'Preparing'; }
                                    elseif ($status == 'ready') { $badge = 'bg-success text-white'; $statusText = 'Ready for Pickup'; }
                                    elseif ($status == 'delivered') { $badge = 'bg-success text-white'; $statusText = 'Delivered'; }
                                    elseif ($status == 'cancelled') { $badge = 'bg-danger text-white'; $statusText = 'Cancelled'; }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['order_number'] ?? 'ORD'.str_pad($t['order_id'],4,'0',STR_PAD_LEFT)) ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?= htmlspecialchars($t['product_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($t['student_id']) ?></td>
                                    <td><?= $t['quantity'] ?? 1 ?></td>
                                    <td class="fw-bold">₱<?= number_format($t['total_price'], 2) ?></td>
                                    <td><span class="badge rounded-pill <?= $badge ?>"><?= $statusText ?></span></td>
                                    <td><small><?= date('M d, Y g:i A', strtotime($t['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fa-solid fa-inbox mb-2" style="font-size:24px;opacity:0.5;"></i><br>No transactions yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Students Tab -->
            <div class="tab-pane fade" id="students" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Total Orders</th>
                                <th>Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($student_sales) > 0): ?>
                                <?php foreach ($student_sales as $ss): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ss['student_id']) ?></strong></td>
                                    <td><?= $ss['total_orders'] ?></td>
                                    <td class="text-success fw-bold">₱<?= number_format($ss['total_spent'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-4"><i class="fa-solid fa-inbox mb-2" style="font-size:24px;opacity:0.5;"></i><br>No student sales yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Team Share Tab -->
            <div class="tab-pane fade" id="team" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="teamTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Team Members</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($team_shares)): ?>
                                <?php foreach ($team_shares as $ts): 
                                    // Parse team members
                                    $groupMembers = $ts['group_members'] ?? '';
                                    $members = [];
                                    if (!empty($groupMembers)) {
                                        $members = explode(',', $groupMembers);
                                        // Filter empty members and trim whitespace
                                        $members = array_filter($members, function($m) { return !empty(trim($m)); });
                                        $members = array_map('trim', $members);
                                    }
                                    
                                    $statusBadge = 'bg-success text-white';
                                    $statusText = 'Approved';
                                    if ($ts['status'] == 'pending') {
                                        $statusBadge = 'bg-warning text-dark';
                                        $statusText = 'Pending';
                                    } elseif ($ts['status'] == 'rejected') {
                                        $statusBadge = 'bg-danger text-white';
                                        $statusText = 'Rejected';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ts['student_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($ts['student_name'] ?? 'Unknown Student') ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($ts['product_image'])): ?>
                                                <img src="<?= htmlspecialchars($ts['product_image']) ?>" alt="Product" class="rounded me-2" style="width:40px;height:40px;object-fit:cover;">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($ts['product_name']) ?></div>
                                                <?php if (!empty($ts['product_description'])): ?>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($ts['product_description'], 0, 50)) ?><?= strlen($ts['product_description']) > 50 ? '...' : '' ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-success">₱<?= number_format($ts['product_price'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($members)): ?>
                                            <ul class="list-unstyled mb-0 small">
                                            <?php foreach ($members as $member): ?>
                                                <li><i class="fa-solid fa-user me-1 text-muted" style="font-size:10px;"></i> <?= htmlspecialchars($member) ?></li>
                                            <?php endforeach; ?>
                                            </ul>
                                            <small class="text-muted"><?= count($members) ?> member(s)</small>
                                        <?php else: ?>
                                            <span class="text-muted">No team members</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge rounded-pill <?= $statusBadge ?>"><?= $statusText ?></span></td>
                                    <td><small><?= date('M d, Y g:i A', strtotime($ts['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fa-solid fa-inbox mb-2" style="font-size:24px;opacity:0.5;"></i><br>No team share products found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php if ($conn) $conn->close(); ?>
