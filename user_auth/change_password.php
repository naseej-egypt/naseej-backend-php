<?php
/**
 * Naseej User App - Change Password
 * POST: /user_profile/change_password.php
 * Body: current_password, new_password, confirm_password
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get and validate input
    $currentPassword = filterRequest("current_password");
    $newPassword = filterRequest("new_password");
    $confirmPassword = filterRequest("confirm_password");
    
    // Validate required fields
    $errors = validateRequired(['current_password', 'new_password', 'confirm_password']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate new password length
    if (strlen($newPassword) < 6) {
        sendResponse(STATUS_FAIL, null, "New password must be at least 6 characters");
    }
    
    // Validate password confirmation
    if ($newPassword !== $confirmPassword) {
        sendResponse(STATUS_FAIL, null, "New passwords do not match");
    }
    
    // Get current password hash from database
    $stmt = $conn->prepare("SELECT password FROM customers WHERE id = ?");
    $stmt->execute([$customer['customer_id']]);
    $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customerData) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $customerData['password'])) {
        sendResponse(STATUS_FAIL, null, "Current password is incorrect");
    }
    
    // Hash new password
    $hashedPassword = hashPassword($newPassword);
    
    // Update password in database
    $updateStmt = $conn->prepare("
        UPDATE customers 
        SET password = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $updateStmt->execute([$hashedPassword, $customer['customer_id']]);
    
    if ($updateStmt->rowCount() > 0) {
        // Invalidate all sessions for security (force logout from all devices)
        $deleteSessionsStmt = $conn->prepare("
            DELETE FROM customer_sessions 
            WHERE customer_id = ?
        ");
        $deleteSessionsStmt->execute([$customer['customer_id']]);
        
        // Log the activity
        logActivity($conn, null, 'change_password', 'customer', $customer['customer_id'], 
                   "Customer changed password", getClientIp());
        
        sendResponse(STATUS_SUCCESS, null, "Password changed successfully. Please login again.");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to update password");
    }
    
} catch (PDOException $e) {
    error_log("Change password error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to change password");
} finally {
    $db->disconnect();
}
?>