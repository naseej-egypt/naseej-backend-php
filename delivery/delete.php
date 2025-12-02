<?php
/**
 * FILE: delivery/delete.php
 * Delete Delivery Man
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    $admin = requireRole($conn, ['super_admin', 'admin']);
    
    $deliveryManId = filterRequest("delivery_man_id");
    
    if (empty($deliveryManId)) {
        sendResponse(STATUS_FAIL, null, "Delivery man ID is required");
    }
    
    // Get details
    $stmt = $conn->prepare("
        SELECT d.id, d.name, d.profile_image, COUNT(o.id) as active_orders
        FROM delivery_men d
        LEFT JOIN orders o ON d.id = o.delivery_man_id 
            AND o.status IN ('confirmed', 'processing', 'shipped')
        WHERE d.id = ?
        GROUP BY d.id
    ");
    $stmt->execute([$deliveryManId]);
    $deliveryMan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    if ($deliveryMan['active_orders'] > 0) {
        sendResponse(STATUS_FAIL, null, "Cannot delete delivery man with active orders");
    }
    
    $conn->beginTransaction();
    
    try {
        $deleteStmt = $conn->prepare("DELETE FROM delivery_men WHERE id = ?");
        $deleteStmt->execute([$deliveryManId]);
        
        $conn->commit();
        
        if (!empty($deliveryMan['profile_image'])) {
            deleteFile(PROFILES_DIR, $deliveryMan['profile_image']);
        }
        
        logActivity($conn, $admin['admin_id'], 'delete', 'delivery_man', $deliveryManId, "Deleted delivery man: {$deliveryMan['name']}");
        
        sendResponse(STATUS_SUCCESS, null, "Delivery man deleted successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete delivery man error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete delivery man");
}
?>