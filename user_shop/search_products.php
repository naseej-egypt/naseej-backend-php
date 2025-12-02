<?php
/**
 * Naseej User - Search Products
 * GET/POST: /user_shop/search_products.php
 * Query/Body: query (search term)
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
    // Get search query
    $query = isset($_GET['query']) ? $_GET['query'] : (isset($_POST['query']) ? filterRequest('query') : '');
    
    if (empty($query)) {
        echo json_encode([
            'status' => 'fail',
            'message' => 'Search query is required'
        ]);
        exit();
    }
    
    // Sanitize query for LIKE search
    $searchTerm = "%{$query}%";
    
    // Search products (only active products and categories)
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
            p.specifications,
            p.colors,
            p.sizes,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 
            (p.name LIKE ? OR 
            p.description LIKE ? OR
            c.name LIKE ?)
            AND p.is_active = 1 
            AND c.is_active = 1
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
        $images = parseJsonArray($product['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        $specifications = parseJsonArray($product['specifications']);
        $colors = parseJsonArray($product['colors']);
        $sizes = parseJsonArray($product['sizes']);
        
        $discountPercentage = null;
        if ($product['discount_price'] && $product['discount_price'] < $product['price']) {
            $discountPercentage = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
        }
        
        $processedProducts[] = [
            'product_id' => $product['id'],
            'product_title' => $product['name'],
            'product_content' => $product['description'],
            'product_image' => !empty($imageUrls) ? $imageUrls[0] : null,
            'images' => $imageUrls,
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'discount_percentage' => $discountPercentage,
            'stock' => (int)$product['stock'],
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'specifications' => $specifications,
            'colors' => $colors,
            'sizes' => $sizes,
            'is_active' => (bool)$product['is_active'],
            'created_at' => formatDateTime($product['created_at'])
        ];
    }
    
    $response = [
        'status' => 'success',
        'query' => $query,
        'count' => count($processedProducts),
        'data' => $processedProducts
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Search products error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to search products'
    ]);
}
?>