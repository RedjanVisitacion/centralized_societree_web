<?php
require_once __DIR__ . '/../../backend/redcross_backend_report.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - REDCROSS</title>
    <link rel="icon" href="../../assets/logo/redcross_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

        *{
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
            background: #f80305;
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
            gap: 10px;
        }

        .sidebar-header img {
            height: 50px;
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .btn-close-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 5px;
            display: none;
        }

        .btn-close-sidebar:hover {
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 5px solid #052369;
        }

        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 260px;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .search-box {
            width: 300px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .content-area {
            padding: 30px;
            flex: 1;
        }

        /* Make headings and texts clearer for Reports page */
        .content-area h2 {
            font-size: 2rem;
            font-weight: 600;
        }

        .recent-activity h5 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .recent-activity,
        .table,
        .form-label,
        .form-control,
        .form-select,
        .btn,
        .nav-tabs .nav-link {
            font-size: 0.98rem;
        }

        /* Tabs styling for clearer active state */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #555;
        }

        .nav-tabs .nav-link:hover {
            color: #f80305;
        }

        .nav-tabs .nav-link.active {
            background-color: #f80305;
            color: #fff;
            border-color: #f80305 #f80305 #fff;
        }

        .nav-tabs .nav-link:not(.active) {
            background-color: #f8f9fa;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #1e174a;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            
            .sidebar.active {
                transform: translateX(0);
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
            
            .search-box {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 15px;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .search-box {
                width: 100%;
                order: 3;
            }
            
            .user-info {
                margin-left: auto;
            }
            
            .user-details {
                display: none;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .recent-activity {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .activity-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .sidebar-header h4 {
                font-size: 1rem;
            }
        }

        /* Overlay for mobile menu */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/redcross_2.png" alt="SITE Logo">
                    <h4>Red Cross Youth</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="redcross_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_membership.php">
                        <i class="bi bi-person-add"></i>
                        <span>Membership</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_patients.php">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Patient Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_promotion.php">
                        <i class="bi bi-chevron-double-up"></i>
                        <span>Promotion</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="redcross_report.php">
                        <i class="bi bi-file-earmark"></i>
                        <span>Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../dashboard.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            <div class="user-info">
                <div class="notifications">
                    <i class="bi bi-bell fs-5"></i>
                </div>
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <div class="user-name">Tim</div>
                    <div class="user-role">Student</div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-4">Reports</h2>

            <!-- Tabs to separate Patients and Member Activities (but still under Reports) -->
            <ul class="nav nav-tabs mb-3" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities-tab-pane" type="button" role="tab" aria-controls="activities-tab-pane" aria-selected="true">
                        Member Activities
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="patients-tab" data-bs-toggle="tab" data-bs-target="#patients-tab-pane" type="button" role="tab" aria-controls="patients-tab-pane" aria-selected="false">
                        Patients
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="reportTabsContent">
                <!-- Member Activities tab -->
                <div class="tab-pane fade show active" id="activities-tab-pane" role="tabpanel" aria-labelledby="activities-tab" tabindex="0">
                    
                    <!-- Member Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?php echo $member_stats['total_members']; ?></h5>
                                    <p class="card-text">Total Members</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><?php echo $member_stats['active_members']; ?></h5>
                                    <p class="card-text">Active Members</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-info"><?php echo $member_stats['members_with_activities']; ?></h5>
                                    <p class="card-text">With Activities</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title text-warning"><?php echo $member_stats['members_with_certificates']; ?></h5>
                                    <p class="card-text">With Certificates</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="recent-activity mb-4">
                        <h5 class="mb-3">Log Volunteer Activity</h5>
                        <?php if (!empty($report_message)): ?>
                            <div class="alert alert-info py-2"><?php echo $report_message; ?></div>
                        <?php endif; ?>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="add_activity">
                            <div class="col-md-4">
                                <label class="form-label">Member</label>
                                <select name="member_id" class="form-select" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Campaign/Activity</label>
                                <select name="campaign_id" class="form-select" required>
                                    <option value="">Select Campaign</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>" 
                                                data-description="<?php echo htmlspecialchars($campaign['description'], ENT_QUOTES); ?>"
                                                data-location="<?php echo htmlspecialchars($campaign['location'] ?? '', ENT_QUOTES); ?>"
                                                data-date="<?php echo $campaign['event_date'] ? date('M d, Y', strtotime($campaign['event_date'])) : ''; ?>">
                                            <?php echo htmlspecialchars($campaign['title']); ?>
                                            <?php if ($campaign['status']): ?>
                                                <span class="text-muted">(<?php echo ucfirst($campaign['status']); ?>)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted" id="campaignInfo"></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" name="activity_date" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hours</label>
                                <input type="number" step="0.5" min="0" name="hours" class="form-control" required>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">Remarks (optional)</label>
                                <input type="text" name="remarks" class="form-control">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">Save Activity</button>
                            </div>
                        </form>
                    </div>

                    <div class="recent-activity mb-4">
                        <h5 class="mb-3">Activity History</h5>
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label mb-1">Member</label>
                                <select name="member_id" class="form-select form-select-sm">
                                    <option value="0">All</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo $filter_member===$m['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_to); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <button type="submit" class="btn btn-outline-secondary btn-sm me-2">Filter</button>
                                <a href="redcross_report.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>

                        <!-- Selected Member Details -->
                        <?php if ($selected_member): ?>
                            <div class="alert alert-info mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="mb-2">
                                            <i class="bi bi-person-circle me-2"></i>
                                            Selected Member: <?php echo htmlspecialchars($selected_member['full_name']); ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <strong>Member ID:</strong> <?php echo htmlspecialchars($selected_member['member_id'] ?? 'N/A'); ?><br>
                                                    <strong>Course:</strong> <?php echo htmlspecialchars($selected_member['course'] ?? 'N/A'); ?><br>
                                                    <strong>Year & Section:</strong> <?php echo htmlspecialchars($selected_member['year_section'] ?? 'N/A'); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($selected_member['email'] ?? 'N/A'); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($selected_member['phone'] ?? 'N/A'); ?><br>
                                                    <strong>Status:</strong> 
                                                    <span class="badge bg-<?php echo $selected_member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($selected_member['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php 
                                        $member_total_hours = 0;
                                        foreach ($selected_member_activities as $activity) {
                                            $member_total_hours += $activity['hours'];
                                        }
                                        ?>
                                        <div class="text-center">
                                            <h4 class="text-primary mb-1"><?php echo number_format($member_total_hours, 1); ?></h4>
                                            <small class="text-muted">Total Hours</small>
                                        </div>
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info"><?php echo count($selected_member_activities); ?> Activities</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Activity</th>
                                        <th class="text-end">Hours</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No activities found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($activities as $a): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($a['activity_date']); ?></td>
                                                <td><?php echo htmlspecialchars($a['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['activity_name']); ?></td>
                                                <td class="text-end"><?php echo number_format($a['hours'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($a['remarks']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="recent-activity">
                        <h5 class="mb-3">Participation Summary & Certificates</h5>
                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-1">Quota (hours for recognition)</label>
                                <input type="number" min="0" name="quota" class="form-control form-control-sm" value="<?php echo htmlspecialchars($quota_hours); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-secondary btn-sm me-2">Set Quota</button>
                                <a href="redcross_report.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th class="text-end">Total Hours</th>
                                        <th class="text-end">Activities</th>
                                        <th>Certificate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($summary)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No data.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($summary as $s): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                                                <td class="text-end"><?php echo number_format($s['total_hours'] ?? 0, 2); ?></td>
                                                <td class="text-end"><?php echo (int)($s['activities'] ?? 0); ?></td>
                                                <td>
                                                    <?php if (($s['has_certificate'] ?? 0) == 1): ?>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="send_certificate">
                                                                <input type="hidden" name="member_id" value="<?php echo $s['id']; ?>">
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="bi bi-send me-1"></i>
                                                                    Send Certificate
                                                                </button>
                                                            </form>
                                                            <a href="redcross_certificate.php?member_id=<?php echo $s['id']; ?>" class="btn btn-outline-primary" target="_blank">
                                                                <i class="bi bi-eye me-1"></i>
                                                                Preview
                                                            </a>
                                                        </div>
                                                    <?php elseif (($s['total_hours'] ?? 0) >= $quota_hours): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="generate_certificate">
                                                            <input type="hidden" name="member_id" value="<?php echo $s['id']; ?>">
                                                            <input type="hidden" name="quota_hours" value="<?php echo $quota_hours; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-award me-1"></i>
                                                                Issue Certificate (<?php echo $quota_hours; ?>+ hrs)
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted small">
                                                            Needs <?php echo number_format($quota_hours - ($s['total_hours'] ?? 0), 1); ?> more hours
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-0">
                            <strong>Tip:</strong> Click "Send Certificate" to email the certificate to the member's registered email address. 
                            Click "Preview" to view the certificate before sending.
                            <?php if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false): ?>
                                <br><em>Development Mode: Certificates are saved to the certificates folder instead of being emailed.</em>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Patients tab -->
                <div class="tab-pane fade" id="patients-tab-pane" role="tabpanel" aria-labelledby="patients-tab" tabindex="0">
                    <div class="recent-activity mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Patient Reports</h5>
                            <div class="d-flex gap-2">
                                <a href="redcross_patients.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>
                                    Open Patient Records
                                </a>
                                <a href="redcross_report.php?export_patients=csv" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-download me-1"></i>
                                    Download CSV
                                </a>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-1">Total Patients</h6>
                                        <div class="display-6"><?php echo $total_patients; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-1">This Month</h6>
                                        <div class="display-6"><?php echo $patients_this_month; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-3 mb-2">Patients by Case / Condition</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Case / Condition</th>
                                        <th class="text-end">Patients</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($patients_by_condition)): ?>
                                        <tr><td colspan="2" class="text-center text-muted">No patient records yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($patients_by_condition as $pc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pc['case_label']); ?></td>
                                                <td class="text-end"><?php echo (int)$pc['c']; ?></td>
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

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            
            // Toggle sidebar on menu button click
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
            // Close sidebar methods:
            
            // 1. Close button click
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            // 2. Overlay click
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });

            // 3. Auto-close when clicking menu links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            // 4. Window resize (close on desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });

            // Campaign selection functionality
            const campaignSelect = document.querySelector('select[name="campaign_id"]');
            const campaignInfo = document.getElementById('campaignInfo');
            
            if (campaignSelect && campaignInfo) {
                campaignSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    
                    if (selectedOption.value) {
                        const description = selectedOption.getAttribute('data-description');
                        const location = selectedOption.getAttribute('data-location');
                        const date = selectedOption.getAttribute('data-date');
                        
                        let infoText = '';
                        if (date) infoText += `📅 ${date}`;
                        if (location) infoText += ` 📍 ${location}`;
                        if (description) infoText += ` - ${description.substring(0, 100)}${description.length > 100 ? '...' : ''}`;
                        
                        campaignInfo.textContent = infoText;
                        campaignInfo.style.display = 'block';
                    } else {
                        campaignInfo.textContent = '';
                        campaignInfo.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>