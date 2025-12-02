<?php
/**
 * Naseej User App - Update Cart Item
 * POST: /cart/update_cart_item.php
 * Body: cart_id, quantity
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
    $cartId = filterRequest("cart_id");
    $quantity = filterRequest("quantity");
    
    // Validate required fields
    if (empty($cartId) || empty($quantity)) {
        sendResponse(STATUS_FAIL, null, "Cart ID and quantity are required");
    }
    
    // Validate quantity
    $quantity = (int)$quantity;
    if ($quantity < 1) {
        sendResponse(STATUS_FAIL, null, "Quantity must be at least 1");
    }
    
    // Verify cart item belongs to customer
    $checkStmt = $conn->prepare("
        SELECT c.id, c.product_id, c.quantity as current_quantity, p.stock, p.name, p.is_active
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.id = ? AND c.customer_id = ?
    ");
    $checkStmt->execute([$cartId, $customer['customer_id']]);
    $cartItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cartItem) {
        sendResponse(STATUS_FAIL, null, "Cart item not found");
    }
    
    // Check if product is still active
    if (!$cartItem['is_active']) {
        sendResponse(STATUS_FAIL, null, "Product is no longer available");
    }
    
    // Check stock availability
    if ($cartItem['stock'] < $quantity) {
        sendResponse(STATUS_FAIL, null, "Insufficient stock. Only {$cartItem['stock']} items available");
    }
    
    // Update cart item
    $updateStmt = $conn->prepare("
        UPDATE cart 
        SET quantity = ?, updated_at = NOW()
        WHERE id = ? AND customer_id = ?
    ");
    $updateStmt->execute([$quantity, $cartId, $customer['customer_id']]);
    
    // Get updated cart item with full details
    $getStmt = $conn->prepare("
        SELECT 
            c.id as cart_id,
            c.product_id,
            c.quantity,
            p.name as product_name,
            p.price,
            p.discount_price,
            p.images,
            p.stock,
            c.updated_at
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.id = ?
    ");
    $getStmt->execute([$cartId]);
    $updatedItem = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $images = parseJsonArray($updatedItem['images']);
    $imageUrl = !empty($images) ? getImageUrl($images[0], 'products') : null;
    
    $finalPrice = $updatedItem['discount_price'] ?: $updatedItem['price'];
    
    $responseData = [
        'cart_id' => (string)$updatedItem['cart_id'],
        'product_id' => (string)$updatedItem['product_id'],
        'product_name' => $updatedItem['product_name'],
        'product_image' => $imageUrl,
        'quantity' => (int)$updatedItem['quantity'],
        'price' => (float)$updatedItem['price'],
        'discount_price' => $updatedItem['discount_price'] ? (float)$updatedItem['discount_price'] : null,
        'final_price' => (float)$finalPrice,
        'subtotal' => (float)($finalPrice * $updatedItem['quantity']),
        'stock' => (int)$updatedItem['stock'],
        'updated_at' => formatDateTime($updatedItem['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Cart updated successfully");
    
} catch (PDOException $e) {
    error_log("Update cart item error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update cart item");
} finally {
    $db->disconnect();
}
?>