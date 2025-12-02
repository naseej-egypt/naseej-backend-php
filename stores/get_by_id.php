<?php
/**
 * Naseej Admin - Get Store By ID
 * GET/POST: /stores/get_by_id.php
 * Headers: Authorization: Bearer <token>
 * Body/Query: store_id
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
    
    // Get store ID
    $storeId = isset($_GET['store_id']) ? $_GET['store_id'] : (isset($_POST['store_id']) ? filterRequest('store_id') : null);
    
    if (empty($storeId)) {
        sendResponse(STATUS_FAIL, null, "Store ID is required");
    }
    
    // Get store details
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            address,
            phone,
            email,
            latitude,
            longitude,
            image_url,
            is_active,
            manager_name,
            manager_phone,
            opening_time,
            closing_time,
            working_days,
            created_at,
            updated_at
        FROM stores
        WHERE id = ?
    ");
    
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    // Get image URL
    $imageUrl = null;
    if (!empty($store['image_url'])) {
        $imageUrl = getImageUrl($store['image_url'], 'stores');
    }
    
    // Parse working days
    $workingDays = parseJsonArray($store['working_days']);
    
    // Check if store is currently open
    $now = new DateTime();
    $currentDay = $now->format('l');
    $currentTime = $now->format('H:i:s');
    
    $isOpen = in_array($currentDay, $workingDays) && 
              $currentTime >= $store['opening_time'] && 
              $currentTime <= $store['closing_time'];
    
    // Get store statistics
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(o.total) as total_revenue,
            COUNT(DISTINCT dm.id) as delivery_men_count
        FROM stores s
        LEFT JOIN orders o ON s.id = o.store_id
        LEFT JOIN delivery_men dm ON s.id = dm.store_id
        WHERE s.id = ?
    ");
    $statsStmt->execute([$storeId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare response
    $responseData = [
        'id' => $store['id'],
        'name' => $store['name'],
        'address' => $store['address'],
        'phone' => $store['phone'],
        'email' => $store['email'],
        'latitude' => (float)$store['latitude'],
        'longitude' => (float)$store['longitude'],
        'image_url' => $imageUrl,
        'is_active' => (bool)$store['is_active'],
        'manager_name' => $store['manager_name'],
        'manager_phone' => $store['manager_phone'],
        'opening_time' => $store['opening_time'],
        'closing_time' => $store['closing_time'],
        'working_days' => $workingDays,
        'is_open' => $isOpen,
        'created_at' => formatDateTime($store['created_at']),
        'updated_at' => formatDateTime($store['updated_at']),
        'statistics' => [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'total_revenue' => (float)($stats['total_revenue'] ?? 0),
            'delivery_men_count' => (int)($stats['delivery_men_count'] ?? 0)
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get store by ID error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve store");
}
?>