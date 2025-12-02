<?php
/**
 * Naseej Admin - Login Endpoint
 * POST: /auth/login.php
 * Body: email, password
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Get and validate input
$email = filterRequest("email");
$password = filterRequest("password");

// Validate required fields
$errors = validateRequired(['email', 'password']);
if (!empty($errors)) {
    sendResponse(STATUS_FAIL, null, implode(", ", $errors));
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Find admin by email
    $stmt = $conn->prepare("
        SELECT 
            id,
            email,
            password,
            name,
            phone_number,
            role,
            is_active,
            last_login
        FROM admins 
        WHERE email = ?
    ");
    
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if admin exists
    if (!$admin) {
        sendResponse(STATUS_FAIL, null, "Invalid email or password");
    }
    
    // Check if admin is active
    if (!$admin['is_active']) {
        sendResponse(STATUS_FAIL, null, "Account is deactivated. Please contact administrator.");
    }
    
    // Verify password
    if (!password_verify($password, $admin['password'])) {
        sendResponse(STATUS_FAIL, null, "Invalid email or password");
    }
    
    // Create session token
    $token = createSession($conn, $admin['id']);
    
    // Clean expired sessions (optional, can be done in cron job)
    cleanExpiredSessions($conn);
    
    // Prepare response data
    $responseData = [
        'admin' => [
            'id' => $admin['id'],
            'email' => $admin['email'],
            'name' => $admin['name'],
            'phone_number' => $admin['phone_number'],
            'role' => $admin['role'],
            'last_login' => formatDateTime($admin['last_login'])
        ],
        'token' => $token
    ];
    
    // Log activity
    logActivity($conn, $admin['id'], 'login', 'admin', $admin['id'], 'Admin logged in');
    
    sendResponse(STATUS_SUCCESS, $responseData, "Login successful");
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred. Please try again.");
}
?>