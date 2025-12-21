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
    $required_fields = ['student_id', 'first_name', 'last_name', 'product_name', 'product_description', 'product_price', 'product_quantity'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
        // For string fields, check if empty after trim
        if (is_string($input[$field]) && trim($input[$field]) === '') {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize and prepare data
    $student_id = trim($input['student_id']);
    $first_name = trim($input['first_name']);
    $middle_name = isset($input['middle_name']) ? trim($input['middle_name']) : '';
    $last_name = trim($input['last_name']);
    $group_members = isset($input['group_members']) ? trim($input['group_members']) : '';
    $product_name = trim($input['product_name']);
    $product_description = trim($input['product_description']);
    $product_price = floatval($input['product_price']);
    $product_quantity = intval($input['product_quantity']);
    $product_location = isset($input['product_location']) ? trim($input['product_location']) : 'USTP MOBOD';
    $preparation_time = isset($input['preparation_time']) ? intval($input['preparation_time']) : 10;
    $preparation_unit = isset($input['preparation_unit']) ? trim($input['preparation_unit']) : 'minutes';
    $product_category = isset($input['product_category']) && trim($input['product_category']) !== '' 
        ? trim($input['product_category']) 
        : 'All Products';
    
    // Process product image - strip data URI prefix if present, handle empty strings
    $product_image = null;
    if (isset($input['product_image']) && !empty(trim($input['product_image']))) {
        $image_data = trim($input['product_image']);
        // Strip data URI prefix if present (e.g., "data:image/jpeg;base64,")
        if (preg_match('/^data:image\/[^;]+;base64,(.+)$/i', $image_data, $matches)) {
            $product_image = $matches[1]; // Store only the base64 string
        } else {
            $product_image = $image_data; // Already plain base64, store as-is
        }
        // Convert empty string to null
        if (empty($product_image)) {
            $product_image = null;
        }
    }
    
    // Validate data
    if ($product_price <= 0) {
        throw new Exception('Product price must be greater than 0');
    }
    
    if ($product_quantity < 0) {
        throw new Exception('Product quantity cannot be negative');
    }
    
    // Validate preparation unit
    if (!in_array($preparation_unit, ['minutes', 'hours', 'Minutes', 'Hours'])) {
        $preparation_unit = 'minutes';
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate unique product ID that fits within INT range (max 2,147,483,647)
    // Use last 6 digits of timestamp (max 999,999) * 1000 + random 3 digits (100-999)
    // This gives us max value of 999,999,999 which is well within INT range
    $timestamp_part = time() % 1000000; // Last 6 digits of timestamp
    $random_part = rand(100, 999);      // 3 random digits
    $product_id = ($timestamp_part * 1000) + $random_part;
    
    // Ensure uniqueness - check if product_id already exists
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM afprotech_student_products WHERE product_id = ?");
    $check_stmt->execute([$product_id]);
    
    // If collision detected, regenerate (very rare)
    $attempts = 0;
    while ($check_stmt->fetchColumn() > 0 && $attempts < 10) {
        $product_id = ((time() % 1000000) * 1000) + rand(100, 999);
        $check_stmt->execute([$product_id]);
        $attempts++;
    }
    
    if ($attempts >= 10) {
        throw new Exception('Unable to generate unique product ID. Please try again.');
    }
    
    // Insert into student products table
    $stmt = $pdo->prepare("
        INSERT INTO afprotech_student_products (
            product_id, student_id, first_name, middle_name, last_name, group_members,
            product_name, product_description, product_price, product_quantity, 
            product_location, preparation_time, preparation_unit, product_image, 
            product_category, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $product_id, $student_id, $first_name, $middle_name, $last_name, $group_members,
        $product_name, $product_description, $product_price, $product_quantity, 
        $product_location, $preparation_time, $preparation_unit, $product_image,
        $product_category
    ]);
    
    $student_product_id = $pdo->lastInsertId();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Product submitted successfully and is pending admin approval',
        'data' => [
            'id' => $student_product_id,
            'product_id' => $product_id,
            'student_id' => $student_id,
            'student_name' => trim("$first_name $middle_name $last_name"),
            'product_name' => $product_name,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'message' => 'Your product has been submitted for admin review. You will be notified once it is approved.'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Student product submission error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit product: ' . $e->getMessage()
    ]);
}
?>
