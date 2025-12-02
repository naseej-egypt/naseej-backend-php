<?php
/**
 * Naseej Admin - Logout Endpoint
 * POST: /auth/logout.php
 * Headers: Authorization: Bearer <token>
 * Body: (optional) token
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication and get admin data
    $admin = verifyAuth($conn);
    
    // Get token from header or POST data
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    if (!$token && isset($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    if (!$token) {
        sendResponse(STATUS_FAIL, null, "Token is required");
    }
    
    // Delete the session
    deleteSession($conn, $token);
    
    // Log activity
    logActivity(
        $conn, 
        $admin['admin_id'], 
        'logout', 
        'admin', 
        $admin['admin_id'], 
        'Admin logged out'
    );
    
    sendResponse(STATUS_SUCCESS, null, "Logged out successfully");
    
} catch (PDOException $e) {
    error_log("Logout error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred during logout");
}
?>