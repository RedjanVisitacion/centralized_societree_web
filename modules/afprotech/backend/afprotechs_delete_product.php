<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use AFPROTECH's own database connection
require_once __DIR__ . '/../config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Allow POST and DELETE methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST and DELETE methods allowed'
    ]);
    exit;
}

// Get product ID
$product_id = $_POST['product_id'] ?? '';

// Validate required fields
if (empty($product_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

try {
    // Check if product exists in admin products table first
    $check_sql = "SELECT product_id, product_image, 'admin' as product_type FROM afprotechs_products WHERE product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $product_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Check if it exists in student products table
        $check_sql_student = "SELECT product_id, product_image, 'student' as product_type FROM afprotech_student_products WHERE product_id = ?";
        $check_stmt_student = $conn->prepare($check_sql_student);
        $check_stmt_student->bind_param('i', $product_id);
        $check_stmt_student->execute();
        $result_student = $check_stmt_student->get_result();
        
        if ($result_student->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Product not found'
            ]);
            exit;
        }
        
        $product = $result_student->fetch_assoc();
        $table_name = 'afprotech_student_products';
        $check_stmt_student->close();
    } else {
        $product = $result->fetch_assoc();
        $table_name = 'afprotechs_products';
    }
    
    $check_stmt->close();
    
    // Delete the product from the appropriate database table
    $delete_sql = "DELETE FROM $table_name WHERE product_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $product_id);
    
    if ($delete_stmt->execute()) {
        // Delete associated image file if it exists (only for admin products)
        if ($product['product_type'] === 'admin' && !empty($product['product_image'])) {
            $imagePath = '../' . $product['product_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete product: ' . $delete_stmt->error
        ]);
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>