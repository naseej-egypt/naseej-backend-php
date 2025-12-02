<?php
/**
 * Naseej User App - Cancel Order
 * POST: /user_orders/cancel_order.php
 * Body: order_id, reason (optional)
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get input
    $orderId = filterRequest('order_id');
    $reason = filterRequest('reason');
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    // Get order details
    $orderStmt = $conn->prepare("
        SELECT id, order_number, status, payment_status, customer_id
        FROM orders
        WHERE id = ? AND customer_id = ?
    ");
    $orderStmt->execute([$orderId, $customer['customer_id']]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Check if order can be cancelled
    $nonCancellableStatuses = ['delivered', 'cancelled', 'shipped'];
    if (in_array($order['status'], $nonCancellableStatuses)) {
        sendResponse(STATUS_FAIL, null, "Order cannot be cancelled. Current status: {$order['status']}");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Get order items to restore stock
    $itemsStmt = $conn->prepare("
        SELECT product_id, quantity
        FROM order_items
        WHERE order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Restore product stock
    $restoreStockStmt = $conn->prepare("
        UPDATE products
        SET stock = stock + ?
        WHERE id = ?
    ");
    
    foreach ($items as $item) {
        $restoreStockStmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Update order status
    $updateStmt = $conn->prepare("
        UPDATE orders
        SET status = 'cancelled',
            payment_status = CASE 
                WHEN payment_status = 'paid' THEN 'refunded'
                ELSE payment_status
            END,
            notes = CONCAT(COALESCE(notes, ''), '\nCancelled by customer', CASE WHEN ? IS NOT NULL THEN CONCAT('. Reason: ', ?) ELSE '' END),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$reason, $reason, $orderId]);
    
    // Commit transaction
    $conn->commit();
    
    // Get updated order
    $getOrderStmt = $conn->prepare("
        SELECT id, order_number, status, payment_status, updated_at
        FROM orders
        WHERE id = ?
    ");
    $getOrderStmt->execute([$orderId]);
    $updatedOrder = $getOrderStmt->fetch(PDO::FETCH_ASSOC);
    
    $responseData = [
        'order_id' => (string)$updatedOrder['id'],
        'order_number' => $updatedOrder['order_number'],
        'status' => $updatedOrder['status'],
        'payment_status' => $updatedOrder['payment_status'],
        'updated_at' => formatDateTime($updatedOrder['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Order cancelled successfully");
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Cancel order error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to cancel order");
} finally {
    $db->disconnect();
}
?>