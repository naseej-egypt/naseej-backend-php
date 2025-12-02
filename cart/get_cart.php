<?php
/**
 * Naseej User App - Get Cart
 * POST: /cart/get_cart.php
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get cart items with product details
    $stmt = $conn->prepare("
        SELECT 
            c.id as cart_id,
            c.product_id,
            c.quantity,
            p.name as product_name,
            p.description,
            p.price,
            p.discount_price,
            p.stock,
            p.images,
            p.colors,
            p.sizes,
            p.is_active,
            cat.name as category_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN categories cat ON p.category_id = cat.id
        WHERE c.customer_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$customer['customer_id']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process cart items
    $processedItems = [];
    $subtotal = 0;
    $totalItems = 0;
    $unavailableItems = [];
    
    foreach ($cartItems as $item) {
        // Check if product is still active and in stock
        if (!$item['is_active']) {
            $unavailableItems[] = [
                'cart_id' => $item['cart_id'],
                'product_name' => $item['product_name'],
                'reason' => 'Product is no longer available'
            ];
            continue;
        }
        
        if ($item['stock'] < $item['quantity']) {
            $unavailableItems[] = [
                'cart_id' => $item['cart_id'],
                'product_name' => $item['product_name'],
                'reason' => "Only {$item['stock']} items available"
            ];
        }
        
        // Parse images
        $images = parseJsonArray($item['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        // Parse colors and sizes
        $colors = parseJsonArray($item['colors']);
        $sizes = parseJsonArray($item['sizes']);
        
        // Calculate price
        $finalPrice = $item['discount_price'] ?: $item['price'];
        $itemSubtotal = $finalPrice * $item['quantity'];
        
        $processedItems[] = [
            'cart_id' => (string)$item['cart_id'],
            'product_id' => (string)$item['product_id'],
            'product_name' => $item['product_name'],
            'description' => $item['description'],
            'category_name' => $item['category_name'],
            'images' => $imageUrls,
            'product_image' => !empty($imageUrls) ? $imageUrls[0] : null,
            'colors' => $colors,
            'sizes' => $sizes,
            'quantity' => (int)$item['quantity'],
            'price' => (float)$item['price'],
            'discount_price' => $item['discount_price'] ? (float)$item['discount_price'] : null,
            'final_price' => (float)$finalPrice,
            'subtotal' => (float)$itemSubtotal,
            'stock' => (int)$item['stock'],
            'is_available' => $item['is_active'] && $item['stock'] >= $item['quantity']
        ];
        
        $subtotal += $itemSubtotal;
        $totalItems += $item['quantity'];
    }
    
    // Calculate shipping (free shipping for orders over 300) - CHANGED FROM 500
    $shipping = $subtotal >= 300 ? 0 : 49.99;
    
    // Calculate tax (8%)
    $tax = $subtotal * 0.08;
    
    // Calculate total
    $total = $subtotal + $shipping + $tax;
    
    $responseData = [
        'cart_items' => $processedItems,
        'unavailable_items' => $unavailableItems,
        'summary' => [
            'total_items' => $totalItems,
            'items_count' => count($processedItems),
            'subtotal' => (float)$subtotal,
            'shipping' => (float)$shipping,
            'tax' => (float)$tax,
            'total' => (float)$total,
            'free_shipping_threshold' => 300.00, // CHANGED FROM 500
            'amount_for_free_shipping' => max(0, 300 - $subtotal) // CHANGED FROM 500
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Cart retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get cart error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve cart");
} finally {
    $db->disconnect();
}
?>