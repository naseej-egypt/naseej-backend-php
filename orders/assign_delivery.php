<?php
/**
 * Naseej Admin - Assign Delivery Person to Order (FIXED)
 * POST: /orders/assign_delivery.php
 * Headers: Authorization: Bearer <token>
 * Body: order_id, delivery_man_id
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
    $orderId = filterRequest("order_id");
    $deliveryManId = filterRequest("delivery_man_id");
    
    // Validate required fields
    $errors = validateRequired(['order_id', 'delivery_man_id']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if order exists
    $orderStmt = $conn->prepare("
        SELECT 
            id, 
            order_number,
            status,
            delivery_man_id as current_delivery_man_id
        FROM orders 
        WHERE id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Check order status - cannot assign to cancelled or delivered orders
    if ($order['status'] === ORDER_CANCELLED) {
        sendResponse(STATUS_FAIL, null, "Cannot assign delivery person to cancelled orders");
    }
    
    if ($order['status'] === ORDER_DELIVERED) {
        sendResponse(STATUS_FAIL, null, "Cannot reassign delivery person to already delivered orders");
    }
    
    // Check if delivery man exists and is available
    $deliveryStmt = $conn->prepare("
        SELECT 
            id, 
            name, 
            status,
            is_active
        FROM delivery_men 
        WHERE id = ?
    ");
    $deliveryStmt->execute([$deliveryManId]);
    $deliveryMan = $deliveryStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery person not found");
    }
    
    if (!$deliveryMan['is_active']) {
        sendResponse(STATUS_FAIL, null, "Delivery person is inactive");
    }
    
    // Optional: Check if delivery man is available (not required, just a warning)
    $isAvailable = $deliveryMan['status'] === DELIVERY_AVAILABLE;
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update order with delivery person - FIXED: Use delivery_man_name from delivery_men table
        $stmt = $conn->prepare("
            UPDATE orders 
            SET 
                delivery_man_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$deliveryManId, $orderId]);
        
        // Update delivery man status to busy if currently available
        if ($isAvailable) {
            $statusStmt = $conn->prepare("
                UPDATE delivery_men 
                SET status = ? 
                WHERE id = ?
            ");
            $statusStmt->execute([DELIVERY_BUSY, $deliveryManId]);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        $action = empty($order['current_delivery_man_id']) ? 'assigned' : 'reassigned';
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'order',
            $orderId,
            "Order #{$order['order_number']} {$action} to delivery person: {$deliveryMan['name']}"
        );
        
        $responseData = [
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'delivery_man_id' => $deliveryManId,
            'delivery_man_name' => $deliveryMan['name'],
            'delivery_man_status' => $isAvailable ? DELIVERY_BUSY : $deliveryMan['status']
        ];
        
        $message = empty($order['current_delivery_man_id']) 
            ? "Delivery person assigned successfully" 
            : "Delivery person reassigned successfully";
        
        if (!$isAvailable) {
            $message .= " (Note: Delivery person was not available)";
        }
        
        sendResponse(STATUS_SUCCESS, $responseData, $message);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Assign delivery error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to assign delivery person");
}
?>