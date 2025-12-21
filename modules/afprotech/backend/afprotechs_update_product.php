<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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

// Allow POST and PUT methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST and PUT methods allowed'
    ]);
    exit;
}

// Get form data
$product_id = $_POST['product_id'] ?? '';
$product_name = $_POST['product_name'] ?? '';
$product_description = $_POST['product_description'] ?? '';
$product_price = $_POST['product_price'] ?? 0;
$product_quantity = $_POST['product_quantity'] ?? 0;
$product_location = $_POST['product_location'] ?? '';
$product_category = $_POST['product_category'] ?? 'Popular';
$preparation_time = $_POST['preparation_time'] ?? 10;
$preparation_unit = $_POST['preparation_unit'] ?? 'minutes';

// Validate required fields
if (empty($product_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

if (empty(trim($product_name))) {
    echo json_encode([
        'success' => false,
        'message' => 'Product name is required'
    ]);
    exit;
}

if (empty(trim($product_description))) {
    echo json_encode([
        'success' => false,
        'message' => 'Product description is required'
    ]);
    exit;
}

if ($product_price <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Product price must be greater than 0'
    ]);
    exit;
}

try {
    // Check if product exists
    $check_sql = "SELECT product_id FROM afprotechs_products WHERE product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $product_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
        exit;
    }
    
    // Handle image upload if provided
    $product_image = null;
    $update_image = false;
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/products/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'product_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
                $product_image = 'uploads/products/' . $fileName;
                $update_image = true;
            }
        }
    }
    
    // Build update query
    if ($update_image) {
        $sql = "UPDATE afprotechs_products SET product_name = ?, product_description = ?, product_price = ?, product_quantity = ?, product_location = ?, product_category = ?, preparation_time = ?, preparation_unit = ?, product_image = ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdissisi', $product_name, $product_description, $product_price, $product_quantity, $product_location, $product_category, $preparation_time, $preparation_unit, $product_image, $product_id);
    } else {
        $sql = "UPDATE afprotechs_products SET product_name = ?, product_description = ?, product_price = ?, product_quantity = ?, product_location = ?, product_category = ?, preparation_time = ?, preparation_unit = ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdissisi', $product_name, $product_description, $product_price, $product_quantity, $product_location, $product_category, $preparation_time, $preparation_unit, $product_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update product: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>