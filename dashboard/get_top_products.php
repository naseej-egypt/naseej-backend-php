<?php
/**
 * Naseej Admin - Get Top Selling Products
 * GET/POST: /dashboard/get_top_products.php
 * Headers: Authorization: Bearer <token>
 * Query: limit (optional, default 5), period (optional, default 30 days)
 * 
 * Returns top selling products by quantity or revenue
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
    
    // Get parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 5);
    $period = isset($_GET['period']) ? (int)$_GET['period'] : (isset($_POST['period']) ? (int)$_POST['period'] : 30);
    
    // Validate parameters
    if ($limit < 1 || $limit > 50) {
        $limit = 5;
    }
    
    if ($period < 1 || $period > 365) {
        $period = 30;
    }
    
    // Get top selling products
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.discount_price,
            p.stock,
            p.images,
            c.name as category_name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.total) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count,
            AVG(oi.price) as average_price
        FROM products p
        INNER JOIN order_items oi ON p.id = oi.product_id
        INNER JOIN orders o ON oi.order_id = o.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        AND o.status = 'delivered'
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT ?
    ");
    
    $stmt->execute([$period, $limit]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process products
    $processedProducts = [];
    $totalRevenue = 0;
    $totalQuantity = 0;
    
    foreach ($products as $product) {
        // Parse images
        $images = parseJsonArray($product['images']);
        $imageUrl = null;
        if (!empty($images)) {
            $imageUrl = getImageUrl($images[0], 'products');
        }
        
        $revenue = (float)$product['total_revenue'];
        $quantity = (int)$product['total_sold'];
        
        $totalRevenue += $revenue;
        $totalQuantity += $quantity;
        
        $processedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'stock' => (int)$product['stock'],
            'category_name' => $product['category_name'],
            'image' => $imageUrl,
            'total_sold' => $quantity,
            'total_revenue' => round($revenue, 2),
            'order_count' => (int)$product['order_count'],
            'average_price' => round((float)$product['average_price'], 2)
        ];
    }
    
    $responseData = [
        'products' => $processedProducts,
        'summary' => [
            'total_revenue' => round($totalRevenue, 2),
            'total_quantity' => $totalQuantity,
            'product_count' => count($processedProducts),
            'period_days' => $period
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get top products error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve top selling products");
}
?>