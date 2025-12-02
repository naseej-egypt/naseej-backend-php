<?php
/**
 * Naseej Admin - Get Low Stock Products
 * GET/POST: /products/get_low_stock.php
 * Headers: Authorization: Bearer <token>
 * Query/Body (optional): threshold (default 10)
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
    
    // Get threshold (default 10)
    $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : (isset($_POST['threshold']) ? (int)filterRequest('threshold') : 10);
    
    if ($threshold < 0) {
        $threshold = 10;
    }
    
    // Get low stock products
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
        WHERE p.stock <= ? AND p.is_active = 1
        ORDER BY p.stock ASC, p.name ASC
    ");
    
    $stmt->execute([$threshold]);
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
        
        // Determine stock status
        $stockStatus = 'low';
        if ($product['stock'] == 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($product['stock'] <= 3) {
            $stockStatus = 'critical';
        }
        
        $processedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'stock' => (int)$product['stock'],
            'stock_status' => $stockStatus,
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'images' => $imageUrls,
            'is_active' => (bool)$product['is_active'],
            'created_at' => formatDateTime($product['created_at']),
            'updated_at' => formatDateTime($product['updated_at'])
        ];
    }
    
    // Get statistics
    $outOfStock = 0;
    $critical = 0;
    $low = 0;
    
    foreach ($processedProducts as $product) {
        switch ($product['stock_status']) {
            case 'out_of_stock':
                $outOfStock++;
                break;
            case 'critical':
                $critical++;
                break;
            case 'low':
                $low++;
                break;
        }
    }
    
    $responseData = [
        'threshold' => $threshold,
        'total_count' => count($processedProducts),
        'statistics' => [
            'out_of_stock' => $outOfStock,
            'critical' => $critical,
            'low' => $low
        ],
        'products' => $processedProducts
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get low stock products error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve low stock products");
}
?>