<?php
/**
 *  /stores/get_stores.php
 * Get active stores
 */
// === get_stores.php ===
include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

handleCorsHeaders();

$db = new Database();
$conn = $db->connect();

try {
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
            manager_name,
            manager_phone,
            opening_time,
            closing_time,
            working_days
        FROM stores
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    
    $stmt->execute();
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedStores = [];
    foreach ($stores as $store) {
        $imageUrl = null;
        if (!empty($store['image_url'])) {
            $imageUrl = getImageUrl($store['image_url'], 'stores');
        }
        
        // Parse working days
        $workingDays = parseJsonArray($store['working_days']);
        
        $processedStores[] = [
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
            'working_days' => $workingDays
        ];
    }
    
    sendResponse(STATUS_SUCCESS, ['stores' => $processedStores]);
    
} catch (PDOException $e) {
    error_log("Get stores error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve stores");
}