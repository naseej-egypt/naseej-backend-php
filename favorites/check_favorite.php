<?php
/**
 * FILE 4: /favorites/check_favorite.php
 * Check if product is in favorites
 */
include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

$db = new Database();
$conn = $db->connect();

try {
    $customerId = (int)($_REQUEST['customer_id'] ?? 0);
    $productId = (int)($_REQUEST['product_id'] ?? 0);
    
    if (!$customerId || !$productId) {
        sendResponse(STATUS_FAIL, null, "Customer ID and Product ID are required");
    }
    
    // Check if in favorites
    $stmt = $conn->prepare("
        SELECT id 
        FROM favorites 
        WHERE customer_id = ? AND product_id = ?
    ");
    $stmt->execute([$customerId, $productId]);
    
    $isFavorite = (bool)$stmt->fetch();
    
    sendResponse(STATUS_SUCCESS, [
        'is_favorite' => $isFavorite,
        'product_id' => $productId
    ]);
    
} catch (PDOException $e) {
    error_log("Check favorite error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to check favorite status");
}
?>