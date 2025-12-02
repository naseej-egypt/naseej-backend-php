<?php
/**
 * Naseej User App - Remove from Cart
 * POST: /cart/remove_from_cart.php
 * Body: cart_id
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
    
    if (empty($cartId)) {
        sendResponse(STATUS_FAIL, null, "Cart ID is required");
    }
    
    // Verify cart item belongs to customer
    $checkStmt = $conn->prepare("
        SELECT id FROM cart 
        WHERE id = ? AND customer_id = ?
    ");
    $checkStmt->execute([$cartId, $customer['customer_id']]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Cart item not found");
    }
    
    // Delete cart item
    $deleteStmt = $conn->prepare("
        DELETE FROM cart 
        WHERE id = ? AND customer_id = ?
    ");
    $deleteStmt->execute([$cartId, $customer['customer_id']]);
    
    if ($deleteStmt->rowCount() > 0) {
        sendResponse(STATUS_SUCCESS, [
            'cart_id' => $cartId
        ], "Item removed from cart successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to remove item from cart");
    }
    
} catch (PDOException $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to remove item from cart");
} finally {
    $db->disconnect();
}
?>