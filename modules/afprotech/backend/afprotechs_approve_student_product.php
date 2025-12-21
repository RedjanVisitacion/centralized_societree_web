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
    $required_fields = ['product_id', 'action', 'admin_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $product_id = trim($input['product_id']);
    $action = trim($input['action']); // 'approve' or 'reject'
    $admin_id = trim($input['admin_id']);
    $rejection_reason = isset($input['rejection_reason']) ? trim($input['rejection_reason']) : '';
    
    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action. Must be "approve" or "reject"');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current product data
    $stmt = $pdo->prepare("
        SELECT * FROM afprotech_student_products 
        WHERE product_id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    if ($product['status'] !== 'pending') {
        throw new Exception('Product is not in pending status');
    }
    
    // Update product status
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    $update_stmt = $pdo->prepare("
        UPDATE afprotech_student_products 
        SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
        WHERE product_id = ?
    ");
    
    $update_stmt->execute([
        $new_status, 
        $admin_id, 
        ($action === 'reject') ? $rejection_reason : null,
        $product_id
    ]);
    
    // Note: Products remain in afprotech_student_products table when approved
    // The mobile app should query approved products from afprotech_student_products instead of afprotechs_products
    
    // Log the action in history (optional - skip if table doesn't exist)
    try {
    $history_stmt = $pdo->prepare("
        INSERT INTO afprotech_product_history (
            product_id, student_id, action, old_values, new_values, changed_by, change_reason
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $old_values = json_encode(['status' => $product['status']]);
    $new_values = json_encode([
        'status' => $new_status,
        'approved_by' => $admin_id,
        'approved_at' => date('Y-m-d H:i:s'),
        'rejection_reason' => $rejection_reason
    ]);
    
    $history_stmt->execute([
        $product_id,
        $product['student_id'],
        $action === 'approve' ? 'approved' : 'rejected',
        $old_values,
        $new_values,
        $admin_id,
        $rejection_reason
    ]);
    } catch (Exception $history_error) {
        // History logging failed - continue anyway (table may not exist)
        error_log("History logging skipped: " . $history_error->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Product {$action}d successfully",
        'data' => [
            'product_id' => $product_id,
            'status' => $new_status,
            'approved_by' => $admin_id,
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $rejection_reason
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Product approval error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process request: ' . $e->getMessage()
    ]);
}
?>