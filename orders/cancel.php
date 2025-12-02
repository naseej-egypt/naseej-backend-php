<?php
/**
 * Naseej Admin - Cancel Order
 * POST: /orders/cancel.php
 * Headers: Authorization: Bearer <token>
 * Body: order_id, reason (optional)
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
    $reason = filterRequest("reason");
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    // Check if order exists
    $checkStmt = $conn->prepare("
        SELECT 
            id, 
            order_number,
            customer_name,
            status,
            payment_status,
            delivery_man_id,
            notes
        FROM orders 
        WHERE id = ?
    ");
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Check if order can be cancelled
    if ($order['status'] === ORDER_CANCELLED) {
        sendResponse(STATUS_FAIL, null, "Order is already cancelled");
    }
    
    if ($order['status'] === ORDER_DELIVERED) {
        sendResponse(STATUS_FAIL, null, "Cannot cancel delivered orders. Consider refunding instead.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Prepare cancellation notes
        $cancellationNote = "Order cancelled by admin";
        if (!empty($reason)) {
            $cancellationNote .= " - Reason: $reason";
        }
        $cancellationNote .= " at " . date('Y-m-d H:i:s');
        
        // Append to existing notes
        $notes = $order['notes'];
        if (!empty($notes)) {
            $notes .= "\n\n" . $cancellationNote;
        } else {
            $notes = $cancellationNote;
        }
        
        // Update order status to cancelled
        $stmt = $conn->prepare("
            UPDATE orders 
            SET 
                status = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([ORDER_CANCELLED, $notes, $orderId]);
        
        // If order has assigned delivery person, free them up
        if (!empty($order['delivery_man_id'])) {
            $deliveryStmt = $conn->prepare("
                UPDATE delivery_men 
                SET status = ? 
                WHERE id = ? AND status = ?
            ");
            $deliveryStmt->execute([
                DELIVERY_AVAILABLE, 
                $order['delivery_man_id'],
                DELIVERY_BUSY
            ]);
        }
        
        // Optional: Restore product stock (if you want to implement this)
        // You would need to get order items and update product stock here
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'cancel',
            'order',
            $orderId,
            "Cancelled order #{$order['order_number']} for {$order['customer_name']}" . (!empty($reason) ? " - Reason: $reason" : "")
        );
        
        $responseData = [
            'id' => $orderId,
            'order_number' => $order['order_number'],
            'status' => ORDER_CANCELLED,
            'cancellation_reason' => $reason
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Order cancelled successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Cancel order error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to cancel order");
}
?>