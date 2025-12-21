<?php
session_start();
require_once '../../db_connection.php';

// Fetch statistics
try {
    // Get total events
    $total_events_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_events");
    $total_events = $total_events_stmt->fetch()['total'] ?? 0;
    
    // Get total announcements
    $total_announcements_stmt = $pdo->query("SELECT COUNT(*) as total FROM pafe_announcements");
    $total_announcements = $total_announcements_stmt->fetch()['total'] ?? 0;
    
    // Get upcoming events (next 30 days)
    $upcoming_events_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM pafe_events 
        WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $upcoming_events_stmt->execute();
    $upcoming_events = $upcoming_events_stmt->fetch()['total'] ?? 0;
    
    // Get events for calendar (current month and next month)
    $calendar_events_stmt = $pdo->prepare("
        SELECT id, title, event_date, event_time, location
        FROM pafe_events 
        WHERE event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
        ORDER BY event_date ASC
    ");
    $calendar_events_stmt->execute();
    $calendar_events = $calendar_events_stmt->fetchAll();
    
} catch (PDOException $e) {
    $total_events = 0;
    $total_announcements = 0;
    $upcoming_events = 0;
    $calendar_events = [];
    $db_error = "Database error: " . $e->getMessage();
}

// Handle AJAX request for event details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_event_details') {
    header('Content-Type: application/json');
    
    try {
        $event_id = $_POST['event_id'];
        $event_stmt = $pdo->prepare("
            SELECT id, title, event_date, event_time, location, 
                   morning_session_locked, afternoon_session_locked,
                   created_at, updated_at
            FROM pafe_events 
            WHERE id = ?
        ");
        $event_stmt->execute([$event_id]);
        $event = $event_stmt->fetch();
        
        if ($event) {
            // Get attendance count for this event
            $attendance_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_attendance,
                    COUNT(CASE WHEN session_type = 'morning' THEN 1 END) as morning_attendance,
                    COUNT(CASE WHEN session_type = 'afternoon' THEN 1 END) as afternoon_attendance
                FROM pafe_event_attendance 
                WHERE event_id = ?
            ");
            $attendance_stmt->execute([$event_id]);
            $attendance_stats = $attendance_stmt->fetch();
            
            $event['attendance_stats'] = $attendance_stats;
            echo json_encode(['success' => true, 'event' => $event]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Event not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PAFE</title>
    <link rel="icon" href="../../assets/logo/pafe_2.png" type="image/png">
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
            background: #eea618;
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
            border-left: 5px solid #132e63;
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(238, 166, 24, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #eea618, #f39c12);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #eea618;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card.events .stat-number {
            color: #28a745;
        }

        .stat-card.announcements .stat-number {
            color: #ffc107;
        }

        .stat-card.upcoming .stat-number {
            color: #17a2b8;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-top: 20px;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .calendar-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .calendar {
            width: 100%;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .calendar-nav {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #eea618;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .calendar-nav:hover {
            background: rgba(238, 166, 24, 0.1);
            transform: scale(1.1);
        }

        .calendar-month {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .calendar-day-header {
            background: #eea618;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .calendar-day {
            background: white;
            padding: 12px 8px;
            text-align: center;
            min-height: 50px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
        }

        .calendar-day:hover {
            background: rgba(238, 166, 24, 0.1);
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #adb5bd;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, #eea618, #f39c12);
            color: white;
            font-weight: bold;
        }

        .calendar-day.has-event {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            font-weight: bold;
        }

        .calendar-day.has-event:hover {
            background: linear-gradient(135deg, #34ce57, #2dd4aa);
            transform: scale(1.05);
        }

        .event-indicator {
            width: 6px;
            height: 6px;
            background: #ffc107;
            border-radius: 50%;
            margin-top: 2px;
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
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
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

        /* Event Details Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #eea618, #f39c12);
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(238, 166, 24, 0.05);
            border-radius: 10px;
        }

        .event-detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eea618, #f39c12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }

        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .attendance-stat {
            text-align: center;
            padding: 15px;
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }

        .attendance-stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .attendance-stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
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
                    <img src="../../assets/logo/pafe_2.png" alt="PAFE Logo">
                    <h4>Prime Association of Future Educators</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="pafe_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_event.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Event</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_attendance.php">
                        <i class="bi bi-person-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pafe_feedback.php">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Feedback</span>
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
            <?php if (isset($db_error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="bi bi-exclamation-triangle"></i> Database Connection Issue</h5>
                <p><?= htmlspecialchars($db_error) ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <h2 class="mb-4">
                <i class="bi bi-speedometer2"></i> PAFE Dashboard
            </h2>

            <!-- Statistics Row -->
            <div class="stats-row">
                <div class="stat-card events">
                    <div class="stat-number"><?= $total_events ?></div>
                    <div class="stat-label">
                        <i class="bi bi-calendar-event"></i> Total Events
                    </div>
                </div>
                <div class="stat-card announcements">
                    <div class="stat-number"><?= $total_announcements ?></div>
                    <div class="stat-label">
                        <i class="bi bi-megaphone"></i> Total Announcements
                    </div>
                </div>
                <div class="stat-card upcoming">
                    <div class="stat-number"><?= $upcoming_events ?></div>
                    <div class="stat-label">
                        <i class="bi bi-calendar-check"></i> Upcoming Events
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h5 class="mb-3">
                        <i class="bi bi-clock-history"></i> Recent Activity
                    </h5>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="bi bi-info-circle"></i>
                            </div>
                            <div>
                                <strong>Welcome to PAFE Dashboard</strong><br>
                                <small class="text-muted">View your events and announcements statistics above</small>
                            </div>
                        </div>
                        <?php if ($total_events > 0): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div>
                                <strong>Events Available</strong><br>
                                <small class="text-muted">Check the calendar to view upcoming events</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($total_announcements > 0): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="bi bi-megaphone"></i>
                            </div>
                            <div>
                                <strong>Announcements Posted</strong><br>
                                <small class="text-muted">Stay updated with the latest announcements</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="calendar-section">
                    <h5 class="mb-4">
                        <i class="bi bi-calendar-event text-success"></i> Event Calendar
                    </h5>
                    
                    <div class="calendar">
                        <div class="calendar-header">
                            <button class="calendar-nav" id="prevMonth">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <div class="calendar-month" id="currentMonth"></div>
                            <button class="calendar-nav" id="nextMonth">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="pafe_event.php" class="btn btn-outline-success">
                            <i class="bi bi-plus-circle"></i> Create Event
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">
                        <i class="bi bi-calendar-event"></i> Event Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody">
                    <!-- Event details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="editEventBtn">
                        <i class="bi bi-pencil"></i> Edit Event
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Calendar events data from PHP
        const calendarEvents = <?= json_encode($calendar_events) ?>;
        
        let currentDate = new Date();
        
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar functionality
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

            // Calendar functionality
            initializeCalendar();
            
            // Calendar navigation
            document.getElementById('prevMonth').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                generateCalendar();
            });
            
            document.getElementById('nextMonth').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                generateCalendar();
            });
        });

        function initializeCalendar() {
            generateCalendar();
        }

        function generateCalendar() {
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonthElement = document.getElementById('currentMonth');
            
            // Clear previous calendar
            calendarGrid.innerHTML = '';
            
            // Set month/year display
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            currentMonthElement.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            
            // Add day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });
            
            // Get first day of month and number of days
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            // Generate calendar days
            const today = new Date();
            for (let i = 0; i < 42; i++) {
                const cellDate = new Date(startDate);
                cellDate.setDate(startDate.getDate() + i);
                
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = cellDate.getDate();
                
                // Add classes for styling
                if (cellDate.getMonth() !== currentDate.getMonth()) {
                    dayCell.classList.add('other-month');
                }
                
                if (cellDate.toDateString() === today.toDateString()) {
                    dayCell.classList.add('today');
                }
                
                // Check for events on this date
                const dateString = cellDate.toISOString().split('T')[0];
                const dayEvents = calendarEvents.filter(event => event.event_date === dateString);
                
                if (dayEvents.length > 0) {
                    dayCell.classList.add('has-event');
                    dayCell.title = `${dayEvents.length} event(s)`;
                    
                    // Add event indicator
                    const indicator = document.createElement('div');
                    indicator.className = 'event-indicator';
                    dayCell.appendChild(indicator);
                    
                    // Add click handler for event details
                    dayCell.addEventListener('click', function() {
                        showEventDetails(dayEvents[0].id);
                    });
                }
                
                calendarGrid.appendChild(dayCell);
            }
        }

        function showEventDetails(eventId) {
            // Show loading state
            const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            const modalBody = document.getElementById('eventModalBody');
            const editBtn = document.getElementById('editEventBtn');
            
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading event details...</p></div>';
            modal.show();
            
            // Fetch event details via AJAX
            fetch('pafe_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_event_details&event_id=${eventId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const event = data.event;
                    const stats = event.attendance_stats;
                    
                    modalBody.innerHTML = `
                        <div class="event-detail-item">
                            <div class="event-detail-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div>
                                <strong>Event Title</strong><br>
                                <span>${event.title}</span>
                            </div>
                        </div>
                        
                        <div class="event-detail-item">
                            <div class="event-detail-icon">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <div>
                                <strong>Date & Time</strong><br>
                                <span>${formatDate(event.event_date)} at ${formatTime(event.event_time)}</span>
                            </div>
                        </div>
                        
                        <div class="event-detail-item">
                            <div class="event-detail-icon">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <div>
                                <strong>Location</strong><br>
                                <span>${event.location}</span>
                            </div>
                        </div>
                        
                        <div class="event-detail-item">
                            <div class="event-detail-icon">
                                <i class="bi bi-lock"></i>
                            </div>
                            <div>
                                <strong>Session Status</strong><br>
                                <span>Morning: ${event.morning_session_locked ? 'Locked' : 'Open'}</span><br>
                                <span>Afternoon: ${event.afternoon_session_locked ? 'Locked' : 'Open'}</span>
                            </div>
                        </div>
                        
                        <h6 class="mt-4 mb-3">Attendance Statistics</h6>
                        <div class="attendance-stats">
                            <div class="attendance-stat">
                                <div class="attendance-stat-number text-primary">${stats.total_attendance || 0}</div>
                                <div class="attendance-stat-label">Total</div>
                            </div>
                            <div class="attendance-stat">
                                <div class="attendance-stat-number text-success">${stats.morning_attendance || 0}</div>
                                <div class="attendance-stat-label">Morning</div>
                            </div>
                            <div class="attendance-stat">
                                <div class="attendance-stat-number text-warning">${stats.afternoon_attendance || 0}</div>
                                <div class="attendance-stat-label">Afternoon</div>
                            </div>
                        </div>
                    `;
                    
                    // Set edit button link
                    editBtn.href = `pafe_event.php?edit=${event.id}`;
                } else {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            ${data.message || 'Failed to load event details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Error loading event details: ${error.message}
                    </div>
                `;
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            const time = new Date(`2000-01-01T${timeString}`);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
    </script>
</body>
</html>