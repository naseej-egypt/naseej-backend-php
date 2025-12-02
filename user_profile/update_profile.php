<?php
/**
 * Naseej User App - Update Profile
 * POST: /user_profile/update_profile.php
 * Body: name, phone (optional)
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
    
    // Get and validate input
    $name = filterRequest("name");
    $phone = filterRequest("phone");
    
    // Validate name
    if (empty($name)) {
        sendResponse(STATUS_FAIL, null, "Name is required");
    }
    
    if (strlen($name) < 2) {
        sendResponse(STATUS_FAIL, null, "Name must be at least 2 characters");
    }
    
    if (strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Name must not exceed 100 characters");
    }
    
    // Validate phone if provided
    if (!empty($phone) && !isValidPhone($phone)) {
        sendResponse(STATUS_FAIL, null, "Invalid phone number format");
    }
    
    // Prepare update query
    $updateFields = ["name = ?", "updated_at = NOW()"];
    $params = [$name];
    
    if (!empty($phone)) {
        $updateFields[] = "phone = ?";
        $params[] = $phone;
    }
    
    $params[] = $customer['customer_id'];
    
    // Update profile
    $stmt = $conn->prepare("
        UPDATE customers 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    // Get updated profile
    $getStmt = $conn->prepare("
        SELECT 
            id, name, email, phone, profile_image, 
            total_orders, total_spent, created_at, updated_at
        FROM customers 
        WHERE id = ?
    ");
    
    $getStmt->execute([$customer['customer_id']]);
    $updatedProfile = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $profileImageUrl = null;
    if (!empty($updatedProfile['profile_image'])) {
        $profileImageUrl = getImageUrl($updatedProfile['profile_image'], 'profiles');
    }
    
    $responseData = [
        'id' => (string)$updatedProfile['id'],
        'user_name' => $updatedProfile['name'],
        'name' => $updatedProfile['name'],
        'email' => $updatedProfile['email'],
        'phone' => $updatedProfile['phone'],
        'profile_image' => $profileImageUrl,
        'total_orders' => (int)$updatedProfile['total_orders'],
        'total_spent' => (float)$updatedProfile['total_spent'],
        'created_at' => formatDateTime($updatedProfile['created_at']),
        'updated_at' => formatDateTime($updatedProfile['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Profile updated successfully");
    
} catch (PDOException $e) {
    error_log("Update profile error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update profile");
}
?>