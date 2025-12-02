<?php
/**
 * Naseej Admin - Validate Session Endpoint
 * POST/GET: /auth/validate_session.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns admin profile data if session is valid
 * Used for checking authentication state on app startup
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Allow both GET and POST methods
if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication and get admin data
    $admin = verifyAuth($conn);
    
    // Get full admin details
    $stmt = $conn->prepare("
        SELECT 
            id,
            email,
            name,
            phone_number,
            role,
            is_active,
            last_login,
            created_at
        FROM admins 
        WHERE id = ? AND is_active = 1
    ");
    
    $stmt->execute([$admin['admin_id']]);
    $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminDetails) {
        sendResponse(STATUS_FAIL, null, "Admin not found or inactive");
    }
    
    // Get session info
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
    
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    $sessionInfo = null;
    if ($token) {
        $sessionStmt = $conn->prepare("
            SELECT 
                created_at,
                expires_at,
                ip_address
            FROM admin_sessions 
            WHERE token = ?
        ");
        $sessionStmt->execute([$token]);
        $sessionInfo = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Prepare response
    $responseData = [
        'admin' => [
            'id' => $adminDetails['id'],
            'email' => $adminDetails['email'],
            'name' => $adminDetails['name'],
            'phone_number' => $adminDetails['phone_number'],
            'role' => $adminDetails['role'],
            'last_login' => formatDateTime($adminDetails['last_login']),
            'created_at' => formatDateTime($adminDetails['created_at'])
        ],
        'session' => [
            'valid' => true,
            'created_at' => $sessionInfo ? formatDateTime($sessionInfo['created_at']) : null,
            'expires_at' => $sessionInfo ? formatDateTime($sessionInfo['expires_at']) : null,
            'ip_address' => $sessionInfo['ip_address'] ?? null
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Session is valid");
    
} catch (PDOException $e) {
    error_log("Session validation error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Session validation failed");
}
?>