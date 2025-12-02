<?php
/**
 * Naseej User App - Clear Cart
 * POST: /cart/clear_cart.php
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
    
    // Get current cart count before clearing
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM cart 
        WHERE customer_id = ?
    ");
    $countStmt->execute([$customer['customer_id']]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Delete all cart items for this customer
    $deleteStmt = $conn->prepare("
        DELETE FROM cart 
        WHERE customer_id = ?
    ");
    $deleteStmt->execute([$customer['customer_id']]);
    
    sendResponse(STATUS_SUCCESS, [
        'items_removed' => (int)$count
    ], "Cart cleared successfully");
    
} catch (PDOException $e) {
    error_log("Clear cart error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to clear cart");
} finally {
    $db->disconnect();
}
?>