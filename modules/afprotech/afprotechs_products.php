<?php
// Use AFPROTECH's own database connection
require_once __DIR__ . '/config/config.php';

try {
    $conn = getAfprotechDbConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

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

// Remove old time columns if they exist (cleanup)
try {
    $checkTimeFromColumn = "SHOW COLUMNS FROM afprotechs_products LIKE 'product_time_from'";
    $result = $conn->query($checkTimeFromColumn);
    if ($result->num_rows > 0) {
        $dropTimeFromColumn = "ALTER TABLE afprotechs_products DROP COLUMN product_time_from";
        $conn->query($dropTimeFromColumn);
    }
} catch (Exception $e) {
    // Column might not exist, ignore error
}

try {
    $checkTimeToColumn = "SHOW COLUMNS FROM afprotechs_products LIKE 'product_time_to'";
    $result = $conn->query($checkTimeToColumn);
    if ($result->num_rows > 0) {
        $dropTimeToColumn = "ALTER TABLE afprotechs_products DROP COLUMN product_time_to";
        $conn->query($dropTimeToColumn);
    }
} catch (Exception $e) {
    // Column might not exist, ignore error
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



// Fetch products from database
// 1) Approved student products (afprotech_student_products)
// 2) Admin-created products (afprotechs_products)

$products = [];

// 1) Approved student products
$sqlStudent = "SELECT * FROM afprotech_student_products WHERE status = 'approved' ORDER BY created_at DESC";
$resultStudent = $conn->query($sqlStudent);

if ($resultStudent && $resultStudent->num_rows > 0) {
    while ($row = $resultStudent->fetch_assoc()) {
        // Format product_image: if it's base64, add data URI prefix
        if (!empty($row['product_image'])) {
            $image = trim($row['product_image']);
            // If already data URI or HTTP URL, keep as is
            if (preg_match('/^(data:|http)/i', $image)) {
                $row['product_image'] = $image;
            }
            // If it's a relative file path, use as-is (img src will resolve correctly)
            elseif (preg_match('/^(uploads|images|img)\//i', $image)) {
                $row['product_image'] = $image;
            }
            // Otherwise, assume it's base64 data from mobile app
            else {
                if (substr($image, 0, 4) === '/9j/') {
                    $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                } elseif (substr($image, 0, 22) === 'iVBORw0KGgoAAAANSUhEUg') {
                    $row['product_image'] = 'data:image/png;base64,' . $image;
                } else {
                    // Default to JPEG
                    $row['product_image'] = 'data:image/jpeg;base64,' . $image;
                }
            }
        }
        $products[] = $row;
    }
}

// 2) Admin-created products from afprotechs_products (quantity > 0)
try {
    $sqlAdmin = "SELECT * FROM afprotechs_products WHERE product_quantity > 0 ORDER BY created_at DESC";
    $resultAdmin = $conn->query($sqlAdmin);

    if ($resultAdmin && $resultAdmin->num_rows > 0) {
        while ($row = $resultAdmin->fetch_assoc()) {
            // For admin products, product_image is usually a relative file path like 'uploads/products/...'
            if (!empty($row['product_image'])) {
                $image = trim($row['product_image']);
                // Keep data URI or HTTP URL as is
                if (preg_match('/^(data:|http)/i', $image)) {
                    $row['product_image'] = $image;
                }
                // If it's a relative path, keep as-is so img src resolves to /modules/afprotech/uploads/...
                elseif (preg_match('/^(uploads|images|img)\//i', $image)) {
                    $row['product_image'] = $image;
                } else {
                    // Fallback: leave unchanged
                    $row['product_image'] = $image;
                }
            }

            // Ensure category has a sensible default
            if (empty($row['product_category'])) {
                $row['product_category'] = 'Popular';
            }

            // Admin products don't have group_members column; add as empty for consistency
            if (!isset($row['group_members'])) {
                $row['group_members'] = '';
            }

            $products[] = $row;
        }
    }
} catch (Exception $e) {
    // If afprotechs_products doesn't exist or query fails, just ignore and proceed with student products
}

// Calculate sold quantities for each product
foreach ($products as &$product) {
    // Get total sold quantity from orders (only confirmed/completed orders)
    $soldSql = "SELECT COALESCE(SUM(quantity), 0) as total_sold 
                FROM afprotechs_orders 
                WHERE product_id = ? AND order_status IN ('confirmed', 'completed', 'delivered')";
    $soldStmt = $conn->prepare($soldSql);
    $soldStmt->bind_param('i', $product['product_id']);
    $soldStmt->execute();
    $soldResult = $soldStmt->get_result();
    $soldData = $soldResult->fetch_assoc();
    $product['total_sold'] = intval($soldData['total_sold']);
    $soldStmt->close();
}

// Separate products by category (ONLY show products that are in stock - quantity > 0)
// All products (for "All Products" section) - includes all categories that are in stock
// Remove duplicates by using product_id as key
$allProductsTemp = array_filter($products, function($product) {
    return intval($product['product_quantity']) > 0;
});

// Remove duplicates by product_id
$allProducts = [];
$seenIds = [];
foreach ($allProductsTemp as $product) {
    $productId = $product['product_id'];
    if (!in_array($productId, $seenIds)) {
        $allProducts[] = $product;
        $seenIds[] = $productId;
    }
}

// Category-specific products (with deduplication)
function getUniqueProductsByCategory($products, $category) {
    $filtered = array_filter($products, function($product) use ($category) {
        return ($product['product_category'] ?? 'Snacks') === $category && intval($product['product_quantity']) > 0;
    });
    
    // Remove duplicates by product_id
    $unique = [];
    $seenIds = [];
    foreach ($filtered as $product) {
        $productId = $product['product_id'];
        if (!in_array($productId, $seenIds)) {
            $unique[] = $product;
            $seenIds[] = $productId;
        }
    }
    return $unique;
}

$dessertProducts = getUniqueProductsByCategory($products, 'Desserts');
$beverageProducts = getUniqueProductsByCategory($products, 'Beverages');
$snackProducts = getUniqueProductsByCategory($products, 'Snacks');

// Separate sold products (quantity = 0) - but we'll hide this section if empty
$soldProductsTemp = array_filter($products, function($product) {
    return intval($product['product_quantity']) <= 0;
});

// Remove duplicates by product_id
$soldProducts = [];
$seenSoldIds = [];
foreach ($soldProductsTemp as $product) {
    $productId = $product['product_id'];
    if (!in_array($productId, $seenSoldIds)) {
        $soldProducts[] = $product;
        $seenSoldIds[] = $productId;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AFPROTECH Products</title>
        <link rel="icon" type="image/png" href="../../assets/logo/afprotech_1.png?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="afprotechs_styles.css?v=<?= time() ?>">
</head>
<body>

<div class="sidebar d-flex flex-column align-items-start pt-4 px-3">
    <div class="sidebar-brand d-flex align-items-center gap-3 mb-4 w-100">
        <div class="sidebar-logo">
            <img src="../../assets/logo/afprotech_1.png?v=<?= time() ?>" alt="logo" width="60" height="60">
        </div>
        <div class="sidebar-org text-start">
            <span class="sidebar-org-title">AFPROTECH</span>
        </div>
    </div>

    <a href="afprotechs_dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="afprotechs_events.php"><i class="fa-solid fa-calendar-days"></i><span>Event</span></a>
    <a href="afprotechs_attendance.php"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a>
    <a href="afprotechs_Announcement.php"><i class="fa-solid fa-bullhorn"></i><span>Announcement</span></a>
    <a href="afprotechs_records.php"><i class="fa-solid fa-chart-bar"></i><span>Records</span></a>
    <a href="#" class="active"><i class="fa-solid fa-cart-shopping"></i><span>Product</span></a>
    <a href="afprotechs_reports.php"><i class="fa-solid fa-file-lines"></i><span>Generate Reports</span></a>
    <a href="#"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
</div>

<div class="content" style="padding-top:100px;">

    <!-- HEADER -->
    <div class="dashboard-header bg-white shadow-sm d-flex justify-content-between align-items-center">
        
        <div>
            <h2 class="fw-bold text-dark mb-0" style="font-size: 24px;">AFPROTECH PRODUCTS</h2>
        </div>

        <div class="dashboard-profile d-flex align-items-center gap-3">
            <span class="dashboard-notify position-relative">
                <i class="fa-regular fa-bell fa-lg"></i>
                <span style="display:block;position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ffd700;"></span>
            </span>
            <div class="rounded-circle dashboard-profile-avatar 
            d-flex align-items-center justify-content-center"
     style="width:40px;height:40px;background:#000080;
            color:#fff;font-weight:bold;font-size:14px; text-transform: uppercase;">
    LB
</div>

<span class="fw-semibold dashboard-admin-name">
    Lester Bulay<br>
    <span class="dashboard-role">ADMIN</span>
</span>

        </div>

    </div>

    <!-- PRODUCTS CONTAINER -->
    <div class="container-fluid px-4">
        
        <!-- PAGE CONTROLS -->
        <div class="row mb-4">
            <div class="col-12">
                <!-- Filter Buttons Row -->
                <div class="d-flex justify-content-start align-items-center gap-2 mb-3">
                    <span class="fw-semibold text-muted me-2">Filter by:</span>
                    <button class="btn btn-filter active" data-filter="all">
                        All
                    </button>
                    <button class="btn btn-filter" data-filter="Desserts">
                        Desserts
                    </button>
                    <button class="btn btn-filter" data-filter="Beverages">
                        Beverages
                    </button>
                    <button class="btn btn-filter" data-filter="Snacks">
                        Snacks
                    </button>
                </div>
                
                <!-- Controls Row -->
                <div class="d-flex justify-content-end align-items-center gap-3">
                    <!-- ADD PRODUCT BUTTON -->
                    <button class="btn btn-create-product d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createProductModal">
                        <i class="fa-solid fa-plus"></i>
                        Add Product
                    </button>
                    
                    <!-- SEARCH BAR -->
                    <div style="max-width: 300px; width: 100%;">
                        <form class="products-search-form">
                            <div class="input-group">
                                <input type="search" class="form-control" id="productsSearchInput" placeholder="Search products..." aria-label="Search products">
                                <button class="btn btn-primary" type="submit" style="background-color: #000080; border-color: #000080;">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- POPULAR SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0" style="color: #000080;">
                        All Products
                        <span class="ms-2" style="font-size: 12px;">
                            <?= count($allProducts) ?>
                        </span>
                    </h4>
                    <a href="#" class="text-primary text-decoration-none fw-semibold">See More ></a>
                </div>
                
                <!-- Popular Products Row -->
                <div class="row g-3">
                    <?php if (empty($allProducts)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa-solid fa-box text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mb-0 text-muted">No products available yet.</p>
                                <small class="text-muted">Add some products to get started!</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allProducts as $product): ?>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                <div class="product-card h-100 position-relative clickable-product-card" 
                                     data-product-id="<?= $product['product_id'] ?>"
                                     data-description="<?= htmlspecialchars($product['product_description'] ?? '') ?>"
                                     data-price="<?= $product['product_price'] ?>"
                                     data-location="<?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?>"
                                     data-time="<?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?>"
                                     data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>"
                                     data-quantity="<?= $product['product_quantity'] ?>"
                                     data-image="<?= htmlspecialchars($product['product_image'] ?? '') ?>"
                                     data-group-members="<?= htmlspecialchars($product['group_members'] ?? '') ?>"
                                     data-sold-count="<?= $product['total_sold'] ?>">
                                    
                                    <!-- Sold Count Badge (Top Left) -->
                                    <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1" style="z-index: 10;">
                                        <span class="badge bg-dark text-white" style="font-size: 9px; border-radius: 12px;">
                                            <?= $product['total_sold'] ?> sold
                                        </span>
                                    </div>
                                    
                                    <!-- 3-dot menu -->
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <div class="dropdown">
                                            <button class="btn btn-sm product-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px; width: 24px; height: 24px; border-radius: 4px; border: none; background: rgba(0,0,0,0.1); color: #6c757d;">
                                                <i class="fa-solid fa-ellipsis-vertical" style="font-size: 12px;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 edit-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                       data-description="<?= htmlspecialchars($product['product_description']) ?>"
                                                       data-price="<?= $product['product_price'] ?>"
                                                       data-quantity="<?= $product['product_quantity'] ?>"
                                                       data-location="<?= htmlspecialchars($product['product_location'] ?? '') ?>"
                                                       data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>">
                                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                                        <i class="fa-regular fa-trash-can"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <?php if (!empty($product['product_image'])): ?>
                                        <div class="product-image-container">
                                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                                        </div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h6 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                                        <div class="product-price fw-bold mt-2" style="font-size: 14px; color: #000080;">
                                            ₱<?= number_format($product['product_price'], 2) ?>
                                        </div>
                                        <div class="product-location-time text-muted mt-1" style="font-size: 10px;">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <i class="fa-solid fa-location-dot" style="font-size: 8px;"></i>
                                                <span><?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-clock" style="font-size: 8px;"></i>
                                                <span><?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DESSERTS SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0" style="color: #000080;">
                        Desserts
                        <span class="ms-2" style="font-size: 12px;">
                            <?= count($dessertProducts) ?>
                        </span>
                    </h4>
                    <a href="#" class="text-primary text-decoration-none fw-semibold">See More ></a>
                </div>
                
                <!-- Desserts Products Row -->
                <div class="row g-3">
                    <?php if (empty($dessertProducts)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa-solid fa-cookie-bite text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mb-0 text-muted">No dessert products available yet.</p>
                                <small class="text-muted">Add some dessert products to display here!</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dessertProducts as $product): ?>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                <div class="product-card h-100 position-relative clickable-product-card" 
                                     data-product-id="<?= $product['product_id'] ?>"
                                     data-description="<?= htmlspecialchars($product['product_description'] ?? '') ?>"
                                     data-price="<?= $product['product_price'] ?>"
                                     data-location="<?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?>"
                                     data-time="<?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?>"
                                     data-category="<?= htmlspecialchars($product['product_category'] ?? 'Desserts') ?>"
                                     data-quantity="<?= $product['product_quantity'] ?>"
                                     data-image="<?= htmlspecialchars($product['product_image'] ?? '') ?>"
                                     data-group-members="<?= htmlspecialchars($product['group_members'] ?? '') ?>"
                                     data-sold-count="<?= $product['total_sold'] ?>">
                                    
                                    <!-- Sold Count Badge (Top Left) -->
                                    <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1" style="z-index: 10;">
                                        <span class="badge bg-dark text-white" style="font-size: 9px; border-radius: 12px;">
                                            <?= $product['total_sold'] ?> sold
                                        </span>
                                    </div>
                                    
                                    <!-- 3-dot menu -->
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <div class="dropdown">
                                            <button class="btn btn-sm product-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px; width: 24px; height: 24px; border-radius: 4px; border: none; background: rgba(0,0,0,0.1); color: #6c757d;">
                                                <i class="fa-solid fa-ellipsis-vertical" style="font-size: 12px;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 edit-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                       data-description="<?= htmlspecialchars($product['product_description']) ?>"
                                                       data-price="<?= $product['product_price'] ?>"
                                                       data-quantity="<?= $product['product_quantity'] ?>"
                                                       data-location="<?= htmlspecialchars($product['product_location'] ?? '') ?>"
                                                       data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>">
                                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                                        <i class="fa-regular fa-trash-can"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <?php if (!empty($product['product_image'])): ?>
                                        <div class="product-image-container">
                                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                                        </div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h6 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                                        <div class="product-price fw-bold mt-2" style="font-size: 14px; color: #000080;">
                                            ₱<?= number_format($product['product_price'], 2) ?>
                                        </div>
                                        <div class="product-location-time text-muted mt-1" style="font-size: 10px;">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <i class="fa-solid fa-location-dot" style="font-size: 8px;"></i>
                                                <span><?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-clock" style="font-size: 8px;"></i>
                                                <span><?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- BEVERAGES SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0" style="color: #000080;">
                        Beverages
                        <span class="ms-2" style="font-size: 12px;">
                            <?= count($beverageProducts) ?>
                        </span>
                    </h4>
                    <a href="#" class="text-primary text-decoration-none fw-semibold">See More ></a>
                </div>
                
                <!-- Beverages Products Row -->
                <div class="row g-3">
                    <?php if (empty($beverageProducts)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa-solid fa-mug-hot text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mb-0 text-muted">No beverage products available yet.</p>
                                <small class="text-muted">Add some beverage products to display here!</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($beverageProducts as $product): ?>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                <div class="product-card h-100 position-relative clickable-product-card" 
                                     data-product-id="<?= $product['product_id'] ?>"
                                     data-description="<?= htmlspecialchars($product['product_description'] ?? '') ?>"
                                     data-price="<?= $product['product_price'] ?>"
                                     data-location="<?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?>"
                                     data-time="<?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?>"
                                     data-category="<?= htmlspecialchars($product['product_category'] ?? 'Beverages') ?>"
                                     data-quantity="<?= $product['product_quantity'] ?>"
                                     data-image="<?= htmlspecialchars($product['product_image'] ?? '') ?>"
                                     data-group-members="<?= htmlspecialchars($product['group_members'] ?? '') ?>"
                                     data-sold-count="<?= $product['total_sold'] ?>">
                                    
                                    <!-- Sold Count Badge (Top Left) -->
                                    <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1" style="z-index: 10;">
                                        <span class="badge bg-dark text-white" style="font-size: 9px; border-radius: 12px;">
                                            <?= $product['total_sold'] ?> sold
                                        </span>
                                    </div>
                                    <!-- 3-dot menu -->
                                    <div class="position-absolute top-0 end-0 p-2" style="z-index: 15;">
                                        <div class="dropdown">
                                            <button class="btn btn-sm product-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px; width: 24px; height: 24px; border-radius: 4px; border: none; background: rgba(0,0,0,0.1); color: #6c757d; z-index: 16;">
                                                <i class="fa-solid fa-ellipsis-vertical" style="font-size: 12px;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 edit-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                       data-description="<?= htmlspecialchars($product['product_description']) ?>"
                                                       data-price="<?= $product['product_price'] ?>"
                                                       data-quantity="<?= $product['product_quantity'] ?>"
                                                       data-location="<?= htmlspecialchars($product['product_location'] ?? '') ?>"
                                                       data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>">
                                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                                        <i class="fa-regular fa-trash-can"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <?php if (!empty($product['product_image'])): ?>
                                        <div class="product-image-container">
                                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                                        </div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h6 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                                        <div class="product-price fw-bold mt-2" style="font-size: 14px; color: #000080;">
                                            ₱<?= number_format($product['product_price'], 2) ?>
                                        </div>
                                        <div class="product-location-time text-muted mt-1" style="font-size: 10px;">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <i class="fa-solid fa-location-dot" style="font-size: 8px;"></i>
                                                <span><?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-clock" style="font-size: 8px;"></i>
                                                <span><?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SNACKS SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0" style="color: #000080;">
                        Snacks
                        <span class="ms-2" style="font-size: 12px;">
                            <?= count($snackProducts) ?>
                        </span>
                    </h4>
                    <a href="#" class="text-primary text-decoration-none fw-semibold">See More ></a>
                </div>
                
                <!-- Snacks Products Row -->
                <div class="row g-3">
                    <?php if (empty($snackProducts)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa-solid fa-cookie text-muted mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mb-0 text-muted">No snack products available yet.</p>
                                <small class="text-muted">Add some snack products to display here!</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($snackProducts as $product): ?>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                <div class="product-card h-100 position-relative clickable-product-card" 
                                     data-product-id="<?= $product['product_id'] ?>"
                                     data-description="<?= htmlspecialchars($product['product_description'] ?? '') ?>"
                                     data-price="<?= $product['product_price'] ?>"
                                     data-location="<?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?>"
                                     data-time="<?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?>"
                                     data-category="<?= htmlspecialchars($product['product_category'] ?? 'Snacks') ?>"
                                     data-quantity="<?= $product['product_quantity'] ?>"
                                     data-image="<?= htmlspecialchars($product['product_image'] ?? '') ?>"
                                     data-group-members="<?= htmlspecialchars($product['group_members'] ?? '') ?>"
                                     data-sold-count="<?= $product['total_sold'] ?>">
                                    
                                    <!-- Sold Count Badge (Top Left) -->
                                    <div class="position-absolute top-0 start-0 p-2" style="z-index: 10;">
                                        <span class="badge bg-dark text-white" style="font-size: 9px; border-radius: 12px;">
                                            <?= $product['total_sold'] ?> sold
                                        </span>
                                    </div>
                                    
                                    <!-- 3-dot menu -->
                                    <div class="position-absolute top-0 end-0 p-2" style="z-index: 15;">
                                        <div class="dropdown">
                                            <button class="btn btn-sm product-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px; width: 24px; height: 24px; border-radius: 4px; border: none; background: rgba(0,0,0,0.1); color: #6c757d; z-index: 16;">
                                                <i class="fa-solid fa-ellipsis-vertical" style="font-size: 12px;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 edit-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                       data-description="<?= htmlspecialchars($product['product_description']) ?>"
                                                       data-price="<?= $product['product_price'] ?>"
                                                       data-quantity="<?= $product['product_quantity'] ?>"
                                                       data-location="<?= htmlspecialchars($product['product_location'] ?? '') ?>"
                                                       data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>">
                                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                                        <i class="fa-regular fa-trash-can"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <?php if (!empty($product['product_image'])): ?>
                                        <div class="product-image-container">
                                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                                        </div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h6 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                                        <div class="product-price fw-bold mt-2" style="font-size: 14px; color: #000080;">
                                            ₱<?= number_format($product['product_price'], 2) ?>
                                        </div>
                                        <div class="product-location-time text-muted mt-1" style="font-size: 10px;">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <i class="fa-solid fa-location-dot" style="font-size: 8px;"></i>
                                                <span><?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-clock" style="font-size: 8px;"></i>
                                                <span><?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SOLD PRODUCTS SECTION (Only show if there are sold out products) -->
        <?php if (!empty($soldProducts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-ban text-danger me-2"></i>
                        Sold Out Products
                        <span class="badge bg-danger ms-2" style="font-size: 12px;">
                            <?= count($soldProducts) ?>
                        </span>
                    </h4>
                    <a href="#" class="text-primary text-decoration-none fw-semibold">See More ></a>
                </div>
                
                <!-- Sold Products Row -->
                <div class="row g-3">
                    <?php if (empty($soldProducts)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa-solid fa-check-circle text-success mb-3" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mb-0 text-muted">No sold out products.</p>
                                <small class="text-muted">All products are currently in stock!</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($soldProducts as $product): ?>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                                <div class="product-card h-100 position-relative sold-out-card clickable-product-card" 
                                     data-product-id="<?= $product['product_id'] ?>"
                                     data-description="<?= htmlspecialchars($product['product_description'] ?? '') ?>"
                                     data-price="<?= $product['product_price'] ?>"
                                     data-location="<?= htmlspecialchars($product['product_location'] ?? 'USTP MOBOD') ?>"
                                     data-time="<?= ($product['preparation_time'] ?? 10) . ' ' . ($product['preparation_unit'] ?? 'minutes') ?>"
                                     data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>"
                                     data-quantity="<?= $product['product_quantity'] ?>"
                                     data-image="<?= htmlspecialchars($product['product_image'] ?? '') ?>"
                                     data-group-members="<?= htmlspecialchars($product['group_members'] ?? '') ?>"
                                     data-sold-count="<?= $product['total_sold'] ?>">
                                    
                                    <!-- Sold Count Badge (Top Left) -->
                                    <div class="position-absolute top-0 start-0 p-2" style="z-index: 10;">
                                        <span class="badge bg-dark text-white" style="font-size: 9px; border-radius: 12px;">
                                            <?= $product['total_sold'] ?> sold
                                        </span>
                                    </div>
                                    
                                    <!-- Sold Out Overlay -->
                                    <div class="sold-out-overlay">
                                        <div class="sold-out-badge">
                                            <i class="fa-solid fa-ban me-1"></i>
                                            SOLD OUT
                                        </div>
                                    </div>
                                    
                                    <!-- 3-dot menu -->
                                    <div class="position-absolute top-0 end-0 p-2" style="z-index: 15;">
                                        <div class="dropdown">
                                            <button class="btn btn-sm product-action-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px; width: 24px; height: 24px; border-radius: 4px; border: none; background: rgba(0,0,0,0.1); color: #6c757d; z-index: 16;">
                                                <i class="fa-solid fa-ellipsis-vertical" style="font-size: 12px;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 edit-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                       data-description="<?= htmlspecialchars($product['product_description']) ?>"
                                                       data-price="<?= $product['product_price'] ?>"
                                                       data-quantity="<?= $product['product_quantity'] ?>"
                                                       data-location="<?= htmlspecialchars($product['product_location'] ?? '') ?>"
                                                       data-category="<?= htmlspecialchars($product['product_category'] ?? 'Popular') ?>">
                                                        <i class="fa-solid fa-plus me-1"></i> Restock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger delete-product-btn" 
                                                       href="#" 
                                                       data-id="<?= $product['product_id'] ?>"
                                                       data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                                        <i class="fa-regular fa-trash-can"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Image -->
                                    <?php if (!empty($product['product_image'])): ?>
                                        <div class="product-image-container">
                                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                                        </div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h6 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                                        <div class="product-price fw-bold mt-2" style="font-size: 14px; color: #6c757d; text-decoration: line-through;">
                                            ₱<?= number_format($product['product_price'], 2) ?>
                                        </div>
                                        <div class="product-status text-danger mt-2" style="font-size: 11px; font-weight: 600;">
                                            <i class="fa-solid fa-exclamation-triangle me-1"></i>
                                            OUT OF STOCK
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PENDING STUDENT PRODUCTS SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-clock text-warning me-2"></i>
                        Pending Student Products
                    </h4>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadPendingProducts()">
                        <i class="fa-solid fa-refresh me-1"></i>Refresh
                    </button>
                </div>
                
                <!-- Pending Products Container -->
                <div id="pendingProductsContainer" class="row g-3">
                    <div class="col-12 text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading pending products...</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- CUSTOM STYLES -->
    <style>
        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        /* Clickable product card styling */
        .clickable-product-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .clickable-product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 2px solid #000080;
        }

        .clickable-product-card:active {
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .product-image-container {
            width: 100%;
            height: 120px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            position: relative;
            z-index: 1;
            border-radius: 12px 12px 0 0;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.2s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-image-placeholder {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 24px;
            position: relative;
            z-index: 1;
            border-radius: 12px 12px 0 0;
        }

        /* Alternative circular image style - uncomment to use */
        /*
        .product-image-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 15px auto 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            position: relative;
            z-index: 1;
        }

        .product-image-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 15px auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 20px;
            position: relative;
            z-index: 1;
        }
        */

        .product-info {
            padding: 15px;
            text-align: center;
            width: 100%;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c2c2c;
            margin-bottom: 0;
            line-height: 1.4;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        /* Add Product Button Styling (White with Navy Outline) */
        .btn-create-product {
            background: white;
            color: #000080;
            border: 2px solid #000080;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-create-product:hover {
            background: #000080;
            color: white;
            border-color: #000080;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 128, 0.3);
        }

        /* Filter Button Styling */
        .btn-filter {
            background: white;
            color: #6c757d;
            border: 1px solid #dee2e6;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-filter:hover {
            background: #f8f9fa;
            color: #495057;
            border-color: #adb5bd;
            transform: translateY(-1px);
        }

        .btn-filter.active {
            background: #000080;
            color: white;
            border-color: #000080;
            box-shadow: 0 2px 8px rgba(0, 0, 128, 0.2);
        }

        .btn-filter.active:hover {
            background: #000066;
            color: white;
            border-color: #000066;
        }

        @media (max-width: 768px) {
            .product-title {
                font-size: 13px;
            }

            .btn-create-product {
                padding: 0.6rem 1.2rem;
                font-size: 14px;
            }
            
            .product-info {
                padding: 12px;
            }

            .product-image-container,
            .product-image-placeholder {
                height: 100px;
            }

            .product-card {
                min-height: 180px;
            }

            /* Filter buttons mobile styling */
            .btn-filter {
                padding: 0.4rem 0.8rem;
                font-size: 12px;
            }

            /* Stack filter buttons on mobile */
            .d-flex.justify-content-start.align-items-center.gap-2.mb-3 {
                flex-wrap: wrap;
                gap: 0.5rem !important;
            }

            /* For circular mobile images - uncomment if using circular style */
            /*
            .product-image-container,
            .product-image-placeholder {
                width: 60px;
                height: 60px;
                margin: 10px auto 8px;
            }
            */
        }

        /* Sold Out Products Styling */
        .sold-out-card {
            opacity: 0.7;
            position: relative;
            filter: grayscale(30%);
        }

        .sold-out-card:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .sold-out-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(220, 53, 69, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 12px;
            pointer-events: none;
        }

        .sold-out-badge {
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
            transform: rotate(-15deg);
        }

        .sold-out-card .product-image {
            filter: grayscale(50%);
        }

        .sold-out-card .product-title {
            color: #6c757d;
        }

        /* Restock button styling */
        .sold-out-card .dropdown-item:first-child {
            color: #28a745;
            font-weight: 500;
        }

        .sold-out-card .dropdown-item:first-child:hover {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
    </style>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Search Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productsSearchInput');
    const searchForm = document.querySelector('.products-search-form');
    const productCards = document.querySelectorAll('.product-card');
    
    // Handle form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Handle real-time search as user types
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            performSearch();
        });
    }
    
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        productCards.forEach(card => {
            const title = card.querySelector('.product-title')?.textContent.toLowerCase() || '';
            const location = card.querySelector('.location')?.textContent.toLowerCase() || '';
            
            if (searchTerm === '' || title.includes(searchTerm) || location.includes(searchTerm)) {
                card.closest('.col-lg-2, .col-md-3, .col-sm-4, .col-6').style.display = '';
            } else {
                card.closest('.col-lg-2, .col-md-3, .col-sm-4, .col-6').style.display = 'none';
            }
        });
        
        // Hide section headers if all products in that section are hidden
        const sections = document.querySelectorAll('.row.mb-4');
        sections.forEach(section => {
            const visibleProducts = section.querySelectorAll('.col-lg-2:not([style*="display: none"]), .col-md-3:not([style*="display: none"]), .col-sm-4:not([style*="display: none"]), .col-6:not([style*="display: none"])');
            const sectionHeader = section.querySelector('h4');
            if (sectionHeader && visibleProducts.length === 0) {
                section.style.display = 'none';
            } else if (sectionHeader) {
                section.style.display = '';
            }
        });
    }

    // Filter Functionality
    const filterButtons = document.querySelectorAll('.btn-filter');
    let currentFilter = 'all';

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get filter value
            currentFilter = this.getAttribute('data-filter');
            
            // Apply filter
            applyFilter(currentFilter);
        });
    });

    function applyFilter(filter) {
        // Target only product sections, not the pending products section
        const sections = document.querySelectorAll('.row.mb-4');
        
        sections.forEach(section => {
            const sectionHeader = section.querySelector('h4');
            if (!sectionHeader) return;
            
            const sectionText = sectionHeader.textContent.toLowerCase();
            
            // Skip the pending products section from filtering
            if (sectionText.includes('pending student products')) {
                return;
            }
            
            if (filter === 'all') {
                // Show all product sections (All Products, Desserts, Beverages, Snacks, Sold Out)
                section.style.display = '';
            } else {
                // Show specific category sections
                if (sectionText.includes(filter.toLowerCase())) {
                    section.style.display = '';
                } else {
                    section.style.display = 'none';
                }
            }
        });
    }
});
</script>

<!-- Delete Product Confirmation Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<span id="deleteProductName" class="fw-bold"></span>"?</p>
                <p class="text-muted mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-delete-yes" id="confirmDeleteProductBtn">Yes</button>
                <button type="button" class="btn btn-delete-no" data-bs-dismiss="modal">No</button>
            </div>
        </div>
    </div>
</div>

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <!-- Header -->
            <div class="modal-header" style="background: #000080; border-bottom: none;">
                <h5 class="modal-title text-white fw-bold">Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Image at top (landscape rectangular) -->
            <div id="productDetailsImageContainer" style="position: relative;">
                <img id="productDetailsImg" src="" alt="" style="width: 100%; height: 500px; object-fit: cover; display: block;">
            </div>
            <!-- Details below -->
            <div class="modal-body p-4">
                <h4 id="productDetailsName" class="fw-bold text-dark mb-2"></h4>
                <p id="productDetailsDescription" class="text-muted mb-3" style="font-size: 14px;"></p>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-peso-sign" style="color: #000080;"></i>
                    <strong class="text-dark">Price:</strong>
                    <span id="productDetailsPrice" class="fw-bold" style="color: #000080;"></span>
                </div>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-location-dot" style="color: #000080;"></i>
                    <strong class="text-dark">Location:</strong>
                    <span id="productDetailsLocation" class="text-muted"></span>
                </div>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-clock" style="color: #000080;"></i>
                    <strong class="text-dark">Available:</strong>
                    <span id="productDetailsTime" class="text-muted"></span>
                </div>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-tag" style="color: #000080;"></i>
                    <strong class="text-dark">Category:</strong>
                    <span id="productDetailsCategory" class="badge" style="background: #000080;"></span>
                </div>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked" style="color: #000080;"></i>
                    <strong class="text-dark">Quantity:</strong>
                    <span id="productDetailsQuantity" class="fw-bold"></span>
                </div>
                <div id="productDetailsGroupMembers" class="mb-2" style="display: none;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-users" style="color: #000080; margin-top: 4px;"></i>
                        <div>
                            <strong class="text-dark d-block mb-1">Group Members:</strong>
                            <div id="productDetailsGroupMembersList" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>
                <div id="productDetailsStatus" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Create Product Modal -->
<div class="modal fade" id="createProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createProductForm" onsubmit="return false;">
                <input type="hidden" name="product_id" id="editProductId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="product_name" id="editProductName" class="form-control" placeholder="Enter product name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="product_description" id="editProductDescription" class="form-control" placeholder="Enter product description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="product_category" id="editProductCategory" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Popular">Popular</option>
                            <option value="Desserts">Desserts</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Snacks">Snacks</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold">Price (₱)</label>
                            <input type="number" name="product_price" id="editProductPrice" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold">Quantity</label>
                            <input type="number" name="product_quantity" id="editProductQuantity" class="form-control" placeholder="0" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="product_location" id="editProductLocation" class="form-control" placeholder="e.g., USTP MOBOD" value="USTP MOBOD">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Preparation Time</label>
                        <div class="preparation-time-container">
                            <div class="input-group">
                                <input type="number" name="preparation_time" id="editPreparationTime" class="form-control" 
                                       placeholder="Enter time" value="10" min="1" max="999" style="max-width: 120px;">
                                <select name="preparation_unit" id="editPreparationUnit" class="form-select" style="max-width: 120px;">
                                    <option value="minutes" selected>Minutes</option>
                                    <option value="hours">Hours</option>
                                </select>
                            </div>
                            <div class="form-text">How long does it take to prepare this product?</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" name="product_image" id="editProductImage" class="form-control" accept="image/*">
                        <div class="form-text">Upload an image for your product (optional)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-save-product w-100" id="productFormSubmitBtn">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional Modal Styles -->
<style>
.btn-save-product {
    background: #000080;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-save-product:hover {
    background: #000066;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 128, 0.3);
}

.modal-header {
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.modal-title {
    color: #000080;
    font-weight: bold;
}

.form-label {
    color: #2c2c2c;
    margin-bottom: 0.5rem;
}

.form-control:focus {
    border-color: #000080;
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 128, 0.25);
}

/* Time Picker Styling */
.time-picker-container .input-group {
    justify-content: center;
}

.time-picker-container .form-select {
    text-align: center;
    font-weight: 500;
}

.time-picker-container .input-group-text {
    background: #f8f9fa;
    border-color: #dee2e6;
    font-weight: bold;
    color: #000080;
}

.time-picker-container .form-select:focus {
    border-color: #000080;
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 128, 0.25);
}

/* Delete Modal Buttons */
.btn-delete-yes {
    background: #dc3545;
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    min-width: 80px;
}

.btn-delete-yes:hover {
    background: #c82333;
    color: white;
}

.btn-delete-no {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    min-width: 80px;
}

.btn-delete-no:hover {
    background: #5a6268;
    color: white;
}

/* Product Action Toggle */
.product-card .position-absolute {
    z-index: 15 !important;
}

.product-action-toggle {
    z-index: 16 !important;
    position: relative !important;
    pointer-events: auto !important;
}

.product-action-toggle:hover {
    background: rgba(0,0,0,0.2) !important;
}

/* Ensure dropdown menu is above everything */
.dropdown-menu {
    z-index: 1050 !important;
}

/* Prevent image from blocking clicks */
.product-image-container,
.product-image-placeholder {
    /* pointer-events: none; */
}

.product-image {
    pointer-events: none;
}
</style>

<!-- Product Form Handler Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const productForm = document.getElementById('createProductForm');
    const productModal = document.getElementById('createProductModal');
    let deleteProductId = null;
    
    // Handle Edit Product button clicks
    document.querySelectorAll('.edit-product-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const productId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const price = this.getAttribute('data-price');
            const quantity = this.getAttribute('data-quantity');
            const location = this.getAttribute('data-location') || '';
            const category = this.getAttribute('data-category') || 'Popular';
            const prepTime = this.getAttribute('data-prep-time') || '10';
            const prepUnit = this.getAttribute('data-prep-unit') || 'minutes';
            
            // Populate form with product data
            document.getElementById('editProductId').value = productId;
            document.getElementById('editProductName').value = name;
            document.getElementById('editProductDescription').value = description;
            document.getElementById('editProductPrice').value = price;
            document.getElementById('editProductQuantity').value = quantity;
            document.getElementById('editProductLocation').value = location;
            document.getElementById('editProductCategory').value = category;
            
            // Update preparation time display
            setPreparationTime(prepTime, prepUnit);
            
            // Change modal title and button text
            document.getElementById('productModalTitle').textContent = 'Edit Product';
            document.getElementById('productFormSubmitBtn').textContent = 'Update Product';
            
            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('createProductModal'));
            editModal.show();
        });
    });
    
    // Handle Delete Product button clicks
    document.querySelectorAll('.delete-product-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            deleteProductId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            
            // Set product name in modal
            document.getElementById('deleteProductName').textContent = productName;
            
            // Show delete confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            deleteModal.show();
        });
    });
    
    // Handle Delete Confirmation
    const confirmDeleteBtn = document.getElementById('confirmDeleteProductBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!deleteProductId) return;
            
            const formData = new FormData();
            formData.append('product_id', deleteProductId);
            
            this.disabled = true;
            this.textContent = 'Deleting...';
            
            fetch('backend/afprotechs_delete_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal first
                    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteProductModal'));
                    deleteModal.hide();
                    
                    // Show success alert
                    alert('Product has been deleted successfully!');
                    
                    // Reload page
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.textContent = 'Yes';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes';
            });
        });
    }
    
    if (productForm) {
        // Remove any existing event listeners
        productForm.removeEventListener('submit', handleFormSubmit);
        
        // Add the event listener
        productForm.addEventListener('submit', handleFormSubmit);
    }
    
    function handleFormSubmit(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Form submitted');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            const productId = document.getElementById('editProductId').value;
            const isEdit = productId && productId !== '';
            
            console.log('Is Edit:', isEdit);
            
            // Validate form
            const productName = document.getElementById('editProductName').value.trim();
            const productDescription = document.getElementById('editProductDescription').value.trim();
            const productPrice = parseFloat(document.getElementById('editProductPrice').value);
            const productQuantity = parseInt(document.getElementById('editProductQuantity').value);
            const productCategory = document.getElementById('editProductCategory').value;
            
            if (!productName) {
                alert('Please enter a product name');
                return;
            }
            
            if (!productDescription) {
                alert('Please enter a product description');
                return;
            }
            
            if (!productCategory) {
                alert('Please select a product category');
                return;
            }
            
            if (isNaN(productPrice) || productPrice <= 0) {
                alert('Please enter a valid price greater than 0');
                return;
            }
            
            if (isNaN(productQuantity) || productQuantity < 0) {
                alert('Please enter a valid quantity (0 or greater)');
                return;
            }
            
            // Disable submit button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (isEdit ? 'Updating...' : 'Creating Product...');
            
            // Add loading overlay to modal
            const modalBody = document.querySelector('#createProductModal .modal-body');
            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'loadingOverlay';
            loadingOverlay.innerHTML = `
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2"></div>
                        <div><strong>${isEdit ? 'Updating Product...' : 'Creating Product...'}</strong></div>
                        <small class="text-muted">${isEdit ? 'Please wait...' : 'Please wait...'}</small>
                    </div>
                </div>
            `;
            modalBody.style.position = 'relative';
            modalBody.appendChild(loadingOverlay);
            
            // Create FormData object
            const formData = new FormData(productForm);
            
            // Choose the correct endpoint
            const apiUrl = isEdit ? 'backend/afprotechs_update_product.php' : 'backend/afprotechs_create_product.php';
            
            // Prepare data
            let requestData;
            let headers = {};
            if (isEdit) {
                requestData = formData;
            } else {
                // For new products, create directly as admin product
                requestData = formData;
            }
            
            // Submit to backend
            fetch(apiUrl, {
                method: 'POST',
                body: requestData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    let successMessage;
                    if (isEdit) {
                        successMessage = 'Product updated successfully!';
                    } else {
                        successMessage = 'Product created successfully!';
                    }
                    alert(successMessage);
                    
                    // Reset form and close modal
                    productForm.reset();
                    document.getElementById('editProductId').value = '';
                    document.getElementById('editProductLocation').value = 'USTP MOBOD';
                    document.getElementById('editProductCategory').value = '';
                    
                    // Reset preparation time display
                    setPreparationTime(10, 'minutes');
                    document.getElementById('productModalTitle').textContent = 'Add New Product';
                    document.getElementById('productFormSubmitBtn').textContent = 'Add Product';
                    
                    // Close modal after a short delay to show success message
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(productModal);
                        if (modal) {
                            modal.hide();
                        }
                    }, 1000);
                    
                    // Reload pending products section instead of full page for new products
                    if (!isEdit) {
                        loadPendingProducts();
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to save product'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Network Error\n\nFailed to submit product. Please check your connection and try again.');
            })
            .finally(() => {
                // Remove loading overlay
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.remove();
                }
                
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }
    
    // Reset form when modal is closed
    if (productModal) {
        productModal.addEventListener('hidden.bs.modal', function() {
            productForm.reset();
            document.getElementById('editProductId').value = '';
            document.getElementById('editProductLocation').value = 'USTP MOBOD';
            document.getElementById('editProductCategory').value = '';
            
            // Reset preparation time display
            setPreparationTime(10, 'minutes');
            document.getElementById('productModalTitle').textContent = 'Add New Product';
            document.getElementById('productFormSubmitBtn').textContent = 'Add Product';
        });
    }
    
    // Prevent dropdown clicks from triggering card click
    document.querySelectorAll('.product-action-toggle').forEach(btn => {
        btn.addEventListener('click', e => e.stopPropagation());
    });
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', e => e.stopPropagation());
    });
    
    // Preparation Time Functionality
    function updatePreparationTime() {
        // No additional processing needed for preparation time
    }
    
    function setPreparationTime(time, unit) {
        document.getElementById('editPreparationTime').value = time || 10;
        document.getElementById('editPreparationUnit').value = unit || 'minutes';
        updatePreparationTime();
    }
    
    // Add event listeners for preparation time changes
    document.getElementById('editPreparationTime').addEventListener('change', updatePreparationTime);
    document.getElementById('editPreparationUnit').addEventListener('change', updatePreparationTime);
    
    // Load pending products on page load
    loadPendingProducts();
    
    // Initialize preparation time
    updatePreparationTime();
    
    // Product Details Functionality
    function showProductDetails(product) {
        document.getElementById('productDetailsName').textContent = product.name;
        document.getElementById('productDetailsDescription').textContent = product.description;
        document.getElementById('productDetailsPrice').textContent = '₱' + parseFloat(product.price).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('productDetailsLocation').textContent = product.location || 'USTP MOBOD';
        document.getElementById('productDetailsTime').textContent = product.time;
        document.getElementById('productDetailsCategory').textContent = product.category || 'Popular';
        
        // Handle quantity
        const quantity = parseInt(product.quantity) || 0;
        const quantityElement = document.getElementById('productDetailsQuantity');
        if (quantity <= 0) {
            quantityElement.innerHTML = '<span class="text-danger fw-bold">Out of Stock</span>';
        } else {
            quantityElement.innerHTML = `<span class="text-success">${quantity} available</span>`;
        }
        
        // Handle image - format base64 if needed
        const imgElement = document.getElementById('productDetailsImg');
        const imgContainer = document.getElementById('productDetailsImageContainer');
        if (product.image && product.image.trim().length > 32) {
            let imageUrl = product.image.trim();
            // Check if it's already a data URI or HTTP URL
            if (!imageUrl.startsWith('data:') && !imageUrl.startsWith('http')) {
                // Check if it's a file path (relative or absolute)
                if (imageUrl.startsWith('/') || imageUrl.startsWith('uploads/') || imageUrl.startsWith('images/') || imageUrl.startsWith('img/')) {
                    // It's a file path, use as-is (will resolve relative to current path)
                    // No change needed
                } else {
                    // It's base64, add data URI prefix
                    if (imageUrl.substring(0, 4) === '/9j/') {
                        imageUrl = 'data:image/jpeg;base64,' + imageUrl;
                    } else if (imageUrl.substring(0, 22) === 'iVBORw0KGgoAAAANSUhEUg') {
                        imageUrl = 'data:image/png;base64,' + imageUrl;
                    } else {
                        // Default to JPEG
                        imageUrl = 'data:image/jpeg;base64,' + imageUrl;
                    }
                }
            }
            imgElement.src = imageUrl;
            imgElement.alt = product.name;
            imgElement.onerror = function() {
                // If image fails to load, show placeholder
                this.style.display = 'none';
                imgContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center" style="height: 500px; background: #f8f9fa;"><i class="fa-solid fa-image text-muted" style="font-size: 64px;"></i></div>';
            };
            imgContainer.style.display = 'block';
        } else {
            // Show placeholder if no image
            imgContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center" style="height: 500px; background: #f8f9fa;"><i class="fa-solid fa-image text-muted" style="font-size: 64px;"></i></div>';
            imgContainer.style.display = 'block';
        }
        
        // Handle group members
        const groupMembersDiv = document.getElementById('productDetailsGroupMembers');
        const groupMembersList = document.getElementById('productDetailsGroupMembersList');
        if (product.groupMembers && product.groupMembers.trim()) {
            // Parse group members (comma or newline separated)
            const members = product.groupMembers.split(/[,\n]/)
                .map(m => m.trim().replace(/^@+/, '')) // remove leading @
                .filter(m => m);
            if (members.length > 0) {
                groupMembersList.innerHTML = '';
                members.forEach(member => {
                    // Get initials from name
                    const nameParts = member.split(' ').filter(p => p);
                    let initials = '';
                    if (nameParts.length >= 1) {
                        // Only first letter of first name
                        initials = nameParts[0][0].toUpperCase();
                    }
                    
                    groupMembersList.innerHTML += `
                        <span class="badge bg-light text-dark border" style="padding: 6px 12px; font-size: 12px;">
                            <span class="fw-bold text-primary me-1">${initials}</span>
                            ${escapeHtml(member)}
                        </span>
                    `;
                });
                groupMembersDiv.style.display = 'block';
            } else {
                groupMembersDiv.style.display = 'none';
            }
        } else {
            groupMembersDiv.style.display = 'none';
        }
        
        // Handle status
        const statusDiv = document.getElementById('productDetailsStatus');
        if (parseInt(product.quantity) <= 0) {
            statusDiv.innerHTML = '<span class="badge bg-danger">SOLD OUT</span>';
        } else {
            statusDiv.innerHTML = '<span class="badge bg-success">Available</span>';
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
        modal.show();
    }
    
    // Add click listeners to product cards
    document.querySelectorAll('.clickable-product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on dropdown menu or action buttons
            if (e.target.closest('.dropdown') || 
                e.target.closest('.product-action-toggle') || 
                e.target.closest('.dropdown-menu') ||
                e.target.closest('.btn')) {
                return;
            }
            
            const productId = this.getAttribute('data-product-id');
            
            // Get product data from the card
            const productData = {
                name: this.querySelector('.product-title').textContent,
                description: this.getAttribute('data-description') || 'No description available',
                price: this.getAttribute('data-price') || '0',
                location: this.getAttribute('data-location') || 'USTP MOBOD',
                time: this.getAttribute('data-time') || '10 minutes',
                category: this.getAttribute('data-category') || 'Popular',
                quantity: this.getAttribute('data-quantity') || '0',
                image: this.getAttribute('data-image') || null,
                groupMembers: this.getAttribute('data-group-members') || ''
            };
            
            showProductDetails(productData);
        });
    });
    
    // Prevent dropdown clicks from triggering card click
    document.querySelectorAll('.product-action-toggle').forEach(btn => {
        btn.addEventListener('click', e => e.stopPropagation());
    });
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', e => e.stopPropagation());
    });
    
    // Preparation Time Functionality
    function updatePreparationTime() {
        // No additional processing needed for preparation time
    }
    
    function setPreparationTime(time, unit) {
        document.getElementById('editPreparationTime').value = time || 10;
        document.getElementById('editPreparationUnit').value = unit || 'minutes';
        updatePreparationTime();
    }
    
    // Add event listeners for preparation time changes
    document.getElementById('editPreparationTime').addEventListener('change', updatePreparationTime);
    document.getElementById('editPreparationUnit').addEventListener('change', updatePreparationTime);
});

