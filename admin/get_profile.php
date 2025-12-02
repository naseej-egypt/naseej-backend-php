<?php
/**
 * Naseej Admin - Get Admin Profile
 * GET/POST: /admin/get_profile.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns the authenticated admin's profile information
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
    
    // Get admin profile details
    $stmt = $conn->prepare("
        SELECT 
            id,
            email,
            name,
            phone_number,
            profile_image,
            role,
            is_active,
            last_login,
            created_at,
            updated_at
        FROM admins 
        WHERE id = ?
    ");
    
    $stmt->execute([$admin['admin_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        sendResponse(STATUS_FAIL, null, "Admin profile not found");
    }
    
    // Check if admin is still active
    if (!$profile['is_active']) {
        sendResponse(STATUS_FAIL, null, "Admin account is deactivated");
    }
    
    // Get profile image URL if exists
    $profileImageUrl = null;
    if (!empty($profile['profile_image'])) {
        $profileImageUrl = getImageUrl($profile['profile_image'], 'profiles');
    }
    
    // Get session statistics
    $sessionStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            MAX(created_at) as last_session_created
        FROM admin_sessions 
        WHERE admin_id = ? AND expires_at > NOW()
    ");
    $sessionStmt->execute([$admin['admin_id']]);
    $sessionStats = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get activity statistics
    $activityStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_activities,
            MAX(created_at) as last_activity
        FROM activity_logs 
        WHERE admin_id = ?
    ");
    $activityStmt->execute([$admin['admin_id']]);
    $activityStats = $activityStmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare response data
    $responseData = [
        'id' => $profile['id'],
        'email' => $profile['email'],
        'name' => $profile['name'],
        'phone_number' => $profile['phone_number'],
        'role' => $profile['role'],
        'profile_image' => $profileImageUrl,
        'is_active' => (bool)$profile['is_active'],
        'last_login' => formatDateTime($profile['last_login']),
        'created_at' => formatDateTime($profile['created_at']),
        'updated_at' => formatDateTime($profile['updated_at']),
        'statistics' => [
            'active_sessions' => (int)$sessionStats['total_sessions'],
            'total_activities' => (int)$activityStats['total_activities'],
            'last_activity' => formatDateTime($activityStats['last_activity'])
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Profile retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get profile error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve profile");
}
?>