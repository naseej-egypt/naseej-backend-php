<?php
/**
 * Naseej User App - Login Endpoint
 * POST: /user_auth/login.php
 * Body: email, password
 */

// Include required files
include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

// Handle CORS
handleCorsHeaders();

// Check request method
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Get and validate input
$email = filterRequest("email");
$password = filterRequest("password");

// Validate required fields
if (empty($email) || empty($password)) {
    sendResponse(STATUS_FAIL, null, "Email and password are required");
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Find customer by email
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            password,
            profile_image,
            email_verified,
            is_active,
            total_orders,
            total_spent,
            last_order_date
        FROM customers 
        WHERE email = ?
    ");
    
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if customer exists
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Invalid email or password");
    }

    // Check if email is verified
    if (!$customer['email_verified']) {
        sendResponse(STATUS_FAIL, null, "Please verify your email before logging in");
    }

    // Check if account is active
    if (!$customer['is_active']) {
        sendResponse(STATUS_FAIL, null, "Account is deactivated. Please contact support.");
    }

    // Verify password
    if (!password_verify($password, $customer['password'])) {
        sendResponse(STATUS_FAIL, null, "Invalid email or password");
    }

    // Create session token
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . USER_TOKEN_EXPIRY_HOURS . ' hours'));

    // Insert session into database
    $sessionStmt = $conn->prepare("
        INSERT INTO customer_sessions 
        (customer_id, token, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $sessionStmt->execute([
        $customer['id'],
        $token,
        getClientIp(),
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        $expiresAt
    ]);

    // Clean expired sessions for this user
    $cleanStmt = $conn->prepare("
        DELETE FROM customer_sessions 
        WHERE customer_id = ? AND expires_at < NOW()
    ");
    $cleanStmt->execute([$customer['id']]);

    // Get profile image URL if exists
    $profileImage = null;
    if (!empty($customer['profile_image']) && $customer['profile_image'] !== 'fail') {
        $profileImage = IMAGE_BASE_URL . 'profiles/' . $customer['profile_image'];
    }

    // Prepare response data
    $responseData = [
        'user' => [
            'id' => (string)$customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'profile_image' => $profileImage,
            'total_orders' => (int)$customer['total_orders'],
            'total_spent' => (float)$customer['total_spent'],
            'last_order_date' => $customer['last_order_date']
        ],
        'token' => $token,
        'expires_at' => $expiresAt
    ];

    // Send success response
    sendResponse(STATUS_SUCCESS, $responseData, "Login successful");

} catch (PDOException $e) {
    // Log error
    error_log("Login error: " . $e->getMessage());
    
    // Send error response
    sendResponse(STATUS_ERROR, null, "Login failed. Please try again.");
}
?>