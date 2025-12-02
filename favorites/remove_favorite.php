<?php
/**
 * FILE 3: /favorites/remove_favorite.php
 * Remove product from favorites
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
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if (!$customerId || !$productId) {
        sendResponse(STATUS_FAIL, null, "Customer ID and Product ID are required");
    }
    
    // Remove from favorites
    $stmt = $conn->prepare("
        DELETE FROM favorites 
        WHERE customer_id = ? AND product_id = ?
    ");
    $stmt->execute([$customerId, $productId]);
    
    if ($stmt->rowCount() > 0) {
        sendResponse(STATUS_SUCCESS, [
            'product_id' => $productId
        ], "Removed from favorites successfully");
    } else {
        sendResponse(STATUS_SUCCESS, null, "Product not in favorites");
    }
    
} catch (PDOException $e) {
    error_log("Remove favorite error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to remove from favorites");
}
?>