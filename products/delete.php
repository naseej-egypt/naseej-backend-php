<?php
/**
 * Naseej Admin - Delete Product
 * POST: /products/delete.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id
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
    // Verify authentication and require admin role
    $admin = requireRole($conn, ['super_admin', 'admin']);
    
    // Get product ID
    $productId = filterRequest("product_id");
    
    if (empty($productId)) {
        sendResponse(STATUS_FAIL, null, "Product ID is required");
    }
    
    // Get product details before deletion
    $stmt = $conn->prepare("SELECT id, name, images FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    // Check if product has orders
    $orderCheckStmt = $conn->prepare("
        SELECT COUNT(*) as order_count 
        FROM order_items 
        WHERE product_id = ?
    ");
    $orderCheckStmt->execute([$productId]);
    $orderCount = $orderCheckStmt->fetch(PDO::FETCH_ASSOC)['order_count'];
    
    if ($orderCount > 0) {
        sendResponse(STATUS_FAIL, null, "Cannot delete product with existing orders. Consider deactivating instead.");
    }
    
    // Parse images
    $images = parseJsonArray($product['images']);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Delete product
        $deleteStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $deleteStmt->execute([$productId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete product images from server
        foreach ($images as $imageName) {
            deleteFile(PRODUCTS_DIR, $imageName);
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'product',
            $productId,
            "Deleted product: {$product['name']}"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Product deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete product error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete product");
}
?>