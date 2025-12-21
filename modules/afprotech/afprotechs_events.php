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
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection not established')
    ]));
}

// Auto-update event statuses based on dates
$statusUpdateSql = "
    UPDATE afprotechs_events 
    SET event_status = CASE
        WHEN CURDATE() > DATE(end_date) THEN 'Finished'
        WHEN CURDATE() BETWEEN DATE(start_date) AND DATE(end_date) THEN 'Ongoing'
        WHEN DATE(start_date) BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 5 DAY THEN 'Upcoming'
        ELSE 'Upcoming'
    END
    WHERE start_date IS NOT NULL AND end_date IS NOT NULL";
$conn->query($statusUpdateSql);

// Fetch events from database
// Use start_date for ordering and alias it as event_date for existing UI bindings
$sql = "
    SELECT 
        event_id,
        event_title,
        event_description,
        start_date AS event_date,
        start_date,
        end_date,
        event_location,
        COALESCE(event_status, 'Upcoming') as event_status
    FROM afprotechs_events 
    ORDER BY start_date ASC";
$result = $conn->query($sql);
$events = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
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

    <a href="afprotechs_dashboard.php">
        <i class="fa-solid fa-house"></i><span>Home</span>
    </a>
    <a href="#" class="active">
        <i class="fa-solid fa-calendar-days"></i><span>Event</span>
    </a>
    <a href="afprotechs_attendance.php">
        <i class="fa-solid fa-clipboard-check"></i><span>Attendance</span>
    </a>
    <a href="afprotechs_Announcement.php">
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
<div class="content events-content">

    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">
        
        <div>
            <h2 class="fw-bold text-dark mb-0" style="font-size: 24px;">Events Management</h2>
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

    <!-- SEARCH BAR AND CONTROLS -->
    <div class="container-fluid px-4 mb-4">
        <div class="row">
            <div class="col-12 d-flex justify-content-end align-items-center gap-3">
                <!-- SEARCH BAR -->
                <div style="max-width: 300px; width: 100%;">
                    <form class="events-search-form">
                        <div class="input-group">
                            <input type="search" class="form-control" id="eventsSearchInput" placeholder="Search events..." aria-label="Search events">
                            <button class="btn btn-primary" type="submit" style="background-color: #000080; border-color: #000080;">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- CREATE EVENT BUTTON -->
                <button class="btn btn-create-event d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createEventModal">
                    <i class="fa-solid fa-plus"></i>
                    Create Event
                </button>
            </div>
        </div>
    </div>

    <!-- EVENTS LIST -->
    <div class="events-list" id="eventsList">
        <?php if (empty($events)): ?>
            <div class="event-card">
                <div class="text-center text-muted py-4">
                    <i class="fa-solid fa-calendar-days fa-2x mb-2"></i>
                    <p class="mb-0">No events have been announced</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="event-card position-relative" style="padding: 24px; margin-bottom: 20px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef; min-height: 120px;"
                     data-title="<?= htmlspecialchars($event['event_title']) ?>"
                     data-desc="<?= htmlspecialchars($event['event_description']) ?>"
                     data-location="<?= htmlspecialchars($event['event_location'] ?? '') ?>"
                     data-date="<?= htmlspecialchars($event['event_date']) ?>"
                     data-start-date="<?= htmlspecialchars(!empty($event['start_date']) ? $event['start_date'] : $event['event_date']) ?>"
                     data-end-date="<?= htmlspecialchars(!empty($event['end_date']) ? $event['end_date'] : $event['event_date']) ?>"
                     data-status="<?= htmlspecialchars($event['event_status'] ?? 'Upcoming') ?>">
                    <!-- Event Content -->
                    <div class="d-flex align-items-start gap-3" style="padding-right: 60px; width: 100%; max-width: 100%;">
                        <div class="event-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0; max-width: calc(100% - 100px);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="event-title" style="font-weight: 600;"><?= htmlspecialchars($event['event_title']) ?></div>
                                <span class="event-status-badge 
                                    <?php 
                                        $status = $event['event_status'] ?? 'Upcoming';
                                        echo match($status) {
                                            'Ongoing' => 'status-ongoing',
                                            'Finished' => 'status-finished',
                                            'Cancelled' => 'status-cancelled',
                                            default => 'status-upcoming'
                                        };
                                    ?>" 
                                    style="font-size: 11px; padding: 4px 8px; border-radius: 12px; font-weight: 500; text-transform: uppercase;">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </div>
                            <div class="event-desc text-muted" style="font-size: 16px; line-height: 1.8; word-wrap: break-word; overflow-wrap: break-word; max-width: 100%; white-space: pre-line; margin: 1rem 0; padding: 0.75rem; background: rgba(248, 249, 250, 0.5); border-radius: 8px;">
                                <?= htmlspecialchars($event['event_description']) ?>
                            </div>
                            <!-- Date, Time, Location Section with Bottom Line -->
                            <div class="event-details-section mt-3 pt-3" style="border-top: 1px solid #f0f0f0;">
                                <div class="d-flex align-items-center gap-4 flex-wrap">
                                    <!-- Date Section -->
                                    <div class="event-date-time d-flex align-items-center gap-1" style="color: #000080;">
                                        <?php 
                                            // Display date range if start_date and end_date are available
                                            $startDate = !empty($event['start_date']) ? $event['start_date'] : $event['event_date'];
                                            $endDate = !empty($event['end_date']) ? $event['end_date'] : $event['event_date'];
                                            
                                            if ($startDate === $endDate) {
                                                // Single day event
                                                echo '<i class="fa-solid fa-calendar-days"></i>';
                                                echo '<span>' . date('M d, Y', strtotime($startDate)) . '</span>';
                                            } else {
                                                // Multi-day event
                                                echo '<i class="fa-solid fa-calendar-days"></i>';
                                                echo '<span>' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . '</span>';
                                            }
                                        ?>
                                    </div>
                                    
                                    <!-- Location Section -->
                                    <?php if (!empty($event['event_location'])): ?>
                                        <div class="event-location d-flex align-items-center gap-1" style="color: #000080;">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <span><?= htmlspecialchars($event['event_location']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 3-dot menu positioned absolutely to the right -->
                    <div class="position-absolute top-0 end-0 p-2 d-flex align-items-center justify-content-center">
                            <div class="dropdown">
                                <button class="btn btn-sm event-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px; width: 32px; height: 32px; border-radius: 6px; border: none; background: transparent; color: #6c757d;">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 edit-event-btn" 
                                           href="#" 
                                           data-id="<?= $event['event_id'] ?>"
                                           data-title="<?= htmlspecialchars($event['event_title']) ?>"
                                           data-desc="<?= htmlspecialchars($event['event_description']) ?>"
                                           data-start-date="<?= htmlspecialchars(!empty($event['start_date']) ? $event['start_date'] : $event['event_date']) ?>"
                                           data-end-date="<?= htmlspecialchars(!empty($event['end_date']) ? $event['end_date'] : $event['event_date']) ?>"
                                           data-location="<?= htmlspecialchars($event['event_location'] ?? '') ?>"
                                           data-status="<?= htmlspecialchars($event['event_status'] ?? 'Upcoming') ?>">
                                            <i class="fa-regular fa-pen-to-square"></i> Edit
                                        </a>
                                    </li>


                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-event-btn" 
                                           href="#" 
                                           data-id="<?= $event['event_id'] ?>"
                                           data-title="<?= htmlspecialchars($event['event_title']) ?>">
                                            <i class="fa-regular fa-trash-can"></i> Delete
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 900px;">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
                
                <!-- App Bar Header -->
                <div class="event-app-bar d-flex align-items-center justify-content-between p-3" style="background: linear-gradient(135deg, #000080 0%, #1a4fa0 100%); color: white;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="event-app-icon d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; backdrop-filter: blur(10px);">
                            <i class="fa-solid fa-calendar-days" style="font-size: 18px;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold" style="font-size: 16px;">Event Details</h6>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body with Scrollable Content -->
                <div class="modal-body p-0" style="max-height: 70vh; overflow-y: auto;">
                    
                    <!-- Event Content -->
                    <div class="event-content p-4">
                        <!-- Event Title -->
                        <h4 id="eventModalTitle" class="mb-4 fw-bold" style="color: #000080; font-size: 24px; line-height: 1.3;"></h4>
                        
                        <div class="content-container">
                            <div id="eventModalDesc" class="mb-3 content-text" style="line-height: 1.6; color: #2c2c2c; font-size: 16px; font-weight: 400; text-align: justify; max-height: 200px; overflow: hidden; transition: max-height 0.3s ease; word-wrap: break-word; white-space: pre-line;"></div>
                            <button id="eventSeeMoreBtn" class="btn btn-link p-0 text-primary d-none" onclick="toggleEventSeeMore()" style="font-size: 14px; text-decoration: none; font-weight: 500;">
                                <i class="fa-solid fa-chevron-down me-1" id="eventSeeMoreIcon"></i> See More
                            </button>
                        </div>
                    </div>

                    <!-- Date and Time Section -->
                    <div class="event-datetime px-4 py-2">
                        <div class="d-flex align-items-center gap-3 text-muted">
                            <div class="d-flex align-items-center gap-1">
                                <i class="fa-solid fa-calendar-days" style="color: #000080; font-size: 14px;"></i>
                                <span id="eventModalDate" style="font-size: 14px; font-weight: 500;"></span>
                            </div>
                            <div class="d-flex align-items-center gap-1" id="eventTimeSection">
                                <i class="fa-solid fa-clock" style="color: #000080; font-size: 14px;"></i>
                                <span id="eventModalTime" style="font-size: 14px; font-weight: 500;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Location Section -->
                    <div id="eventModalLocation" class="px-4 py-2 d-none">
                        <div class="d-flex align-items-center gap-2 text-muted">
                            <i class="fa-solid fa-location-dot" style="color: #000080; font-size: 14px;"></i>
                            <span id="eventLocationText" style="font-size: 14px; font-weight: 500; color: #2c2c2c;"></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Delete Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="deleteEventTitle" class="fw-bold"></span>"?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-delete-yes" id="confirmDeleteBtn">Yes</button>
                    <button type="button" class="btn btn-delete-no" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Event Modal -->
    <div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalFormTitle">Create Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createEventForm">
                    <input type="hidden" name="event_id" id="editEventId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Event Title</label>
                            <input type="text" name="event_title" id="editEventTitle" class="form-control" placeholder="Title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="event_description" id="editEventDescription" class="form-control" placeholder="Description" rows="3" required></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="start_date" id="editStartDate" class="form-control" required>
                            </div>
                            <div class="col">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="end_date" id="editEndDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" name="event_location" id="editEventLocation" class="form-control" placeholder="Location">
                        </div>
                        <div class="form-text text-muted mb-3">
                            <i class="fa-solid fa-info-circle"></i> Event status will be automatically determined based on the event dates
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-save-event w-100" id="eventFormSubmitBtn">Create Event</button>
                    </div>
                </form>
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
                <button type="button" class="btn btn-logout-yes" onclick="window.location.href='afprotech_logout.php'" style="background: #000080; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">Yes</button>
                <button type="button" class="btn btn-logout-no" data-bs-dismiss="modal" style="background: #6c757d; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 500; min-width: 80px;">No</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Badge Styles -->
<style>
.event-status-badge {
    display: inline-block;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-upcoming {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.status-ongoing {
    background-color: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.status-finished {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}

.status-cancelled {
    background-color: #ffebee;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js?v=<?= time() ?>"></script>

<!-- Search Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('eventsSearchInput');
    const searchForm = document.querySelector('.events-search-form');
    const eventCards = document.querySelectorAll('.event-card');
    
    // Handle form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Handle real-time search as user types
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            performSearch();
        });
    }
    
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        eventCards.forEach(card => {
            const title = card.getAttribute('data-title')?.toLowerCase() || '';
            const desc = card.getAttribute('data-desc')?.toLowerCase() || '';
            const location = card.getAttribute('data-location')?.toLowerCase() || '';
            const status = card.getAttribute('data-status')?.toLowerCase() || '';
            
            if (searchTerm === '' || 
                title.includes(searchTerm) || 
                desc.includes(searchTerm) || 
                location.includes(searchTerm) ||
                status.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
});
</script>

</body>
</html>



