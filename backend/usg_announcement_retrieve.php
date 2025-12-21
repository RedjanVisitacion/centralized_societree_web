<?php
// modules/usg/announcement_functions.php
require_once(__DIR__ . '/../db_connection.php');

function getRecentAnnouncements($limit = 5) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usg_announcement 
                               ORDER BY announcement_datetime DESC 
                               LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error fetching announcements: " . $e->getMessage());
        return [];
    }
}

function getAllAnnouncements() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM usg_announcement 
                             ORDER BY announcement_datetime DESC");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error fetching all announcements: " . $e->getMessage());
        return [];
    }
}

// New function to get announcements by type
function getAnnouncementsByType($type, $limit = null) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM usg_announcement 
                WHERE announcement_type = :type 
                ORDER BY announcement_datetime DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error fetching announcements by type: " . $e->getMessage());
        return [];
    }
}

// Function to get all available announcement types with counts
function getAnnouncementTypesWithCounts() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT announcement_type, COUNT(*) as count 
                             FROM usg_announcement 
                             GROUP BY announcement_type 
                             ORDER BY count DESC");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error fetching announcement types with counts: " . $e->getMessage());
        return [];
    }
}
?>