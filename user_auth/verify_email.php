<?php
/**
 * Naseej User App - Verify Email Endpoint
 * POST: /user_auth/verify_email.php
 * Body: email, otp
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
$otp = filterRequest("otp");

// Validate required fields
$errors = validateRequired(['email', 'otp']);
if (!empty($errors)) {
    sendResponse(STATUS_FAIL, null, implode(", ", $errors));
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Validate OTP format
if (!preg_match('/^\d{6}$/', $otp)) {
    sendResponse(STATUS_FAIL, null, "Invalid OTP format");
}

$db = new Database();
$conn = $db->connect();

try {
    // Find valid verification record
    $stmt = $conn->prepare("
        SELECT id, expires_at 
        FROM email_verifications 
        WHERE email = ? AND otp = ? AND type = 'signup' AND verified = 0 AND expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$email, $otp]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {
        sendResponse(STATUS_FAIL, null, "Invalid or expired verification code");
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Mark verification as used
        $updateStmt = $conn->prepare("
            UPDATE email_verifications 
            SET verified = 1 
            WHERE id = ?
        ");
        $updateStmt->execute([$verification['id']]);

        // Update customer email verification status
        $customerStmt = $conn->prepare("
            UPDATE customers 
            SET email_verified = 1, updated_at = NOW() 
            WHERE email = ?
        ");
        $customerStmt->execute([$email]);

        // Commit transaction
        $conn->commit();

        sendResponse(STATUS_SUCCESS, null, "Email verified successfully");

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Email verification error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Email verification failed");
}
?>