<?php
/**
 * Naseej Admin - Get Active Categories
 * GET/POST: /categories/get_active.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns only active categories ordered by display order
 * Used for dropdowns and filters
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Allow GET and POST methods
if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get active categories
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.image_url,
            c.order as display_order,
            c.created_at,
            COUNT(p.id) as product_count,
            COUNT(CASE WHEN p.is_active = 1 THEN 1 END) as active_product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.order ASC, c.name ASC
    ");
    
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process categories
    $processedCategories = [];
    foreach ($categories as $category) {
        $imageUrl = null;
        if (!empty($category['image_url'])) {
            $imageUrl = getImageUrl($category['image_url'], 'categories');
        }
        
        $processedCategories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'image_url' => $imageUrl,
            'order' => (int)$category['display_order'],
            'product_count' => (int)$category['product_count'],
            'active_product_count' => (int)$category['active_product_count'],
            'created_at' => formatDateTime($category['created_at'])
        ];
    }
    
    $responseData = [
        'count' => count($processedCategories),
        'categories' => $processedCategories
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get active categories error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve active categories");
}
?>