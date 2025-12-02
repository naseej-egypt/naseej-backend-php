<?php
/**
 * Naseej User App - Signup Endpoint (FIXED)
 * POST: /user_auth/signup.php
 * Body: name, email, phone, password, confirm_password
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
$name = filterRequest("name");
$email = filterRequest("email");
$phone = filterRequest("phone");
$password = filterRequest("password");
$confirmPassword = filterRequest("confirm_password");

// Validate required fields
$errors = [];

if (empty($name)) {
    $errors[] = "Name is required";
}

if (empty($email)) {
    $errors[] = "Email is required";
} elseif (!isValidEmail($email)) {
    $errors[] = "Invalid email format";
}

if (empty($phone)) {
    $errors[] = "Phone number is required";
} elseif (!isValidPhone($phone)) {
    $errors[] = "Invalid phone number format";
}

if (empty($password)) {
    $errors[] = "Password is required";
} elseif (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters";
}

if (empty($confirmPassword)) {
    $errors[] = "Confirm password is required";
} elseif ($password !== $confirmPassword) {
    $errors[] = "Passwords do not match";
}

if (!empty($errors)) {
    sendResponse(STATUS_FAIL, null, implode(", ", $errors));
}

$db = new Database();
$conn = $db->connect();

try {
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $checkStmt->execute([$email]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(STATUS_FAIL, null, "Email already registered");
    }

    // Check if phone already exists
    $checkStmt = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
    $checkStmt->execute([$phone]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(STATUS_FAIL, null, "Phone number already registered");
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Hash password
        $hashedPassword = hashPassword($password);

        // Insert customer
        $insertStmt = $conn->prepare("
            INSERT INTO customers
            (name, email, phone, password, email_verified, is_active)
            VALUES (?, ?, ?, ?, 0, 1)
        ");

        $insertStmt->execute([$name, $email, $phone, $hashedPassword]);
        $customerId = $conn->lastInsertId();

        // Generate OTP (6-digit code - NUMBERS ONLY)
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Store verification record with correct type
        $otpStmt = $conn->prepare("
            INSERT INTO email_verifications
            (email, otp, type, expires_at, verified)
            VALUES (?, ?, 'signup', ?, 0)
        ");

        $otpStmt->execute([$email, $otp, $expiresAt]);

        // Commit transaction
        $conn->commit();

        // Send OTP via email
        $emailSent = sendOTPEmail($email, $name, $otp, 'signup');
        
        if (!$emailSent) {
            error_log("Failed to send OTP email to: $email");
            // Don't fail the registration, just log it
        }

        $responseData = [
            'customer_id' => (string)$customerId,
            'email' => $email,
            'name' => $name,
            'expires_at' => $expiresAt,
            'message' => 'Account created successfully. Please check your email for verification code.',
            'email_sent' => $emailSent
        ];

        // For development/testing only - REMOVE IN PRODUCTION
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
            $responseData['otp'] = $otp;
        }

        sendResponse(
            STATUS_SUCCESS, 
            $responseData, 
            "Registration successful. Verification code sent to your email."
        );

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Signup transaction error: " . $e->getMessage());
        sendResponse(STATUS_ERROR, null, "Registration failed. Please try again.");
    }

} catch (PDOException $e) {
    error_log("Signup error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Registration failed. Please try again.");
}
?>