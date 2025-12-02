<?php
/**
 * FILE 1: /favorites/get_favorites.php
 * Get user's favorite products
 */
include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

$db = new Database();
$conn = $db->connect();

try {
    // Get customer_id from POST request (no auth verification)
    $customerId = (int)($_POST['customer_id'] ?? 0);
    
    if (!$customerId) {
        sendResponse(STATUS_FAIL, null, "Customer ID is required");
    }
    
    // Get favorites with product details
    $stmt = $conn->prepare("
        SELECT 
            f.id as favorite_id,
            f.created_at as added_at,
            p.id as product_id,
            p.name,
            p.description,
            p.price,
            p.discount_price,
            p.stock,
            p.images,
            c.name as category_name
        FROM favorites f
        INNER JOIN products p ON f.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE f.customer_id = ? AND p.is_active = 1
        ORDER BY f.created_at DESC
    ");
    
    $stmt->execute([$customerId]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedFavorites = [];
    
    foreach ($favorites as $favorite) {
        // Parse images
        $images = [];
        if (!empty($favorite['images'])) {
            $imageArray = json_decode($favorite['images'], true);
            if (is_array($imageArray)) {
                foreach ($imageArray as $img) {
                    $images[] = getImageUrl($img, 'products');
                }
            }
        }
        
        $finalPrice = $favorite['discount_price'] ?: $favorite['price'];
        $discountPercent = 0;
        if ($favorite['discount_price'] && $favorite['price'] > 0) {
            $discountPercent = round((($favorite['price'] - $favorite['discount_price']) / $favorite['price']) * 100);
        }
        
        $processedFavorites[] = [
            'favorite_id' => (int)$favorite['favorite_id'],
            'product_id' => (int)$favorite['product_id'],
            'name' => $favorite['name'],
            'description' => $favorite['description'],
            'price' => (float)$favorite['price'],
            'discount_price' => $favorite['discount_price'] ? (float)$favorite['discount_price'] : null,
            'discount_percent' => $discountPercent,
            'final_price' => (float)$finalPrice,
            'stock' => (int)$favorite['stock'],
            'in_stock' => $favorite['stock'] > 0,
            'images' => $images,
            'category_name' => $favorite['category_name'],
            'added_at' => formatDateTime($favorite['added_at'])
        ];
    }
    
    $responseData = [
        'favorites' => $processedFavorites,
        'total' => count($processedFavorites)
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get favorites error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve favorites");
}
?>