// Pending Products Management Functions
function loadPendingProducts() {
    const container = document.getElementById('pendingProductsContainer');
    
    // Show loading
    container.innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading pending products...</p>
        </div>
    `;
    
    fetch('backend/afprotechs_get_student_products.php?status=pending')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPendingProducts(data.data);
            } else {
                container.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fa-solid fa-exclamation-triangle text-warning mb-3" style="font-size: 48px;"></i>
                        <p class="mb-0 text-muted">Error loading pending products</p>
                        <small class="text-muted">${data.message}</small>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading pending products:', error);
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fa-solid fa-exclamation-triangle text-danger mb-3" style="font-size: 48px;"></i>
                    <p class="mb-0 text-muted">Failed to load pending products</p>
                    <small class="text-muted">Please try again later</small>
                </div>
            `;
        });
}

function displayPendingProducts(products) {
    const container = document.getElementById('pendingProductsContainer');
    
    if (!products || products.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-4">
                <i class="fa-solid fa-check-circle text-success mb-3" style="font-size: 48px;"></i>
                <p class="mb-0 text-muted">No pending products</p>
                <small class="text-muted">All student products have been reviewed</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    products.forEach(product => {
        // Format image URL - handle base64 images
        // Clean and check if product_image is really usable
        const rawImg = product.product_image ? String(product.product_image).trim() : '';
        let imageUrl = '';
        if (rawImg.length > 32) { // sensible min-length for base64
            if (rawImg.startsWith('data:') || rawImg.startsWith('http')) {
                imageUrl = rawImg;
            } else {
                imageUrl = 'data:image/jpeg;base64,' + rawImg;
            }
        } else {
            imageUrl = '';
        }
            
        html += `
            <div class="col-lg-4 col-md-6 col-12">
                <div class="card h-100 border-warning" style="border-width: 2px;">
                    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">${escapeHtml(product.product_name)}</h6>
                        </div>
                        <span class="badge bg-warning text-dark">PENDING</span>
                    </div>
                    
                    ${imageUrl ? `
                        <div style="height: 300px; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                            <img src="${imageUrl}" 
                                 alt="${escapeHtml(product.product_name)}" 
                                 class="card-img-top" 
                                 style="max-height: 100%; max-width: 100%; width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'d-flex align-items-center justify-content-center h-100\\'><i class=\\'fa-solid fa-image text-muted\\' style=\\'font-size: 48px;\\'></i></div>'">
                        </div>
                    ` : `
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                            <i class="fa-solid fa-image text-muted" style="font-size: 48px;"></i>
                        </div>
                    `}
                    
                    <div class="card-body">
                        <p class="card-text text-muted mb-2" style="font-size: 14px;">
                            ${escapeHtml(product.product_description)}
                        </p>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Price</small>
                                <strong class="text-primary">₱${parseFloat(product.product_price).toFixed(2)}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Quantity</small>
                                <strong>${product.product_quantity}</strong>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted d-block">Location</small>
                            <span style="font-size: 14px;">${escapeHtml(product.product_location || 'Not specified')}</span>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Preparation Time</small>
                            <span style="font-size: 14px;">${product.preparation_time || 10} ${product.preparation_unit || 'minutes'}</span>
                        </div>
                        
                        ${product.group_members && product.group_members.trim() ? `
                        <div class="mb-3">
                            <small class="text-muted d-block">Group Members</small>
                            <span style="font-size: 14px;">
                                ${escapeHtml(
                                    product.group_members
                                        .split(',')
                                        .map(m => m.trim().replace(/^@+/, ''))
                                        .filter(m => m.length > 0)
                                        .join(', ')
                                )}
                            </span>
                        </div>
                        ` : ''}
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-fill" 
                                    onclick="approveProduct('${product.product_id}', '${escapeHtml(product.product_name)}')">
                                <i class="fa-solid fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-danger btn-sm flex-fill" 
                                    onclick="showRejectModal('${product.product_id}', '${escapeHtml(product.product_name)}')">
                                <i class="fa-solid fa-times me-1"></i>Reject
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fa-solid fa-calendar me-1"></i>
                            Submitted: ${new Date(product.created_at).toLocaleDateString()}
                        </small>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function approveProduct(productId, productName) {
    if (!confirm(`Are you sure you want to approve "${productName}"?`)) {
        return;
    }
    
    const adminId = 'admin'; // You might want to get this from session or user data
    
    fetch('backend/afprotechs_approve_student_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            action: 'approve',
            admin_id: adminId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Product "${productName}" has been approved successfully!`);
            loadPendingProducts(); // Reload the pending products
            location.reload(); // Reload page to show approved product in main sections
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error approving product:', error);
        alert('An error occurred while approving the product. Please try again.');
    });
}

function showRejectModal(productId, productName) {
    const reason = prompt(`Please provide a reason for rejecting "${productName}":`);
    if (reason === null) return; // User cancelled
    
    if (reason.trim() === '') {
        alert('Please provide a reason for rejection.');
        return;
    }
    
    rejectProduct(productId, productName, reason.trim());
}

function rejectProduct(productId, productName, reason) {
    const adminId = 'admin'; // You might want to get this from session or user data
    
    fetch('backend/afprotechs_approve_student_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            action: 'reject',
            admin_id: adminId,
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Product "${productName}" has been rejected.`);
            loadPendingProducts(); // Reload the pending products
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error rejecting product:', error);
        alert('An error occurred while rejecting the product. Please try again.');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
