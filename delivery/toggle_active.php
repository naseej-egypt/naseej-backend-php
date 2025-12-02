<?php
/**
 * FILE: delivery/toggle_active.php
 * Toggle Active Status
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
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($deliveryManId) || $isActive === null) {
        sendResponse(STATUS_FAIL, null, "Delivery man ID and status are required");
    }
    
    if ($isActive !== 0 && $isActive !== 1) {
        sendResponse(STATUS_FAIL, null, "Status must be 0 or 1");
    }
    
    $checkStmt = $conn->prepare("SELECT id, name, is_active FROM delivery_men WHERE id = ?");
    $checkStmt->execute([$deliveryManId]);
    $deliveryMan = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    if ((int)$deliveryMan['is_active'] === $isActive) {
        sendResponse(STATUS_SUCCESS, null, "Status is already " . ($isActive ? "active" : "inactive"));
    }
    
    $stmt = $conn->prepare("
        UPDATE delivery_men 
        SET is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $deliveryManId]);
    
    $statusText = $isActive ? "activated" : "deactivated";
    logActivity($conn, $admin['admin_id'], 'update', 'delivery_man', $deliveryManId, 
                "Delivery man $statusText: {$deliveryMan['name']}");
    
    $responseData = [
        'id' => $deliveryManId,
        'is_active' => (bool)$isActive
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Active status updated successfully");
    
} catch (PDOException $e) {
    error_log("Toggle active error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update active status");
}
?>