<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use main database connection (same as get products endpoint)
// Try remote first, then fallback to local (same logic as db_connection.php)
$host = '103.125.219.236';
$user = 'societree';
$password = 'socieTree12345';
$database = 'societree';

try {
    // Try remote first
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception('Remote connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    // Fall back to local XAMPP defaults
    try {
        $conn = new mysqli('localhost', 'root', '', $database);
        
        if ($conn->connect_error) {
            throw new Exception('Local connection failed: ' . $conn->connect_error);
        }
        
        $conn->set_charset('utf8mb4');
    } catch (Exception $e2) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e2->getMessage()
        ]);
        exit;
    }
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Get form data
$product_name = $_POST['product_name'] ?? '';
$product_description = $_POST['product_description'] ?? '';
$product_price = floatval($_POST['product_price'] ?? 0); // Ensure proper float conversion
$product_quantity = intval($_POST['product_quantity'] ?? 0); // Ensure proper int conversion
$product_location = $_POST['product_location'] ?? '';
$product_category = $_POST['product_category'] ?? 'Popular';
$preparation_time = intval($_POST['preparation_time'] ?? 10); // Ensure proper int conversion
$preparation_unit = $_POST['preparation_unit'] ?? 'minutes';
$product_image = null; // Initialize as null

// Handle image upload - support both file upload and base64
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    // Traditional file upload
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
        }
    }
} elseif (isset($_POST['product_image_base64']) && !empty($_POST['product_image_base64'])) {
    // Base64 image upload (from mobile app)
    $uploadDir = '../uploads/products/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $base64Data = $_POST['product_image_base64'];
    
    // Remove data:image/jpeg;base64, prefix if present
    if (strpos($base64Data, ',') !== false) {
        $base64Data = explode(',', $base64Data)[1];
    }
    
    // Decode base64
    $imageData = base64_decode($base64Data);
    
    if ($imageData !== false) {
        // Detect image type from binary data
        $imageInfo = getimagesizefromstring($imageData);
        $mimeType = $imageInfo['mime'] ?? '';
        
        $extension = 'jpg'; // default
        switch ($mimeType) {
            case 'image/jpeg':
                $extension = 'jpg';
                break;
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
        }
        
        $fileName = 'product_' . time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $fileName;
        
        if (file_put_contents($uploadPath, $imageData)) {
            $product_image = 'uploads/products/' . $fileName;
        }
    }
}

// Validate required fields
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
        'message' => 'Product price must be greater than 0',
        'debug' => [
            'received_price' => $_POST['product_price'] ?? 'not set',
            'converted_price' => $product_price
        ]
    ]);
    exit;
}

try {
    // Create products table if it doesn't exist
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS afprotechs_products (
            product_id INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(255) NOT NULL,
            product_description TEXT NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            product_quantity INT NOT NULL DEFAULT 0,
            product_location VARCHAR(255) DEFAULT NULL,
            product_category VARCHAR(100) DEFAULT 'Popular',
            preparation_time INT DEFAULT 10,
            preparation_unit ENUM('minutes', 'hours') DEFAULT 'minutes',
            product_image VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($createTableSql);
    
    // Add category column if it doesn't exist (for existing tables)
    try {
        $checkColumn = "SHOW COLUMNS FROM afprotechs_products LIKE 'product_category'";
        $result = $conn->query($checkColumn);
        if ($result->num_rows == 0) {
            $addCategoryColumn = "ALTER TABLE afprotechs_products ADD COLUMN product_category VARCHAR(100) DEFAULT 'Popular'";
            $conn->query($addCategoryColumn);
        }
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }
    
    // Add preparation time columns if they don't exist
    try {
        $checkPrepTimeColumn = "SHOW COLUMNS FROM afprotechs_products LIKE 'preparation_time'";
        $result = $conn->query($checkPrepTimeColumn);
        if ($result->num_rows == 0) {
            $addPrepTimeColumn = "ALTER TABLE afprotechs_products ADD COLUMN preparation_time INT DEFAULT 10";
            $conn->query($addPrepTimeColumn);
        }
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }
    
    try {
        $checkPrepUnitColumn = "SHOW COLUMNS FROM afprotechs_products LIKE 'preparation_unit'";
        $result = $conn->query($checkPrepUnitColumn);
        if ($result->num_rows == 0) {
            $addPrepUnitColumn = "ALTER TABLE afprotechs_products ADD COLUMN preparation_unit ENUM('minutes', 'hours') DEFAULT 'minutes'";
            $conn->query($addPrepUnitColumn);
        }
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }
    
    // Insert new product
    $sql = "INSERT INTO afprotechs_products (product_name, product_description, product_price, product_quantity, product_location, product_category, preparation_time, preparation_unit, product_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param(
        'ssdississ',
        $product_name,
        $product_description,
        $product_price,
        $product_quantity,
        $product_location,
        $product_category,
        $preparation_time,
        $preparation_unit,
        $product_image
    );
    
    if ($stmt->execute()) {
        $product_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Product created successfully',
            'product_id' => $product_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create product: ' . $stmt->error,
            'sql_error' => $conn->error,
            'debug_info' => [
                'preparation_time' => $preparation_time,
                'preparation_unit' => $preparation_unit,
                'sql' => $sql
            ]
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