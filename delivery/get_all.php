<?php
/**
 * Naseej Admin - Get All Delivery Men
 * GET/POST: /delivery/get_all.php
 * Headers: Authorization: Bearer <token>
 * QueryParams (optional): 
 *   - status: Filter by status (available, busy, offline)
 *   - is_active: Filter by active status (1/0)
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
    $status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? filterRequest('status') : null);
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : (isset($_POST['is_active']) ? $_POST['is_active'] : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if ($status !== null && $status !== '') {
        $whereClauses[] = "status = ?";
        $params[] = $status;
    }
    
    if ($isActive !== null && $isActive !== '') {
        $whereClauses[] = "is_active = ?";
        $params[] = (int)$isActive;
    }
    
    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM delivery_men
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get delivery men
    $sql = "
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
        $whereSQL
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $deliveryMen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each delivery man
    $processedDeliveryMen = [];
    foreach ($deliveryMen as $dm) {
        $profileImageUrl = null;
        if (!empty($dm['profile_image'])) {
            $profileImageUrl = getImageUrl($dm['profile_image'], 'profiles');
        }
        
        $processedDeliveryMen[] = [
            'id' => $dm['id'],
            'name' => $dm['name'],
            'email' => $dm['email'],
            'phone' => $dm['phone'],
            'profile_image' => $profileImageUrl,
            'vehicle_type' => $dm['vehicle_type'],
            'vehicle_number' => $dm['vehicle_number'],
            'status' => $dm['status'],
            'is_active' => (bool)$dm['is_active'],
            'rating' => (float)$dm['rating'],
            'total_deliveries' => (int)$dm['total_deliveries'],
            'current_latitude' => $dm['current_latitude'] ? (float)$dm['current_latitude'] : null,
            'current_longitude' => $dm['current_longitude'] ? (float)$dm['current_longitude'] : null,
            'created_at' => formatDateTime($dm['created_at']),
            'updated_at' => formatDateTime($dm['updated_at'])
        ];
    }
    
    // Get statistics
    $statsStmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'available' AND is_active = 1 THEN 1 END) as available,
            COUNT(CASE WHEN status = 'busy' AND is_active = 1 THEN 1 END) as busy,
            COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
            AVG(rating) as avg_rating,
            SUM(total_deliveries) as total_deliveries
        FROM delivery_men
    ");
    $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'delivery_men' => $processedDeliveryMen,
        'statistics' => [
            'total' => (int)$statistics['total'],
            'available' => (int)$statistics['available'],
            'busy' => (int)$statistics['busy'],
            'offline' => (int)$statistics['offline'],
            'active' => (int)$statistics['active'],
            'average_rating' => round((float)$statistics['avg_rating'], 2),
            'total_deliveries' => (int)$statistics['total_deliveries']
        ],
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
    error_log("Get all delivery men error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve delivery men");
}
?>