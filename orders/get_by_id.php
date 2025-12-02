<?php
/**
 * Naseej Admin - Get Order By ID (FIXED - Returns Items)
 * GET/POST: /orders/get_by_id.php
 * Headers: Authorization: Bearer <token>
 * Body/Query: order_id
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
    
    // Get order ID
    $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : (isset($_POST['order_id']) ? filterRequest('order_id') : null);
    
    if (empty($orderId)) {
        sendResponse(STATUS_FAIL, null, "Order ID is required");
    }
    
    // Use the view to get order details with items
    $stmt = $conn->prepare("
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
            items,
            item_count
        FROM v_order_details 
        WHERE id = ?
    ");
    
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Parse items from JSON
    $items = [];
    if (!empty($order['items'])) {
        $items = json_decode($order['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $items = [];
        }
    }
    
    // Parse delivery address
    $deliveryAddress = [];
    if (!empty($order['delivery_address'])) {
        if (is_string($order['delivery_address'])) {
            try {
                $deliveryAddress = json_decode($order['delivery_address'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $deliveryAddress = ['street' => $order['delivery_address']];
                }
            } catch (Exception $e) {
                $deliveryAddress = ['street' => $order['delivery_address']];
            }
        } else {
            $deliveryAddress = (array)$order['delivery_address'];
        }
    }
    
    // Ensure delivery address has required fields
    $deliveryAddress = array_merge([
        'street' => '',
        'city' => '',
        'state' => '',
        'zip_code' => '',
        'country' => ''
    ], $deliveryAddress);
    
    // Prepare response
    $responseData = [
        'id' => (int)$order['id'],
        'order_number' => $order['order_number'],
        'customer_id' => (int)$order['customer_id'],
        'customer_name' => $order['customer_name'] ?? 'Unknown Customer',
        'customer_email' => $order['customer_email'] ?? '',
        'customer_phone' => $order['customer_phone'] ?? '',
        'items' => $items, // This is now properly included
        'subtotal' => (float)$order['subtotal'],
        'tax' => (float)$order['tax'],
        'delivery_fee' => (float)$order['delivery_fee'],
        'total' => (float)$order['total'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'payment_method' => $order['payment_method'],
        'delivery_man_id' => $order['delivery_man_id'] ? (int)$order['delivery_man_id'] : null,
        'delivery_man_name' => $order['delivery_man_name'],
        'delivery_address' => $deliveryAddress,
        'notes' => $order['notes'],
        'store_name' => $order['store_name'],
        'created_at' => formatDateTime($order['created_at']),
        'updated_at' => formatDateTime($order['updated_at']),
        'delivered_at' => formatDateTime($order['delivered_at']),
        'item_count' => (int)$order['item_count']
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Order details retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get order by ID error: " . $e->getMessage());
    error_log("Error details: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve order: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error in get_by_id.php: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve order");
}
?>