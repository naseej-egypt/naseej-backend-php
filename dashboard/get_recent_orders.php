<?php
/**
 * Naseej Admin - Get Recent Orders
 * GET/POST: /dashboard/get_recent_orders.php
 * Headers: Authorization: Bearer <token>
 * Query: limit (optional, default 10)
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
    
    // Get limit parameter
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 10);
    
    // Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    // Get recent orders
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.customer_name,
            o.customer_email,
            o.customer_phone,
            o.subtotal,
            o.tax,
            o.delivery_fee,
            o.total,
            o.status,
            o.payment_status,
            o.payment_method,
            o.delivery_man_name,
            o.created_at,
            COUNT(oi.id) as item_count,
            s.name as store_name
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN stores s ON o.store_id = s.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process orders
    $processedOrders = [];
    foreach ($orders as $order) {
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_id' => $order['customer_id'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'subtotal' => (float)$order['subtotal'],
            'tax' => (float)$order['tax'],
            'delivery_fee' => (float)$order['delivery_fee'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_man_name' => $order['delivery_man_name'],
            'store_name' => $order['store_name'],
            'item_count' => (int)$order['item_count'],
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    $responseData = [
        'orders' => $processedOrders,
        'count' => count($processedOrders)
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get recent orders error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve recent orders");
}
?>