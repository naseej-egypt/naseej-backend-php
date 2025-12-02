<?php
// ============= get_product_details.php =============
/**
 * Naseej User - Get Product Details
 * GET/POST: /user_shop/get_product_details.php
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
    $productId = isset($_GET['product_id']) ? $_GET['product_id'] : 
                 (isset($_POST['product_id']) ? filterRequest('product_id') : null);
    
    if (empty($productId)) {
        echo json_encode([
            'status' => 'fail',
            'message' => 'Product ID is required'
        ]);
        exit();
    }
    
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
            c.description as category_description,
            p.images,
            p.is_active,
            p.specifications,
            p.colors,
            p.sizes,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.is_active = 1
    ");
    
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode([
            'status' => 'fail',
            'message' => 'Product not found or inactive'
        ]);
        exit();
    }
    
    $images = parseJsonArray($product['images']);
    $imageUrls = [];
    foreach ($images as $imageName) {
        $imageUrls[] = getImageUrl($imageName, 'products');
    }
    
    $specifications = parseJsonArray($product['specifications']);
    $colors = parseJsonArray($product['colors']);
    $sizes = parseJsonArray($product['sizes']);
    
    $discountPercentage = null;
    $savings = null;
    if ($product['discount_price'] && $product['discount_price'] < $product['price']) {
        $discountPercentage = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
        $savings = $product['price'] - $product['discount_price'];
    }
    
    $finalPrice = $product['discount_price'] ?? $product['price'];
    $freeShippingEligible = $finalPrice >= 300;
    
    $relatedStmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.discount_price,
            p.images
        FROM products p
        WHERE p.category_id = ? 
            AND p.id != ? 
            AND p.is_active = 1
        ORDER BY RAND()
        LIMIT 6
    ");
    $relatedStmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedRelated = [];
    foreach ($relatedProducts as $related) {
        $relatedImages = parseJsonArray($related['images']);
        $relatedImageUrl = null;
        if (!empty($relatedImages)) {
            $relatedImageUrl = getImageUrl($relatedImages[0], 'products');
        }
        
        $processedRelated[] = [
            'product_id' => $related['id'],
            'product_title' => $related['name'],
            'price' => (float)$related['price'],
            'discount_price' => $related['discount_price'] ? (float)$related['discount_price'] : null,
            'product_image' => $relatedImageUrl
        ];
    }
    
    $responseData = [
        'product_id' => $product['id'],
        'product_title' => $product['name'],
        'product_content' => $product['description'],
        'images' => $imageUrls,
        'product_image' => !empty($imageUrls) ? $imageUrls[0] : null,
        'price' => (float)$product['price'],
        'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
        'discount_percentage' => $discountPercentage,
        'savings' => $savings ? (float)$savings : null,
        'final_price' => (float)$finalPrice,
        'stock' => (int)$product['stock'],
        'in_stock' => (int)$product['stock'] > 0,
        'category_id' => $product['category_id'],
        'category_name' => $product['category_name'],
        'category_description' => $product['category_description'],
        'specifications' => $specifications,
        'colors' => $colors,
        'sizes' => $sizes,
        'free_shipping_eligible' => $freeShippingEligible,
        'is_active' => (bool)$product['is_active'],
        'created_at' => formatDateTime($product['created_at']),
        'updated_at' => formatDateTime($product['updated_at']),
        'related_products' => $processedRelated
    ];
    
    echo json_encode([
        'status' => 'success',
        'data' => $responseData
    ]);
    
} catch (PDOException $e) {
    error_log("Get product details error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve product details'
    ]);
}
?>