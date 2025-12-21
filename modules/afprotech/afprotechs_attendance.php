<?php
date_default_timezone_set('Asia/Manila');

// Use AFPROTECH's own database connection
require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Fetch attendance records from the database
$attendance_records = [];
$total_attendance = 0;



// Ongoing event data
$ongoing_event = null;

try {
    // Check if events table exists first
    $table_check = $conn->query("SHOW TABLES LIKE 'afprotechs_events'");
    if ($table_check && $table_check->num_rows > 0) {
        // Fetch ongoing event (event happening today or upcoming)
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // First try to find events happening today
        $today_event_sql = "
            SELECT event_id, event_title, start_date as event_date, start_date, end_date, event_location, event_description
            FROM afprotechs_events 
            WHERE DATE(start_date) <= '$today' AND DATE(end_date) >= '$today'
            ORDER BY start_date ASC 
            LIMIT 1
        ";
        $today_result = $conn->query($today_event_sql);
        if ($today_result && $today_result->num_rows > 0) {
            $ongoing_event = $today_result->fetch_assoc();
        } else {
            // If no events today, look for upcoming events (next 7 days)
            $upcoming_event_sql = "
                SELECT event_id, event_title, start_date as event_date, start_date, end_date, event_location, event_description
                FROM afprotechs_events 
                WHERE start_date > '$today' AND start_date <= DATE_ADD('$today', INTERVAL 7 DAY)
                ORDER BY start_date ASC 
                LIMIT 1
            ";
            $upcoming_result = $conn->query($upcoming_event_sql);
            if ($upcoming_result && $upcoming_result->num_rows > 0) {
                $ongoing_event = $upcoming_result->fetch_assoc();
                $ongoing_event['is_upcoming'] = true; // Mark as upcoming event
            } else {
                // If no upcoming events, get the most recent or next available event
                $any_event_sql = "
                    SELECT event_id, event_title, start_date as event_date, start_date, end_date, event_location, event_description
                    FROM afprotechs_events 
                    ORDER BY ABS(DATEDIFF(start_date, '$today')) ASC 
                    LIMIT 1
                ";
                $any_result = $conn->query($any_event_sql);
                if ($any_result && $any_result->num_rows > 0) {
                    $ongoing_event = $any_result->fetch_assoc();
                    $ongoing_event['is_general'] = true; // Mark as general event (not today, not upcoming)
                }
            }
        }
        
        // Check if there are any events at all and get debug info
        $debug_events_sql = "SELECT COUNT(*) as total_events FROM afprotechs_events";
        $debug_result = $conn->query($debug_events_sql);
        $total_events = 0;
        if ($debug_result) {
            $total_events = $debug_result->fetch_assoc()['total_events'];
        }
        
        // If we still don't have an ongoing_event, try to get any event from the database
        if (!isset($ongoing_event)) {
            $fallback_event_sql = "
                SELECT event_id, event_title, start_date as event_date, start_date, end_date, event_location, event_description
                FROM afprotechs_events 
                ORDER BY start_date DESC 
                LIMIT 1
            ";
            $fallback_result = $conn->query($fallback_event_sql);
            if ($fallback_result && $fallback_result->num_rows > 0) {
                $ongoing_event = $fallback_result->fetch_assoc();
                $ongoing_event['is_general'] = true; // Mark as general event
            }
        }
    }
} catch (Exception $e) {
    // Ignore if events table doesn't exist, but try one more time
    try {
        if (!isset($ongoing_event)) {
            $final_fallback_sql = "SELECT event_id, event_title, start_date as event_date, start_date, end_date, event_location, event_description FROM afprotechs_events ORDER BY start_date DESC LIMIT 1";
            $final_result = $conn->query($final_fallback_sql);
            if ($final_result && $final_result->num_rows > 0) {
                $ongoing_event = $final_result->fetch_assoc();
                $ongoing_event['is_general'] = true;
            }
        }
    } catch (Exception $e2) {
        // Really no events table
    }
}

