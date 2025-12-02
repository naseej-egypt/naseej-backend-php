<?php
/**
 * Naseej User - Get Active Categories
 * GET/POST: /user_shop/get_active_categories.php
 * Returns only active categories for user app
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Get active categories with product count
    $stmt = $conn->prepare("
        SELECT DISTINCT
            c.id,
            c.name,
            c.description,
            c.image_url,
            c.order as display_order,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as active_product_count
        FROM categories c
        WHERE c.is_active = 1
        HAVING active_product_count > 0
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
        
        // Get first two words of category name
        $categoryName = $category['name'];
        $words = explode(' ', $categoryName);
        $shortName = implode(' ', array_slice($words, 0, 2));
        
        $processedCategories[] = [
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'short_name' => $shortName,
            'description' => $category['description'],
            'image_url' => $imageUrl,
            'order' => (int)$category['display_order'],
            'product_count' => (int)$category['active_product_count'] // Explicit cast to int
        ];
    }
    
    $response = [
        'status' => 'success',
        'data' => $processedCategories,
        'count' => count($processedCategories)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Get active categories error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve categories'
    ]);
}
?>