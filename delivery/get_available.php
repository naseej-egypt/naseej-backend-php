<?php
/**
 * FILE: delivery/get_available.php
 * Get Available Delivery Men for Assignment
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
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            profile_image,
            vehicle_type,
            vehicle_number,
            rating,
            total_deliveries,
            current_latitude,
            current_longitude
        FROM delivery_men
        WHERE status = 'available' AND is_active = 1
        ORDER BY rating DESC, total_deliveries ASC
    ");
    
    $stmt->execute();
    $deliveryMen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
            'rating' => (float)$dm['rating'],
            'total_deliveries' => (int)$dm['total_deliveries'],
            'current_latitude' => $dm['current_latitude'] ? (float)$dm['current_latitude'] : null,
            'current_longitude' => $dm['current_longitude'] ? (float)$dm['current_longitude'] : null
        ];
    }
    
    $responseData = [
        'count' => count($processedDeliveryMen),
        'delivery_men' => $processedDeliveryMen
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get available delivery men error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve available delivery men");
}
?>