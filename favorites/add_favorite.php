<?php
/**
 * FILE 2: /favorites/add_favorite.php
 * Add product to favorites
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
    
    // Check product exists
    $checkStmt = $conn->prepare("
        SELECT id, name, is_active 
        FROM products 
        WHERE id = ?
    ");
    $checkStmt->execute([$productId]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    if (!$product['is_active']) {
        sendResponse(STATUS_FAIL, null, "Product is no longer available");
    }
    
    // Check if already in favorites
    $existingStmt = $conn->prepare("
        SELECT id 
        FROM favorites 
        WHERE customer_id = ? AND product_id = ?
    ");
    $existingStmt->execute([$customerId, $productId]);
    
    if ($existingStmt->fetch()) {
        sendResponse(STATUS_SUCCESS, [
            'message' => 'Product already in favorites'
        ], "Product already in favorites");
    }
    
    // Add to favorites
    $insertStmt = $conn->prepare("
        INSERT INTO favorites (customer_id, product_id) 
        VALUES (?, ?)
    ");
    $insertStmt->execute([$customerId, $productId]);
    
    sendResponse(STATUS_SUCCESS, [
        'favorite_id' => $conn->lastInsertId(),
        'product_id' => $productId
    ], "Added to favorites successfully");
    
} catch (PDOException $e) {
    error_log("Add favorite error: " . $e->getMessage());
    
    // Handle unique constraint violation
    if ($e->getCode() == 23000) {
        sendResponse(STATUS_SUCCESS, null, "Product already in favorites");
    }
    
    sendResponse(STATUS_ERROR, null, "Failed to add to favorites");
}
?>