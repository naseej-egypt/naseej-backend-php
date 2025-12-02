<?php
/**
 * Naseej Admin - Get Customer By ID
 * GET/POST: /customers/get_by_id.php
 * Headers: Authorization: Bearer <token>
 * Body/Query: customer_id
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
    
    // Get customer details
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            c.phone,
            c.profile_image,
            c.is_active,
            c.total_orders,
            c.total_spent,
            c.last_order_date,
            c.created_at,
            c.updated_at
        FROM customers c
        WHERE c.id = ?
    ");
    
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Get customer addresses
    $addressStmt = $conn->prepare("
        SELECT 
            id,
            street,
            city,
            state,
            zip_code,
            country,
            latitude,
            longitude,
            is_default,
            created_at
        FROM customer_addresses
        WHERE customer_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    $addressStmt->execute([$customerId]);
    $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process addresses
    $processedAddresses = [];
    foreach ($addresses as $address) {
        $processedAddresses[] = [
            'id' => $address['id'],
            'street' => $address['street'],
            'city' => $address['city'],
            'state' => $address['state'],
            'zip_code' => $address['zip_code'],
            'country' => $address['country'],
            'latitude' => $address['latitude'] ? (float)$address['latitude'] : null,
            'longitude' => $address['longitude'] ? (float)$address['longitude'] : null,
            'is_default' => (bool)$address['is_default'],
            'full_address' => trim("{$address['street']}, {$address['city']}, {$address['state']} {$address['zip_code']}, {$address['country']}"),
            'created_at' => formatDateTime($address['created_at'])
        ];
    }
    
    // Get profile image URL
    $profileImageUrl = null;
    if (!empty($customer['profile_image'])) {
        $profileImageUrl = getImageUrl($customer['profile_image'], 'profiles');
    }
    
    // Get order statistics by status
    $orderStatsStmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total) as total_amount
        FROM orders
        WHERE customer_id = ?
        GROUP BY status
    ");
    $orderStatsStmt->execute([$customerId]);
    $orderStats = $orderStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusCounts = [];
    foreach ($orderStats as $stat) {
        $statusCounts[$stat['status']] = [
            'count' => (int)$stat['count'],
            'total_amount' => (float)$stat['total_amount']
        ];
    }
    
    // Get recent orders (last 5)
    $recentOrdersStmt = $conn->prepare("
        SELECT 
            id,
            order_number,
            total,
            status,
            payment_status,
            created_at
        FROM orders
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentOrdersStmt->execute([$customerId]);
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedRecentOrders = [];
    foreach ($recentOrders as $order) {
        $processedRecentOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    // Prepare response
    $responseData = [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'email' => $customer['email'],
        'phone' => $customer['phone'],
        'profile_image' => $profileImageUrl,
        'addresses' => $processedAddresses,
        'is_active' => (bool)$customer['is_active'],
        'total_orders' => (int)$customer['total_orders'],
        'total_spent' => (float)$customer['total_spent'],
        'last_order_date' => formatDateTime($customer['last_order_date']),
        'created_at' => formatDateTime($customer['created_at']),
        'updated_at' => formatDateTime($customer['updated_at']),
        'statistics' => [
            'order_status_counts' => $statusCounts,
            'recent_orders' => $processedRecentOrders
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get customer by ID error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve customer");
}
?>