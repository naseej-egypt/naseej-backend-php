<?php
/**
 * Naseej Admin - Refresh Token Endpoint
 * POST: /auth/refresh_token.php
 * Headers: Authorization: Bearer <token>
 * 
 * Extends the current session by creating a new token
 * Useful for keeping users logged in without re-authentication
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
    // Verify current authentication
    $admin = verifyAuth($conn);
    
    // Get current token
    $headers = getallheaders();
    $currentToken = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            $currentToken = $matches[1];
        }
    }
    
    if (!$currentToken && isset($_POST['token'])) {
        $currentToken = $_POST['token'];
    }
    
    if (!$currentToken) {
        sendResponse(STATUS_FAIL, null, "Token is required");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Delete old session
    deleteSession($conn, $currentToken);
    
    // Create new session
    $newToken = createSession($conn, $admin['admin_id']);
    
    // Commit transaction
    $conn->commit();
    
    // Get admin details
    $stmt = $conn->prepare("
        SELECT 
            id,
            email,
            name,
            phone_number,
            role
        FROM admins 
        WHERE id = ?
    ");
    
    $stmt->execute([$admin['admin_id']]);
    $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare response
    $responseData = [
        'admin' => [
            'id' => $adminDetails['id'],
            'email' => $adminDetails['email'],
            'name' => $adminDetails['name'],
            'phone_number' => $adminDetails['phone_number'],
            'role' => $adminDetails['role']
        ],
        'token' => $newToken
    ];
    
    // Log activity
    logActivity(
        $conn, 
        $admin['admin_id'], 
        'refresh_token', 
        'admin', 
        $admin['admin_id'], 
        'Token refreshed'
    );
    
    sendResponse(STATUS_SUCCESS, $responseData, "Token refreshed successfully");
    
} catch (PDOException $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Token refresh error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Token refresh failed");
}
?>