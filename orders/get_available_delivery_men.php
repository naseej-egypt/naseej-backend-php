<?php
/**
 * Naseej Admin - Get Available Delivery Men
 * GET/POST: /orders/get_available_delivery_men.php
 * Headers: Authorization: Bearer <token>
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
    
    // Get available delivery men (active and available status)
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            profile_image,
            vehicle_type,
            vehicle_number,
            status,
            rating,
            total_deliveries
        FROM delivery_men 
        WHERE is_active = 1 
        AND status = 'available'
        ORDER BY name ASC
    ");
    
    $stmt->execute();
    $deliveryMen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process delivery men data
    $processedDeliveryMen = [];
    foreach ($deliveryMen as $dm) {
        $imageUrl = null;
        if (!empty($dm['profile_image'])) {
            $imageUrl = getImageUrl($dm['profile_image'], 'delivery_men');
        }
        
        $processedDeliveryMen[] = [
            'id' => $dm['id'],
            'name' => $dm['name'],
            'email' => $dm['email'],
            'phone' => $dm['phone'],
            'profile_image' => $imageUrl,
            'vehicle_type' => $dm['vehicle_type'],
            'vehicle_number' => $dm['vehicle_number'],
            'status' => $dm['status'],
            'rating' => (float)$dm['rating'],
            'total_deliveries' => (int)$dm['total_deliveries']
        ];
    }
    
    sendResponse(STATUS_SUCCESS, $processedDeliveryMen);
    
} catch (PDOException $e) {
    error_log("Get available delivery men error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve delivery men");
}
?>