<?php
/**
 * FILE 4: /stores/get_store_details.php
 * Get detailed store information
 */
// === get_store_details.php ===
include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

handleCorsHeaders();

$db = new Database();
$conn = $db->connect();

try {
    $storeId = (int)($_REQUEST['store_id'] ?? 0);
    
    if (!$storeId) {
        sendResponse(STATUS_FAIL, null, "Store ID is required");
    }
    
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            COUNT(DISTINCT dm.id) as delivery_men_count
        FROM stores s
        LEFT JOIN delivery_men dm ON s.id = dm.store_id AND dm.is_active = 1
        WHERE s.id = ? AND s.is_active = 1
        GROUP BY s.id
    ");
    
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    $imageUrl = null;
    if (!empty($store['image_url'])) {
        $imageUrl = getImageUrl($store['image_url'], 'stores');
    }
    
    $workingDays = parseJsonArray($store['working_days']);
    
    $responseData = [
        'id' => (int)$store['id'],
        'name' => $store['name'],
        'address' => $store['address'],
        'phone' => $store['phone'],
        'email' => $store['email'],
        'location' => [
            'latitude' => (float)$store['latitude'],
            'longitude' => (float)$store['longitude']
        ],
        'image_url' => $imageUrl,
        'manager' => [
            'name' => $store['manager_name'],
            'phone' => $store['manager_phone']
        ],
        'hours' => [
            'opening_time' => substr($store['opening_time'], 0, 5),
            'closing_time' => substr($store['closing_time'], 0, 5)
        ],
        'working_days' => $workingDays,
        'delivery_men_count' => (int)$store['delivery_men_count']
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get store details error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve store details");
}
?>