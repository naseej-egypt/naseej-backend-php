<?php
/**
 * Naseej Admin - Get Product Details by ID
 * POST: /products/get_by_id.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get product ID
    $productId = filterRequest("product_id");
    
    if (empty($productId)) {
        sendResponse(STATUS_FAIL, null, "Product ID is required");
    }
    
    // Get product details
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
            c.image as category_image,
            p.images,
            p.colors,
            p.sizes,
            p.is_active,
            p.specifications,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    // Parse images
    $images = parseJsonArray($product['images']);
    $imageUrls = [];
    foreach ($images as $imageName) {
        $imageUrls[] = getImageUrl($imageName, 'products');
    }
    
    // Parse colors
    $colors = parseJsonArray($product['colors']);
    
    // Parse sizes
    $sizes = parseJsonArray($product['sizes']);
    
    // Parse specifications
    $specifications = parseJsonArray($product['specifications']);
    
    // Calculate discount percentage
    $discountPercentage = 0;
    if ($product['discount_price'] && $product['discount_price'] < $product['price']) {
        $discountPercentage = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
    }
    
    // Get category image URL if exists
    $categoryImageUrl = null;
    if ($product['category_image']) {
        $categoryImageUrl = getImageUrl($product['category_image'], 'categories');
    }
    
    // Determine stock status
    $stockStatus = 'in_stock';
    if ($product['stock'] == 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($product['stock'] <= 3) {
        $stockStatus = 'critical';
    } elseif ($product['stock'] <= 10) {
        $stockStatus = 'low';
    }
    
    // Get total orders count for this product
    $ordersStmt = $conn->prepare("
        SELECT COUNT(DISTINCT order_id) as total_orders,
               SUM(quantity) as total_quantity_sold
        FROM order_items
        WHERE product_id = ?
    ");
    $ordersStmt->execute([$productId]);
    $orderStats = $ordersStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent orders (last 5)
    $recentOrdersStmt = $conn->prepare("
        SELECT 
            oi.order_id,
            oi.quantity,
            oi.price,
            o.order_number,
            o.status,
            o.created_at
        FROM order_items oi
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrdersStmt->execute([$productId]);
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedOrders = [];
    foreach ($recentOrders as $order) {
        $processedOrders[] = [
            'order_id' => $order['order_id'],
            'order_number' => $order['order_number'],
            'quantity' => (int)$order['quantity'],
            'price' => (float)$order['price'],
            'status' => $order['status'],
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    $responseData = [
        'id' => $product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => (float)$product['price'],
        'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
        'discount_percentage' => $discountPercentage,
        'effective_price' => $product['discount_price'] ? (float)$product['discount_price'] : (float)$product['price'],
        'stock' => (int)$product['stock'],
        'stock_status' => $stockStatus,
        'category_id' => $product['category_id'],
        'category_name' => $product['category_name'],
        'category_image' => $categoryImageUrl,
        'images' => $imageUrls,
        'colors' => $colors,
        'sizes' => $sizes,
        'is_active' => (bool)$product['is_active'],
        'specifications' => $specifications,
        'created_at' => formatDateTime($product['created_at']),
        'updated_at' => formatDateTime($product['updated_at']),
        'statistics' => [
            'total_orders' => (int)$orderStats['total_orders'],
            'total_quantity_sold' => (int)$orderStats['total_quantity_sold'],
            'revenue' => (float)($orderStats['total_quantity_sold'] * ($product['discount_price'] ?? $product['price']))
        ],
        'recent_orders' => $processedOrders
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get product by ID error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve product details");
}
?>