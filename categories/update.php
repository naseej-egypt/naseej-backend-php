<?php
/**
 * Naseej Admin - Update Category
 * POST: /categories/update.php
 * Headers: Authorization: Bearer <token>
 * Body: category_id, name, description, is_active (optional)
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
    $categoryId = filterRequest("category_id");
    $name = filterRequest("name");
    $description = filterRequest("description");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    // Validate required fields
    $errors = validateRequired(['category_id', 'name', 'description']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if category exists
    $checkStmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $checkStmt->execute([$categoryId]);
    $existingCategory = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingCategory) {
        sendResponse(STATUS_FAIL, null, "Category not found");
    }
    
    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Category name must be between 2 and 100 characters");
    }
    
    // Check if name already exists (excluding current category)
    $nameCheckStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $nameCheckStmt->execute([$name, $categoryId]);
    if ($nameCheckStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Category with this name already exists");
    }
    
    // Update category
    $updateFields = [
        "name = ?",
        "description = ?",
        "updated_at = NOW()"
    ];
    
    $params = [$name, $description];
    
    if ($isActive !== null) {
        $updateFields[] = "is_active = ?";
        $params[] = $isActive;
    }
    
    $params[] = $categoryId;
    
    $stmt = $conn->prepare("
        UPDATE categories 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    // Log activity
    logActivity($conn, $admin['admin_id'], 'update', 'category', $categoryId, "Updated category: $name");
    
    // Get updated category
    $getStmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.image_url,
            c.is_active,
            c.`order` as display_order,
            c.updated_at,
            COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $getStmt->execute([$categoryId]);
    $category = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $imageUrl = null;
    if (!empty($category['image_url'])) {
        $imageUrl = getImageUrl($category['image_url'], 'categories');
    }
    
    $responseData = [
        'id' => $category['id'],
        'name' => $category['name'],
        'description' => $category['description'],
        'image_url' => $imageUrl,
        'is_active' => (bool)$category['is_active'],
        'order' => (int)$category['display_order'],
        'product_count' => (int)$category['product_count'],
        'updated_at' => formatDateTime($category['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Category updated successfully");
    
} catch (PDOException $e) {
    error_log("Update category error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update category");
}
?>