try {
    // Create attendance table if it doesn't exist (same structure as Flutter backend)
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS attendance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(64) NOT NULL,
            event_id INT UNSIGNED NULL,
            organization VARCHAR(64) NOT NULL DEFAULT 'afprotechs',
            attendance_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_event (student_id, event_id),
            INDEX idx_organization (organization),
            INDEX idx_attendance_date (attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $conn->query($create_table_sql);

    // Fetch attendance records from afprotechs_attendance table
    $sql = "
        SELECT 
            afprotechs_id_attendance as id,
            id_number as student_id,
            first_name,
            middle_name,
            last_name,
            course,
            year,
            section,
            event_id,
            morning_in,
            morning_out,
            afternoon_in,
            afternoon_out,
            attendance_date
        FROM afprotechs_attendance
        ORDER BY attendance_date DESC, created_at DESC
    ";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Build full name
            $name_parts = array_filter([
                $row['first_name'] ?? '',
                $row['middle_name'] ?? '',
                $row['last_name'] ?? ''
            ]);
            $full_name = !empty($name_parts) ? implode(' ', $name_parts) : 'Student ' . $row['student_id'];
            
            // Determine time in based on morning/afternoon
            $time_in = $row['morning_in'] ?? $row['afternoon_in'] ?? null;
            $time_out = $row['morning_out'] ?? $row['afternoon_out'] ?? null;
            
            $formatted_record = [
                'id' => $row['id'],
                'student_id' => $row['student_id'],
                'student_name' => $full_name,
                'year_section' => trim(($row['year'] ?? '') . ($row['section'] ?? '')) ?: 'N/A',
                'section' => $row['section'] ?? 'N/A',
                'attendance_date' => $row['attendance_date'],
                'attendance_time' => $time_in,
                'time_out' => $time_out,
                'morning_in' => $row['morning_in'],
                'morning_out' => $row['morning_out'],
                'afternoon_in' => $row['afternoon_in'],
                'afternoon_out' => $row['afternoon_out'],
                'course' => $row['course'] ?? '',
                'year' => $row['year'] ?? '',
                'event_id' => $row['event_id']
            ];
            
            $attendance_records[] = $formatted_record;
        }
    }
    
    $total_attendance = count($attendance_records);
    
    // Calculate morning and afternoon attendance counts
    $morning_count = 0;
    $afternoon_count = 0;
    
    foreach ($attendance_records as $record) {
        if (!empty($record['morning_in'])) {
            $morning_count++;
        }
        if (!empty($record['afternoon_in'])) {
            $afternoon_count++;
        }
    }
    
    // Get attendance by section for reports
    $section_sql = "
        SELECT section, COUNT(*) as count
        FROM afprotechs_attendance
        WHERE section IS NOT NULL AND section != ''
        GROUP BY section
        ORDER BY section
    ";
    $section_result = $conn->query($section_sql);
    if ($section_result) {
        while ($row = $section_result->fetch_assoc()) {
            $attendance_by_section[] = $row;
        }
    }
    
    // Get attendance by date (last 7 days)
    $date_sql = "
        SELECT attendance_date as date, COUNT(*) as count
        FROM afprotechs_attendance
        WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY attendance_date
        ORDER BY attendance_date DESC
    ";
    $date_result = $conn->query($date_sql);
    if ($date_result) {
        while ($row = $date_result->fetch_assoc()) {
            $attendance_by_date[] = $row;
        }
    }
    
    // Get attendance by event - show all events with their attendance count (including 0)
    try {
        $event_sql = "
            SELECT 
                e.event_title, 
                e.start_date as event_date,
                e.event_id,
                COALESCE(COUNT(a.afprotechs_id_attendance), 0) as count
            FROM afprotechs_events e
            LEFT JOIN afprotechs_attendance a ON e.event_id = a.event_id
            GROUP BY e.event_id, e.event_title, e.start_date
            ORDER BY e.start_date DESC, count DESC
            LIMIT 10
        ";
        $event_result = $conn->query($event_sql);
        if ($event_result) {
            while ($row = $event_result->fetch_assoc()) {
                $attendance_by_event[] = $row;
            }
        }
        
        // Debug: If no events found but we have attendance records, create a fallback entry
        if (empty($attendance_by_event) && $total_attendance > 0) {
            // Check if we have any events at all
            $check_events_sql = "SELECT COUNT(*) as event_count FROM afprotechs_events";
            $check_result = $conn->query($check_events_sql);
            if ($check_result) {
                $event_count = $check_result->fetch_assoc()['event_count'];
                if ($event_count > 0) {
                    // We have events but the join didn't work, let's get the first event and assign attendance to it
                    $first_event_sql = "SELECT event_title, start_date as event_date, event_id FROM afprotechs_events ORDER BY start_date DESC LIMIT 1";
                    $first_event_result = $conn->query($first_event_sql);
                    if ($first_event_result && $first_event_result->num_rows > 0) {
                        $first_event = $first_event_result->fetch_assoc();
                        $attendance_by_event[] = [
                            'event_title' => $first_event['event_title'],
                            'event_date' => $first_event['event_date'],
                            'event_id' => $first_event['event_id'],
                            'count' => $total_attendance
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // If there's an error with the events table, continue without it
        error_log("Events query error: " . $e->getMessage());
    }
    
    // Get unique students count
    $unique_sql = "SELECT COUNT(DISTINCT id_number) as count FROM afprotechs_attendance";
    $unique_result = $conn->query($unique_sql);
    if ($unique_result) {
        $total_unique_students = $unique_result->fetch_assoc()['count'];
    }
    
} catch (Exception $e) {
    error_log("Attendance fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECHS</title>
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
    <a href="#" class="active"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a>
    <a href="afprotechs_Announcement.php"><i class="fa-solid fa-bullhorn"></i><span>Announcement</span></a>
    <a href="afprotechs_records.php"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="afprotechs_products.php"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="afprotechs_reports.php"><i class="fa-solid fa-file-lines"></i><span>Generate Reports</span></a>
    <a href="#" onclick="showLogoutModal()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">

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

    <!-- ATTENDANCE CONTAINER -->
    <div class="attendance-container" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef; overflow: hidden;">
        
        <!-- ATTENDANCE HEADER BAR -->
        <div class="attendance-header-bar d-flex align-items-center justify-content-between px-4 py-3" style="background: #f8f9fa; border-bottom: 2px solid #000080;">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #000080; border-radius: 8px;">
                    <i class="fa-solid fa-clipboard-check" style="font-size: 16px; color: white;"></i>
                </div>
                <h5 class="mb-0 fw-bold" style="color: #000080; font-size: 20px;">Student Attendance</h5>
            </div>
            
            <!-- ONGOING EVENT DISPLAY -->
            <?php if ($ongoing_event): ?>
                <?php if (isset($ongoing_event['is_upcoming']) && $ongoing_event['is_upcoming']): ?>
                    <!-- Upcoming Event -->
                    <div class="ongoing-event-badge d-flex align-items-center gap-2 px-3 py-2" style="background: linear-gradient(135deg, #007bff, #0056b3); border-radius: 8px; color: white;">
                        <div class="pulse-dot" style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1.5s infinite;"></div>
                        <div>
                            <small style="font-size: 10px; opacity: 0.9;">UPCOMING EVENT</small>
                            <div class="fw-bold" style="font-size: 13px;"><?= htmlspecialchars($ongoing_event['event_title']) ?></div>
                            <small style="font-size: 9px; opacity: 0.8;"><?= date('M d, Y', strtotime($ongoing_event['event_date'])) ?></small>
                        </div>
                        <i class="fa-solid fa-calendar-plus ms-2" style="font-size: 18px;"></i>
                    </div>
                <?php elseif (isset($ongoing_event['is_general']) && $ongoing_event['is_general']): ?>
                    <!-- General Event (not today, not upcoming, but exists) -->
                    <div class="ongoing-event-badge d-flex align-items-center gap-2 px-3 py-2" style="background: linear-gradient(135deg, #6f42c1, #5a32a3); border-radius: 8px; color: white;">
                        <div class="pulse-dot" style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1.5s infinite;"></div>
                        <div>
                            <small style="font-size: 10px; opacity: 0.9;">EVENT</small>
                            <div class="fw-bold" style="font-size: 13px;"><?= htmlspecialchars($ongoing_event['event_title']) ?></div>
                            <small style="font-size: 9px; opacity: 0.8;"><?= date('M d, Y', strtotime($ongoing_event['event_date'])) ?></small>
                        </div>
                        <i class="fa-solid fa-calendar ms-2" style="font-size: 18px;"></i>
                    </div>
                <?php else: ?>
                    <!-- Today's Event -->
                    <div class="ongoing-event-badge d-flex align-items-center gap-2 px-3 py-2" style="background: linear-gradient(135deg, #28a745, #20c997); border-radius: 8px; color: white;">
                        <div class="pulse-dot" style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 1.5s infinite;"></div>
                        <div>
                            <small style="font-size: 10px; opacity: 0.9;">ONGOING EVENT</small>
                            <div class="fw-bold" style="font-size: 13px;"><?= htmlspecialchars($ongoing_event['event_title']) ?></div>
                        </div>
                        <i class="fa-solid fa-calendar-check ms-2" style="font-size: 18px;"></i>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
            @keyframes pulse {
                0% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.2); }
                100% { opacity: 1; transform: scale(1); }
            }
            
            .time-period-btn {
                transition: all 0.3s ease !important;
            }
            
            .time-period-btn:hover {
                transform: scale(1.02) !important;
                box-shadow: 0 2px 8px rgba(0,0,128,0.2) !important;
            }
            
            .time-period-btn.active {
                transform: scale(1.02) !important;
                box-shadow: 0 2px 8px rgba(0,0,128,0.3) !important;
            }
        </style>

        <!-- TABS -->
        <ul class="nav nav-tabs px-4 pt-3" id="attendanceTabs" role="tablist" style="border-bottom: none;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-content" type="button" role="tab" style="color: #000080; font-weight: 600; border: none; border-bottom: 3px solid #000080;">
                    <i class="fa-solid fa-list me-2"></i>Attendance List
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-content" type="button" role="tab" style="color: #6c757d; font-weight: 600; border: none;">
                    <i class="fa-solid fa-chart-pie me-2"></i>Reports
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="attendanceTabContent">
            
            <!-- ATTENDANCE LIST TAB -->
            <div class="tab-pane fade show active" id="attendance-content" role="tabpanel">
                <div class="attendance-content p-4">
            
                    <!-- ATTENDANCE STATS -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <div class="attendance-stats-card total">
                                <div class="stats-icon">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div class="stats-value"><?= $total_attendance ?></div>
                                <div class="stats-label">Total Attendance</div>
                            </div>
                        </div>
                    </div>
                    


            <!-- TIME PERIOD TABS -->
            <div class="time-period-tabs mb-3">
                <ul class="nav nav-pills" id="timePeriodTabs" role="tablist">

                    <li class="nav-item" role="presentation">
                        <button class="nav-link active time-period-btn" id="morning-tab" data-bs-toggle="pill" data-bs-target="#morning-content" type="button" role="tab" data-time="morning" style="background: #000080; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-weight: 500; margin-right: 8px;">
                            <i class="fa-solid fa-sun me-2"></i>Morning
                             <span class="badge ms-1" style="background: rgba(255,255,255,0.2); color: white; font-size: 11px;"><?= $morning_count ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link time-period-btn" id="afternoon-tab" data-bs-toggle="pill" data-bs-target="#afternoon-content" type="button" role="tab" data-time="afternoon" style="background: white; color: #000080; border: 1px solid #000080; padding: 8px 16px; border-radius: 20px; font-weight: 500;">
                            <i class="fa-solid fa-cloud-sun me-2"></i>Afternoon
                            <span class="badge ms-1" style="background: #000080; color: white; font-size: 11px;"><?= $afternoon_count ?></span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- FILTERS -->
            <div class="filters-section mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <!-- SECTION FILTER (LEFT SIDE) -->
                    <div class="section-filter">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold" style="color: #000080; font-size: 14px;">Filter:</span>
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn btn-sm section-filter-btn active" data-section="all" style="background: #000080; color: white; border: none; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">All</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3A" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3A</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3B" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3B</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3C" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3C</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3D" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3D</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3E" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3E</button>
                                <button class="btn btn-sm section-filter-btn" data-section="3F" style="background: white; color: #000080; border: 1px solid #000080; padding: 4px 12px; border-radius: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 12px;">3F</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- DATE FILTER (MIDDLE) -->
                    <div class="date-filter">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold" style="color: #000080; font-size: 14px;">Date Range:</span>
                            <div class="d-flex gap-2">
                            <input type="date" class="form-control form-control-sm" id="fromDate" style="border: 1px solid #000080; border-radius: 6px; font-size: 13px; width: 140px;" max="<?= date('Y-m-d') ?>">
                                <span class="text-muted" style="font-size: 14px;">to</span>
                                <input type="date" class="form-control form-control-sm" id="toDate" style="border: 1px solid #000080; border-radius: 6px; font-size: 13px; width: 140px;" max="<?= date('Y-m-d') ?>">
                                <button class="btn btn-sm" type="button" id="clearDateFilter" style="background: #dc3545; color: white; border: none; padding: 4px 12px; border-radius: 6px; font-size: 12px;">
                                    <i class="fa-solid fa-times me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- NAME SEARCH (RIGHT SIDE) -->
                    <div class="name-filter">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold" style="color: #000080; font-size: 14px;">Search:</span>
                            <div style="width: 250px;">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="nameSearchInput" placeholder="Enter student name..." style="border: 1px solid #000080; border-radius: 6px 0 0 6px; font-size: 13px;">
                                    <button class="btn btn-sm" type="button" id="searchToggleBtn" style="background: #000080; color: white; border: 1px solid #000080; border-radius: 0 6px 6px 0;">
                                        <i class="fa-solid fa-search" id="searchIcon" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ATTENDANCE TABLE -->
            <div class="attendance-table">
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Student Info</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Section</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Time In</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Status</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Date</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Method</th>
                                <th style="background: #f8f9fa; border-bottom: 2px solid #000080; color: #000080; font-weight: 600; padding: 16px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance_records)): ?>
                                <tr class="no-students-row">
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fa-solid fa-qrcode fa-2x mb-3"></i>
                                            <p class="mb-0">No students have scanned their QR codes yet.</p>
                                            <small>Students will appear here when they scan their QR codes for attendance.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_records as $index => $record): 
                                    // Determine time for filtering - use morning_in or afternoon_in
                                    $filter_time = '';
                                    $is_morning = !empty($record['morning_in']);
                                    $is_afternoon = !empty($record['afternoon_in']);
                                    if ($is_morning) {
                                        $filter_time = $record['morning_in'];
                                    } elseif ($is_afternoon) {
                                        $filter_time = $record['afternoon_in'];
                                    }
                                ?>
                                    <tr data-section="<?= htmlspecialchars($record['year_section']) ?>" data-name="<?= htmlspecialchars(strtolower($record['student_name'])) ?>" data-time="<?= $filter_time ? date('H:i', strtotime($filter_time)) : '' ?>" data-is-morning="<?= $is_morning ? '1' : '0' ?>" data-is-afternoon="<?= $is_afternoon ? '1' : '0' ?>" data-date="<?= date('Y-m-d', strtotime($record['attendance_date'])) ?>">
                                        <td style="vertical-align: middle;">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="student-avatar" style="width: 40px; height: 40px; min-width: 40px; background: #000080; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fa-solid fa-user"></i>
                                                </div>
                                                <div style="text-align: left;">
                                                    <div class="student-name" style="font-weight: 600; color: #333; font-size: 14px; white-space: nowrap;"><?= htmlspecialchars($record['student_name']) ?></div>
                                                    <div class="student-id" style="color: #666; font-size: 12px;">ID: <?= htmlspecialchars($record['student_id']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="section-badge" style="background: #000080; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 500;"><?= htmlspecialchars($record['year_section']) ?></span>
                                        </td>
                                        <td>
                                            <div class="attendance-time" style="color: #000080; font-weight: 500;">
                                                <i class="fa-solid fa-clock me-1"></i>
                                                <?= date('g:i A', strtotime($record['attendance_time'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge present" style="background: #28a745; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 500;">
                                                <i class="fa-solid fa-check me-1"></i>
                                                Present
                                            </span>
                                        </td>
                                        <td style="color: #666; font-size: 13px;"><?= date('M d, Y', strtotime($record['attendance_date'])) ?></td>
                                        <td>
                                            <span style="background: #f8f9fa; color: #000080; padding: 4px 8px; border-radius: 8px; font-size: 11px; font-weight: 500;">
                                                <i class="fa-solid fa-qrcode me-1"></i>
                                                QR Scan
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <button class="btn btn-sm btn-outline-primary view-attendance-btn" 
                                                        data-id="<?= $record['id'] ?>"
                                                        data-name="<?= htmlspecialchars($record['student_name']) ?>"
                                                        data-student-id="<?= htmlspecialchars($record['student_id']) ?>"
                                                        data-section="<?= htmlspecialchars($record['section']) ?>"
                                                        data-date="<?= date('M d, Y', strtotime($record['attendance_date'])) ?>"
                                                        data-time="<?= date('g:i A', strtotime($record['attendance_time'])) ?>"
                                                        data-course="<?= htmlspecialchars($record['course']) ?>"
                                                        data-year="<?= htmlspecialchars($record['year']) ?>"
                                                        style="border: 1px solid #000080; color: #000080; padding: 4px 8px;">
                                                    <i class="fa-solid fa-eye" style="font-size: 12px;"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-attendance-btn" 
                                                        data-id="<?= $record['id'] ?>"
                                                        data-name="<?= htmlspecialchars($record['student_name']) ?>"
                                                        style="border: 1px solid #dc3545; color: #dc3545; padding: 4px 8px;">
                                                    <i class="fa-solid fa-trash" style="font-size: 12px;"></i>
                                                </button>
                                            </div>
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
            
            <!-- REPORTS TAB -->
            <div class="tab-pane fade" id="reports-content" role="tabpanel">
                <div class="reports-content p-4">
                    
                    <!-- REPORTS SUMMARY CARDS -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-body text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #000080, #4169E1); border-radius: 12px; margin: 0 auto;">
                                        <i class="fa-solid fa-clipboard-check text-white" style="font-size: 24px;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" style="color: #000080;"><?= $total_attendance ?></h3>
                                    <p class="text-muted mb-0">Total Attendance</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-body text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #28a745, #20c997); border-radius: 12px; margin: 0 auto;">
                                        <i class="fa-solid fa-user-check text-white" style="font-size: 24px;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" style="color: #28a745;"><?= $total_unique_students ?></h3>
                                    <p class="text-muted mb-0">Unique Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-body text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffc107, #fd7e14); border-radius: 12px; margin: 0 auto;">
                                        <i class="fa-solid fa-layer-group text-white" style="font-size: 24px;"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" style="color: #ffc107;"><?= count($attendance_by_section) ?></h3>
                                    <p class="text-muted mb-0">Sections</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <!-- ATTENDANCE BY SECTION -->
                        <div class="col-md-6">
                            <div class="card h-100" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-header bg-white border-0 pt-3">
                                    <h6 class="fw-bold mb-0" style="color: #000080;">
                                        <i class="fa-solid fa-users-rectangle me-2"></i>Attendance by Section
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($attendance_by_section)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-chart-bar fa-2x mb-2" style="opacity: 0.3;"></i>
                                            <p class="mb-0">No section data available</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th style="color: #000080;">Section</th>
                                                        <th style="color: #000080;">Count</th>
                                                        <th style="color: #000080;">Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($attendance_by_section as $section): ?>
                                                        <?php $percentage = $total_attendance > 0 ? round(($section['count'] / $total_attendance) * 100, 1) : 0; ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge" style="background: #000080;"><?= htmlspecialchars($section['section'] ?? 'N/A') ?></span>
                                                            </td>
                                                            <td><?= $section['count'] ?></td>
                                                            <td>
                                                                <div class="progress" style="height: 8px; width: 100px;">
                                                                    <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%; background: #000080;"></div>
                                                                </div>
                                                                <small class="text-muted"><?= $percentage ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ATTENDANCE BY DATE -->
                        <div class="col-md-6">
                            <div class="card h-100" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-header bg-white border-0 pt-3">
                                    <h6 class="fw-bold mb-0" style="color: #000080;">
                                        <i class="fa-solid fa-calendar-days me-2"></i>Recent Attendance (Last 7 Days)
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($attendance_by_date)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-calendar-xmark fa-2x mb-2" style="opacity: 0.3;"></i>
                                            <p class="mb-0">No recent attendance data</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th style="color: #000080;">Date</th>
                                                        <th style="color: #000080;">Attendance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($attendance_by_date as $date_record): ?>
                                                        <tr>
                                                            <td><?= date('M d, Y (D)', strtotime($date_record['date'])) ?></td>
                                                            <td>
                                                                <span class="badge bg-success"><?= $date_record['count'] ?> students</span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ATTENDANCE BY EVENT -->
                        <div class="col-12">
                            <div class="card" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 12px;">
                                <div class="card-header bg-white border-0 pt-3">
                                    <h6 class="fw-bold mb-0" style="color: #000080;">
                                        <i class="fa-solid fa-calendar-check me-2"></i>Attendance by Event
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th style="color: #000080;">Event</th>
                                                    <th style="color: #000080;">Date</th>
                                                    <th style="color: #000080;">Attendees</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($attendance_by_event)): ?>
                                                    <?php foreach ($attendance_by_event as $event): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="fa-solid fa-calendar-day me-2" style="color: #000080;"></i>
                                                                <?= htmlspecialchars($event['event_title'] ?? 'Unknown Event') ?>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?= isset($event['event_date']) ? date('M d, Y', strtotime($event['event_date'])) : 'No date' ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php if ($event['count'] > 0): ?>
                                                                    <span class="badge" style="background: #28a745;"><?= $event['count'] ?> students</span>
                                                                <?php else: ?>
                                                                    <span class="badge" style="background: #6c757d;">0 students</span>
                                                                <?php endif; ?>
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
</div>

<!-- LOGOUT MODAL -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fa-solid fa-right-from-bracket fa-3x" style="color: #000080;"></i>
                </div>
                <h5 class="modal-title mb-3" style="color: #000080; font-weight: 600;">Logout Confirmation</h5>
                <p class="mb-4" style="color: #666;">Are you sure you want to logout?</p>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn" onclick="confirmLogout()" style="background: #000080; color: white; padding: 8px 24px; border-radius: 6px; font-weight: 500; border: none;">Yes</button>
                    <button type="button" class="btn" data-bs-dismiss="modal" style="background: #f8f9fa; color: #000080; padding: 8px 24px; border-radius: 6px; font-weight: 500; border: 1px solid #000080;">No</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
/* Tab Styling */
#attendanceTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    padding: 10px 20px;
    transition: all 0.3s ease;
}
#attendanceTabs .nav-link:hover {
    color: #000080;
    border-bottom-color: rgba(0, 0, 128, 0.3);
}
#attendanceTabs .nav-link.active {
    color: #000080 !important;
    border-bottom: 3px solid #000080 !important;
    background: transparent;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter elements
    const nameSearchInput = document.getElementById('nameSearchInput');
    const searchToggleBtn = document.getElementById('searchToggleBtn');
    const searchIcon = document.getElementById('searchIcon');
    const sectionFilterBtns = document.querySelectorAll('.section-filter-btn');
    const timePeriodBtns = document.querySelectorAll('.time-period-btn');
    const tableRows = document.querySelectorAll('tbody tr:not(.no-students-row)');
    
    let currentNameFilter = '';
    let currentSectionFilter = 'all';
    let currentTimePeriod = 'morning';
    let currentFromDate = '';
    let currentToDate = '';
    let isSearchActive = false;
    
    // Name search input event listener
    nameSearchInput.addEventListener('input', function() {
        currentNameFilter = this.value.toLowerCase().trim();
        updateSearchIcon();
        applyFilters();
    });
    
    // Search toggle button (magnifying glass / X)
    searchToggleBtn.addEventListener('click', function() {
        if (isSearchActive) {
            // Clear search
            nameSearchInput.value = '';
            currentNameFilter = '';
            nameSearchInput.focus();
            applyFilters();
        } else {
            // Focus on search input
            nameSearchInput.focus();
        }
        updateSearchIcon();
    });
    
    // Update search icon based on input state
    function updateSearchIcon() {
        if (nameSearchInput.value.trim() !== '') {
            searchIcon.className = 'fa-solid fa-times';
            isSearchActive = true;
        } else {
            searchIcon.className = 'fa-solid fa-search';
            isSearchActive = false;
        }
    }
    
    // Date filter inputs
    const fromDateInput = document.getElementById('fromDate');
    const toDateInput = document.getElementById('toDate');
    const clearDateFilterBtn = document.getElementById('clearDateFilter');
    
    fromDateInput.addEventListener('change', function() {
        currentFromDate = this.value;
        applyFilters();
    });
    
    toDateInput.addEventListener('change', function() {
        currentToDate = this.value;
        applyFilters();
    });
    
    clearDateFilterBtn.addEventListener('click', function() {
        fromDateInput.value = '';
        toDateInput.value = '';
        currentFromDate = '';
        currentToDate = '';
        applyFilters();
    });
    
    // Section filter buttons
    sectionFilterBtns.forEach(button => {
        button.addEventListener('click', function() {
            currentSectionFilter = this.getAttribute('data-section');
            
            // Update active button styling
            sectionFilterBtns.forEach(btn => {
                btn.style.background = 'white';
                btn.style.color = '#000080';
                btn.style.border = '1px solid #000080';
                btn.style.transform = 'scale(1)';
            });
            this.style.background = '#000080';
            this.style.color = 'white';
            this.style.border = 'none';
            this.style.transform = 'scale(1.02)';
            
            applyFilters();
        });
        
        // Add hover effects
        button.addEventListener('mouseenter', function() {
            if (this.getAttribute('data-section') !== currentSectionFilter) {
                this.style.transform = 'scale(1.01)';
                this.style.boxShadow = '0 1px 4px rgba(0,0,128,0.2)';
            }
        });
        
        button.addEventListener('mouseleave', function() {
            if (this.getAttribute('data-section') !== currentSectionFilter) {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            }
        });
    });
    
    // Time period filter buttons
    timePeriodBtns.forEach(button => {
        button.addEventListener('click', function() {
            currentTimePeriod = this.getAttribute('data-time');
            
            // Update active button styling
            timePeriodBtns.forEach(btn => {
                btn.style.background = 'white';
                btn.style.color = '#000080';
                btn.style.border = '1px solid #000080';
                btn.classList.remove('active');
                // Update badge styling for inactive buttons
                const badge = btn.querySelector('.badge');
                if (badge) {
                    badge.style.background = '#000080';
                    badge.style.color = 'white';
                }
            });
            this.style.background = '#000080';
            this.style.color = 'white';
            this.style.border = 'none';
            this.classList.add('active');
            // Update badge styling for active button
            const activeBadge = this.querySelector('.badge');
            if (activeBadge) {
                activeBadge.style.background = 'rgba(255,255,255,0.2)';
                activeBadge.style.color = 'white';
            }
            
            applyFilters();
        });
    });
    
    function applyFilters() {
        let visibleCount = 0;
        
        // Debug: Log current filter state
        console.log('Applying filters:', {
            timePeriod: currentTimePeriod,
            section: currentSectionFilter,
            name: currentNameFilter,
            fromDate: currentFromDate,
            toDate: currentToDate
        });
        
        tableRows.forEach(row => {
            // Get data from row attributes
            const studentName = row.getAttribute('data-name') || '';
            const studentSection = row.getAttribute('data-section') || '';
            const attendanceTime = row.getAttribute('data-time') || '';
            const attendanceDate = row.getAttribute('data-date') || '';
            
            // Check name and section filters
            const nameMatch = currentNameFilter === '' || studentName.includes(currentNameFilter);
            const sectionMatch = currentSectionFilter === 'all' || studentSection === currentSectionFilter;
            
            // Check time period filter using data attributes
            const isMorning = row.getAttribute('data-is-morning') === '1';
            const isAfternoon = row.getAttribute('data-is-afternoon') === '1';
            let timeMatch = false;
            if (currentTimePeriod === 'morning') {
                timeMatch = isMorning;
            } else if (currentTimePeriod === 'afternoon') {
                timeMatch = isAfternoon;
            }
            
            // Check date range filter
            let dateMatch = true;
            if (currentFromDate || currentToDate) {
                if (attendanceDate) {
                    const recordDate = new Date(attendanceDate);
                    if (currentFromDate) {
                        const fromDate = new Date(currentFromDate);
                        if (recordDate < fromDate) {
                            dateMatch = false;
                        }
                    }
                    if (currentToDate) {
                        const toDate = new Date(currentToDate);
                        toDate.setHours(23, 59, 59, 999); // Include the entire day
                        if (recordDate > toDate) {
                            dateMatch = false;
                        }
                    }
                } else {
                    dateMatch = false; // No date means it doesn't match date filters
                }
            }
            
            if (nameMatch && sectionMatch && timeMatch && dateMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide no students message
        const noStudentsRow = document.querySelector('.no-students-row');
        if (noStudentsRow) {
            if (visibleCount === 0 && tableRows.length > 0) {
                noStudentsRow.style.display = '';
                const timeLabel = currentTimePeriod === 'morning' ? 'morning (8AM-12PM)' : 'afternoon (1PM-5PM)';
                if (currentNameFilter !== '' || currentSectionFilter !== 'all' || currentFromDate !== '' || currentToDate !== '') {
                    noStudentsRow.querySelector('p').textContent = `No students found matching your filters for ${timeLabel}.`;
                } else {
                    noStudentsRow.querySelector('p').textContent = `No students have attendance records for ${timeLabel}.`;
                }
            } else {
                noStudentsRow.style.display = 'none';
            }
        }
        
        // Update stats based on visible students
        updateAttendanceStats(visibleCount);
    }
    
    function updateAttendanceStats(visibleCount) {
        // Keep the original total attendance count - don't update it based on filters
        // The total should always show the actual database count, not filtered count
        
        // Update time period badge counts
        let morningCount = 0;
        let afternoonCount = 0;
        
        tableRows.forEach(row => {
            const studentName = row.getAttribute('data-name') || '';
            const studentSection = row.getAttribute('data-section') || '';
            const isMorning = row.getAttribute('data-is-morning') === '1';
            const isAfternoon = row.getAttribute('data-is-afternoon') === '1';
            
            // Check name and section filters (but not time filter for counting)
            const nameMatch = currentNameFilter === '' || studentName.includes(currentNameFilter);
            const sectionMatch = currentSectionFilter === 'all' || studentSection === currentSectionFilter;
            
            if (nameMatch && sectionMatch) {
                if (isMorning) morningCount++;
                if (isAfternoon) afternoonCount++;
            }
        });
        
        // Update badge counts
        const morningBadge = document.querySelector('#morning-tab .badge');
        const afternoonBadge = document.querySelector('#afternoon-tab .badge');
        
        if (morningBadge) morningBadge.textContent = morningCount;
        if (afternoonBadge) afternoonBadge.textContent = afternoonCount;
        

    }
    
    // Initialize filters on page load (apply morning filter by default)
    applyFilters();
});

// Logout functions
function showLogoutModal() {
    const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    logoutModal.show();
}

function confirmLogout() {
    window.location.href = '../afprotech/afprotech_logout.php';
}

// View Attendance Details
document.querySelectorAll('.view-attendance-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const name = this.dataset.name;
        const studentId = this.dataset.studentId;
        const section = this.dataset.section;
        const date = this.dataset.date;
        const time = this.dataset.time;
        const course = this.dataset.course;
        const year = this.dataset.year;
        
        document.getElementById('viewStudentName').textContent = name;
        document.getElementById('viewStudentId').textContent = studentId;
        document.getElementById('viewSection').textContent = section;
        document.getElementById('viewDate').textContent = date;
        document.getElementById('viewTime').textContent = time;
        document.getElementById('viewCourse').textContent = course || 'N/A';
        document.getElementById('viewYear').textContent = year || 'N/A';
        
        const viewModal = new bootstrap.Modal(document.getElementById('viewAttendanceModal'));
        viewModal.show();
    });
});

// Delete Attendance
let deleteAttendanceId = null;
document.querySelectorAll('.delete-attendance-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        deleteAttendanceId = this.dataset.id;
        const name = this.dataset.name;
        document.getElementById('deleteStudentName').textContent = name;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteAttendanceModal'));
        deleteModal.show();
    });
});

