<?php
/**
 * Naseej Admin - Get All Stores
 * GET/POST: /stores/get_all.php
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
        $whereClauses[] = "is_active = ?";
        $params[] = (int)$isActive;
    }
    
    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM stores
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get stores
    $sql = "
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
        $whereSQL
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each store
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
        $currentDay = $now->format('l'); // Monday, Tuesday, etc.
        $currentTime = $now->format('H:i:s');
        
        $isOpen = in_array($currentDay, $workingDays) && 
                  $currentTime >= $store['opening_time'] && 
                  $currentTime <= $store['closing_time'];
        
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
            'updated_at' => formatDateTime($store['updated_at'])
        ];
    }
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'stores' => $processedStores,
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
    error_log("Get all stores error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve stores");
}
?>