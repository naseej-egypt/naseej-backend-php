<?php
/**
 * Naseej User App - Track Order
 * POST: /user_orders/track_order.php
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
    
    // Get order with tracking info
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.status,
            o.payment_status,
            o.created_at,
            o.updated_at,
            o.delivered_at,
            o.delivery_address,
            dm.id as delivery_man_id,
            dm.name as delivery_man_name,
            dm.phone as delivery_man_phone,
            dm.vehicle_type,
            dm.vehicle_number,
            s.name as store_name,
            s.address as store_address,
            s.phone as store_phone
        FROM orders o
        LEFT JOIN delivery_men dm ON o.delivery_man_id = dm.id
        LEFT JOIN stores s ON o.store_id = s.id
        WHERE o.id = ? AND o.customer_id = ?
    ");
    
    $stmt->execute([$orderId, $customer['customer_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendResponse(STATUS_FAIL, null, "Order not found");
    }
    
    // Define tracking timeline
    $timeline = [
        [
            'status' => 'pending',
            'title' => 'Order Placed',
            'description' => 'Your order has been received',
            'completed' => true,
            'timestamp' => $order['created_at']
        ],
        [
            'status' => 'confirmed',
            'title' => 'Order Confirmed',
            'description' => 'Your order has been confirmed',
            'completed' => in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered']),
            'timestamp' => null
        ],
        [
            'status' => 'processing',
            'title' => 'Preparing Order',
            'description' => 'Your order is being prepared',
            'completed' => in_array($order['status'], ['processing', 'shipped', 'delivered']),
            'timestamp' => null
        ],
        [
            'status' => 'shipped',
            'title' => 'Out for Delivery',
            'description' => 'Your order is on the way',
            'completed' => in_array($order['status'], ['shipped', 'delivered']),
            'timestamp' => null
        ],
        [
            'status' => 'delivered',
            'title' => 'Delivered',
            'description' => 'Your order has been delivered',
            'completed' => $order['status'] === 'delivered',
            'timestamp' => $order['delivered_at']
        ]
    ];
    
    // Handle cancelled status
    if ($order['status'] === 'cancelled') {
        $timeline = [
            [
                'status' => 'cancelled',
                'title' => 'Order Cancelled',
                'description' => 'Your order has been cancelled',
                'completed' => true,
                'timestamp' => $order['updated_at']
            ]
        ];
    }
    
    // Parse delivery address
    $deliveryAddress = parseJsonArray($order['delivery_address']);
    
    // Prepare delivery info
    $deliveryInfo = null;
    if ($order['delivery_man_id']) {
        $deliveryInfo = [
            'driver_name' => $order['delivery_man_name'],
            'driver_phone' => $order['delivery_man_phone'],
            'vehicle_type' => $order['vehicle_type'],
            'vehicle_number' => $order['vehicle_number']
        ];
    }
    
    // Prepare store info
    $storeInfo = null;
    if ($order['store_name']) {
        $storeInfo = [
            'name' => $order['store_name'],
            'address' => $order['store_address'],
            'phone' => $order['store_phone']
        ];
    }
    
    // Calculate estimated delivery
    $estimatedDelivery = null;
    if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled') {
        $createdDate = new DateTime($order['created_at']);
        $estimatedDate = $createdDate->modify('+3 days');
        $estimatedDelivery = $estimatedDate->format('Y-m-d H:i:s');
    }
    
    $responseData = [
        'order_id' => (string)$order['id'],
        'order_number' => $order['order_number'],
        'current_status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'timeline' => array_map(function($step) {
            return [
                'status' => $step['status'],
                'title' => $step['title'],
                'description' => $step['description'],
                'completed' => $step['completed'],
                'timestamp' => formatDateTime($step['timestamp'])
            ];
        }, $timeline),
        'delivery_address' => $deliveryAddress,
        'delivery_info' => $deliveryInfo,
        'store_info' => $storeInfo,
        'estimated_delivery' => formatDateTime($estimatedDelivery),
        'created_at' => formatDateTime($order['created_at']),
        'updated_at' => formatDateTime($order['updated_at']),
        'delivered_at' => formatDateTime($order['delivered_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Order tracking retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Track order error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve order tracking");
} finally {
    $db->disconnect();
}
?>