<?php
/**
 * Naseej Admin - Upload Category Image
 * POST: /categories/upload_image.php
 * Headers: Authorization: Bearer <token>
 * Body: category_id
 * Files: file (category image)
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
    
    // Get category ID
    $categoryId = filterRequest("category_id");
    
    if (empty($categoryId)) {
        sendResponse(STATUS_FAIL, null, "Category ID is required");
    }
    
    // Check if category exists
    $stmt = $conn->prepare("SELECT id, name, image_url FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        sendResponse(STATUS_FAIL, null, "Category not found");
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(STATUS_FAIL, null, "No file uploaded or upload error occurred");
    }
    
    // Get current image
    $currentImage = $category['image_url'];
    
    // Upload new image
    $newImageName = uploadImage('file', CATEGORIES_DIR);
    
    if ($newImageName === 'fail') {
        sendResponse(STATUS_FAIL, null, "Failed to upload image. Check file size and format.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update database with new image
        $updateStmt = $conn->prepare("
            UPDATE categories 
            SET image_url = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newImageName, $categoryId]);
        
        // Delete old image if exists
        if (!empty($currentImage) && $currentImage !== 'fail') {
            deleteFile(CATEGORIES_DIR, $currentImage);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Generate image URL
        $imageUrl = getImageUrl($newImageName, 'categories');
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'category',
            $categoryId,
            "Updated image for category: {$category['name']}"
        );
        
        $responseData = [
            'category_id' => $categoryId,
            'image_url' => $imageUrl,
            'image_name' => $newImageName
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Category image uploaded successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded image if database update failed
        if (!empty($newImageName) && $newImageName !== 'fail') {
            deleteFile(CATEGORIES_DIR, $newImageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Upload category image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to upload category image");
}
?>