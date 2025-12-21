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
    $required_fields = ['student_id', 'product_name', 'product_description', 'product_price', 'product_quantity'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize and prepare data
    $student_id = trim($input['student_id']);
    $product_name = trim($input['product_name']);
    $product_description = trim($input['product_description']);
    $product_price = floatval($input['product_price']);
    $product_quantity = intval($input['product_quantity']);
    $product_location = isset($input['product_location']) ? trim($input['product_location']) : '';
    $preparation_time = isset($input['preparation_time']) ? intval($input['preparation_time']) : 0;
    $preparation_unit = isset($input['preparation_unit']) ? trim($input['preparation_unit']) : 'Minutes';
    $product_image = isset($input['product_image']) ? trim($input['product_image']) : '';
    
    // Validate preparation unit
    if (!in_array($preparation_unit, ['Minutes', 'Hours'])) {
        $preparation_unit = 'Minutes';
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate unique product ID
    $product_id = time() . rand(1000, 9999);
    
    // Insert into student products table
    $stmt = $pdo->prepare("
        INSERT INTO afprotech_student_products (
            product_id, student_id, product_name, product_description, 
            product_price, product_quantity, product_location, 
            preparation_time, preparation_unit, product_image, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $product_id, $student_id, $product_name, $product_description,
        $product_price, $product_quantity, $product_location,
        $preparation_time, $preparation_unit, $product_image
    ]);
    
    $student_product_id = $pdo->lastInsertId();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Product created successfully and submitted for approval',
        'data' => [
            'id' => $student_product_id,
            'product_id' => $product_id,
            'student_id' => $student_id,
            'product_name' => $product_name,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Product creation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create product: ' . $e->getMessage()
    ]);
}
?>