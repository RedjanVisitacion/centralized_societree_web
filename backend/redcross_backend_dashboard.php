<?php
require_once __DIR__ . '/../db_connection.php';

   // Ensure a mysqli connection named $conn exists for this backend
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        die('MySQLi connection failed: ' . $conn->connect_error);
    }
}

$dash_announcements = [];
$recent_campaigns   = [];
$recent_patients    = [];
$recent_members     = [];

// Use new redcross_announcements schema (no scheduled_at column)
// Only show active, non-expired announcements and order by created_at.
$resA = $conn->query(
    "SELECT title,
            body,
            created_at AS when_at
     FROM redcross_announcements
     WHERE is_active = 1
       AND (expires_at IS NULL OR expires_at >= NOW())
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($resA) {
    while ($row = $resA->fetch_assoc()) {
        $dash_announcements[] = $row;
    }
    $resA->free();
}

$stat_members   = 0;
$stat_hours     = 0;
$stat_campaigns = 0;

// New status enum in schema: active | inactive | suspended
$r = $conn->query("SELECT COUNT(*) AS c FROM redcross_members WHERE status = 'active'");
if ($r && $row = $r->fetch_assoc()) {
    $stat_members = (int) $row['c'];
}

$r = $conn->query("SELECT SUM(hours) AS h FROM redcross_activities");
if ($r && $row = $r->fetch_assoc()) {
    $stat_hours = (float) $row['h'];
}

$r = $conn->query("SELECT COUNT(*) AS c FROM redcross_campaigns");
if ($r && $row = $r->fetch_assoc()) {
    $stat_campaigns = (int) $row['c'];
}

$resCampaigns = $conn->query(
    "SELECT title, description, created_at
     FROM redcross_campaigns
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($resCampaigns) {
    while ($row = $resCampaigns->fetch_assoc()) {
        $recent_campaigns[] = $row;
    }
    $resCampaigns->free();
}

$resPatients = $conn->query(
    "SELECT name, case_description, date_of_service, created_at
     FROM redcross_patients
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($resPatients) {
    while ($row = $resPatients->fetch_assoc()) {
        $recent_patients[] = $row;
    }
    $resPatients->free();
}

$resMembers = $conn->query(
    "SELECT full_name, course, year_section, status, created_at
     FROM redcross_members
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($resMembers) {
    while ($row = $resMembers->fetch_assoc()) {
        $recent_members[] = $row;
    }
    $resMembers->free();
}

