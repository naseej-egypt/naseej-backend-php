<?php
/**
 * Naseej Admin - Update Payment Status
 * POST: /orders/update_payment_status.php
 * Headers: Authorization: Bearer <token>
 * Body: order_id, payment_status (pending, paid, failed, refunded)
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
    $paymentStatus = filterRequest("payment_status");
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    if (empty($paymentStatus)) {
        sendResponse(STATUS_FAIL, null, "Payment status is required");
    }
    
    // Validate payment status
    $validPaymentStatuses = [
        PAYMENT_PENDING,
        PAYMENT_PAID,
        PAYMENT_FAILED,
        PAYMENT_REFUNDED
    ];
    
    if (!in_array($paymentStatus, $validPaymentStatuses)) {
        sendResponse(STATUS_FAIL, null, "Invalid payment status. Valid values: " . implode(', ', $validPaymentStatuses));
    }
    
    // Check if order exists
    $checkStmt = $conn->prepare("
        SELECT 
            id, 
            order_number,
            payment_status as current_payment_status,
            status as order_status,
            total
        FROM orders 
        WHERE id = ?
    ");
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Check if payment status is already the same
    if ($order['current_payment_status'] === $paymentStatus) {
        sendResponse(STATUS_SUCCESS, null, "Payment status is already $paymentStatus");
    }
    
    // Validate payment status transitions
    $currentPaymentStatus = $order['current_payment_status'];
    
    // Cannot mark as refunded if not paid first
    if ($paymentStatus === PAYMENT_REFUNDED && $currentPaymentStatus !== PAYMENT_PAID) {
        sendResponse(STATUS_FAIL, null, "Cannot refund payment that is not marked as paid");
    }
    
    // Update payment status
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$paymentStatus, $orderId]);
    
    // Log activity
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'order',
        $orderId,
        "Updated order #{$order['order_number']} payment status from {$currentPaymentStatus} to {$paymentStatus}"
    );
    
    // Get updated order
    $getStmt = $conn->prepare("
        SELECT 
            id,
            order_number,
            payment_status,
            updated_at
        FROM orders 
        WHERE id = ?
    ");
    $getStmt->execute([$orderId]);
    $updatedOrder = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $responseData = [
        'id' => $updatedOrder['id'],
        'order_number' => $updatedOrder['order_number'],
        'payment_status' => $updatedOrder['payment_status'],
        'updated_at' => formatDateTime($updatedOrder['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Payment status updated successfully to $paymentStatus");
    
} catch (PDOException $e) {
    error_log("Update payment status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update payment status");
}
?>