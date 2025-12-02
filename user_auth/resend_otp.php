<?php
/**
 * Naseej User App - Resend OTP Endpoint
 * POST: /user_auth/resend_otp.php
 * Body: email, type (signup/reset_password)
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Get and validate input
$email = filterRequest("email");
$type = filterRequest("type") ?: 'signup';

// Validate required fields
if (empty($email)) {
    sendResponse(STATUS_FAIL, null, "Email is required");
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Validate type
if (!in_array($type, ['signup', 'reset_password'])) {
    sendResponse(STATUS_FAIL, null, "Invalid verification type");
}

$db = new Database();
$conn = $db->connect();

try {
    // Check if customer exists
    $customerStmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $customerStmt->execute([$email]);
    
    if (!$customerStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(STATUS_FAIL, null, "Email not found");
    }

    // Generate new OTP
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Insert new verification record
    $stmt = $conn->prepare("
        INSERT INTO email_verifications 
        (email, otp, type, expires_at, verified) 
        VALUES (?, ?, ?, ?, 0)
    ");
    
    $stmt->execute([$email, $otp, $type, $expiresAt]);

    // In production, send OTP via email/SMS here
    // For development, we'll return the OTP in response
    $responseData = [
        'email' => $email,
        'otp' => $otp, // Remove this in production
        'type' => $type,
        'expires_at' => $expiresAt
    ];

    sendResponse(STATUS_SUCCESS, $responseData, "Verification code sent successfully");

} catch (PDOException $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to send verification code");
}
?>