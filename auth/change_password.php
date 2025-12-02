<?php
/**
 * Naseej Admin - Change Password Endpoint
 * POST: /auth/change_password.php
 * Headers: Authorization: Bearer <token>
 * Body: current_password, new_password, confirm_password
 * 
 * Allows authenticated admin to change their password
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
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
        sendResponse(STATUS_FAIL, null, "New password and confirmation do not match");
    }
    
    // Check if new password is same as current
    if ($currentPassword === $newPassword) {
        sendResponse(STATUS_FAIL, null, "New password must be different from current password");
    }
    
    // Get current password hash from database
    $stmt = $conn->prepare("
        SELECT password 
        FROM admins 
        WHERE id = ?
    ");
    $stmt->execute([$admin['admin_id']]);
    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminData) {
        sendResponse(STATUS_FAIL, null, "Admin not found");
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $adminData['password'])) {
        sendResponse(STATUS_FAIL, null, "Current password is incorrect");
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("
        UPDATE admins 
        SET password = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$newPasswordHash, $admin['admin_id']]);
    
    if ($updateStmt->rowCount() > 0) {
        // Log activity
        logActivity(
            $conn, 
            $admin['admin_id'], 
            'change_password', 
            'admin', 
            $admin['admin_id'], 
            'Password changed'
        );
        
        sendResponse(STATUS_SUCCESS, null, "Password changed successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to update password");
    }
    
} catch (PDOException $e) {
    error_log("Change password error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while changing password");
}
?>