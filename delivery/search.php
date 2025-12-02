<?php
/**
 * FILE: delivery/search.php
 * Search Delivery Men
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
    
    $query = isset($_GET['query']) ? $_GET['query'] : (isset($_POST['query']) ? filterRequest('query') : '');
    
    if (empty($query)) {
        sendResponse(STATUS_FAIL, null, "Search query is required");
    }
    
    $searchTerm = "%{$query}%";
    
    $stmt = $conn->prepare("
        SELECT 
            id, name, email, phone, profile_image, vehicle_type, 
            vehicle_number, status, is_active, rating, total_deliveries, created_at
        FROM delivery_men
        WHERE 
            name LIKE ? OR
            email LIKE ? OR
            phone LIKE ? OR
            vehicle_number LIKE ?
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN email LIKE ? THEN 2
                ELSE 3
            END,
            rating DESC
        LIMIT 50
    ");
    
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
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
            'status' => $dm['status'],
            'is_active' => (bool)$dm['is_active'],
            'rating' => (float)$dm['rating'],
            'total_deliveries' => (int)$dm['total_deliveries'],
            'created_at' => formatDateTime($dm['created_at'])
        ];
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedDeliveryMen),
        'delivery_men' => $processedDeliveryMen
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search delivery men error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search delivery men");
}
?>