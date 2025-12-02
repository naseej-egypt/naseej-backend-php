<?php
/**
 * Naseej User App - Verify Reset OTP Endpoint (WITH DEBUG)
 * POST: /user_auth/verify_reset_otp.php
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

// 🔍 DEBUG
error_log("=== VERIFY RESET OTP DEBUG ===");
error_log("Email: " . $email);
error_log("OTP: " . $otp);

// Validate required fields
$errors = validateRequired(['email', 'otp']);
if (!empty($errors)) {
    sendResponse(STATUS_FAIL, null, implode(", ", $errors));
}

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(STATUS_FAIL, null, "Invalid email format");
}

// Validate OTP format (must be 6 digits)
if (!preg_match('/^\d{6}$/', $otp)) {
    sendResponse(STATUS_FAIL, null, "Invalid OTP format. Must be 6 digits.");
}

$db = new Database();
$conn = $db->connect();

try {
    // 🔍 DEBUG: Check what exists
    $debugStmt = $conn->prepare("
        SELECT id, otp, type, verified, expires_at, created_at
        FROM email_verifications 
        WHERE email = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $debugStmt->execute([$email]);
    $allRecords = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All records before verification: " . json_encode($allRecords));
    
    // Find valid verification record
    $stmt = $conn->prepare("
        SELECT id, expires_at
        FROM email_verifications
        WHERE email = ? 
        AND otp = ? 
        AND type = 'reset_password' 
        AND verified = 0 
        AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $otp]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Verification found: " . ($verification ? "YES - ID: " . $verification['id'] : "NO"));
    
    if (!$verification) {
        // Check if OTP exists but expired
        $expiredStmt = $conn->prepare("
            SELECT id, verified, expires_at
            FROM email_verifications 
            WHERE email = ? 
            AND otp = ? 
            AND type = 'reset_password'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $expiredStmt->execute([$email, $otp]);
        $expiredCheck = $expiredStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($expiredCheck) {
            error_log("OTP exists but: verified=" . $expiredCheck['verified'] . ", expires_at=" . $expiredCheck['expires_at']);
            
            if ($expiredCheck['verified'] == 1) {
                error_log("OTP already used!");
                sendResponse(STATUS_FAIL, null, "This verification code has already been used.");
            }
            
            if ($expiredCheck['expires_at'] <= date('Y-m-d H:i:s')) {
                sendResponse(STATUS_FAIL, null, "Verification code has expired. Please request a new one.");
            }
        } else {
            error_log("OTP doesn't exist at all!");
        }
        
        sendResponse(STATUS_FAIL, null, "Invalid verification code. Please check and try again.");
    }
    
    // Mark verification as used
    $updateStmt = $conn->prepare("
        UPDATE email_verifications
        SET verified = 1
        WHERE id = ?
    ");
    $updateStmt->execute([$verification['id']]);
    error_log("Marked OTP as verified: ID " . $verification['id']);
    
    // ✅ SIMPLIFIED: Just return the OTP itself as the reset token
    $resetToken = $otp; // Use the same OTP
    
    error_log("Returning reset token: " . $resetToken);
    
    $responseData = [
        'email' => $email,
        'reset_token' => $resetToken,
        'expires_at' => $verification['expires_at'],
        'message' => 'Verification successful. Use this token to reset your password.'
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Verification successful");
    
} catch (PDOException $e) {
    error_log("Verify reset OTP error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Verification failed");
}
?>