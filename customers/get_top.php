<?php
/**
 * Naseej Admin - Get Top Customers (FIXED)
 * GET/POST: /customers/get_top.php
 * Headers: Authorization: Bearer <token>
 * 
 * FIXED: Now correctly fetches top customers by actual spending
 * - Uses SUM of delivered + paid orders only
 * - Excludes customers with $0.00 spent
 * - Matches customer detail page calculations
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    $admin = verifyAuth($conn);
    
    // Get parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 10);
    
    // Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    // Get top customers by ACTUAL spending (delivered + paid orders only)
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            c.phone,
            c.profile_image,
            c.is_active,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(CASE WHEN o.status = 'delivered' AND o.payment_status = 'paid' THEN o.total ELSE 0 END), 0) as total_spent,
            c.last_order_date,
            c.created_at,
            c.updated_at,
            COUNT(DISTINCT ca.id) as address_count
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
        WHERE c.is_active = 1
        GROUP BY c.id
        HAVING total_spent > 0
        ORDER BY total_spent DESC, c.name ASC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get addresses for each customer
    $processedCustomers = [];
    $totalSpent = 0;
    $totalOrders = 0;
    
    foreach ($customers as $customer) {
        // Get addresses
        $addressStmt = $conn->prepare("
            SELECT street, city, state, country, is_default
            FROM customer_addresses
            WHERE customer_id = ?
            ORDER BY is_default DESC, created_at DESC
        ");
        $addressStmt->execute([$customer['id']]);
        $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedAddresses = [];
        foreach ($addresses as $addr) {
            $processedAddresses[] = [
                'street' => $addr['street'],
                'city' => $addr['city'],
                'state' => $addr['state'],
                'country' => $addr['country'],
                'is_default' => (bool)$addr['is_default']
            ];
        }
        
        // Get profile image URL
        $profileImageUrl = null;
        if (!empty($customer['profile_image'])) {
            $profileImageUrl = getImageUrl($customer['profile_image'], 'profiles');
        }
        
        // Calculate average order value
        $avgOrderValue = $customer['total_orders'] > 0 
            ? (float)$customer['total_spent'] / (int)$customer['total_orders'] 
            : 0;
        
        $processedCustomers[] = [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'profile_image' => $profileImageUrl,
            'addresses' => $processedAddresses,
            'address_count' => (int)$customer['address_count'],
            'is_active' => (bool)$customer['is_active'],
            'total_orders' => (int)$customer['total_orders'],
            'total_spent' => round((float)$customer['total_spent'], 2),
            'average_order_value' => round($avgOrderValue, 2),
            'last_order_date' => formatDateTime($customer['last_order_date']),
            'created_at' => formatDateTime($customer['created_at']),
            'updated_at' => formatDateTime($customer['updated_at'])
        ];
        
        $totalSpent += (float)$customer['total_spent'];
        $totalOrders += (int)$customer['total_orders'];
    }
    
    // Calculate statistics
    $avgSpent = count($processedCustomers) > 0 
        ? $totalSpent / count($processedCustomers) 
        : 0;
    
    $avgOrders = count($processedCustomers) > 0 
        ? $totalOrders / count($processedCustomers) 
        : 0;
    
    $responseData = [
        'customers' => $processedCustomers,
        'statistics' => [
            'total_customers' => count($processedCustomers),
            'total_spent' => round($totalSpent, 2),
            'total_orders' => $totalOrders,
            'average_spent_per_customer' => round($avgSpent, 2),
            'average_orders_per_customer' => round($avgOrders, 2)
        ],
        'filters' => [
            'limit' => $limit,
            'order_by' => 'total_spent'
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get top customers error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve top customers");
}
?>