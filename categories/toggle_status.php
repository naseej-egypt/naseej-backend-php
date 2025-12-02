<?php
/**
 * Naseej Admin - Toggle Category Active Status
 * POST: /categories/toggle_status.php
 * Headers: Authorization: Bearer <token>
 * Body: category_id, is_active (1 or 0)
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
    
    // Get input
    $categoryId = filterRequest("category_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($categoryId)) {
        sendResponse(STATUS_FAIL, null, "Category ID is required");
    }
    
    if ($isActive === null) {
        sendResponse(STATUS_FAIL, null, "Status value is required");
    }
    
    // Validate status value
    if ($isActive !== 0 && $isActive !== 1) {
        sendResponse(STATUS_FAIL, null, "Status must be 0 or 1");
    }
    
    // Check if category exists
    $checkStmt = $conn->prepare("SELECT id, name, is_active FROM categories WHERE id = ?");
    $checkStmt->execute([$categoryId]);
    $category = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        sendResponse(STATUS_FAIL, null, "Category not found");
    }
    
    // Check if status is already the same
    if ((int)$category['is_active'] === $isActive) {
        sendResponse(STATUS_SUCCESS, null, "Category status is already " . ($isActive ? "active" : "inactive"));
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE categories 
        SET is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $categoryId]);
    
    // Log activity
    $statusText = $isActive ? "activated" : "deactivated";
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'category',
        $categoryId,
        "Category {$statusText}: {$category['name']}"
    );
    
    $responseData = [
        'id' => $categoryId,
        'is_active' => (bool)$isActive
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Category status updated successfully");
    
} catch (PDOException $e) {
    error_log("Toggle category status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update category status");
}
?>