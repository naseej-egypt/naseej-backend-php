<?php
/**
 * Naseej Admin - Delete Category Image
 * POST: /categories/delete_image.php
 * Headers: Authorization: Bearer <token>
 * Body: category_id
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
    
    // Get category
    $stmt = $conn->prepare("SELECT id, name, image_url FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        sendResponse(STATUS_FAIL, null, "Category not found");
    }
    
    // Check if image exists
    if (empty($category['image_url'])) {
        sendResponse(STATUS_FAIL, null, "Category has no image to delete");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update database to remove image reference
        $updateStmt = $conn->prepare("
            UPDATE categories 
            SET image_url = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$categoryId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete image file
        deleteFile(CATEGORIES_DIR, $category['image_url']);
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'category',
            $categoryId,
            "Deleted image from category: {$category['name']}"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Category image deleted successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete category image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete category image");
}
?>