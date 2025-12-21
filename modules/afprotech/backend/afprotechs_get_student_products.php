<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db_connection.php';

try {
    // Get query parameters
    $student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
    
    // Build query - try to get product_image column (MySQL is case-insensitive on Windows)
    // If column name differs, we'll handle it in post-processing
    $query = "
        SELECT 
            sp.id,
            sp.product_id,
            sp.student_id as creator_id,
            CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as creator_name,
            sp.product_name,
            sp.product_description,
            sp.product_price,
            sp.product_quantity,
            sp.product_location,
            sp.preparation_time,
            sp.preparation_unit,
            sp.product_image,
            sp.group_members,
            sp.product_category,
            sp.status,
            sp.created_at,
            sp.updated_at,
            sp.approved_by,
            sp.approved_at,
            sp.rejection_reason
        FROM afprotech_student_products sp
        LEFT JOIN student s ON sp.student_id = s.id_number
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($student_id)) {
        $query .= " AND sp.student_id = ?";
        $params[] = $student_id;
    }
    
    if (!empty($status)) {
        $query .= " AND sp.status = ?";
        $params[] = $status;
    }
    
    if (!empty($product_id)) {
        $query .= " AND sp.product_id = ?";
        $params[] = $product_id;
    }
    
    $query .= " ORDER BY sp.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each product: format images and set empty team data (teams feature removed)
    foreach ($products as &$product) {
        // Handle different column name cases (product_image vs product_Image)
        $imageValue = null;
        if (isset($product['product_image']) && !empty($product['product_image'])) {
            $imageValue = $product['product_image'];
        } elseif (isset($product['product_Image']) && !empty($product['product_Image'])) {
            $imageValue = $product['product_Image'];
            $product['product_image'] = $imageValue; // Normalize to lowercase
        }
        
        // Format image: if it's base64, add data URI prefix
        if (!empty($imageValue)) {
            $image = $imageValue;
            // Check if it's already a data URI or HTTP URL
            if (!preg_match('/^(data:|http)/i', $image)) {
                // It's likely base64, determine image type
                if (substr($image, 0, 4) === '/9j/') {
                    $product['product_image'] = 'data:image/jpeg;base64,' . $image;
                } elseif (substr($image, 0, 22) === 'iVBORw0KGgoAAAANSUhEUg') {
                    $product['product_image'] = 'data:image/png;base64,' . $image;
                } else {
                    // Try JPEG as default for base64
                    $product['product_image'] = 'data:image/jpeg;base64,' . $image;
                }
            } else {
                $product['product_image'] = $image;
            }
        } else {
            $product['product_image'] = null;
        }
        
        // Set empty team data (teams feature removed)
        $product['team_members'] = [];
        $product['team_count'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
    
} catch (Exception $e) {
    error_log("Get student products error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve products: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>