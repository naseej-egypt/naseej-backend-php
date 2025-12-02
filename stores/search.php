<?php
/**
 * Naseej Admin - Search Stores
 * GET/POST: /stores/search.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: query (search by name, address, manager name, email, or phone)
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
    
    // Search stores
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
        WHERE 
            name LIKE ? OR
            address LIKE ? OR
            manager_name LIKE ? OR
            email LIKE ? OR
            phone LIKE ? OR
            manager_phone LIKE ?
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN manager_name LIKE ? THEN 2
                WHEN address LIKE ? THEN 3
                ELSE 4
            END,
            is_active DESC,
            name ASC
        LIMIT 50
    ");
    
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm, 
        $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm, $searchTerm
    ]);
    
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process stores
    $processedStores = [];
    foreach ($stores as $store) {
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
        
        $isOpen = $store['is_active'] && 
                  in_array($currentDay, $workingDays) && 
                  $currentTime >= $store['opening_time'] && 
                  $currentTime <= $store['closing_time'];
        
        // Get store statistics
        $statsStmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT dm.id) as delivery_men_count,
                COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'confirmed', 'processing', 'shipped') 
                       THEN o.id END) as active_orders
            FROM stores s
            LEFT JOIN orders o ON s.id = o.store_id
            LEFT JOIN delivery_men dm ON s.id = dm.store_id AND dm.is_active = 1
            WHERE s.id = ?
        ");
        $statsStmt->execute([$store['id']]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        $processedStores[] = [
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
                'delivery_men_count' => (int)($stats['delivery_men_count'] ?? 0),
                'active_orders' => (int)($stats['active_orders'] ?? 0)
            ]
        ];
    }
    
    // Get search statistics
    $activeCount = 0;
    $inactiveCount = 0;
    $openCount = 0;
    
    foreach ($processedStores as $store) {
        if ($store['is_active']) {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
        if ($store['is_open']) {
            $openCount++;
        }
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedStores),
        'statistics' => [
            'total' => count($processedStores),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'currently_open' => $openCount
        ],
        'stores' => $processedStores
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search stores error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search stores");
}
?>