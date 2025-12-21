<?php
require_once __DIR__ . '/../../backend/redcross_backend_promotion.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion - REDCROSS</title>
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
                    <a class="nav-link active" href="redcross_promotion.php">
                        <i class="bi bi-chevron-double-up"></i>
                        <span>Promotion</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_report.php">
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
            <h2 class="mb-4">Promotion</h2>

            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">Campaign Actions</h5>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#campaignModal">
                        <i class="bi bi-plus-circle me-1"></i>
                        Create Campaign
                    </button>
                </div>
                <?php if (!empty($promo_message)): ?>
                    <div class="alert alert-info py-2 mb-0"><?php echo $promo_message; ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">Use the button to launch a new campaign.</p>
                <?php endif; ?>
            </div>

            <div class="recent-activity">
                <h5 class="mb-3">Campaigns</h5>
                <div class="row g-3">
                    <?php if (empty($campaigns)): ?>
                        <div class="col-12 text-muted">No campaigns yet.</div>
                    <?php else: ?>
                        <?php foreach ($campaigns as $c): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <?php if (!empty($c['image_path'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($c['image_path']); ?>" class="card-img-top" alt="Campaign">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($c['title']); ?></h6>
                                        <p class="card-text small text-truncate" style="max-height: 3.6em; overflow: hidden;">
                                            <?php echo nl2br(htmlspecialchars($c['description'])); ?>
                                        </p>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary mt-2 view-campaign-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#campaignViewModal"
                                            data-title="<?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-description="<?php echo htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-event-link="<?php echo htmlspecialchars($c['event_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-image="<?php echo !empty($c['image_path']) ? htmlspecialchars('../../' . $c['image_path'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                            data-created-at="<?php echo date('M d, Y', strtotime($c['created_at'])); ?>"
                                            data-status="<?php echo htmlspecialchars($c['status'] ?? 'upcoming', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <i class="bi bi-eye me-1"></i> View
                                        </button>
                                        <?php if (!empty($c['event_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($c['event_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2 ms-1">Event Link</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                        </small>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?php echo !empty($c['event_date']) ? date('M d, Y', strtotime($c['event_date'])) : 'No date set'; ?>
                                            </span>
                                            <div class="text-end">
                                                <span class="badge bg-primary"><?php echo $c['registration_count']; ?> Registered</span>
                                                <?php if ($c['attended_count'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $c['attended_count']; ?> Attended</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Registration & Monitoring Section -->
            <div class="recent-activity mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-person-check-fill text-primary me-2"></i>
                        Event Registration & Monitoring
                    </h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerEventModal">
                        <i class="bi bi-plus-circle me-1"></i>
                        Register Member
                    </button>
                </div>

                <!-- Filter by Campaign -->
                <form method="GET" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <select name="campaign_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">Select an event to view registrations...</option>
                                <?php foreach ($campaigns as $camp): ?>
                                    <option value="<?php echo $camp['id']; ?>" <?php echo $selected_campaign == $camp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($camp['title']); ?> 
                                        (<?php echo $camp['registration_count']; ?> registered)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <a href="redcross_promotion.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>

                <!-- Registrations Table -->
                <?php if ($selected_campaign > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Member</th>
                                    <th>Member ID</th>
                                    <th>Email</th>
                                    <th>Registered At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registrations)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No registrations yet for this event.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registrations as $index => $reg): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['member_id']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($reg['registered_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($reg['status']) {
                                                        'attended' => 'success',
                                                        'absent' => 'danger',
                                                        'cancelled' => 'secondary',
                                                        default => 'primary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($reg['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="mark_attendance">
                                                    <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                    <select name="attendance_status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                        <option value="">Mark as...</option>
                                                        <option value="attended">Attended</option>
                                                        <option value="absent">Absent</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Select an event from the dropdown above to view and manage registrations.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Register Member Modal -->
    <div class="modal fade" id="registerEventModal" tabindex="-1" aria-labelledby="registerEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerEventModalLabel">
                        <i class="bi bi-person-plus me-2"></i>
                        Register Member for Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="register_event">
                        <div class="mb-3">
                            <label class="form-label">Select Event</label>
                            <select name="campaign_id" class="form-select" required>
                                <option value="">Choose event...</option>
                                <?php foreach ($campaigns as $camp): ?>
                                    <option value="<?php echo $camp['id']; ?>">
                                        <?php echo htmlspecialchars($camp['title']); ?>
                                        <?php if (!empty($camp['event_date'])): ?>
                                            - <?php echo date('M d, Y', strtotime($camp['event_date'])); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Member</label>
                            <select name="member_id" class="form-select" required>
                                <option value="">Choose member...</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['full_name']); ?> 
                                        (<?php echo htmlspecialchars($member['member_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="registration_notes" class="form-control" rows="2" placeholder="Any special notes or requirements..."></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                Register
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Campaign Modal -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignModalLabel">Create Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event Link (optional)</label>
                            <input type="url" name="event_link" class="form-control" placeholder="https://">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Promotional Image (optional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Publish Campaign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Campaign Modal -->
    <div class="modal fade" id="campaignViewModal" tabindex="-1" aria-labelledby="campaignViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignViewModalLabel">Campaign Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="campaignViewImageWrapper" class="mb-3 d-none">
                        <img id="campaignViewImage" src="" alt="Campaign image" class="img-fluid rounded">
                    </div>
                    <h5 id="campaignViewTitle" class="mb-2"></h5>
                    <p class="text-muted small mb-2">
                        <span id="campaignViewDate"></span>
                        &nbsp;•&nbsp;
                        <span id="campaignViewStatus"></span>
                    </p>
                    <p id="campaignViewDescription" class="mb-3"></p>
                    <a id="campaignViewEventLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary d-none">
                        <i class="bi bi-link-45deg me-1"></i> Open Event Link
                    </a>
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

            // View Campaign Modal population
            const viewModal = document.getElementById('campaignViewModal');
            const viewTitle = document.getElementById('campaignViewTitle');
            const viewDescription = document.getElementById('campaignViewDescription');
            const viewImageWrapper = document.getElementById('campaignViewImageWrapper');
            const viewImage = document.getElementById('campaignViewImage');
            const viewDate = document.getElementById('campaignViewDate');
            const viewStatus = document.getElementById('campaignViewStatus');
            const viewEventLink = document.getElementById('campaignViewEventLink');

            document.querySelectorAll('.view-campaign-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const title = this.getAttribute('data-title') || '';
                    const description = this.getAttribute('data-description') || '';
                    const eventLink = this.getAttribute('data-event-link') || '';
                    const image = this.getAttribute('data-image') || '';
                    const createdAt = this.getAttribute('data-created-at') || '';
                    const status = this.getAttribute('data-status') || 'upcoming';

                    viewTitle.textContent = title;
                    viewDescription.textContent = description.replace(/\n/g, '\n');
                    viewDate.textContent = createdAt;
                    viewStatus.textContent = 'Status: ' + status.charAt(0).toUpperCase() + status.slice(1);

                    if (image) {
                        viewImageWrapper.classList.remove('d-none');
                        viewImage.src = image;
                    } else {
                        viewImageWrapper.classList.add('d-none');
                        viewImage.src = '';
                    }

                    if (eventLink) {
                        viewEventLink.classList.remove('d-none');
                        viewEventLink.href = eventLink;
                    } else {
                        viewEventLink.classList.add('d-none');
                        viewEventLink.href = '#';
                    }
                });
            });
        });
    </script>
</body>
</html>