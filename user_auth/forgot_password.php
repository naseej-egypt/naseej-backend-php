<?php
/**
 * Naseej User App - Forgot Password Endpoint (FIXED)
 * POST: /user_auth/forgot_password.php
 * Body: email
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/email_service.php"; // Include email service

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Get and validate input
$email = filterRequest("email");

// Validate required fields
if (empty($email)) {
    sendResponse(STATUS_FAIL, null, "Email is required");
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

$db = new Database();
$conn = $db->connect();

try {
    // Check if customer exists and is active
    $stmt = $conn->prepare("
        SELECT id, name, email_verified, is_active
        FROM customers
        WHERE email = ?
    ");

    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        // Don't reveal that email doesn't exist for security
        sendResponse(
            STATUS_SUCCESS, 
            ['email' => $email], 
            "If the email exists, a reset code will be sent"
        );
    }

    if (!$customer['is_active']) {
        sendResponse(STATUS_FAIL, null, "Account is deactivated. Please contact support.");
    }

    if (!$customer['email_verified']) {
        sendResponse(STATUS_FAIL, null, "Please verify your email first");
    }

    // Generate OTP (6-digit code - NUMBERS ONLY)
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Store verification record with correct type
    $stmt = $conn->prepare("
        INSERT INTO email_verifications
        (email, otp, type, expires_at, verified)
        VALUES (?, ?, 'reset_password', ?, 0)
    ");

    $stmt->execute([$email, $otp, $expiresAt]);

    // Send OTP via email
    $emailSent = sendOTPEmail($email, $customer['name'], $otp, 'reset_password');
    
    if (!$emailSent) {
        error_log("Failed to send reset OTP email to: $email");
    }

    $responseData = [
        'email' => $email,
        'expires_at' => $expiresAt,
        'message' => 'Password reset code sent successfully',
        'email_sent' => $emailSent
    ];

    // For development/testing only - REMOVE IN PRODUCTION
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
        $responseData['otp'] = $otp;
    }

    sendResponse(STATUS_SUCCESS, $responseData, "Reset code sent to your email");

} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to process reset request");
}
?>