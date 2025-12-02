<?php
/**
 * Naseej Admin - Get Delivery Man By ID
 * GET/POST: /delivery/get_by_id.php
 * Headers: Authorization: Bearer <token>
 * Body/Query: delivery_man_id
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
    
    // Get delivery man ID
    $deliveryManId = isset($_GET['delivery_man_id']) ? $_GET['delivery_man_id'] : (isset($_POST['delivery_man_id']) ? filterRequest('delivery_man_id') : null);
    
    if (empty($deliveryManId)) {
        sendResponse(STATUS_FAIL, null, "Delivery man ID is required");
    }
    
    // Get delivery man details
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            profile_image,
            vehicle_type,
            vehicle_number,
            status,
            is_active,
            rating,
            total_deliveries,
            current_latitude,
            current_longitude,
            created_at,
            updated_at
        FROM delivery_men
        WHERE id = ?
    ");
    
    $stmt->execute([$deliveryManId]);
    $deliveryMan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    // Get profile image URL
    $profileImageUrl = null;
    if (!empty($deliveryMan['profile_image'])) {
        $profileImageUrl = getImageUrl($deliveryMan['profile_image'], 'profiles');
    }
    
    // Get current assigned orders
    $ordersStmt = $conn->prepare("
        SELECT 
            id,
            order_number,
            customer_name,
            total,
            status,
            delivery_address,
            created_at
        FROM orders
        WHERE delivery_man_id = ? 
        AND status IN ('confirmed', 'processing', 'shipped')
        ORDER BY created_at DESC
    ");
    $ordersStmt->execute([$deliveryManId]);
    $currentOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process current orders
    $processedOrders = [];
    foreach ($currentOrders as $order) {
        $deliveryAddress = parseJsonArray($order['delivery_address']);
        $processedOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'delivery_address' => $deliveryAddress,
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    // Get delivery statistics
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_completed,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time
        FROM orders
        WHERE delivery_man_id = ? 
        AND status = 'delivered'
        AND delivered_at IS NOT NULL
    ");
    $statsStmt->execute([$deliveryManId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare response
    $responseData = [
        'id' => $deliveryMan['id'],
        'name' => $deliveryMan['name'],
        'email' => $deliveryMan['email'],
        'phone' => $deliveryMan['phone'],
        'profile_image' => $profileImageUrl,
        'vehicle_type' => $deliveryMan['vehicle_type'],
        'vehicle_number' => $deliveryMan['vehicle_number'],
        'status' => $deliveryMan['status'],
        'is_active' => (bool)$deliveryMan['is_active'],
        'rating' => (float)$deliveryMan['rating'],
        'total_deliveries' => (int)$deliveryMan['total_deliveries'],
        'current_latitude' => $deliveryMan['current_latitude'] ? (float)$deliveryMan['current_latitude'] : null,
        'current_longitude' => $deliveryMan['current_longitude'] ? (float)$deliveryMan['current_longitude'] : null,
        'created_at' => formatDateTime($deliveryMan['created_at']),
        'updated_at' => formatDateTime($deliveryMan['updated_at']),
        'current_orders' => $processedOrders,
        'statistics' => [
            'total_completed' => (int)$stats['total_completed'],
            'average_delivery_time' => $stats['avg_delivery_time'] ? round((float)$stats['avg_delivery_time'], 1) : null
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get delivery man by ID error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve delivery man");
}
?>