<?php
// ============= get_new_arrivals.php (Updated) =============
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
    $page = isset($_POST['page']) ? (int)$_POST['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 20);
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            p.id, p.name, p.description, p.price, p.discount_price, p.stock,
            p.category_id, c.name as category_name, p.images, p.is_active,
            p.specifications, p.colors, p.sizes, p.created_at,
            DATEDIFF(NOW(), p.created_at) as days_since_added
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND c.is_active = 1
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$limit, $offset]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND c.is_active = 1
    ");
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $processedProducts = [];
    foreach ($products as $product) {
        $images = parseJsonArray($product['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        $discountPercentage = null;
        if ($product['discount_price'] && $product['discount_price'] < $product['price']) {
            $discountPercentage = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
        }
        
        $isNew = $product['days_since_added'] <= 30;
        
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
            'specifications' => parseJsonArray($product['specifications']),
            'colors' => parseJsonArray($product['colors']),
            'sizes' => parseJsonArray($product['sizes']),
            'is_active' => (bool)$product['is_active'],
            'created_at' => formatDateTime($product['created_at']),
            'days_since_added' => (int)$product['days_since_added'],
            'is_new' => $isNew
        ];
    }
    
    $totalPages = ceil($totalCount / $limit);
    echo json_encode([
        'status' => 'success',
        'data' => $processedProducts,
        'count' => count($processedProducts),
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
} catch (PDOException $e) {
    error_log("Get new arrivals error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve new arrivals']);
}
?>