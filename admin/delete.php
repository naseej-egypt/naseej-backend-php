<?php
/**
 * Naseej Admin - Delete Admin
 * POST: /admin/delete.php
 * Headers: Authorization: Bearer <token>
 * Body: admin_id
 * 
 * Deletes an admin account (super_admin only)
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
    
    if (empty($adminId)) {
        sendResponse(STATUS_FAIL, null, "Admin ID is required");
    }
    
    // Prevent self-deletion
    if ($adminId == $admin['admin_id']) {
        sendResponse(STATUS_FAIL, null, "Cannot delete your own account");
    }
    
    // Get admin info for logging
    $infoStmt = $conn->prepare("SELECT name, email FROM admins WHERE id = ?");
    $infoStmt->execute([$adminId]);
    $adminInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminInfo) {
        sendResponse(STATUS_FAIL, null, "Admin not found");
    }
    
    // Delete admin
    $deleteStmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $deleteStmt->execute([$adminId]);
    
    if ($deleteStmt->rowCount() > 0) {
        // Also delete any active sessions
        $deleteSessionsStmt = $conn->prepare("DELETE FROM admin_sessions WHERE admin_id = ?");
        $deleteSessionsStmt->execute([$adminId]);
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'admin',
            $adminId,
            "Deleted admin: {$adminInfo['name']} ({$adminInfo['email']})"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Admin deleted successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to delete admin");
    }
    
} catch (PDOException $e) {
    error_log("Delete admin error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while deleting admin");
}
?>