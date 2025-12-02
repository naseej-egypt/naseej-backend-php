<?php
/**
 * Naseej User App - Get Profile
 * POST: /user_profile/get_profile.php
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get full customer profile with addresses count
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            c.phone,
            c.profile_image,
            c.email_verified,
            c.is_active,
            c.total_orders,
            c.total_spent,
            c.last_order_date,
            c.created_at,
            c.updated_at,
            COUNT(DISTINCT ca.id) as address_count,
            COUNT(DISTINCT f.id) as favorite_count
        FROM customers c
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
        LEFT JOIN favorites f ON c.id = f.customer_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    
    $stmt->execute([$customer['customer_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        sendResponse(STATUS_FAIL, null, "Profile not found");
    }
    
    // Format profile image URL
    $profileImageUrl = null;
    if (!empty($profile['profile_image'])) {
        $profileImageUrl = getImageUrl($profile['profile_image'], 'profiles');
    }
    
    // Prepare response data
    $responseData = [
        'id' => (string)$profile['id'],
        'user_name' => $profile['name'],
        'name' => $profile['name'],
        'email' => $profile['email'],
        'phone' => $profile['phone'],
        'profile_image' => $profileImageUrl,
        'profile_image_filename' => $profile['profile_image'],
        'email_verified' => (bool)$profile['email_verified'],
        'is_active' => (bool)$profile['is_active'],
        'total_orders' => (int)$profile['total_orders'],
        'total_spent' => (float)$profile['total_spent'],
        'address_count' => (int)$profile['address_count'],
        'favorite_count' => (int)$profile['favorite_count'],
        'last_order_date' => formatDateTime($profile['last_order_date']),
        'created_at' => formatDateTime($profile['created_at']),
        'updated_at' => formatDateTime($profile['updated_at']),
        'member_since' => date('F Y', strtotime($profile['created_at']))
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Profile retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get profile error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve profile");
}
?>