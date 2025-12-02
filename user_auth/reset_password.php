<?php
/**
 * Naseej User App - Reset Password Endpoint (WITH DEBUG)
 * POST: /user_auth/reset_password.php
 * Body: email, reset_token, new_password, confirm_password
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
$resetToken = filterRequest("reset_token");
$newPassword = filterRequest("new_password");
$confirmPassword = filterRequest("confirm_password");

// 🔍 DEBUG: Log what we received
error_log("=== RESET PASSWORD DEBUG ===");
error_log("Email: " . $email);
error_log("Reset Token: " . $resetToken);
error_log("Reset Token Length: " . strlen($resetToken));
error_log("New Password Length: " . strlen($newPassword));

// Validate required fields
$errors = validateRequired(['email', 'reset_token', 'new_password', 'confirm_password']);
if (!empty($errors)) {
    error_log("Validation errors: " . implode(", ", $errors));
    sendResponse(STATUS_FAIL, null, implode(", ", $errors));
}

// Validate email format
if (!isValidEmail($email)) {
    error_log("Invalid email format: " . $email);
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Validate reset token format (must be 6 digits)
if (!preg_match('/^\d{6}$/', $resetToken)) {
    error_log("Invalid token format. Token: " . $resetToken);
    sendResponse(STATUS_FAIL, null, "Invalid reset token format. Must be 6 digits.");
}

// Validate password length
if (strlen($newPassword) < 6) {
    error_log("Password too short");
    sendResponse(STATUS_FAIL, null, "Password must be at least 6 characters");
}

// Validate password confirmation
if ($newPassword !== $confirmPassword) {
    error_log("Passwords don't match");
    sendResponse(STATUS_FAIL, null, "Passwords do not match");
}

$db = new Database();
$conn = $db->connect();

try {
    // 🔍 DEBUG: Check what's in the database for this email
    $debugStmt = $conn->prepare("
        SELECT id, otp, type, verified, expires_at, created_at
        FROM email_verifications 
        WHERE email = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $debugStmt->execute([$email]);
    $allRecords = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All recent records for " . $email . ": " . json_encode($allRecords));
    
    // Try to find the token
    $stmt = $conn->prepare("
        SELECT id, otp, type, verified, expires_at
        FROM email_verifications
        WHERE email = ? 
        AND otp = ? 
        AND type = 'reset_password'
        AND verified = 1 
        AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $resetToken]);
    $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Token record found: " . ($tokenRecord ? "YES" : "NO"));
    if ($tokenRecord) {
        error_log("Token details: " . json_encode($tokenRecord));
    }
    
    if (!$tokenRecord) {
        // Check different scenarios
        
        // 1. Check if OTP exists at all
        $checkOtpStmt = $conn->prepare("
            SELECT id, type, verified, expires_at
            FROM email_verifications 
            WHERE email = ? 
            AND otp = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $checkOtpStmt->execute([$email, $resetToken]);
        $otpExists = $checkOtpStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otpExists) {
            error_log("OTP doesn't exist in database");
            sendResponse(STATUS_FAIL, null, "Invalid reset token. The code doesn't match our records.");
        }
        
        error_log("OTP exists but doesn't match criteria: " . json_encode($otpExists));
        
        // 2. Check if it's the wrong type
        if ($otpExists['type'] !== 'reset_password') {
            error_log("Wrong type: " . $otpExists['type']);
            sendResponse(STATUS_FAIL, null, "Invalid token type. Expected reset_password, got " . $otpExists['type']);
        }
        
        // 3. Check if it's not verified
        if ($otpExists['verified'] != 1) {
            error_log("Token not verified");
            sendResponse(STATUS_FAIL, null, "Token not verified. Please verify the OTP first.");
        }
        
        // 4. Check if expired
        if ($otpExists['expires_at'] <= date('Y-m-d H:i:s')) {
            error_log("Token expired at: " . $otpExists['expires_at']);
            sendResponse(STATUS_FAIL, null, "Reset token has expired. Please request a new password reset.");
        }
        
        // If we got here, something else is wrong
        sendResponse(STATUS_FAIL, null, "Token validation failed for unknown reason.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update customer password
        $updateStmt = $conn->prepare("
            UPDATE customers
            SET password = ?, updated_at = NOW()
            WHERE email = ?
        ");
        $updated = $updateStmt->execute([$hashedPassword, $email]);
        error_log("Password updated: " . ($updated ? "YES" : "NO"));
        
        // Invalidate used reset token
        $invalidateStmt = $conn->prepare("
            UPDATE email_verifications
            SET verified = 0
            WHERE id = ?
        ");
        $invalidateStmt->execute([$tokenRecord['id']]);
        error_log("Token invalidated: ID " . $tokenRecord['id']);
        
        // Invalidate all other tokens for this email
        $invalidateAllStmt = $conn->prepare("
            UPDATE email_verifications
            SET verified = 0
            WHERE email = ? AND type IN ('reset_password', 'reset_token')
        ");
        $invalidateAllStmt->execute([$email]);
        
        // Commit transaction
        $conn->commit();
        
        error_log("Password reset successful for: " . $email);
        sendResponse(STATUS_SUCCESS, ['email' => $email], "Password reset successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Transaction error: " . $e->getMessage());
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Password reset failed: " . $e->getMessage());
}
?>