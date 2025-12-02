<?php
/**
 * Naseej Admin - Get Orders By Customer
 * GET/POST: /orders/get_by_customer.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: customer_id
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
    
    // Get customer ID
    $customerId = isset($_GET['customer_id']) ? $_GET['customer_id'] : (isset($_POST['customer_id']) ? filterRequest('customer_id') : null);
    
    if (empty($customerId)) {
        sendResponse(STATUS_FAIL, null, "Customer ID is required");
    }
    
    // Check if customer exists
    $customerStmt = $conn->prepare("SELECT id, name, email FROM customers WHERE id = ?");
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Get customer's orders
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_name,
            o.subtotal,
            o.tax,
            o.delivery_fee,
            o.total,
            o.status,
            o.payment_status,
            o.payment_method,
            o.created_at,
            o.delivered_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process orders
    $processedOrders = [];
    $totalSpent = 0;
    $statusCounts = [
        'pending' => 0,
        'confirmed' => 0,
        'processing' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'cancelled' => 0
    ];
    
    foreach ($orders as $order) {
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'subtotal' => (float)$order['subtotal'],
            'tax' => (float)$order['tax'],
            'delivery_fee' => (float)$order['delivery_fee'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'item_count' => (int)$order['item_count'],
            'created_at' => formatDateTime($order['created_at']),
            'delivered_at' => formatDateTime($order['delivered_at'])
        ];
        
        // Calculate statistics
        if ($order['status'] === ORDER_DELIVERED) {
            $totalSpent += (float)$order['total'];
        }
        
        if (isset($statusCounts[$order['status']])) {
            $statusCounts[$order['status']]++;
        }
    }
    
    $responseData = [
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email']
        ],
        'statistics' => [
            'total_orders' => count($orders),
            'total_spent' => $totalSpent,
            'status_counts' => $statusCounts
        ],
        'orders' => $processedOrders
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get orders by customer error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve customer orders");
}
?>