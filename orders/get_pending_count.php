<?php
/**
 * Naseej Admin - Get Pending Orders Count (FIXED - Accurate Counting)
 * GET/POST: /orders/get_pending_count.php
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Allow GET and POST methods
if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get count of pending orders - FIXED: Use direct count from orders table
    $pendingStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = ?
    ");
    $pendingStmt->execute([ORDER_PENDING]);
    $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get count of confirmed orders
    $confirmedStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = ?
    ");
    $confirmedStmt->execute([ORDER_CONFIRMED]);
    $confirmedCount = $confirmedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get count of processing orders
    $processingStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = ?
    ");
    $processingStmt->execute([ORDER_PROCESSING]);
    $processingCount = $processingStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get count of shipped orders
    $shippedStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = ?
    ");
    $shippedStmt->execute([ORDER_SHIPPED]);
    $shippedCount = $shippedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get count of orders awaiting payment
    $awaitingPaymentStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE payment_status = ?
    ");
    $awaitingPaymentStmt->execute([PAYMENT_PENDING]);
    $awaitingPaymentCount = $awaitingPaymentStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get count of orders without assigned delivery person
    $noDeliveryStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE delivery_man_id IS NULL 
        AND status NOT IN (?, ?)
    ");
    $noDeliveryStmt->execute([ORDER_CANCELLED, ORDER_DELIVERED]);
    $noDeliveryCount = $noDeliveryStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total active orders (not cancelled or delivered)
    $activeStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status NOT IN (?, ?)
    ");
    $activeStmt->execute([ORDER_CANCELLED, ORDER_DELIVERED]);
    $activeCount = $activeStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get today's orders
    $todayStmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $todayCount = $todayStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get all orders count for verification
    $totalStmt = $conn->query("SELECT COUNT(*) as count FROM orders");
    $totalCount = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $responseData = [
        'pending' => (int)$pendingCount,
        'confirmed' => (int)$confirmedCount,
        'processing' => (int)$processingCount,
        'shipped' => (int)$shippedCount,
        'awaiting_payment' => (int)$awaitingPaymentCount,
        'no_delivery_assigned' => (int)$noDeliveryCount,
        'active_orders' => (int)$activeCount,
        'today_orders' => (int)$todayCount,
        'total_orders' => (int)$totalCount,
        'total_pending_actions' => (int)($pendingCount + $noDeliveryCount)
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get pending orders count error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve pending orders count");
}
?>