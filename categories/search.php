<?php
/**
 * Naseej Admin - Search Categories
 * GET/POST: /categories/search.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: query (search by name or description)
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
    
    // Get search query
    $query = isset($_GET['query']) ? $_GET['query'] : (isset($_POST['query']) ? filterRequest('query') : '');
    
    if (empty($query)) {
        sendResponse(STATUS_FAIL, null, "Search query is required");
    }
    
    // Sanitize query for LIKE search
    $searchTerm = "%{$query}%";
    
    // Search categories
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.image_url,
            c.is_active,
            c.`order` as display_order,
            c.created_at,
            c.updated_at,
            COUNT(p.id) as product_count,
            COUNT(CASE WHEN p.is_active = 1 THEN 1 END) as active_product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE 
            c.name LIKE ? OR
            c.description LIKE ?
        ORDER BY 
            CASE 
                WHEN c.name LIKE ? THEN 1
                WHEN c.description LIKE ? THEN 2
                ELSE 3
            END,
            c.`order` ASC,
            c.name ASC
        LIMIT 50
    ");
    
    $stmt->execute([
        $searchTerm, 
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);
    
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
            'is_active' => (bool)$category['is_active'],
            'order' => (int)$category['display_order'],
            'product_count' => (int)$category['product_count'],
            'active_product_count' => (int)$category['active_product_count'],
            'created_at' => formatDateTime($category['created_at']),
            'updated_at' => formatDateTime($category['updated_at'])
        ];
    }
    
    // Get search statistics
    $activeCount = 0;
    $inactiveCount = 0;
    
    foreach ($processedCategories as $category) {
        if ($category['is_active']) {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedCategories),
        'statistics' => [
            'total' => count($processedCategories),
            'active' => $activeCount,
            'inactive' => $inactiveCount
        ],
        'categories' => $processedCategories
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search categories error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search categories");
}
?>