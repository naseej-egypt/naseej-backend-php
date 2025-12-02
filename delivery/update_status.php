<?php
/**
 * FILE: delivery/update_status.php
 * Update Delivery Man Status
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
    $admin = verifyAuth($conn);
    
    $deliveryManId = filterRequest("delivery_man_id");
    $status = filterRequest("status");
    
    if (empty($deliveryManId) || empty($status)) {
        sendResponse(STATUS_FAIL, null, "Delivery man ID and status are required");
    }
    
    $validStatuses = ['available', 'busy', 'offline'];
    if (!in_array($status, $validStatuses)) {
        sendResponse(STATUS_FAIL, null, "Invalid status. Valid values: " . implode(', ', $validStatuses));
    }
    
    $checkStmt = $conn->prepare("SELECT id, name, status FROM delivery_men WHERE id = ?");
    $checkStmt->execute([$deliveryManId]);
    $deliveryMan = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    if ($deliveryMan['status'] === $status) {
        sendResponse(STATUS_SUCCESS, null, "Status is already $status");
    }
    
    $stmt = $conn->prepare("
        UPDATE delivery_men 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$status, $deliveryManId]);
    
    logActivity($conn, $admin['admin_id'], 'update', 'delivery_man', $deliveryManId, 
                "Updated status to $status for: {$deliveryMan['name']}");
    
    $responseData = [
        'id' => $deliveryManId,
        'status' => $status
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Status updated successfully");
    
} catch (PDOException $e) {
    error_log("Update status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update status");
}
?>