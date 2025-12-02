<?php
/**
 * Naseej Admin - Change Admin Password
 * POST: /admin/change_admin_password.php
 * Headers: Authorization: Bearer <token>
 * Body: admin_id, new_password
 * 
 * Changes another admin's password (super_admin only)
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
    // Verify authentication and require super_admin role
    $admin = requireRole($conn, ['super_admin']);
    
    // Get and validate input
    $adminId = filterRequest("admin_id");
    $newPassword = filterRequest("new_password");
    
    // Validate required fields
    $errors = validateRequired(['admin_id', 'new_password']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Prevent changing own password through this endpoint
    if ($adminId == $admin['admin_id']) {
        sendResponse(STATUS_FAIL, null, "Use the change password feature for your own account");
    }
    
    // Validate password length
    if (strlen($newPassword) < 6) {
        sendResponse(STATUS_FAIL, null, "Password must be at least 6 characters");
    }
    
    // Check if admin exists
    $checkStmt = $conn->prepare("SELECT name, email FROM admins WHERE id = ?");
    $checkStmt->execute([$adminId]);
    $adminInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminInfo) {
        sendResponse(STATUS_FAIL, null, "Admin not found");
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("
        UPDATE admins 
        SET password = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $updateStmt->execute([$newPasswordHash, $adminId]);
    
    if ($updateStmt->rowCount() > 0) {
        // Delete all sessions for this admin (force logout)
        $deleteSessionsStmt = $conn->prepare("DELETE FROM admin_sessions WHERE admin_id = ?");
        $deleteSessionsStmt->execute([$adminId]);
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'change_password',
            'admin',
            $adminId,
            "Changed password for admin: {$adminInfo['name']} ({$adminInfo['email']})"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Admin password changed successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to change password");
    }
    
} catch (PDOException $e) {
    error_log("Change admin password error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while changing password");
}
?>