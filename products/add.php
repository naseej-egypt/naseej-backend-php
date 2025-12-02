<?php
/**
 * Naseej Admin - Add New Product
 * POST: /products/add.php
 * Headers: Authorization: Bearer <token>
 * Body: name, description, price, discount_price (optional), stock, category_id, is_active, colors[], sizes[]
 * Files: file[] (multiple images)
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get and validate input
    $name = filterRequest("name");
    $description = filterRequest("description");
    $price = filterRequest("price");
    $discountPrice = filterRequest("discount_price");
    $stock = filterRequest("stock");
    $categoryId = filterRequest("category_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Get colors and sizes arrays
    $colors = isset($_POST['colors']) ? $_POST['colors'] : [];
    $sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    
    // Validate required fields
    $errors = validateRequired(['name', 'description', 'price', 'stock', 'category_id']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate name length
    if (strlen($name) < 3 || strlen($name) > 200) {
        sendResponse(STATUS_FAIL, null, "Product name must be between 3 and 200 characters");
    }
    
    // Validate price
    if (!is_numeric($price) || (float)$price <= 0) {
        sendResponse(STATUS_FAIL, null, "Price must be a positive number");
    }
    
    // Validate discount price if provided
    if (!empty($discountPrice)) {
        if (!is_numeric($discountPrice) || (float)$discountPrice <= 0) {
            sendResponse(STATUS_FAIL, null, "Discount price must be a positive number");
        }
        if ((float)$discountPrice >= (float)$price) {
            sendResponse(STATUS_FAIL, null, "Discount price must be less than regular price");
        }
    }
    
    // Validate stock
    if (!is_numeric($stock) || (int)$stock < 0) {
        sendResponse(STATUS_FAIL, null, "Stock must be a non-negative integer");
    }
    
    // Validate category exists
    $catStmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
    $catStmt->execute([$categoryId]);
    if (!$catStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Invalid category ID");
    }
    
    // Validate colors (at least one required)
    if (!is_array($colors) || empty($colors)) {
        sendResponse(STATUS_FAIL, null, "At least one color must be selected");
    }
    
    // Validate sizes (at least one required)
    if (!is_array($sizes) || empty($sizes)) {
        sendResponse(STATUS_FAIL, null, "At least one size must be selected");
    }
    
    // Clean and validate colors
    $cleanColors = [];
    foreach ($colors as $color) {
        $color = trim($color);
        if (!empty($color) && strlen($color) <= 50) {
            $cleanColors[] = $color;
        }
    }
    
    if (empty($cleanColors)) {
        sendResponse(STATUS_FAIL, null, "Invalid colors provided");
    }
    
    // Clean and validate sizes
    $cleanSizes = [];
    foreach ($sizes as $size) {
        $size = trim($size);
        if (!empty($size) && strlen($size) <= 50) {
            $cleanSizes[] = $size;
        }
    }
    
    if (empty($cleanSizes)) {
        sendResponse(STATUS_FAIL, null, "Invalid sizes provided");
    }
    
    // Handle image uploads
    $uploadedImages = [];
    if (isset($_FILES['file'])) {
        $fileCount = is_array($_FILES['file']['name']) ? count($_FILES['file']['name']) : 1;
        
        if ($fileCount > 5) {
            sendResponse(STATUS_FAIL, null, "Maximum 5 images allowed");
        }
        
        if (is_array($_FILES['file']['name'])) {
            // Multiple files
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                    // Create temporary single file array
                    $_FILES['temp_file'] = [
                        'name' => $_FILES['file']['name'][$i],
                        'type' => $_FILES['file']['type'][$i],
                        'tmp_name' => $_FILES['file']['tmp_name'][$i],
                        'error' => $_FILES['file']['error'][$i],
                        'size' => $_FILES['file']['size'][$i]
                    ];
                    
                    $imageName = uploadImage('temp_file', PRODUCTS_DIR);
                    if ($imageName !== 'fail') {
                        $uploadedImages[] = $imageName;
                    }
                }
            }
        } else {
            // Single file
            $imageName = uploadImage('file', PRODUCTS_DIR);
            if ($imageName !== 'fail') {
                $uploadedImages[] = $imageName;
            }
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert product
        $stmt = $conn->prepare("
            INSERT INTO products 
            (name, description, price, discount_price, stock, category_id, images, colors, sizes, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $imagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : json_encode([]);
        $colorsJson = json_encode($cleanColors);
        $sizesJson = json_encode($cleanSizes);
        $discountPriceValue = !empty($discountPrice) ? (float)$discountPrice : null;
        
        $stmt->execute([
            $name,
            $description,
            (float)$price,
            $discountPriceValue,
            (int)$stock,
            $categoryId,
            $imagesJson,
            $colorsJson,
            $sizesJson,
            $isActive
        ]);
        
        $productId = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity($conn, $admin['admin_id'], 'create', 'product', $productId, "Created product: $name");
        
        // Get created product
        $getStmt = $conn->prepare("
            SELECT 
                p.id,
                p.name,
                p.description,
                p.price,
                p.discount_price,
                p.stock,
                p.category_id,
                c.name as category_name,
                p.images,
                p.colors,
                p.sizes,
                p.is_active,
                p.created_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $getStmt->execute([$productId]);
        $product = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        // Parse images
        $images = parseJsonArray($product['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        // Parse colors and sizes
        $productColors = parseJsonArray($product['colors']);
        $productSizes = parseJsonArray($product['sizes']);
        
        $responseData = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'stock' => (int)$product['stock'],
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'images' => $imageUrls,
            'colors' => $productColors,
            'sizes' => $productSizes,
            'is_active' => (bool)$product['is_active'],
            'created_at' => formatDateTime($product['created_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Product added successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded images
        foreach ($uploadedImages as $imageName) {
            deleteFile(PRODUCTS_DIR, $imageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Add product error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add product");
}
?>