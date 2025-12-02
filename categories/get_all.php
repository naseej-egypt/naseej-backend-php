<?php
/**
 * Naseej Admin - Get All Categories
 * GET/POST: /categories/get_all.php
 * Headers: Authorization: Bearer <token>
 * QueryParams (optional): 
 *   - is_active: Filter by status (1/0)
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
    
    // Get filter parameter
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : (isset($_POST['is_active']) ? $_POST['is_active'] : null);
    
    // Build WHERE clause
    $whereSQL = "";
    $params = [];
    
    if ($isActive !== null && $isActive !== '') {
        $whereSQL = "WHERE is_active = ?";
        $params[] = (int)$isActive;
    }
    
    // Get categories with product count
    $sql = "
        SELECT 
            c.id,
            c.name,
            c.description,
            c.image_url,
            c.is_active,
            c.order as display_order,
            c.created_at,
            c.updated_at,
            COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        $whereSQL
        GROUP BY c.id
        ORDER BY c.order ASC, c.name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each category
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
            'is_active' => (bool)$category['is_active'],
            'order' => (int)$category['display_order'],
            'product_count' => (int)$category['product_count'],
            'created_at' => formatDateTime($category['created_at']),
            'updated_at' => formatDateTime($category['updated_at'])
        ];
    }
    
    $responseData = [
        'total_count' => count($processedCategories),
        'categories' => $processedCategories
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get all categories error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve categories");
}
?>