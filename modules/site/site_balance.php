<?php
session_start();
require_once '../../db_connection.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_transaction':
            addTransaction();
            break;
        case 'edit_transaction':
            editTransaction();
            break;
        case 'delete_transaction':
            deleteTransaction();
            break;
        case 'get_transaction':
            getTransaction();
            break;
        case 'get_student_balance':
            getStudentBalance();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function addTransaction() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required_fields = ['student_id', 'transaction_type', 'description', 'amount', 'transaction_date'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Validate student exists
        $stmt = $pdo->prepare("SELECT id_number, first_name, last_name FROM student WHERE id_number = ?");
        $stmt->execute([$_POST['student_id']]);
        $student = $stmt->fetch();
        if (!$student) {
            throw new Exception('Student ID ' . $_POST['student_id'] . ' not found');
        }
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT current_balance FROM site_student_balance WHERE student_id = ?");
        $stmt->execute([$_POST['student_id']]);
        $balance_row = $stmt->fetch();
        $current_balance = $balance_row ? $balance_row['current_balance'] : 0.00;
        
        // Calculate new balance
        $amount = floatval($_POST['amount']);
        $transaction_type = $_POST['transaction_type'];
        
        // Adjust amount based on transaction type
        if (in_array($transaction_type, ['fee', 'fine'])) {
            $amount = -abs($amount); // Make negative for fees and fines
        } else if (in_array($transaction_type, ['payment', 'refund'])) {
            $amount = abs($amount); // Make positive for payments
            if ($transaction_type === 'refund') {
                $amount = -$amount; // Refunds reduce balance
            }
        }
        
        $new_balance = $current_balance + $amount;
        
        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO site_balance (student_id, transaction_type, description, amount, balance_after, payment_method, reference_number, processed_by, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['student_id'],
            $transaction_type,
            $_POST['description'],
            $amount,
            $new_balance,
            null, // Payment method removed
            !empty($_POST['reference_number']) ? $_POST['reference_number'] : null,
            !empty($_POST['processed_by']) ? $_POST['processed_by'] : 'System',
            $_POST['transaction_date']
        ]);
        
        // Update or insert current balance
        $stmt = $pdo->prepare("INSERT INTO site_student_balance (student_id, current_balance, last_transaction_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE current_balance = ?, last_transaction_date = ?");
        $stmt->execute([$_POST['student_id'], $new_balance, $_POST['transaction_date'], $new_balance, $_POST['transaction_date']]);
        
        $pdo->commit();
        
        $student_name = $student['first_name'] . ' ' . $student['last_name'];
        echo json_encode([
            'success' => true, 
            'message' => "Transaction added successfully for {$student_name} (ID: {$student['id_number']})",
            'new_balance' => $new_balance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editTransaction() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get original transaction
        $stmt = $pdo->prepare("SELECT * FROM site_balance WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $original = $stmt->fetch();
        if (!$original) {
            throw new Exception('Transaction not found');
        }
        
        // Validate required fields
        $required_fields = ['id', 'student_id', 'transaction_type', 'description', 'amount', 'transaction_date'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Calculate amount adjustment
        $new_amount = floatval($_POST['amount']);
        $transaction_type = $_POST['transaction_type'];
        
        if (in_array($transaction_type, ['fee', 'fine'])) {
            $new_amount = -abs($new_amount);
        } else if (in_array($transaction_type, ['payment', 'refund'])) {
            $new_amount = abs($new_amount);
            if ($transaction_type === 'refund') {
                $new_amount = -$new_amount;
            }
        }
        
        // Get current balance and adjust
        $stmt = $pdo->prepare("SELECT current_balance FROM site_student_balance WHERE student_id = ?");
        $stmt->execute([$_POST['student_id']]);
        $balance_row = $stmt->fetch();
        $current_balance = $balance_row ? $balance_row['current_balance'] : 0.00;
        
        // Remove original transaction effect and add new transaction effect
        $adjusted_balance = $current_balance - $original['amount'] + $new_amount;
        
        // Update transaction
        $stmt = $pdo->prepare("UPDATE site_balance SET student_id = ?, transaction_type = ?, description = ?, amount = ?, balance_after = ?, payment_method = ?, reference_number = ?, processed_by = ?, transaction_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $_POST['student_id'],
            $transaction_type,
            $_POST['description'],
            $new_amount,
            $adjusted_balance,
            null, // Payment method removed
            !empty($_POST['reference_number']) ? $_POST['reference_number'] : null,
            !empty($_POST['processed_by']) ? $_POST['processed_by'] : 'System',
            $_POST['transaction_date'],
            $_POST['id']
        ]);
        
        // Update current balance
        $stmt = $pdo->prepare("UPDATE site_student_balance SET current_balance = ?, last_transaction_date = ? WHERE student_id = ?");
        $stmt->execute([$adjusted_balance, $_POST['transaction_date'], $_POST['student_id']]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteTransaction() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get transaction to delete
        $stmt = $pdo->prepare("SELECT * FROM site_balance WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        // Get current balance and reverse the transaction
        $stmt = $pdo->prepare("SELECT current_balance FROM site_student_balance WHERE student_id = ?");
        $stmt->execute([$transaction['student_id']]);
        $balance_row = $stmt->fetch();
        $current_balance = $balance_row ? $balance_row['current_balance'] : 0.00;
        
        $new_balance = $current_balance - $transaction['amount'];
        
        // Delete transaction
        $stmt = $pdo->prepare("DELETE FROM site_balance WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        
        // Update current balance
        $stmt = $pdo->prepare("UPDATE site_student_balance SET current_balance = ? WHERE student_id = ?");
        $stmt->execute([$new_balance, $transaction['student_id']]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTransaction() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM site_balance WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Convert negative amounts back to positive for display
            if ($transaction['amount'] < 0 && in_array($transaction['transaction_type'], ['fee', 'fine', 'refund'])) {
                $transaction['display_amount'] = abs($transaction['amount']);
            } else {
                $transaction['display_amount'] = $transaction['amount'];
            }
            echo json_encode(['success' => true, 'data' => $transaction]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudentBalance() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT sb.current_balance, s.first_name, s.last_name 
            FROM site_student_balance sb 
            JOIN student s ON sb.student_id = s.id_number 
            WHERE sb.student_id = ?
        ");
        $stmt->execute([$_POST['student_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            echo json_encode(['success' => true, 'data' => ['current_balance' => 0.00, 'first_name' => '', 'last_name' => '']]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get all transactions with student information
try {
    $stmt = $pdo->query("
        SELECT b.*, s.first_name, s.last_name, s.course, s.year, s.section 
        FROM site_balance b 
        LEFT JOIN student s ON b.student_id = s.id_number 
        ORDER BY b.transaction_date DESC, b.created_at DESC
    ");
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
    $error_message = "Database error: " . $e->getMessage();
}

// Get student balances summary
try {
    $stmt = $pdo->query("
        SELECT sb.*, s.first_name, s.last_name, s.course, s.year, s.section 
        FROM site_student_balance sb 
        LEFT JOIN student s ON sb.student_id = s.id_number 
        ORDER BY sb.current_balance DESC
    ");
    $student_balances = $stmt->fetchAll();
} catch (PDOException $e) {
    $student_balances = [];
}

// Get all students for dropdown
try {
    $stmt = $pdo->query("SELECT id_number, first_name, last_name, course, year, section FROM student ORDER BY last_name, first_name");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
}

// Calculate summary statistics
$total_transactions = count($transactions);
$positive_balances = count(array_filter($student_balances, function($b) { return $b['current_balance'] > 0; }));
$negative_balances = count(array_filter($student_balances, function($b) { return $b['current_balance'] < 0; }));
$total_balance = array_sum(array_column($student_balances, 'current_balance'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SITE - Student Balance Management</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

        * {
            font-family: "Oswald", sans-serif;
            font-weight: 500;
            font-style: normal;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: #20a8f8;
            color: white;
            width: 260px;
            min-height: 100vh;
            transition: all 0.3s;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-container img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
        }

        .logo-container h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .btn-close-sidebar {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
            display: none;
        }

        .btn-close-sidebar:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .sidebar-menu .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.15);
            border-left-color: white;
        }

        .sidebar-menu .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
            display: none;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            margin: 0 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications {
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .notifications:hover {
            color: #20a8f8;
        }

        .user-avatar {
            font-size: 2rem;
            color: #20a8f8;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #333;
        }

        .user-role {
            font-size: 0.85rem;
            color: #666;
        }

        .content-area {
            padding: 30px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .balance-positive {
            color: #28a745;
            font-weight: bold;
        }

        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .balance-zero {
            color: #6c757d;
        }

        .transaction-type-badge {
            font-size: 0.8em;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .btn-close-sidebar {
                display: block;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/site_2.png" alt="SITE Logo">
                    <h4>Society of Information Technology Enthusiasts</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="site_dashboard.php"><i class="bi bi-house-door"></i><span>Home</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_event.php"><i class="bi bi-calendar-event"></i><span>Event</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_service.php"><i class="bi bi-wrench-adjustable"></i><span>Services</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_penalties.php"><i class="bi bi-exclamation-triangle"></i><span>Penalties</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="site_balance.php"><i class="bi bi-wallet2"></i><span>Balance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_chat.php"><i class="bi bi-chat-dots"></i><span>Chat</span></a></li>
                <li class="nav-item"><a class="nav-link" href="site_report.php"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a></li>
                <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="bi bi-clipboard-check"></i><span>Attendance</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../../dashboard.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search..." id="searchTransaction">
                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                        <i class="bi bi-times"></i>
                    </button>
                </div>
            </div>
            <div class="user-info">
                <div class="notifications"><i class="bi bi-bell fs-5"></i></div>
                <div class="user-avatar"><i class="bi bi-person-circle"></i></div>
                <div class="user-details"><div class="user-name">Admin</div><div class="user-role">SITE Treasurer</div></div>
            </div>
        </nav>

        <div class="content-area">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-wallet2 text-success"></i> Student Balance Management</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick="openAddModal()">
                            <i class="bi bi-plus"></i> Add Transaction
                        </button>
                    </div>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?php echo $total_transactions; ?></h5>
                                    <p class="card-text">Total Transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><?php echo $positive_balances; ?></h5>
                                    <p class="card-text">Positive Balances</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-danger"><?php echo $negative_balances; ?></h5>
                                    <p class="card-text">Negative Balances</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title <?php echo $total_balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        ₱<?php echo number_format($total_balance, 2); ?>
                                    </h5>
                                    <p class="card-text">Total Balance</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs for different views -->
                    <ul class="nav nav-tabs" id="balanceTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                                <i class="bi bi-list-ul"></i> Transaction History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="balances-tab" data-bs-toggle="tab" data-bs-target="#balances" type="button" role="tab">
                                <i class="bi bi-wallet"></i> Student Balances
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="balanceTabContent">
                        <!-- Transaction History Tab -->
                        <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Transaction History</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="transactionsTable">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Student</th>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th>Amount</th>
                                                    <th>Balance After</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($transactions)): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">No transactions found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($transactions as $transaction): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></strong><br>
                                                                <small class="text-muted">ID: <?php echo htmlspecialchars($transaction['student_id']); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $typeClass = '';
                                                                switch ($transaction['transaction_type']) {
                                                                    case 'payment':
                                                                        $typeClass = 'bg-success';
                                                                        break;
                                                                    case 'fee':
                                                                        $typeClass = 'bg-primary';
                                                                        break;
                                                                    case 'fine':
                                                                        $typeClass = 'bg-warning text-dark';
                                                                        break;
                                                                    case 'refund':
                                                                        $typeClass = 'bg-info';
                                                                        break;
                                                                    case 'adjustment':
                                                                        $typeClass = 'bg-secondary';
                                                                        break;
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $typeClass; ?> transaction-type-badge">
                                                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                            <td class="<?php echo $transaction['amount'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                                                ₱<?php echo number_format($transaction['amount'], 2); ?>
                                                            </td>
                                                            <td class="<?php echo $transaction['balance_after'] >= 0 ? 'balance-positive' : ($transaction['balance_after'] < 0 ? 'balance-negative' : 'balance-zero'); ?>">
                                                                ₱<?php echo number_format($transaction['balance_after'], 2); ?>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editTransaction(<?php echo $transaction['id']; ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Student Balances Tab -->
                        <div class="tab-pane fade" id="balances" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Current Student Balances</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Student ID</th>
                                                    <th>Name</th>
                                                    <th>Course</th>
                                                    <th>Current Balance</th>
                                                    <th>Last Transaction</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($student_balances)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">No student balances found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($student_balances as $balance): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($balance['student_id']); ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name']); ?></strong>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($balance['course'] . ' ' . $balance['year'] . '-' . $balance['section']); ?></td>
                                                            <td class="<?php echo $balance['current_balance'] > 0 ? 'balance-positive' : ($balance['current_balance'] < 0 ? 'balance-negative' : 'balance-zero'); ?>">
                                                                ₱<?php echo number_format($balance['current_balance'], 2); ?>
                                                            </td>
                                                            <td><?php echo $balance['last_transaction_date'] ? date('M d, Y', strtotime($balance['last_transaction_date'])) : 'N/A'; ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="addTransactionForStudent(<?php echo $balance['student_id']; ?>)">
                                                                    <i class="bi bi-plus"></i> Add Transaction
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="transactionForm">
                    <div class="modal-body">
                        <input type="hidden" id="transactionId" name="id">
                        <input type="hidden" id="formAction" name="action" value="add_transaction">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="studentId" class="form-label">Student ID & Name *</label>
                                    <select class="form-select" id="studentId" name="student_id" required>
                                        <option value="">Select Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id_number']; ?>" data-student-info="<?php echo htmlspecialchars($student['course'] . ' ' . $student['year'] . '-' . $student['section']); ?>">
                                                <?php echo htmlspecialchars($student['id_number'] . ' - ' . $student['last_name'] . ', ' . $student['first_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="studentInfo"></div>
                                    <div class="form-text" id="currentBalance"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transactionType" class="form-label">Transaction Type *</label>
                                    <select class="form-select" id="transactionType" name="transaction_type" required>
                                        <option value="">Select Type</option>
                                        <option value="payment">Payment (Credit)</option>
                                        <option value="fee">Fee (Debit)</option>
                                        <option value="fine">Fine (Debit)</option>
                                        <option value="refund">Refund (Debit)</option>
                                        <option value="adjustment">Adjustment</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Describe the transaction..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount (₱) *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                    <div class="form-text">Enter positive amount</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="transactionDate" class="form-label">Transaction Date *</label>
                                    <input type="date" class="form-control" id="transactionDate" name="transaction_date" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="referenceNumber" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="referenceNumber" name="reference_number" placeholder="e.g., REF001">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="processedBy" class="form-label">Processed By</label>
                            <input type="text" class="form-control" id="processedBy" name="processed_by" placeholder="e.g., Admin Name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Add Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default
        document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];

        // Search functionality
        document.getElementById('searchTransaction').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#transactionsTable tbody tr');
            
            tableRows.forEach(row => {
                const studentCell = row.cells[1]; // Student column
                const descCell = row.cells[3]; // Description column
                if (studentCell && descCell) {
                    const text = (studentCell.textContent + ' ' + descCell.textContent).toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        function clearSearch() {
            document.getElementById('searchTransaction').value = '';
            const tableRows = document.querySelectorAll('#transactionsTable tbody tr');
            tableRows.forEach(row => {
                row.style.display = '';
            });
        }

        // Show student info and current balance when selected
        document.getElementById('studentId').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const studentInfo = document.getElementById('studentInfo');
            const currentBalance = document.getElementById('currentBalance');
            
            if (selectedOption.value) {
                const studentData = selectedOption.getAttribute('data-student-info');
                studentInfo.textContent = 'Course: ' + studentData;
                studentInfo.style.color = '#0d6efd';
                
                // Get current balance
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_student_balance&student_id=' + selectedOption.value
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const balance = parseFloat(data.data.current_balance);
                        const balanceClass = balance > 0 ? 'text-success' : (balance < 0 ? 'text-danger' : 'text-muted');
                        currentBalance.innerHTML = `Current Balance: <span class="${balanceClass}">₱${balance.toFixed(2)}</span>`;
                    } else {
                        currentBalance.innerHTML = 'Current Balance: <span class="text-muted">₱0.00</span>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    currentBalance.innerHTML = 'Current Balance: <span class="text-muted">Error loading</span>';
                });
            } else {
                studentInfo.textContent = '';
                currentBalance.textContent = '';
            }
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Transaction';
            document.getElementById('submitBtn').textContent = 'Add Transaction';
            document.getElementById('formAction').value = 'add_transaction';
            document.getElementById('transactionForm').reset();
            document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
        }

        function addTransactionForStudent(studentId) {
            openAddModal();
            document.getElementById('studentId').value = studentId;
            document.getElementById('studentId').dispatchEvent(new Event('change'));
            new bootstrap.Modal(document.getElementById('transactionModal')).show();
        }

        function editTransaction(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_transaction&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Transaction';
                    document.getElementById('submitBtn').textContent = 'Update Transaction';
                    document.getElementById('formAction').value = 'edit_transaction';
                    document.getElementById('transactionId').value = data.data.id;
                    document.getElementById('studentId').value = data.data.student_id;
                    document.getElementById('transactionType').value = data.data.transaction_type;
                    document.getElementById('description').value = data.data.description;
                    document.getElementById('amount').value = data.data.display_amount || Math.abs(data.data.amount);
                    document.getElementById('transactionDate').value = data.data.transaction_date;
                    document.getElementById('referenceNumber').value = data.data.reference_number || '';
                    document.getElementById('processedBy').value = data.data.processed_by || '';
                    
                    // Trigger student selection to show info
                    document.getElementById('studentId').dispatchEvent(new Event('change'));
                    
                    new bootstrap.Modal(document.getElementById('transactionModal')).show();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching transaction data');
            });
        }

        function deleteTransaction(id) {
            if (confirm('Are you sure you want to delete this transaction? This will affect the student\'s balance.')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_transaction&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the transaction');
                });
            }
        }

        document.getElementById('transactionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate student ID is selected
            const studentId = document.getElementById('studentId').value;
            if (!studentId) {
                alert('Please select a student ID');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('transactionModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the request');
            });
        });

        // Sidebar functionality
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('show');
            document.getElementById('sidebarOverlay').classList.add('show');
        });

        document.getElementById('closeSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });
    </script>
</body>
</html>