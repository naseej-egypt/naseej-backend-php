<?php
/**
 * Naseej Admin - Search Orders
 * GET/POST: /orders/search.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: query (search by order number, customer name, email, phone)
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
    
    // Get search query
    $query = isset($_GET['query']) ? $_GET['query'] : (isset($_POST['query']) ? filterRequest('query') : '');
    
    if (empty($query)) {
        sendResponse(STATUS_FAIL, null, "Search query is required");
    }
    
    // Sanitize query for LIKE search
    $searchTerm = "%{$query}%";
    
    // Search orders - FIXED: Use proper JOINs and handle missing columns
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            COALESCE(o.customer_name, c.name) as customer_name,
            COALESCE(o.customer_email, c.email) as customer_email,
            COALESCE(o.customer_phone, c.phone) as customer_phone,
            o.subtotal,
            o.tax,
            o.delivery_fee,
            o.total,
            o.status,
            o.payment_status,
            o.payment_method,
            o.delivery_man_id,
            o.delivery_man_name,
            o.delivery_address,
            o.notes,
            o.created_at,
            o.updated_at,
            o.delivered_at,
            s.name as store_name,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN stores s ON o.store_id = s.id
        WHERE 
            o.order_number LIKE ? OR
            COALESCE(o.customer_name, c.name) LIKE ? OR
            COALESCE(o.customer_email, c.email) LIKE ? OR
            COALESCE(o.customer_phone, c.phone) LIKE ?
        ORDER BY 
            CASE 
                WHEN o.order_number LIKE ? THEN 1
                WHEN COALESCE(o.customer_name, c.name) LIKE ? THEN 2
                ELSE 3
            END,
            o.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([
        $searchTerm, 
        $searchTerm, 
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process orders
    $processedOrders = [];
    foreach ($orders as $order) {
        // Parse delivery address
        $deliveryAddress = [];
        if (!empty($order['delivery_address'])) {
            $deliveryAddress = parseJsonArray($order['delivery_address']);
        } else {
            $deliveryAddress = [
                'street' => '',
                'city' => '',
                'state' => '',
                'zip_code' => '',
                'country' => ''
            ];
        }
        
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_id' => $order['customer_id'],
            'customer_name' => $order['customer_name'] ?? 'Unknown Customer',
            'customer_email' => $order['customer_email'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'subtotal' => (float)$order['subtotal'],
            'tax' => (float)$order['tax'],
            'delivery_fee' => (float)$order['delivery_fee'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_man_id' => $order['delivery_man_id'],
            'delivery_man_name' => $order['delivery_man_name'],
            'delivery_address' => $deliveryAddress,
            'notes' => $order['notes'],
            'item_count' => (int)$order['item_count'],
            'store_name' => $order['store_name'],
            'created_at' => formatDateTime($order['created_at']),
            'updated_at' => formatDateTime($order['updated_at']),
            'delivered_at' => formatDateTime($order['delivered_at'])
        ];
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedOrders),
        'orders' => $processedOrders
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search orders error: " . $e->getMessage());
    error_log("SQL Error: " . print_r($e->errorInfo, true));
    sendResponse(STATUS_ERROR, null, "Failed to search orders: " . $e->getMessage());
}
?>