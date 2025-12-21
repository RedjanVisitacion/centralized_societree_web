<?php
require_once(__DIR__ . '/../../backend/site_event_backend.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SITE</title>
    <link rel="icon" href="../../assets/logo/site_2.png" type="image/png">
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
            border-left: 5px solid #081b5b;
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
                    <img src="../../assets/logo/site_2.png" alt="SITE Logo">
                    <h4>Society of Information Technology Enthusiasts</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="site_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="site_event.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Event</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_service.php">
                        <i class="bi bi-wrench-adjustable"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_penalties.php">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Penalties</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_balance.php">
                        <i class="bi bi-wallet2"></i>
                        <span>Balance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_chat.php">
                        <i class="bi bi-chat-dots"></i>
                        <span>Chat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="site_report.php">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="attendance.php">
                        <i class="bi bi-clipboard-check"></i>
                        <span>Attendance</span>
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
            <h2 class="mb-4">Event</h2>

            <?php if (isset($db_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i> <?php echo htmlspecialchars($db_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Header with Title and Button -->
            <div class="content-header">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h2 class="mb-0">Events</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" id="newEventBtn">
                        <i class="bi bi-plus-circle"></i> New Event
                    </button>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div id="eventsList">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="announcement-title"><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                    <p class="announcement-content"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
                                    <small class="announcement-date d-block mb-2">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php if (!empty($event['event_datetime'])) echo date('F j, Y g:i A', strtotime($event['event_datetime'])); ?>
                                        <?php if (!empty($event['event_location'])): ?>
                                            <span class="ms-3"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($event['event_location']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary editEventBtn" data-event='<?php echo json_encode($event, JSON_HEX_APOS|JSON_HEX_QUOT); ?>'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="?delete_event_id=<?php echo (int)$event['id']; ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Delete this event?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>

                                        <!-- Attendance controls -->
                                        <div class="mt-2">
                                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                                <?php
                                                    $fields = [
                                                        'morning_in' => 'Morning In',
                                                        'morning_out' => 'Morning Out',
                                                        'afternoon_in' => 'Afternoon In',
                                                        'afternoon_out' => 'Afternoon Out'
                                                    ];
                                                ?>
                                                <?php foreach ($fields as $key => $label): ?>
                                                    <?php $val = $event[$key] ?? null; ?>
                                                    <?php
                                                        // ON if has value, OFF if not
                                                        $isOn = !empty($val);
                                                        $btnClass = $isOn ? 'btn-success' : 'btn-outline-secondary';
                                                        // Buttons should always be clickable to allow toggling
                                                        $disabled = '';
                                                    ?>
                                                    <form method="POST" class="attendanceForm d-inline" style="display:inline-block;" action="">
                                                        <input type="hidden" name="attendance_event_id" value="<?php echo (int)$event['id']; ?>">
                                                        <input type="hidden" name="attendance_field" value="<?php echo $key; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $btnClass; ?>" <?php echo $disabled; ?> >
                                                            <i class="bi bi-clock-history me-1"></i>
                                                            <?php echo htmlspecialchars($label); ?>
                                                        </button>
                                                        <?php if ($isOn): ?>
                                                            <small class="text-success ms-2"><i class="bi bi-check-circle me-1"></i>Activated</small>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-event display-1 text-muted mb-3"></i>
                            <p class="text-muted">No events yet. Create your first event!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Modal (create / edit) -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel"><i class="bi bi-calendar-event me-2"></i><span id="eventModalTitle">Create New Event</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="eventForm" method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="event_id" id="event_id" value="">
                        <div class="mb-3">
                            <label for="eventTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="eventTitle" name="event_title" placeholder="Enter event title" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="eventDescription" class="form-label">Description *</label>
                            <textarea class="form-control" id="eventDescription" name="event_description" rows="6" placeholder="Enter event description" required></textarea>
                        </div>
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="eventDatetime" class="form-label">Date & Time</label>
                                <input type="datetime-local" class="form-control" id="eventDatetime" name="event_datetime">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="eventLocation" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="eventLocation" name="event_location" placeholder="Enter event location" required>

                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveEventBtn"><i class="bi bi-save me-1"></i><span id="saveEventText">Save Event</span></button>
                    </div>
                </form>
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

            // -- Event modal (create / edit) handling --
            const eventModalEl = document.getElementById('eventModal');
            const eventForm = document.getElementById('eventForm');
            const eventModalTitle = document.getElementById('eventModalTitle');
            const saveEventBtn = document.getElementById('saveEventBtn');
            const saveEventText = document.getElementById('saveEventText');
            const eventIdField = document.getElementById('event_id');
            const titleField = document.getElementById('eventTitle');
            const descriptionField = document.getElementById('eventDescription');
            const datetimeField = document.getElementById('eventDatetime');
            const locationField = document.getElementById('eventLocation');

            // Set default date/time to now (YYYY-MM-DDTHH:mm)
            function setDatetimeNow(field) {
                if (!field) return;
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                field.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            // When the "New Event" button is clicked we reset the form for a new create
            const newEventBtn = document.getElementById('newEventBtn');
            if (newEventBtn) {
                newEventBtn.addEventListener('click', function() {
                    eventIdField.value = '';
                    titleField.value = '';
                    descriptionField.value = '';
                    locationField.value = '';
                    document.getElementById('eventLat').value = '';
                    document.getElementById('eventLng').value = '';
                    document.getElementById('locationCoords').textContent = '';
                    setDatetimeNow(datetimeField);
                    eventModalTitle.textContent = 'Create New Event';
                    saveEventText.textContent = 'Save Event';
                });
            }

            // Edit event buttons: read data-event attribute, populate form and open modal
            document.querySelectorAll('.editEventBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const ev = btn.getAttribute('data-event');
                    if (!ev) return;
                    try {
                        const data = JSON.parse(ev);
                        eventIdField.value = data.id || '';
                        titleField.value = data.event_title || '';
                        descriptionField.value = data.event_description || '';
                        locationField.value = data.event_location || '';
                        document.getElementById('eventLat').value = data.event_latitude || '';
                        document.getElementById('eventLng').value = data.event_longitude || '';
                        if (data.event_latitude && data.event_longitude) {
                            document.getElementById('locationCoords').textContent = `Lat: ${data.event_latitude}, Lng: ${data.event_longitude}`;
                        } else {
                            document.getElementById('locationCoords').textContent = '';
                        }
                        if (data.event_datetime) {
                            // HTML expects a format like 2025-12-02T13:00
                            const dt = new Date(data.event_datetime);
                            if (!isNaN(dt)) {
                                const year = dt.getFullYear();
                                const month = String(dt.getMonth() + 1).padStart(2, '0');
                                const day = String(dt.getDate()).padStart(2, '0');
                                const hours = String(dt.getHours()).padStart(2, '0');
                                const minutes = String(dt.getMinutes()).padStart(2, '0');
                                datetimeField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                            } else {
                                datetimeField.value = '';
                            }
                        } else {
                            datetimeField.value = '';
                        }

                        eventModalTitle.textContent = 'Edit Event';
                        saveEventText.textContent = 'Update Event';

                        // Show the modal
                        const modal = new bootstrap.Modal(eventModalEl);
                        modal.show();
                    } catch (e) {
                        console.error('Failed to parse event data for editing', e);
                    }
                });
            });


            // Form submit: show loading state
            if (eventForm) {
                eventForm.addEventListener('submit', function() {
                    const original = saveEventBtn.innerHTML;
                    saveEventBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                    saveEventBtn.disabled = true;
                });
            }

            // Attendance buttons: remove all event listeners to ensure forms work
            // No JavaScript interference - let the forms submit naturally
        });
    </script>
</body>
</html>