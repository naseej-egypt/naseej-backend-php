<?php
/**
 * Naseej User App - Get User Orders
 * POST: /user_orders/get_orders.php
 * Body: status (optional), page (optional), limit (optional)
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
    
    // Get filter parameters
    $status = filterRequest('status');
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = ["customer_id = ?"];
    $params = [$customer['customer_id']];
    
    if ($status && $status !== '') {
        $whereClauses[] = "status = ?";
        $params[] = $status;
    }
    
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM orders
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get orders
    $sql = "
        SELECT 
            id, order_number, customer_name, subtotal, tax, delivery_fee, total,
            status, payment_status, payment_method, created_at, delivered_at,
            item_count, store_name, delivery_man_name, items
        FROM v_order_details
        $whereSQL
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process orders
    $processedOrders = [];
    foreach ($orders as $order) {
        // Parse items
        $items = [];
        if (!empty($order['items'])) {
            $itemsData = json_decode($order['items'], true) ?: [];
            
            foreach ($itemsData as $item) {
                // Process product image - ensure proper URL format
                $imageUrl = null;
                if (!empty($item['product_image'])) {
                    $imageUrl = getImageUrl($item['product_image'], 'products');
                }
                
                $items[] = [
                    'id' => (string)$item['id'],
                    'product_id' => (string)$item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_image' => $imageUrl, // Use processed image URL
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$item['price'],
                    'total' => (float)$item['total']
                ];
            }
        }
        
        $processedOrders[] = [
            'id' => (string)$order['id'],
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
            'items' => $items, // Use processed items with proper image URLs
            'store_name' => $order['store_name'],
            'delivery_man_name' => $order['delivery_man_name'],
            'created_at' => formatDateTime($order['created_at']),
            'delivered_at' => formatDateTime($order['delivered_at'])
        ];
    }
    
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
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
    error_log("Get orders error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve orders");
} finally {
    $db->disconnect();
}
?>