<?php
/**
 * Naseej Admin - Delete Category
 * POST: /categories/delete.php
 * Headers: Authorization: Bearer <token>
 * Body: category_id
 * 
 * Note: Cannot delete category with existing products (enforced by trigger)
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
    // Verify authentication and require admin role
    $admin = requireRole($conn, ['super_admin', 'admin']);
    
    // Get category ID
    $categoryId = filterRequest("category_id");
    
    if (empty($categoryId)) {
        sendResponse(STATUS_FAIL, null, "Category ID is required");
    }
    
    // Get category details before deletion
    $stmt = $conn->prepare("
        SELECT 
            c.id, 
            c.name, 
            c.image_url,
            COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        sendResponse(STATUS_FAIL, null, "Category not found");
    }
    
    // Check if category has products
    if ($category['product_count'] > 0) {
        sendResponse(STATUS_FAIL, null, "Cannot delete category with existing products ({$category['product_count']} products). Move products to another category or delete them first.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Delete category
        $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $deleteStmt->execute([$categoryId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete category image from server
        if (!empty($category['image_url'])) {
            deleteFile(CATEGORIES_DIR, $category['image_url']);
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'category',
            $categoryId,
            "Deleted category: {$category['name']}"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Category deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete category error: " . $e->getMessage());
    
    // Check if error is due to foreign key constraint
    if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
        strpos($e->getMessage(), 'existing products') !== false) {
        sendResponse(STATUS_FAIL, null, "Cannot delete category with existing products");
    }
    
    sendResponse(STATUS_ERROR, null, "Failed to delete category");
}
?>