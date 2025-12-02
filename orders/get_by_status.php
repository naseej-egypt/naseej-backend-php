<?php
/**
 * Naseej Admin - Get Orders By Status
 * GET/POST: /orders/get_by_status.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: status (pending, confirmed, processing, shipped, delivered, cancelled)
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
    
    // Get status
    $status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? filterRequest('status') : null);
    
    if (empty($status)) {
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
    
    if (!in_array($status, $validStatuses)) {
        sendResponse(STATUS_FAIL, null, "Invalid status. Valid values: " . implode(', ', $validStatuses));
    }
    
    // Get pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE status = ?");
    $countStmt->execute([$status]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get orders
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.customer_name,
            o.customer_email,
            o.total,
            o.status,
            o.payment_status,
            o.payment_method,
            o.delivery_man_name,
            o.created_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$status, $limit, $offset]);
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
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_man_name' => $order['delivery_man_name'],
            'item_count' => (int)$order['item_count'],
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'status_filter' => $status,
        'orders' => $processedOrders,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get orders by status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve orders");
}
?>