<?php
/**
 * Auto-Lock Cron Job for PAFE Events
 * 
 * This script should be run every minute via cron job to automatically
 * lock event sessions based on the configured auto-lock times.
 * 
 * Cron job example (runs every minute):
 * * * * * * /usr/bin/php /path/to/modules/pafe/auto_lock_cron.php
 */

require_once '../../db_connection.php';

function autoLockSessions($pdo) {
    try {
        $current_time = date('H:i:s');
        $current_date = date('Y-m-d');
        
        // Log the execution
        error_log("Auto-lock cron running at " . date('Y-m-d H:i:s'));
        
        // Get events that have auto-lock enabled and are happening today
        $stmt = $pdo->prepare("
            SELECT * FROM pafe_events 
            WHERE auto_lock_enabled = 1 
            AND event_date = ? 
            AND (
                (morning_session_locked = 0 AND morning_auto_lock_time IS NOT NULL AND morning_auto_lock_time <= ?) 
                OR 
                (afternoon_session_locked = 0 AND afternoon_auto_lock_time IS NOT NULL AND afternoon_auto_lock_time <= ?)
            )
        ");
        $stmt->execute([$current_date, $current_time, $current_time]);
        $events = $stmt->fetchAll();
        
        $locked_sessions = 0;
        
        foreach ($events as $event) {
            // Check morning session auto-lock
            if (!$event['morning_session_locked'] && 
                $event['morning_auto_lock_time'] && 
                $current_time >= $event['morning_auto_lock_time']) {
                
                $update_stmt = $pdo->prepare("UPDATE pafe_events SET morning_session_locked = 1 WHERE id = ?");
                $update_stmt->execute([$event['id']]);
                
                error_log("Auto-locked morning session for event ID: " . $event['id'] . " (" . $event['title'] . ")");
                $locked_sessions++;
            }
            
            // Check afternoon session auto-lock
            if (!$event['afternoon_session_locked'] && 
                $event['afternoon_auto_lock_time'] && 
                $current_time >= $event['afternoon_auto_lock_time']) {
                
                $update_stmt = $pdo->prepare("UPDATE pafe_events SET afternoon_session_locked = 1 WHERE id = ?");
                $update_stmt->execute([$event['id']]);
                
                error_log("Auto-locked afternoon session for event ID: " . $event['id'] . " (" . $event['title'] . ")");
                $locked_sessions++;
            }
        }
        
        if ($locked_sessions > 0) {
            error_log("Auto-lock completed: $locked_sessions sessions locked");
        }
        
        return $locked_sessions;
        
    } catch (PDOException $e) {
        error_log("Auto-lock database error: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Auto-lock general error: " . $e->getMessage());
        return false;
    }
}

// Run the auto-lock function
$result = autoLockSessions($pdo);

// If running from command line, output result
if (php_sapi_name() === 'cli') {
    if ($result !== false) {
        echo "Auto-lock completed successfully. Sessions locked: $result\n";
    } else {
        echo "Auto-lock failed. Check error logs.\n";
    }
}
?>