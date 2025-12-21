<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Use main database connection (same as student products)
require_once '../../../db_connection.php';

try {
    // Fetch products from BOTH sources:
    // 1. Approved student products (from afprotech_student_products)
    // 2. Admin-created products (from afprotechs_products)
    
    // First, get approved student products
    $studentProductsSql = "
        SELECT 
            sp.product_id,
            sp.product_name,
            sp.product_description,
            sp.product_price,
            sp.product_quantity,
            sp.product_location,
            sp.preparation_time,
            sp.preparation_unit,
            sp.product_image,
            sp.group_members,
            sp.created_at,
            sp.updated_at,
            sp.student_id AS creator_student_id,
            CONCAT(
                COALESCE(s.first_name, ''), ' ',
                COALESCE(CONCAT(s.middle_name, ' '), ''),
                COALESCE(s.last_name, '')
            ) AS owner_name,
            -- Determine category based on description or default to 'Popular'
            CASE 
                WHEN sp.product_description LIKE '%dessert%' OR sp.product_name LIKE '%cake%' OR sp.product_name LIKE '%donut%' THEN 'Desserts'
                WHEN sp.product_description LIKE '%drink%' OR sp.product_name LIKE '%coffee%' OR sp.product_name LIKE '%juice%' THEN 'Beverages'
                WHEN sp.product_description LIKE '%snack%' OR sp.product_name LIKE '%sandwich%' OR sp.product_name LIKE '%cracker%' THEN 'Snacks'
                ELSE 'Popular'
            END as product_category
        FROM afprotech_student_products sp
        LEFT JOIN student s ON sp.student_id = s.id_number
        WHERE sp.status = 'approved' 
        AND sp.product_quantity > 0
    ";
    
    $stmt = $pdo->prepare($studentProductsSql);
    $stmt->execute();
    $studentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Second, get admin-created products from afprotechs_products
    $adminProducts = [];
    
    try {
        $adminProductsSql = "
            SELECT 
                ap.product_id,
                ap.product_name,
                ap.product_description,
                ap.product_price,
                ap.product_quantity,
                ap.product_location,
                COALESCE(ap.preparation_time, 10) as preparation_time,
                COALESCE(ap.preparation_unit, 'minutes') as preparation_unit,
                ap.product_image,
                NULL as group_members,
                ap.created_at,
                ap.updated_at,
                NULL as creator_student_id,
                'AFPROTECH Admin' as owner_name,
                COALESCE(ap.product_category, 'Popular') as product_category
            FROM afprotechs_products ap
            WHERE ap.product_quantity > 0
        ";
        
        $stmt2 = $pdo->prepare($adminProductsSql);
        $stmt2->execute();
        $adminProducts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist or query failed - just continue with empty array
        error_log("Could not fetch admin products: " . $e->getMessage());
        $adminProducts = [];
    }
    
    // Combine both results
    $rows = array_merge($studentProducts, $adminProducts);
    
    // Remove duplicates by product_id (in case same product exists in both tables)
    $uniqueProducts = [];
    $seenIds = [];
    foreach ($rows as $row) {
        $productId = $row['product_id'];
        if (!in_array($productId, $seenIds)) {
            $uniqueProducts[] = $row;
            $seenIds[] = $productId;
        }
    }
    
    // Sort by created_at descending
    usort($uniqueProducts, function($a, $b) {
        $dateA = strtotime($a['created_at'] ?? '1970-01-01');
        $dateB = strtotime($b['created_at'] ?? '1970-01-01');
        return $dateB - $dateA;
    });
    
    $rows = $uniqueProducts;
    
    $products = [];
    foreach ($rows as $row) {
        // Format product_image: handle both base64 (student products) and file paths (admin products)
        $product_image = null;
        if (!empty($row['product_image'])) {
            $image = trim($row['product_image']);
            
            // Check if it's already a data URI or HTTP URL
            if (preg_match('/^(data:|http)/i', $image)) {
                $product_image = $image;
            } 
            // Check if it's a file path (starts with "uploads/" or similar)
            elseif (preg_match('/^(uploads|images|img)\//i', $image)) {
                // Convert relative path to full URL
                // Use the actual request host
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '103.125.219.236';
                
                // For admin products, uploaded images live under modules/afprotech/uploads/...
                // So if the path does NOT already contain "modules/afprotech", add it.
                if (strpos($image, 'modules/afprotech/') === 0) {
                    $fullPath = "/societrees_web/{$image}";
                } else {
                    $fullPath = "/societrees_web/modules/afprotech/{$image}";
                }
                
                $product_image = $protocol . '://' . $host . $fullPath;
            }
            // Otherwise, assume it's base64
            else {
                // It's likely base64, determine image type
                if (substr($image, 0, 4) === '/9j/') {
                    $product_image = 'data:image/jpeg;base64,' . $image;
                } elseif (substr($image, 0, 22) === 'iVBORw0KGgoAAAANSUhEUg') {
                    $product_image = 'data:image/png;base64,' . $image;
                } else {
                    // Try JPEG as default for base64
                    $product_image = 'data:image/jpeg;base64,' . $image;
                }
            }
        }
        
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'product_description' => $row['product_description'] ?? 'No description available',
            'product_price' => (float)$row['product_price'],
            'product_quantity' => (int)$row['product_quantity'],
            'product_location' => $row['product_location'] ?? 'USTP MOBOD',
            'product_category' => $row['product_category'] ?? 'Popular',
            'product_image' => $product_image,
            'preparation_time' => isset($row['preparation_time']) ? (int)$row['preparation_time'] : 10,
            'preparation_unit' => isset($row['preparation_unit']) ? $row['preparation_unit'] : 'minutes',
            'group_members' => $row['group_members'] ?? null,
            'creator_student_id' => $row['creator_student_id'] ?? null,
            'owner_name' => $row['owner_name'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products),
        'server' => 'AFPROTECH Products Backend',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

?>