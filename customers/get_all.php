<?php
/**
 * Naseej Admin - Get All Customers
 * GET/POST: /customers/get_all.php
 * Headers: Authorization: Bearer <token>
 * QueryParams (optional): 
 *   - is_active: Filter by status (1/0)
 *   - page: Page number for pagination
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
    
    // Get filter parameters
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : (isset($_POST['is_active']) ? $_POST['is_active'] : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if ($isActive !== null && $isActive !== '') {
        $whereClauses[] = "c.is_active = ?";
        $params[] = (int)$isActive;
    }
    
    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM customers c
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get customers with their addresses and statistics
    $sql = "
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
            c.updated_at,
            GROUP_CONCAT(
                CONCAT(
                    '{\"street\":\"', ca.street, '\",',
                    '\"city\":\"', ca.city, '\",',
                    '\"state\":\"', ca.state, '\",',
                    '\"zip_code\":\"', ca.zip_code, '\",',
                    '\"country\":\"', ca.country, '\",',
                    '\"is_default\":', IF(ca.is_default = 1, 'true', 'false'), '}'
                )
                SEPARATOR ','
            ) as addresses_json
        FROM customers c
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
        $whereSQL
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each customer
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
            'created_at' => formatDateTime($customer['created_at']),
            'updated_at' => formatDateTime($customer['updated_at'])
        ];
    }
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'customers' => $processedCustomers,
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
    error_log("Get all customers error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve customers");
}
?>