<?php
/**
 * Naseej Admin - Delete Admin Profile Image
 * POST: /admin/delete_profile_image.php
 * Headers: Authorization: Bearer <token>
 * 
 * Removes the authenticated admin's profile image
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
    
    // Get current profile image
    $stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id = ?");
    $stmt->execute([$admin['admin_id']]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentImage = $currentData['profile_image'] ?? '';
    
    // Check if profile image exists
    if (empty($currentImage) || $currentImage === 'fail') {
        sendResponse(STATUS_FAIL, null, "No profile image to delete");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update database to remove image reference
        $updateStmt = $conn->prepare("
            UPDATE admins 
            SET profile_image = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$admin['admin_id']]);
        
        // Delete image file
        deleteFile(PROFILES_DIR, $currentImage);
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'admin',
            $admin['admin_id'],
            "Deleted profile image"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Profile image deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete profile image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while deleting profile image");
}
?>