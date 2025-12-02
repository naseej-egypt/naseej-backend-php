<?php
/**
 * Naseej Admin - Search Customers
 * GET/POST: /customers/search.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: query (search by name, email, or phone)
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
    
    // Search customers
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
            GROUP_CONCAT(
                CONCAT(
                    '{\"street\":\"', ca.street, '\",',
                    '\"city\":\"', ca.city, '\",',
                    '\"state\":\"', ca.state, '\",',
                    '\"country\":\"', ca.country, '\",',
                    '\"is_default\":', IF(ca.is_default = 1, 'true', 'false'), '}'
                )
                SEPARATOR ','
            ) as addresses_json
        FROM customers c
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
        WHERE 
            c.name LIKE ? OR
            c.email LIKE ? OR
            c.phone LIKE ?
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.name LIKE ? THEN 1
                WHEN c.email LIKE ? THEN 2
                ELSE 3
            END,
            c.total_spent DESC,
            c.name ASC
        LIMIT 50
    ");
    
    $stmt->execute([
        $searchTerm, 
        $searchTerm, 
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process customers
    $processedCustomers = [];
    foreach ($customers as $customer) {
        // Parse addresses
        $addresses = [];
        if (!empty($customer['addresses_json'])) {
            $addressesJson = '[' . $customer['addresses_json'] . ']';
            $addresses = json_decode($addressesJson, true) ?? [];
        }
        
        // Get profile image URL
        $profileImageUrl = null;
        if (!empty($customer['profile_image'])) {
            $profileImageUrl = getImageUrl($customer['profile_image'], 'profiles');
        }
        
        $processedCustomers[] = [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'profile_image' => $profileImageUrl,
            'addresses' => $addresses,
            'is_active' => (bool)$customer['is_active'],
            'total_orders' => (int)$customer['total_orders'],
            'total_spent' => (float)$customer['total_spent'],
            'last_order_date' => formatDateTime($customer['last_order_date']),
            'created_at' => formatDateTime($customer['created_at'])
        ];
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedCustomers),
        'customers' => $processedCustomers
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search customers error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search customers");
}
?>