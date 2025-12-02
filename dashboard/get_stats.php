<?php
/**
 * Naseej Admin - Get Dashboard Statistics (FULLY CORRECTED VERSION)
 * FIXES:
 * - Top customers: Top 3 by total spent (all time, exclude zero spenders)
 * - Top delivery men: Top 3 by successful deliveries count (all time, exclude zero deliveries)
 * - Top categories: Top 3 by quantity sold (all time, exclude zero sales)
 * - Top stores: Top 3 by revenue (all time, exclude zero revenue)
 * - Consistent revenue calculations across all metrics
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    $admin = verifyAuth($conn);
    
    // ============================================
    // 1. SALES & REVENUE STATISTICS (FIXED)
    // ============================================
    $salesStmt = $conn->query("
        SELECT 
            COUNT(*) as total_orders,
            -- Total Revenue: delivered + paid only
            COALESCE(SUM(CASE WHEN status = 'delivered' AND payment_status = 'paid' THEN total ELSE 0 END), 0) as total_revenue,
            -- Today Sales: delivered + paid only
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = 'delivered' AND payment_status = 'paid' THEN total ELSE 0 END), 0) as today_sales,
            -- Week Sales: delivered + paid only
            COALESCE(SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'delivered' AND payment_status = 'paid' THEN total ELSE 0 END), 0) as week_sales,
            -- Month Sales: delivered + paid only
            COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'delivered' AND payment_status = 'paid' THEN total ELSE 0 END), 0) as month_sales,
            -- Last Month Sales: delivered + paid only
            COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND status = 'delivered' AND payment_status = 'paid' THEN total ELSE 0 END), 0) as last_month_sales
        FROM orders
    ");
    $salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);
    
    $monthGrowth = 0;
    if ($salesData['last_month_sales'] > 0) {
        $monthGrowth = (($salesData['month_sales'] - $salesData['last_month_sales']) / $salesData['last_month_sales']) * 100;
    }
    
    // ============================================
    // 2. ORDERS STATISTICS
    // ============================================
    $ordersStmt = $conn->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_orders
        FROM orders
    ");
    $ordersData = $ordersStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 3. CUSTOMERS STATISTICS
    // ============================================
    $customersStmt = $conn->query("
        SELECT 
            COUNT(*) as total_customers,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_customers,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month,
            COALESCE(AVG(total_spent), 0) as avg_customer_value
        FROM customers
    ");
    $customersData = $customersStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 4. PRODUCTS STATISTICS
    // ============================================
    $productsStmt = $conn->query("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
            COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock,
            COUNT(CASE WHEN stock <= 10 AND stock > 0 THEN 1 END) as low_stock
        FROM products
    ");
    $productsData = $productsStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 5. DELIVERY MEN STATISTICS
    // ============================================
    $deliveryStmt = $conn->query("
        SELECT 
            COUNT(*) as total_delivery_men,
            COUNT(CASE WHEN status = 'available' AND is_active = 1 THEN 1 END) as available,
            COUNT(CASE WHEN status = 'busy' THEN 1 END) as busy,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_delivery_men,
            COALESCE(AVG(rating), 0) as avg_rating,
            COALESCE(SUM(total_deliveries), 0) as total_deliveries_completed
        FROM delivery_men
    ");
    $deliveryData = $deliveryStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 6. STORES STATISTICS
    // ============================================
    $storesStmt = $conn->query("
        SELECT 
            COUNT(*) as total_stores,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_stores
        FROM stores
    ");
    $storesData = $storesStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 7. RECENT ORDERS
    // ============================================
    $recentOrdersStmt = $conn->prepare("
        SELECT 
            o.id, o.order_number, o.customer_name, o.total, o.status,
            o.payment_status, o.created_at, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedRecentOrders = [];
    foreach ($recentOrders as $order) {
        $processedRecentOrders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'item_count' => (int)$order['item_count'],
            'created_at' => formatDateTime($order['created_at'])
        ];
    }
    
    // ============================================
    // 8. TOP 3 PRODUCTS - BY QUANTITY SOLD (FIXED)
    // ============================================
    $topProductsStmt = $conn->prepare("
        SELECT 
            p.id, p.name, p.price, p.images,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.total), 0) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id 
            AND o.status = 'delivered'
            AND o.payment_status = 'paid'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 3
    ");
    $topProductsStmt->execute();
    $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedTopProducts = [];
    foreach ($topProducts as $product) {
        $images = parseJsonArray($product['images']);
        $imageUrl = null;
        if (!empty($images)) {
            $imageUrl = getImageUrl($images[0], 'products');
        }
        
        $processedTopProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'image' => $imageUrl,
            'total_sold' => (int)$product['total_sold'],
            'total_revenue' => round((float)$product['revenue'], 2)
        ];
    }
    
    // ============================================
    // 9. TOP 3 CUSTOMERS - BY TOTAL SPENT (FIXED - ALL TIME)
    // ============================================
    $topCustomersStmt = $conn->prepare("
        SELECT 
            c.id, c.name, c.email, c.phone, c.profile_image,
            c.total_orders as order_count,
            c.total_spent as total_spent
        FROM customers c
        WHERE c.is_active = 1 
            AND c.total_spent > 0
        ORDER BY c.total_spent DESC
        LIMIT 3
    ");
    $topCustomersStmt->execute();
    $topCustomers = $topCustomersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedTopCustomers = [];
    foreach ($topCustomers as $customer) {
        $processedTopCustomers[] = [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'profile_image' => $customer['profile_image'] ? getImageUrl($customer['profile_image'], 'profiles') : null,
            'order_count' => (int)$customer['order_count'],
            'total_spent' => round((float)$customer['total_spent'], 2)
        ];
    }
    
    // ============================================
    // 10. TOP 3 DELIVERY MEN - BY SUCCESSFUL DELIVERIES COUNT (FIXED - ALL TIME)
    // ============================================
    $topDeliveryStmt = $conn->prepare("
        SELECT 
            dm.id, dm.name, dm.phone, dm.profile_image, dm.rating,
            dm.total_deliveries as successful_deliveries
        FROM delivery_men dm
        WHERE dm.is_active = 1 
            AND dm.total_deliveries > 0
        ORDER BY dm.total_deliveries DESC, dm.rating DESC
        LIMIT 3
    ");
    $topDeliveryStmt->execute();
    $topDelivery = $topDeliveryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedTopDelivery = [];
    foreach ($topDelivery as $delivery) {
        $processedTopDelivery[] = [
            'id' => $delivery['id'],
            'name' => $delivery['name'],
            'phone' => $delivery['phone'],
            'profile_image' => $delivery['profile_image'] ? getImageUrl($delivery['profile_image'], 'profiles') : null,
            'rating' => round((float)$delivery['rating'], 2),
            'deliveries_count' => (int)$delivery['successful_deliveries']
        ];
    }
    
    // ============================================
    // 11. TOP 3 STORES - BY REVENUE (FIXED - ALL TIME, BY REVENUE)
    // ============================================
    $topStoresStmt = $conn->prepare("
        SELECT 
            s.id, s.name, s.address, s.phone, s.image_url,
            COUNT(o.id) as order_count,
            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' AND o.status = 'delivered' THEN o.total ELSE 0 END), 0) as total_revenue
        FROM stores s
        LEFT JOIN orders o ON s.id = o.store_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING total_revenue > 0
        ORDER BY total_revenue DESC
        LIMIT 3
    ");
    $topStoresStmt->execute();
    $topStores = $topStoresStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedTopStores = [];
    foreach ($topStores as $store) {
        $processedTopStores[] = [
            'id' => $store['id'],
            'name' => $store['name'],
            'address' => $store['address'],
            'phone' => $store['phone'],
            'image_url' => $store['image_url'] ? getImageUrl($store['image_url'], 'stores') : null,
            'order_count' => (int)$store['order_count'],
            'total_revenue' => round((float)$store['total_revenue'], 2)
        ];
    }
    
    // ============================================
    // 12. TOP 3 CATEGORIES - BY QUANTITY SOLD (FIXED - ALL TIME, BY ITEMS SOLD)
    // ============================================
    $topCategoriesStmt = $conn->prepare("
        SELECT 
            c.id, c.name, c.image_url,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            COALESCE(SUM(oi.total), 0) as total_revenue
        FROM categories c
        INNER JOIN products p ON c.id = p.category_id
        INNER JOIN order_items oi ON p.id = oi.product_id
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE c.is_active = 1
            AND o.status = 'delivered'
            AND o.payment_status = 'paid'
        GROUP BY c.id
        HAVING total_quantity > 0
        ORDER BY total_quantity DESC
        LIMIT 3
    ");
    $topCategoriesStmt->execute();
    $topCategories = $topCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedTopCategories = [];
    foreach ($topCategories as $category) {
        $processedTopCategories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'image_url' => $category['image_url'] ? getImageUrl($category['image_url'], 'categories') : null,
            'order_count' => (int)$category['order_count'],
            'total_quantity' => (int)$category['total_quantity'],
            'total_revenue' => round((float)$category['total_revenue'], 2)
        ];
    }
    
    // ============================================
    // 13. WEEKLY SALES TREND
    // ============================================
    $weeklySalesStmt = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' AND status = 'delivered' THEN total ELSE 0 END), 0) as revenue
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $weeklySales = $weeklySalesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $weeklySalesData = ['labels' => [], 'orders' => [], 'revenue' => []];
    foreach ($weeklySales as $day) {
        $weeklySalesData['labels'][] = date('D', strtotime($day['date']));
        $weeklySalesData['orders'][] = (int)$day['orders'];
        $weeklySalesData['revenue'][] = round((float)$day['revenue'], 2);
    }
    
    // ============================================
    // 14. MONTHLY SALES TREND (Last 6 months)
    // ============================================
    $monthlySalesStmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' AND status = 'delivered' THEN total ELSE 0 END), 0) as revenue
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthlySales = $monthlySalesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $monthlySalesData = ['labels' => [], 'orders' => [], 'revenue' => []];
    foreach ($monthlySales as $month) {
        $monthlySalesData['labels'][] = date('M Y', strtotime($month['month'] . '-01'));
        $monthlySalesData['orders'][] = (int)$month['orders'];
        $monthlySalesData['revenue'][] = round((float)$month['revenue'], 2);
    }
    
    // ============================================
    // 15. ORDER & PAYMENT DISTRIBUTION
    // ============================================
    $statusDistribution = [
        'pending' => (int)$ordersData['pending_orders'],
        'confirmed' => (int)$ordersData['confirmed_orders'],
        'processing' => (int)$ordersData['processing_orders'],
        'shipped' => (int)$ordersData['shipped_orders'],
        'delivered' => (int)$ordersData['delivered_orders'],
        'cancelled' => (int)$ordersData['cancelled_orders']
    ];
    
    $paymentStmt = $conn->query("
        SELECT 
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid,
            COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed,
            COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END) as refunded
        FROM orders
    ");
    $paymentData = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    $paymentDistribution = [
        'pending' => (int)$paymentData['pending'],
        'paid' => (int)$paymentData['paid'],
        'failed' => (int)$paymentData['failed'],
        'refunded' => (int)$paymentData['refunded']
    ];
    
    // ============================================
    // 16. LOW STOCK ALERTS
    // ============================================
    $lowStockStmt = $conn->query("
        SELECT id, name, stock, price
        FROM products
        WHERE stock <= 10 AND stock > 0 AND is_active = 1
        ORDER BY stock ASC
        LIMIT 10
    ");
    $lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedLowStock = [];
    foreach ($lowStockProducts as $product) {
        $processedLowStock[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'stock' => (int)$product['stock'],
            'price' => (float)$product['price']
        ];
    }
    
    // ============================================
    // PREPARE FINAL RESPONSE
    // ============================================
    $responseData = [
        'sales' => [
            'total_revenue' => round((float)$salesData['total_revenue'], 2),
            'today_sales' => round((float)$salesData['today_sales'], 2),
            'week_sales' => round((float)$salesData['week_sales'], 2),
            'month_sales' => round((float)$salesData['month_sales'], 2),
            'last_month_sales' => round((float)$salesData['last_month_sales'], 2),
            'growth_percentage' => round($monthGrowth, 2)
        ],
        'orders' => [
            'total' => (int)$ordersData['total_orders'],
            'pending' => (int)$ordersData['pending_orders'],
            'confirmed' => (int)$ordersData['confirmed_orders'],
            'processing' => (int)$ordersData['processing_orders'],
            'shipped' => (int)$ordersData['shipped_orders'],
            'delivered' => (int)$ordersData['delivered_orders'],
            'cancelled' => (int)$ordersData['cancelled_orders'],
            'today' => (int)$ordersData['today_orders'],
            'this_week' => (int)$ordersData['week_orders'],
            'status_distribution' => $statusDistribution,
            'payment_distribution' => $paymentDistribution
        ],
        'customers' => [
            'total' => (int)$customersData['total_customers'],
            'active' => (int)$customersData['active_customers'],
            'new_this_week' => (int)$customersData['new_this_week'],
            'new_this_month' => (int)$customersData['new_this_month'],
            'avg_customer_value' => round((float)$customersData['avg_customer_value'], 2)
        ],
        'products' => [
            'total' => (int)$productsData['total_products'],
            'active' => (int)$productsData['active_products'],
            'out_of_stock' => (int)$productsData['out_of_stock'],
            'low_stock' => (int)$productsData['low_stock'],
            'low_stock_items' => $processedLowStock
        ],
        'delivery' => [
            'total_delivery_men' => (int)$deliveryData['total_delivery_men'],
            'available' => (int)$deliveryData['available'],
            'busy' => (int)$deliveryData['busy'],
            'active' => (int)$deliveryData['active_delivery_men'],
            'avg_rating' => round((float)$deliveryData['avg_rating'], 2),
            'total_deliveries_completed' => (int)$deliveryData['total_deliveries_completed']
        ],
        'stores' => [
            'total' => (int)$storesData['total_stores'],
            'active' => (int)$storesData['active_stores']
        ],
        'recent_orders' => $processedRecentOrders,
        'top_products' => $processedTopProducts,
        'top_customers' => $processedTopCustomers,
        'top_delivery_men' => $processedTopDelivery,
        'top_stores' => $processedTopStores,
        'top_categories' => $processedTopCategories,
        'weekly_sales' => $weeklySalesData,
        'monthly_sales' => $monthlySalesData,
        'quick_stats' => [
            [
                'title' => 'Total Sales',
                'value' => '$' . number_format($salesData['total_revenue'], 0),
                'subtitle' => ($monthGrowth >= 0 ? '+' : '') . number_format($monthGrowth, 1) . '% from last month',
                'icon' => 'attach_money',
                'color' => 'success'
            ],
            [
                'title' => 'This Month',
                'value' => '$' . number_format($salesData['month_sales'], 0),
                'subtitle' => (int)$ordersData['today_orders'] . ' orders today',
                'icon' => 'calendar_today',
                'color' => 'primary'
            ],
            [
                'title' => 'Orders',
                'value' => (string)$ordersData['total_orders'],
                'subtitle' => $ordersData['pending_orders'] . ' pending',
                'icon' => 'shopping_cart',
                'color' => 'info'
            ],
            [
                'title' => 'Customers',
                'value' => (string)$customersData['total_customers'],
                'subtitle' => $customersData['new_this_week'] . ' new this week',
                'icon' => 'people',
                'color' => 'accent'
            ]
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Dashboard statistics retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get dashboard stats error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve dashboard statistics");
}
?>