function confirmDeleteAttendance() {
    if (!deleteAttendanceId) return;
    
    fetch('backend/afprotechs_delete_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + deleteAttendanceId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to delete: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}
</script>

<!-- View Attendance Modal -->
<div class="modal fade" id="viewAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background: #000080; color: white; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title"><i class="fa-solid fa-user me-2"></i>Attendance Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: #000080; border-radius: 50%;">
                        <i class="fa-solid fa-user text-white" style="font-size: 36px;"></i>
                    </div>
                    <h4 class="mt-3 mb-1 fw-bold" id="viewStudentName">-</h4>
                    <span class="badge" style="background: #000080;" id="viewSection">-</span>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <small class="text-muted d-block">Student ID</small>
                            <strong id="viewStudentId">-</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <small class="text-muted d-block">Course</small>
                            <strong id="viewCourse">-</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <small class="text-muted d-block">Year</small>
                            <strong id="viewYear">-</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <small class="text-muted d-block">Date</small>
                            <strong id="viewDate">-</strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 rounded" style="background: #d4edda;">
                            <small class="text-muted d-block">Time In</small>
                            <strong class="text-success"><i class="fa-solid fa-clock me-1"></i><span id="viewTime">-</span></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn" data-bs-dismiss="modal" style="background: #000080; color: white;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Attendance Modal -->
<div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fa-solid fa-trash fa-3x text-danger"></i>
                </div>
                <h5 class="modal-title mb-3" style="color: #dc3545; font-weight: 600;">Delete Attendance Record</h5>
                <p class="mb-1">Are you sure you want to delete the attendance record for:</p>
                <p class="fw-bold" id="deleteStudentName">-</p>
                <p class="text-muted small">This action cannot be undone.</p>
                <div class="d-flex gap-3 justify-content-center mt-4">
                    <button type="button" class="btn btn-danger" onclick="confirmDeleteAttendance()" style="padding: 8px 24px; border-radius: 6px; font-weight: 500;">Delete</button>
                    <button type="button" class="btn" data-bs-dismiss="modal" style="background: #f8f9fa; color: #333; padding: 8px 24px; border-radius: 6px; font-weight: 500; border: 1px solid #ddd;">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

