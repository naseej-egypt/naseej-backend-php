<?php
/**
 * Naseej Admin - Get All Orders (FIXED - Proper Pagination)
 * GET/POST: /orders/get_all.php
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
    
    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? filterRequest('status') : null);
    $paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : (isset($_POST['payment_status']) ? filterRequest('payment_status') : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    $getAll = isset($_GET['get_all']) ? filterRequest('get_all') : (isset($_POST['get_all']) ? filterRequest('get_all') : false);
    
    // If get_all is true, fetch all records without pagination
    if ($getAll === 'true' || $getAll === true) {
        $limit = 1000; // Large number to effectively get all records
        $offset = 0;
    } else {
        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;
    }
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if ($status !== null && $status !== '') {
        $whereClauses[] = "o.status = ?";
        $params[] = $status;
    }
    
    if ($paymentStatus !== null && $paymentStatus !== '') {
        $whereClauses[] = "o.payment_status = ?";
        $params[] = $paymentStatus;
    }
    
    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM orders o
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get orders - FIXED: Use the view to include items
    $sql = "
        SELECT 
            id,
            order_number,
            customer_id,
            customer_name,
            customer_email,
            customer_phone,
            subtotal,
            tax,
            delivery_fee,
            total,
            status,
            payment_status,
            payment_method,
            delivery_man_id,
            delivery_man_name,
            delivery_address,
            notes,
            created_at,
            updated_at,
            delivered_at,
            store_name,
            item_count,
            items
        FROM v_order_details
        $whereSQL
        ORDER BY created_at DESC
    ";
    
    // Only apply LIMIT if not getting all records
    if (!($getAll === 'true' || $getAll === true)) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each order
    $processedOrders = [];
    foreach ($orders as $order) {
        // Parse items from JSON
        $items = [];
        if (!empty($order['items'])) {
            $items = json_decode($order['items'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $items = [];
            }
        }
        
        // Parse delivery address
        $deliveryAddress = parseJsonArray($order['delivery_address']);
        
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_id' => $order['customer_id'],
            'customer_name' => $order['customer_name'] ?? 'Unknown Customer',
            'customer_email' => $order['customer_email'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'items' => $items, // Include items in the response
            'subtotal' => (float)$order['subtotal'],
            'tax' => (float)$order['tax'],
            'delivery_fee' => (float)$order['delivery_fee'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_man_id' => $order['delivery_man_id'],
            'delivery_man_name' => $order['delivery_man_name'],
            'store_name' => $order['store_name'],
            'delivery_address' => $deliveryAddress,
            'notes' => $order['notes'],
            'item_count' => (int)$order['item_count'],
            'created_at' => formatDateTime($order['created_at']),
            'updated_at' => formatDateTime($order['updated_at']),
            'delivered_at' => formatDateTime($order['delivered_at'])
        ];
    }
    
    // Prepare response data
    if ($getAll === 'true' || $getAll === true) {
        // When getting all records, just return the orders array
        $responseData = [
            'orders' => $processedOrders,
            'total_count' => (int)$totalCount
        ];
    } else {
        // Prepare pagination data for normal requests
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
    }
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get all orders error: " . $e->getMessage());
    error_log("SQL Error: " . print_r($e->errorInfo, true));
    sendResponse(STATUS_ERROR, null, "Failed to retrieve orders: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error in get_all.php: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve orders");
}
?>