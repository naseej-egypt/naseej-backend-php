<?php
/**
 * Naseej Admin - Update Product
 * POST: /products/update.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id, name, description, price, discount_price (optional), stock, category_id, is_active, colors[], sizes[]
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
    $productId = filterRequest("product_id");
    $name = filterRequest("name");
    $description = filterRequest("description");
    $price = filterRequest("price");
    $discountPrice = filterRequest("discount_price");
    $stock = filterRequest("stock");
    $categoryId = filterRequest("category_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    // Get colors and sizes arrays
    $colors = isset($_POST['colors']) ? $_POST['colors'] : [];
    $sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    
    // Validate required fields
    $errors = validateRequired(['product_id', 'name', 'description', 'price', 'stock', 'category_id']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT id, name FROM products WHERE id = ?");
    $checkStmt->execute([$productId]);
    $existingProduct = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingProduct) {
        sendResponse(STATUS_FAIL, null, "Product not found");
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
    
    // Update product
    $updateFields = [
        "name = ?",
        "description = ?",
        "price = ?",
        "discount_price = ?",
        "stock = ?",
        "category_id = ?",
        "colors = ?",
        "sizes = ?",
        "updated_at = NOW()"
    ];
    
    $params = [
        $name,
        $description,
        (float)$price,
        !empty($discountPrice) ? (float)$discountPrice : null,
        (int)$stock,
        $categoryId,
        json_encode($cleanColors),
        json_encode($cleanSizes)
    ];
    
    if ($isActive !== null) {
        $updateFields[] = "is_active = ?";
        $params[] = $isActive;
    }
    
    $params[] = $productId;
    
    $stmt = $conn->prepare("
        UPDATE products 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    // Log activity
    logActivity($conn, $admin['admin_id'], 'update', 'product', $productId, "Updated product: $name");
    
    // Get updated product
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
            p.updated_at
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
        'updated_at' => formatDateTime($product['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Product updated successfully");
    
} catch (PDOException $e) {
    error_log("Update product error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update product");
}
?>