<?php
/**
 * Naseej User - Get All Products (Explore Page)
 * GET/POST: /user_shop/get_all_products.php
 * Query Params: category_id (optional), page, limit
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
    // Get filter parameters
    $categoryId = isset($_POST['category_id']) ? filterRequest('category_id') : 
                  (isset($_GET['category_id']) ? $_GET['category_id'] : null);
    $userId = isset($_POST['user_id']) ? filterRequest('user_id') : 
              (isset($_GET['user_id']) ? $_GET['user_id'] : null);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 
            (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 
             (isset($_GET['limit']) ? (int)$_GET['limit'] : 20);
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = ["p.is_active = 1", "c.is_active = 1"];
    $params = [];
    
    if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'All') {
        $whereClauses[] = "p.category_id = ?";
        $params[] = $categoryId;
    }
    
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get products with colors and sizes
    $sql = "
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
        $whereSQL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
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
    
    $totalPages = ceil($totalCount / $limit);
    
    $response = [
        'status' => 'success',
        'data' => $processedProducts,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Get all products error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve products'
    ]);
}
?>