<?php
/**
 * Naseej Admin - Toggle Product Active Status
 * POST: /products/toggle_status.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id, is_active (1 or 0)
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
    
    // Get input
    $productId = filterRequest("product_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($productId)) {
        sendResponse(STATUS_FAIL, null, "Product ID is required");
    }
    
    if ($isActive === null) {
        sendResponse(STATUS_FAIL, null, "Status value is required");
    }
    
    // Validate status value
    if ($isActive !== 0 && $isActive !== 1) {
        sendResponse(STATUS_FAIL, null, "Status must be 0 or 1");
    }
    
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT id, name, is_active FROM products WHERE id = ?");
    $checkStmt->execute([$productId]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    // Check if status is already the same
    if ((int)$product['is_active'] === $isActive) {
        sendResponse(STATUS_SUCCESS, null, "Product status is already " . ($isActive ? "active" : "inactive"));
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE products 
        SET is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $productId]);
    
    // Log activity
    $statusText = $isActive ? "activated" : "deactivated";
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'product',
        $productId,
        "Product {$statusText}: {$product['name']}"
    );
    
    $responseData = [
        'id' => $productId,
        'is_active' => (bool)$isActive
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Product status updated successfully");
    
} catch (PDOException $e) {
    error_log("Toggle product status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update product status");
}
?>