<?php
/**
 * Naseej Admin - Update Order Status
 * POST: /orders/update_status.php
 * Headers: Authorization: Bearer <token>
 * Body: order_id, status (pending, confirmed, processing, shipped, delivered, cancelled)
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
    $newStatus = filterRequest("status");
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    if (empty($newStatus)) {
        sendResponse(STATUS_FAIL, null, "Status is required");
    }
    
    // Validate status
    $validStatuses = [
        ORDER_PENDING,
        ORDER_CONFIRMED,
        ORDER_PROCESSING,
        ORDER_SHIPPED,
        ORDER_DELIVERED,
        ORDER_CANCELLED
    ];
    
    if (!in_array($newStatus, $validStatuses)) {
        sendResponse(STATUS_FAIL, null, "Invalid status. Valid values: " . implode(', ', $validStatuses));
    }
    
    // Check if order exists
    $checkStmt = $conn->prepare("
        SELECT 
            id, 
            order_number, 
            customer_name,
            status, 
            payment_status,
            delivery_man_id
        FROM orders 
        WHERE id = ?
    ");
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Check if status is already the same
    if ($order['status'] === $newStatus) {
        sendResponse(STATUS_SUCCESS, null, "Order status is already $newStatus");
    }
    
    // Validate status transitions
    $currentStatus = $order['status'];
    
    // Cannot change status of cancelled orders
    if ($currentStatus === ORDER_CANCELLED && $newStatus !== ORDER_CANCELLED) {
        sendResponse(STATUS_FAIL, null, "Cannot change status of cancelled orders");
    }
    
    // Cannot change status of delivered orders (except to cancelled for refunds)
    if ($currentStatus === ORDER_DELIVERED && $newStatus !== ORDER_DELIVERED && $newStatus !== ORDER_CANCELLED) {
        sendResponse(STATUS_FAIL, null, "Cannot change status of delivered orders");
    }
    
    // Check if order can be shipped/delivered without delivery person
    if (($newStatus === ORDER_SHIPPED || $newStatus === ORDER_DELIVERED) && empty($order['delivery_man_id'])) {
        sendResponse(STATUS_FAIL, null, "Cannot mark order as $newStatus without assigning a delivery person");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Prepare update fields
        $updateFields = ["status = ?", "updated_at = NOW()"];
        $params = [$newStatus];
        
        // If marking as delivered, set delivered_at timestamp
        if ($newStatus === ORDER_DELIVERED) {
            $updateFields[] = "delivered_at = NOW()";
        }
        
        $params[] = $orderId;
        
        // Update order status
        $stmt = $conn->prepare("
            UPDATE orders 
            SET " . implode(", ", $updateFields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
        
        // If delivered, update delivery man's total deliveries (handled by trigger)
        // If cancelled, we might want to restore stock (optional - add if needed)
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'order',
            $orderId,
            "Updated order #{$order['order_number']} status from {$currentStatus} to {$newStatus}"
        );
        
        // Get updated order
        $getStmt = $conn->prepare("
            SELECT 
                id,
                order_number,
                status,
                updated_at,
                delivered_at
            FROM orders 
            WHERE id = ?
        ");
        $getStmt->execute([$orderId]);
        $updatedOrder = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        $responseData = [
            'id' => $updatedOrder['id'],
            'order_number' => $updatedOrder['order_number'],
            'status' => $updatedOrder['status'],
            'updated_at' => formatDateTime($updatedOrder['updated_at']),
            'delivered_at' => formatDateTime($updatedOrder['delivered_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Order status updated successfully to $newStatus");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Update order status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update order status");
}
?>