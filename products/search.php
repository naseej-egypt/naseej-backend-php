<?php
/**
 * Naseej Admin - Search Products
 * GET/POST: /products/search.php
 * Headers: Authorization: Bearer <token>
 * Query/Body: query (search term)
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
    
    // Search products
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.discount_price,
            p.stock,
            p.category_id,
            c.name as category_name,
            p.images,
            p.is_active,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 
            p.name LIKE ? OR 
            p.description LIKE ? OR
            c.name LIKE ?
        ORDER BY 
            CASE 
                WHEN p.name LIKE ? THEN 1
                WHEN p.description LIKE ? THEN 2
                ELSE 3
            END,
            p.name ASC
        LIMIT 50
    ");
    
    $stmt->execute([
        $searchTerm, 
        $searchTerm, 
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process products
    $processedProducts = [];
    foreach ($products as $product) {
        // Parse images
        $images = parseJsonArray($product['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        $processedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'stock' => (int)$product['stock'],
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'images' => $imageUrls,
            'is_active' => (bool)$product['is_active'],
            'created_at' => formatDateTime($product['created_at']),
            'updated_at' => formatDateTime($product['updated_at'])
        ];
    }
    
    $responseData = [
        'query' => $query,
        'count' => count($processedProducts),
        'products' => $processedProducts
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Search products error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to search products");
}
?>