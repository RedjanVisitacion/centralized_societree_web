<?php
/**
 * AFPROTECH Configuration
 * Main configuration file for AFPROTECH module
 */

// =====================================================
// DATABASE CONFIGURATION
// =====================================================

// Primary database (online server)
define('DB_HOST_PRIMARY', '103.125.219.236');
define('DB_USER_PRIMARY', 'societree');
define('DB_PASS_PRIMARY', 'socieTree12345');
define('DB_NAME_PRIMARY', 'societree');

// Fallback database (local XAMPP)
define('DB_HOST_FALLBACK', 'localhost');
define('DB_USER_FALLBACK', 'root');
define('DB_PASS_FALLBACK', '');
define('DB_NAME_FALLBACK', 'societree');

// =====================================================
// API CONFIGURATION
// =====================================================

// API Base URLs
define('API_BASE_URL_LOCAL', 'http://192.168.0.129/societrees_web');
define('API_BASE_URL_ONLINE', 'http://103.125.219.236/societree_web');

// API Endpoints
define('API_EVENTS_ENDPOINT', '/modules/afprotech/backend/afprotechs_get_events.php');
define('API_ANNOUNCEMENTS_ENDPOINT', '/modules/afprotech/backend/afprotechs_get_announcements.php');
define('API_CREATE_EVENT_ENDPOINT', '/modules/afprotech/backend/afprotechs_create_event.php');
define('API_UPDATE_EVENT_ENDPOINT', '/modules/afprotech/backend/afprotechs_update_event.php');
define('API_DELETE_EVENT_ENDPOINT', '/modules/afprotech/backend/afprotechs_delete_event.php');
define('API_COUNTDOWN_ENDPOINT', '/modules/afprotech/backend/afprotechs_get_countdown.php');

// =====================================================
// SECURITY CONFIGURATION
// =====================================================

// CORS Settings
define('CORS_ALLOWED_ORIGINS', '*');
define('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization');

// =====================================================
// APPLICATION SETTINGS
// =====================================================

// Timezone
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// Error Reporting
define('DEBUG_MODE', true); // Set to false in production
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Mobile API Timeouts (seconds)
define('MOBILE_API_TIMEOUT_LOCAL', 10);
define('MOBILE_API_TIMEOUT_ONLINE', 15);

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Get database connection with fallback (MySQLi connection)
 */
function getAfprotechDbConnection() {
    // Database credentials (same as db_connection.php)
    $host = '103.125.219.236';
    $user = 'societree';
    $password = 'socieTree12345';
    $database = 'societree';
    
    try {
        // Try remote first
        $conn = new mysqli($host, $user, $password, $database);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        // Fall back to local XAMPP defaults
        try {
            $conn = new mysqli('localhost', 'root', '', $database);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Set charset
            $conn->set_charset("utf8mb4");
            
            return $conn;
        } catch (Exception $e2) {
            throw new Exception("Unable to connect to any database server: " . $e2->getMessage());
        }
    }
}

/**
 * Set CORS headers
 */
function setAfprotechCorsHeaders() {
    header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
    header('Access-Control-Allow-Methods: ' . CORS_ALLOWED_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_ALLOWED_HEADERS);
    header('Content-Type: application/json');
}

/**
 * Handle preflight requests
 */
function handleAfprotechPreflight() {
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        setAfprotechCorsHeaders();
        exit(0);
    }
}

/**
 * Format response for mobile app
 */
function formatAfprotechResponse($success, $data = null, $message = null) {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => 'AFPROTECH Backend'
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
        if (is_array($data)) {
            $response['count'] = count($data);
        }
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    return $response;
}

// =====================================================
// DATABASE BACKUP SQL
// =====================================================

/**
 * Get database backup SQL
 */
function getAfprotechBackupSQL() {
    return "
-- AFPROTECH Database Backup and Setup Script
-- Generated: " . date('Y-m-d H:i:s') . "
-- Database: societree
-- Tables: afprotechs_events, afprotechs_announcements

-- =====================================================
-- AFPROTECH EVENTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `afprotechs_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `event_location` varchar(255) DEFAULT NULL,
  `event_status` varchar(50) DEFAULT 'Upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_status` (`event_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- AFPROTECH ANNOUNCEMENTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `afprotechs_announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_title` varchar(255) NOT NULL,
  `announcement_content` text NOT NULL,
  `announcement_datetime` datetime NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcement_id`),
  KEY `idx_datetime` (`announcement_datetime`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Announcements Data
INSERT INTO `afprotechs_announcements` (`announcement_title`, `announcement_content`, `announcement_datetime`) VALUES
('Welcome to AFPROTECH', 'Welcome to the Association of Food Processing and Technology Students. We are excited to have you join our community!', '2025-12-10 21:00:00'),
('Event Registration Open', 'Registration for upcoming events is now open. Please check the events section for more details and registration links.', '2025-12-11 10:00:00');

-- =====================================================
-- AFPROTECH attendance TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS afprotechs_attendance (
            afprotechs_id_attendance INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_number VARCHAR(64) NOT NULL,
            event_id INT UNSIGNED NULL,
            morning_in TIME NULL,
            morning_out TIME NULL,
            afternoon_in TIME NULL,
            afternoon_out TIME NULL,
            attendance_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_date (id_number, attendance_date),
            INDEX idx_event (event_id),
            INDEX idx_attendance_date (attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
}

// =====================================================
// BACKEND CODE TEMPLATES
// =====================================================

// Backend code templates removed to fix syntax errors
// Individual backend files should be created separately

// Template functions removed to fix syntax errors
// Backend files should be created separately

// =====================================================
// BACKUP FUNCTIONS
// =====================================================

// Backup functions removed to fix syntax errors

// =====================================================
// INITIALIZATION
// =====================================================

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

?>