<?php
/**
 * Naseej User App - Add to Cart
 * POST: /cart/add_to_cart.php
 * Body: product_id, quantity, color (optional), size (optional)
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get and validate input
    $productId = filterRequest("product_id");
    $quantity = filterRequest("quantity") ?: '1';
    $color = filterRequest("color");
    $size = filterRequest("size");
    
    // Validate required fields
    if (empty($productId)) {
        sendResponse(STATUS_FAIL, null, "Product ID is required");
    }
    
    // Validate quantity
    $quantity = (int)$quantity;
    if ($quantity < 1) {
        sendResponse(STATUS_FAIL, null, "Quantity must be at least 1");
    }
    
    // Check if product exists and is active
    $productStmt = $conn->prepare("
        SELECT id, name, price, discount_price, stock, images, colors, sizes, is_active
        FROM products 
        WHERE id = ?
    ");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    if (!$product['is_active']) {
        sendResponse(STATUS_FAIL, null, "Product is not available");
    }
    
    // Check stock availability
    if ($product['stock'] < $quantity) {
        sendResponse(STATUS_FAIL, null, "Insufficient stock. Only {$product['stock']} items available");
    }
    
    // Validate color and size if provided
    if ($color) {
        $availableColors = parseJsonArray($product['colors']);
        if (!in_array($color, $availableColors)) {
            sendResponse(STATUS_FAIL, null, "Selected color is not available");
        }
    }
    
    if ($size) {
        $availableSizes = parseJsonArray($product['sizes']);
        if (!in_array($size, $availableSizes)) {
            sendResponse(STATUS_FAIL, null, "Selected size is not available");
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if item already exists in cart (same product, color, size)
    $checkStmt = $conn->prepare("
        SELECT id, quantity 
        FROM cart 
        WHERE customer_id = ? AND product_id = ?
    ");
    $checkStmt->execute([$customer['customer_id'], $productId]);
    $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
        // Update quantity
        $newQuantity = $existingItem['quantity'] + $quantity;
        
        // Check stock for new quantity
        if ($product['stock'] < $newQuantity) {
            $conn->rollBack();
            sendResponse(STATUS_FAIL, null, "Cannot add more. Maximum available: {$product['stock']}");
        }
        
        $updateStmt = $conn->prepare("
            UPDATE cart 
            SET quantity = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$newQuantity, $existingItem['id']]);
        $cartItemId = $existingItem['id'];
        $message = "Cart updated successfully";
    } else {
        // Insert new item
        $insertStmt = $conn->prepare("
            INSERT INTO cart (customer_id, product_id, quantity, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([$customer['customer_id'], $productId, $quantity]);
        $cartItemId = $conn->lastInsertId();
        $message = "Item added to cart successfully";
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get updated cart item details
    $getStmt = $conn->prepare("
        SELECT 
            c.id,
            c.product_id,
            c.quantity,
            p.name as product_name,
            p.price,
            p.discount_price,
            p.images,
            p.colors,
            p.sizes,
            c.created_at,
            c.updated_at
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.id = ?
    ");
    $getStmt->execute([$cartItemId]);
    $cartItem = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $images = parseJsonArray($cartItem['images']);
    $imageUrl = !empty($images) ? getImageUrl($images[0], 'products') : null;
    
    $finalPrice = $cartItem['discount_price'] ?: $cartItem['price'];
    
    $responseData = [
        'cart_item_id' => (string)$cartItem['id'],
        'product_id' => (string)$cartItem['product_id'],
        'product_name' => $cartItem['product_name'],
        'product_image' => $imageUrl,
        'quantity' => (int)$cartItem['quantity'],
        'price' => (float)$cartItem['price'],
        'discount_price' => $cartItem['discount_price'] ? (float)$cartItem['discount_price'] : null,
        'final_price' => (float)$finalPrice,
        'subtotal' => (float)($finalPrice * $cartItem['quantity']),
        'created_at' => formatDateTime($cartItem['created_at']),
        'updated_at' => formatDateTime($cartItem['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, $message);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Add to cart error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add item to cart");
} finally {
    $db->disconnect();
}
?>