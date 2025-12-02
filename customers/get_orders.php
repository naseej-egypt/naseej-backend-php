<?php
/**
 * Naseej Admin - Get Customer Orders
 * GET/POST: /customers/get_orders.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: customer_id (required)
 * QueryParams (optional): 
 *   - status: Filter by order status
 *   - payment_status: Filter by payment status
 *   - page: Page number for pagination (default 1)
 *   - limit: Items per page (default 20)
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
    $customerStmt = $conn->prepare("
        SELECT 
            id, 
            name, 
            email, 
            phone,
            is_active,
            total_orders,
            total_spent
        FROM customers 
        WHERE id = ?
    ");
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? filterRequest('status') : null);
    $paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : (isset($_POST['payment_status']) ? filterRequest('payment_status') : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = ["o.customer_id = ?"];
    $params = [$customerId];
    
    if ($status !== null && $status !== '') {
        $whereClauses[] = "o.status = ?";
        $params[] = $status;
    }
    
    if ($paymentStatus !== null && $paymentStatus !== '') {
        $whereClauses[] = "o.payment_status = ?";
        $params[] = $paymentStatus;
    }
    
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM orders o
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get orders
    $sql = "
        SELECT 
            o.id,
            o.order_number,
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
            o.delivery_man_id,
            o.delivery_man_name,
            o.delivery_address,
            o.notes,
            o.created_at,
            o.updated_at,
            o.delivered_at,
            s.name as store_name,
            s.address as store_address,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN stores s ON o.store_id = s.id
        $whereSQL
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each order
    $processedOrders = [];
    foreach ($orders as $order) {
        // Parse delivery address
        $deliveryAddress = parseJsonArray($order['delivery_address']);
        
        // Get order items
        $itemsStmt = $conn->prepare("
            SELECT 
                oi.id,
                oi.product_id,
                oi.product_name,
                oi.product_image,
                oi.quantity,
                oi.price,
                oi.total
            FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process items
        $processedItems = [];
        foreach ($items as $item) {
            $imageUrl = null;
            if (!empty($item['product_image'])) {
                $imageUrl = getImageUrl($item['product_image'], 'products');
            }
            
            $processedItems[] = [
                'id' => $item['id'],
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'product_image' => $imageUrl,
                'quantity' => (int)$item['quantity'],
                'price' => (float)$item['price'],
                'total' => (float)$item['total']
            ];
        }
        
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'items' => $processedItems,
            'item_count' => (int)$order['item_count'],
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
            'store' => [
                'name' => $order['store_name'],
                'address' => $order['store_address']
            ],
            'created_at' => formatDateTime($order['created_at']),
            'updated_at' => formatDateTime($order['updated_at']),
            'delivered_at' => formatDateTime($order['delivered_at'])
        ];
    }
    
    // Calculate order statistics for this customer
    $statusStatsStmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total) as total_amount
        FROM orders
        WHERE customer_id = ?
        GROUP BY status
    ");
    $statusStatsStmt->execute([$customerId]);
    $statusStats = $statusStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusCounts = [];
    foreach ($statusStats as $stat) {
        $statusCounts[$stat['status']] = [
            'count' => (int)$stat['count'],
            'total_amount' => (float)$stat['total_amount']
        ];
    }
    
    // Get payment statistics
    $paymentStatsStmt = $conn->prepare("
        SELECT 
            payment_status,
            COUNT(*) as count,
            SUM(total) as total_amount
        FROM orders
        WHERE customer_id = ?
        GROUP BY payment_status
    ");
    $paymentStatsStmt->execute([$customerId]);
    $paymentStats = $paymentStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $paymentCounts = [];
    foreach ($paymentStats as $stat) {
        $paymentCounts[$stat['payment_status']] = [
            'count' => (int)$stat['count'],
            'total_amount' => (float)$stat['total_amount']
        ];
    }
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'is_active' => (bool)$customer['is_active'],
            'total_orders' => (int)$customer['total_orders'],
            'total_spent' => (float)$customer['total_spent']
        ],
        'orders' => $processedOrders,
        'statistics' => [
            'total_orders' => (int)$totalCount,
            'status_counts' => $statusCounts,
            'payment_counts' => $paymentCounts
        ],
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
    error_log("Get customer orders error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve customer orders");
}
?>