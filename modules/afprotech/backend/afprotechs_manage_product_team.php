<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db_connection.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['product_id', 'action', 'student_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $product_id = trim($input['product_id']);
    $action = trim($input['action']); // 'add' or 'remove'
    $student_id = trim($input['student_id']); // The student making the change
    
    // Validate action
    if (!in_array($action, ['add', 'remove'])) {
        throw new Exception('Invalid action. Must be "add" or "remove"');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Verify the student owns this product
    $owner_stmt = $pdo->prepare("
        SELECT student_id FROM afprotech_student_products 
        WHERE product_id = ? AND student_id = ?
    ");
    $owner_stmt->execute([$product_id, $student_id]);
    
    if (!$owner_stmt->fetch()) {
        throw new Exception('You can only manage team members for your own products');
    }
    
    if ($action === 'add') {
        // Add team member
        $required_add_fields = ['team_member_id', 'team_member_name'];
        foreach ($required_add_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                throw new Exception("Missing required field for add action: $field");
            }
        }
        
        $team_member_id = trim($input['team_member_id']);
        $team_member_name = trim($input['team_member_name']);
        
        // Check if member already exists
        $check_stmt = $pdo->prepare("
            SELECT id FROM afprotech_product_teams 
            WHERE product_id = ? AND team_member_id = ?
        ");
        $check_stmt->execute([$product_id, $team_member_id]);
        
        if ($check_stmt->fetch()) {
            throw new Exception('Team member already exists in this product');
        }
        
        // Add team member
        $add_stmt = $pdo->prepare("
            INSERT INTO afprotech_product_teams (
                product_id, student_id, team_member_id, team_member_name, role
            ) VALUES (?, ?, ?, ?, 'member')
        ");
        
        $add_stmt->execute([$product_id, $student_id, $team_member_id, $team_member_name]);
        
        // Log the action (optional - skip if table doesn't exist)
        try {
        $history_stmt = $pdo->prepare("
            INSERT INTO afprotech_product_history (
                product_id, student_id, action, new_values, changed_by
            ) VALUES (?, ?, 'team_added', ?, ?)
        ");
        
        $new_values = json_encode([
            'team_member_id' => $team_member_id,
            'team_member_name' => $team_member_name
        ]);
        
        $history_stmt->execute([$product_id, $student_id, $new_values, $student_id]);
        } catch (Exception $history_error) {
            // History logging failed - continue anyway (table may not exist)
            error_log("History logging skipped: " . $history_error->getMessage());
        }
        
        $message = "Team member added successfully";
        $data = [
            'team_member_id' => $team_member_id,
            'team_member_name' => $team_member_name,
            'added_at' => date('Y-m-d H:i:s')
        ];
        
    } else { // remove
        $team_member_id = isset($input['team_member_id']) ? trim($input['team_member_id']) : '';
        
        if (empty($team_member_id)) {
            throw new Exception('Missing team_member_id for remove action');
        }
        
        // Cannot remove creator
        if ($team_member_id === $student_id) {
            throw new Exception('Cannot remove the product creator from the team');
        }
        
        // Get member info before deletion
        $member_stmt = $pdo->prepare("
            SELECT team_member_name FROM afprotech_product_teams 
            WHERE product_id = ? AND team_member_id = ? AND role != 'creator'
        ");
        $member_stmt->execute([$product_id, $team_member_id]);
        $member = $member_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception('Team member not found or cannot be removed');
        }
        
        // Remove team member
        $remove_stmt = $pdo->prepare("
            DELETE FROM afprotech_product_teams 
            WHERE product_id = ? AND team_member_id = ? AND role != 'creator'
        ");
        
        $remove_stmt->execute([$product_id, $team_member_id]);
        
        if ($remove_stmt->rowCount() === 0) {
            throw new Exception('Team member not found or could not be removed');
        }
        
        // Log the action (optional - skip if table doesn't exist)
        try {
        $history_stmt = $pdo->prepare("
            INSERT INTO afprotech_product_history (
                product_id, student_id, action, old_values, changed_by
            ) VALUES (?, ?, 'team_removed', ?, ?)
        ");
        
        $old_values = json_encode([
            'team_member_id' => $team_member_id,
            'team_member_name' => $member['team_member_name']
        ]);
        
        $history_stmt->execute([$product_id, $student_id, $old_values, $student_id]);
        } catch (Exception $history_error) {
            // History logging failed - continue anyway (table may not exist)
            error_log("History logging skipped: " . $history_error->getMessage());
        }
        
        $message = "Team member removed successfully";
        $data = [
            'team_member_id' => $team_member_id,
            'team_member_name' => $member['team_member_name'],
            'removed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Get updated team count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as team_count FROM afprotech_product_teams 
        WHERE product_id = ?
    ");
    $count_stmt->execute([$product_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $data['team_count'] = $count_result['team_count'];
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Team management error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to manage team: ' . $e->getMessage()
    ]);
}
?>