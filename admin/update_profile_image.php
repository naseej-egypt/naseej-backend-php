<?php
/**
 * Naseej Admin - Update Admin Profile Image
 * POST: /admin/update_profile_image.php
 * Headers: Authorization: Bearer <token>
 * Body: file (image file)
 * 
 * Uploads and updates the authenticated admin's profile image
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
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(STATUS_FAIL, null, "No file uploaded or upload error occurred");
    }
    
    // Get current profile image
    $stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id = ?");
    $stmt->execute([$admin['admin_id']]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentImage = $currentData['profile_image'] ?? '';
    
    // Upload new profile image
    $newImageName = uploadImage('file', PROFILES_DIR, MAX_PROFILE_SIZE);
    
    if ($newImageName === 'fail') {
        sendResponse(STATUS_FAIL, null, "Failed to upload image. Please check file size and format.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update database with new image
        $updateStmt = $conn->prepare("
            UPDATE admins 
            SET profile_image = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newImageName, $admin['admin_id']]);
        
        // Delete old image if exists
        if (!empty($currentImage) && $currentImage !== 'fail') {
            deleteFile(PROFILES_DIR, $currentImage);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Generate image URL
        $imageUrl = getImageUrl($newImageName, 'profiles');
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'admin',
            $admin['admin_id'],
            "Updated profile image"
        );
        
        // Prepare response
        $responseData = [
            'profile_image' => $imageUrl,
            'image_name' => $newImageName
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Profile image updated successfully");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        // Delete uploaded image if database update failed
        if (!empty($newImageName) && $newImageName !== 'fail') {
            deleteFile(PROFILES_DIR, $newImageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Update profile image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while updating profile image");
}
?>