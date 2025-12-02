<?php
/**
 * Naseej Admin - Toggle Store Active Status
 * POST: /stores/toggle_status.php
 * Headers: Authorization: Bearer <token>
 * Body: store_id, is_active (1 or 0)
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
    $storeId = filterRequest("store_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($storeId)) {
        sendResponse(STATUS_FAIL, null, "Store ID is required");
    }
    
    if ($isActive === null) {
        sendResponse(STATUS_FAIL, null, "Status value is required");
    }
    
    // Validate status value
    if ($isActive !== 0 && $isActive !== 1) {
        sendResponse(STATUS_FAIL, null, "Status must be 0 or 1");
    }
    
    // Check if store exists
    $checkStmt = $conn->prepare("SELECT id, name, is_active FROM stores WHERE id = ?");
    $checkStmt->execute([$storeId]);
    $store = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    // Check if status is already the same
    if ((int)$store['is_active'] === $isActive) {
        sendResponse(STATUS_SUCCESS, null, "Store status is already " . ($isActive ? "active" : "inactive"));
    }
    
    // Check if deactivating - warn about active orders/delivery men
    if ($isActive === 0) {
        // Check for active orders
        $orderCheckStmt = $conn->prepare("
            SELECT COUNT(*) as active_orders 
            FROM orders 
            WHERE store_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')
        ");
        $orderCheckStmt->execute([$storeId]);
        $orderCount = $orderCheckStmt->fetch(PDO::FETCH_ASSOC)['active_orders'];
        
        if ($orderCount > 0) {
            sendResponse(STATUS_FAIL, null, "Cannot deactivate store with {$orderCount} active orders. Complete or reassign orders first.");
        }
        
        // Check for active delivery men
        $deliveryCheckStmt = $conn->prepare("
            SELECT COUNT(*) as active_delivery 
            FROM delivery_men 
            WHERE store_id = ? AND status IN ('available', 'busy') AND is_active = 1
        ");
        $deliveryCheckStmt->execute([$storeId]);
        $deliveryCount = $deliveryCheckStmt->fetch(PDO::FETCH_ASSOC)['active_delivery'];
        
        if ($deliveryCount > 0) {
            sendResponse(STATUS_FAIL, null, "Store has {$deliveryCount} active delivery personnel. Reassign or deactivate them first.");
        }
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE stores 
        SET is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $storeId]);
    
    // Log activity
    $statusText = $isActive ? "activated" : "deactivated";
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'store',
        $storeId,
        "Store {$statusText}: {$store['name']}"
    );
    
    // Get updated store details for response
    $getStmt = $conn->prepare("
        SELECT 
            id, name, address, phone, email, latitude, longitude,
            image_url, is_active, manager_name, manager_phone,
            opening_time, closing_time, working_days, updated_at
        FROM stores WHERE id = ?
    ");
    $getStmt->execute([$storeId]);
    $updatedStore = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $imageUrl = null;
    if (!empty($updatedStore['image_url'])) {
        $imageUrl = getImageUrl($updatedStore['image_url'], 'stores');
    }
    
    // Check if store is currently open
    $now = new DateTime();
    $currentDay = $now->format('l');
    $currentTime = $now->format('H:i:s');
    $workingDays = parseJsonArray($updatedStore['working_days']);
    
    $isOpen = $isActive && 
              in_array($currentDay, $workingDays) && 
              $currentTime >= $updatedStore['opening_time'] && 
              $currentTime <= $updatedStore['closing_time'];
    
    $responseData = [
        'id' => $updatedStore['id'],
        'name' => $updatedStore['name'],
        'address' => $updatedStore['address'],
        'phone' => $updatedStore['phone'],
        'email' => $updatedStore['email'],
        'latitude' => (float)$updatedStore['latitude'],
        'longitude' => (float)$updatedStore['longitude'],
        'image_url' => $imageUrl,
        'is_active' => (bool)$updatedStore['is_active'],
        'manager_name' => $updatedStore['manager_name'],
        'manager_phone' => $updatedStore['manager_phone'],
        'opening_time' => $updatedStore['opening_time'],
        'closing_time' => $updatedStore['closing_time'],
        'working_days' => $workingDays,
        'is_open' => $isOpen,
        'updated_at' => formatDateTime($updatedStore['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Store status updated successfully to " . ($isActive ? "active" : "inactive"));
    
} catch (PDOException $e) {
    error_log("Toggle store status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update store status");
}
?>