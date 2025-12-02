<?php
/**
 * Naseej Admin - Delete Store
 * POST: /stores/delete.php
 * Headers: Authorization: Bearer <token>
 * Body: store_id
 * 
 * Note: Cannot delete store with existing orders or delivery personnel
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
    
    // Get store ID
    $storeId = filterRequest("store_id");
    
    if (empty($storeId)) {
        sendResponse(STATUS_FAIL, null, "Store ID is required");
    }
    
    // Get store details before deletion
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.email,
            s.image_url,
            COUNT(DISTINCT o.id) as order_count,
            COUNT(DISTINCT dm.id) as delivery_men_count,
            COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'confirmed', 'processing', 'shipped') 
                   THEN o.id END) as active_orders
        FROM stores s
        LEFT JOIN orders o ON s.id = o.store_id
        LEFT JOIN delivery_men dm ON s.id = dm.store_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    // Check if store has active orders
    if ($store['active_orders'] > 0) {
        sendResponse(
            STATUS_FAIL, 
            null, 
            "Cannot delete store with active orders ({$store['active_orders']} active orders). Complete or cancel orders first."
        );
    }
    
    // Check if store has delivery personnel
    if ($store['delivery_men_count'] > 0) {
        sendResponse(
            STATUS_FAIL, 
            null, 
            "Cannot delete store with delivery personnel ({$store['delivery_men_count']} delivery staff). Reassign or delete them first."
        );
    }
    
    // Check if store has any orders (even completed ones)
    if ($store['order_count'] > 0) {
        sendResponse(
            STATUS_FAIL, 
            null, 
            "Cannot delete store with order history ({$store['order_count']} orders). Consider deactivating the store instead to preserve order history."
        );
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Delete store
        $deleteStmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
        $deleteStmt->execute([$storeId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete store image from server if exists
        if (!empty($store['image_url'])) {
            deleteFile(STORES_DIR, $store['image_url']);
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'store',
            $storeId,
            "Deleted store: {$store['name']} ({$store['email']})"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Store deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete store error: " . $e->getMessage());
    
    // Check if error is due to foreign key constraint
    if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
        strpos($e->getMessage(), 'existing orders') !== false ||
        strpos($e->getMessage(), 'delivery') !== false) {
        sendResponse(
            STATUS_FAIL, 
            null, 
            "Cannot delete store due to existing data dependencies (orders, delivery personnel, etc.)"
        );
    }
    
    sendResponse(STATUS_ERROR, null, "Failed to delete store");
}
?>