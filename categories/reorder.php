<?php
/**
 * Naseej Admin - Reorder Categories
 * POST: /categories/reorder.php
 * Headers: Authorization: Bearer <token>
 * Body: categories (JSON array of objects with id and order)
 * Example: [{"id": "1", "order": 0}, {"id": "2", "order": 1}]
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
    
    // Get categories data
    $categoriesJson = filterRequest("categories");
    
    if (empty($categoriesJson)) {
        sendResponse(STATUS_FAIL, null, "Categories data is required");
    }
    
    // Parse JSON
    $categories = json_decode($categoriesJson, true);
    
    if (!is_array($categories) || empty($categories)) {
        sendResponse(STATUS_FAIL, null, "Invalid categories data format");
    }
    
    // Validate each category entry
    foreach ($categories as $index => $cat) {
        if (!isset($cat['id']) || !isset($cat['order'])) {
            sendResponse(STATUS_FAIL, null, "Each category must have 'id' and 'order' fields");
        }
        
        if (!is_numeric($cat['order'])) {
            sendResponse(STATUS_FAIL, null, "Order must be a number");
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        $stmt = $conn->prepare("UPDATE categories SET `order` = ?, updated_at = NOW() WHERE id = ?");
        
        $updatedCount = 0;
        foreach ($categories as $cat) {
            $stmt->execute([(int)$cat['order'], $cat['id']]);
            if ($stmt->rowCount() > 0) {
                $updatedCount++;
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'category',
            null,
            "Reordered $updatedCount categories"
        );
        
        // Get updated categories list
        $getStmt = $conn->query("
            SELECT 
                id,
                name,
                `order` as display_order,
                is_active
            FROM categories 
            ORDER BY `order` ASC, name ASC
        ");
        $updatedCategories = $getStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCategories = [];
        foreach ($updatedCategories as $category) {
            $processedCategories[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'order' => (int)$category['display_order'],
                'is_active' => (bool)$category['is_active']
            ];
        }
        
        $responseData = [
            'updated_count' => $updatedCount,
            'categories' => $processedCategories
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Categories reordered successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Reorder categories error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to reorder categories");
}
?>