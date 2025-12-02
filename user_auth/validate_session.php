<?php
/**
 * Naseej User App - Validate Session Endpoint
 * POST/GET: /user_auth/validate_session.php
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

// Allow both GET and POST methods
if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get full customer details
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            profile_image,
            email_verified,
            is_active,
            total_orders,
            total_spent,
            last_order_date,
            created_at,
            updated_at
        FROM customers 
        WHERE id = ? AND is_active = 1 AND email_verified = 1
    ");
    
    $stmt->execute([$customer['customer_id']]);
    $customerDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customerDetails) {
        sendResponse(STATUS_FAIL, null, "Customer not found or inactive");
    }

    // Get profile image URL
    $profileImageUrl = null;
    if (!empty($customerDetails['profile_image'])) {
        $profileImageUrl = getImageUrl($customerDetails['profile_image'], 'profiles');
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
            FROM customer_sessions 
            WHERE token = ?
        ");
        $sessionStmt->execute([$token]);
        $sessionInfo = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Prepare response data to match Flutter expectations
    $responseData = [
        'user' => [
            'id' => (string)$customerDetails['id'],
            'name' => $customerDetails['name'],
            'email' => $customerDetails['email'],
            'phone' => $customerDetails['phone'],
            'profile_image' => $profileImageUrl,
            'email_verified' => (bool)$customerDetails['email_verified'],
            'is_active' => (bool)$customerDetails['is_active'],
            'total_orders' => (int)$customerDetails['total_orders'],
            'total_spent' => (float)$customerDetails['total_spent'],
            'last_order_date' => formatDateTime($customerDetails['last_order_date']),
            'created_at' => formatDateTime($customerDetails['created_at']),
            'updated_at' => formatDateTime($customerDetails['updated_at'])
        ],
        'session' => [
            'valid' => true,
            'created_at' => $sessionInfo ? formatDateTime($sessionInfo['created_at']) : null,
            'expires_at' => $sessionInfo ? formatDateTime($sessionInfo['expires_at']) : null,
            'ip_address' => $sessionInfo['ip_address'] ?? null
        ],
        // For Flutter compatibility
        'user_id' => (string)$customerDetails['id'],
        'user_email' => $customerDetails['email']
    ];

    sendResponse(STATUS_SUCCESS, $responseData, "Session is valid");

} catch (PDOException $e) {
    error_log("Session validation error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Session validation failed");
}
?>