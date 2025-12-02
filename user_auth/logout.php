<?php
/**
 * Naseej User App - Logout Endpoint
 * POST: /user_auth/logout.php
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
    deleteCustomerSession($conn, $token);

    sendResponse(STATUS_SUCCESS, null, "Logged out successfully");

} catch (PDOException $e) {
    error_log("Logout error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Logout failed");
}
?>