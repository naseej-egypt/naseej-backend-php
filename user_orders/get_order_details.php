<?php
/**
 * Naseej User App - Get Order Details
 * POST: /user_orders/get_order_details.php
 * Body: order_id
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
    
    // Get order ID
    $orderId = filterRequest('order_id');
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    // Get order details with customer verification
    $stmt = $conn->prepare("
        SELECT 
            id, order_number, customer_id, customer_name, customer_email, customer_phone,
            subtotal, tax, delivery_fee, total, status, payment_status, payment_method,
            delivery_man_id, delivery_man_name, delivery_address, notes,
            created_at, updated_at, delivered_at, store_name, items, item_count
        FROM v_order_details
        WHERE id = ? AND customer_id = ?
    ");
    
    $stmt->execute([$orderId, $customer['customer_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Parse items
    $items = [];
    if (!empty($order['items'])) {
        $itemsData = json_decode($order['items'], true);
        if ($itemsData) {
            foreach ($itemsData as $item) {
                $imageUrl = null;
                if (!empty($item['product_image'])) {
                    $imageUrl = getImageUrl($item['product_image'], 'products');
                }
                
                $items[] = [
                    'id' => (string)$item['id'],
                    'product_id' => (string)$item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_image' => $imageUrl,
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$item['price'],
                    'total' => (float)$item['total']
                ];
            }
        }
    }
    
    // Parse delivery address
    $deliveryAddress = parseJsonArray($order['delivery_address']);
    
    // Prepare response
    $responseData = [
        'id' => (string)$order['id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'customer_phone' => $order['customer_phone'],
        'items' => $items,
        'subtotal' => (float)$order['subtotal'],
        'tax' => (float)$order['tax'],
        'delivery_fee' => (float)$order['delivery_fee'],
        'total' => (float)$order['total'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'payment_method' => $order['payment_method'],
        'delivery_man_name' => $order['delivery_man_name'],
        'store_name' => $order['store_name'],
        'delivery_address' => $deliveryAddress,
        'notes' => $order['notes'],
        'item_count' => (int)$order['item_count'],
        'created_at' => formatDateTime($order['created_at']),
        'updated_at' => formatDateTime($order['updated_at']),
        'delivered_at' => formatDateTime($order['delivered_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Order details retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get order details error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve order details");
} finally {
    $db->disconnect();
}
?>