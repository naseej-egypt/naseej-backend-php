<?php
/**
 * Naseej Admin - Add New Category
 * POST: /categories/add.php
 * Headers: Authorization: Bearer <token>
 * Body: name, description, is_active (optional)
 * Files: file (optional - category image)
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
    $name = filterRequest("name");
    $description = filterRequest("description");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Validate required fields
    $errors = validateRequired(['name', 'description']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Category name must be between 2 and 100 characters");
    }
    
    // Check if category name already exists
    $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
    $checkStmt->execute([$name]);
    if ($checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Category with this name already exists");
    }
    
    // Handle image upload
    $imageName = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $imageName = uploadImage('file', CATEGORIES_DIR);
        if ($imageName === 'fail') {
            sendResponse(STATUS_FAIL, null, "Failed to upload image. Check file size and format.");
        }
    }
    
    // Get the next order number
    $orderStmt = $conn->query("SELECT COALESCE(MAX(`order`), 0) + 1 as next_order FROM categories");
    $nextOrder = $orderStmt->fetch(PDO::FETCH_ASSOC)['next_order'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert category
        $stmt = $conn->prepare("
            INSERT INTO categories 
            (name, description, image_url, is_active, `order`, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $name,
            $description,
            $imageName,
            $isActive,
            $nextOrder
        ]);
        
        $categoryId = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity($conn, $admin['admin_id'], 'create', 'category', $categoryId, "Created category: $name");
        
        // Get created category
        $getStmt = $conn->prepare("
            SELECT 
                id,
                name,
                description,
                image_url,
                is_active,
                `order` as display_order,
                created_at
            FROM categories 
            WHERE id = ?
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
            'created_at' => formatDateTime($category['created_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Category added successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded image if transaction failed
        if ($imageName) {
            deleteFile(CATEGORIES_DIR, $imageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Add category error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add category");
}
?>