<?php
/**
 * Naseej Admin - Get Revenue Growth
 * GET/POST: /dashboard/get_revenue_growth.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns revenue growth comparison and trends
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
    
    // Get current month revenue
    $currentMonthStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())
        AND status = 'delivered'
    ");
    $currentMonthStmt->execute();
    $currentMonth = $currentMonthStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last month revenue
    $lastMonthStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND status = 'delivered'
    ");
    $lastMonthStmt->execute();
    $lastMonth = $lastMonthStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current year revenue
    $currentYearStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = YEAR(CURDATE())
        AND status = 'delivered'
    ");
    $currentYearStmt->execute();
    $currentYear = $currentYearStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last year revenue
    $lastYearStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
        AND status = 'delivered'
    ");
    $lastYearStmt->execute();
    $lastYear = $lastYearStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's revenue
    $todayStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE DATE(created_at) = CURDATE()
        AND status = 'delivered'
    ");
    $todayStmt->execute();
    $today = $todayStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get yesterday's revenue
    $yesterdayStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND status = 'delivered'
    ");
    $yesterdayStmt->execute();
    $yesterday = $yesterdayStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate growth percentages
    $monthlyGrowth = 0;
    if ($lastMonth['revenue'] > 0) {
        $monthlyGrowth = (($currentMonth['revenue'] - $lastMonth['revenue']) / $lastMonth['revenue']) * 100;
    }
    
    $yearlyGrowth = 0;
    if ($lastYear['revenue'] > 0) {
        $yearlyGrowth = (($currentYear['revenue'] - $lastYear['revenue']) / $lastYear['revenue']) * 100;
    }
    
    $dailyGrowth = 0;
    if ($yesterday['revenue'] > 0) {
        $dailyGrowth = (($today['revenue'] - $yesterday['revenue']) / $yesterday['revenue']) * 100;
    }
    
    // Get quarterly revenue
    $quarterlyStmt = $conn->prepare("
        SELECT 
            QUARTER(created_at) as quarter,
            YEAR(created_at) as year,
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        AND status = 'delivered'
        GROUP BY YEAR(created_at), QUARTER(created_at)
        ORDER BY year DESC, quarter DESC
    ");
    $quarterlyStmt->execute();
    $quarterlyData = $quarterlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedQuarterly = [];
    foreach ($quarterlyData as $quarter) {
        $processedQuarterly[] = [
            'quarter' => 'Q' . $quarter['quarter'] . ' ' . $quarter['year'],
            'revenue' => round((float)$quarter['revenue'], 2),
            'order_count' => (int)$quarter['order_count']
        ];
    }
    
    $responseData = [
        'daily' => [
            'today_revenue' => round((float)$today['revenue'], 2),
            'today_orders' => (int)$today['order_count'],
            'yesterday_revenue' => round((float)$yesterday['revenue'], 2),
            'yesterday_orders' => (int)$yesterday['order_count'],
            'growth_percentage' => round($dailyGrowth, 2)
        ],
        'monthly' => [
            'current_month_revenue' => round((float)$currentMonth['revenue'], 2),
            'current_month_orders' => (int)$currentMonth['order_count'],
            'last_month_revenue' => round((float)$lastMonth['revenue'], 2),
            'last_month_orders' => (int)$lastMonth['order_count'],
            'growth_percentage' => round($monthlyGrowth, 2)
        ],
        'yearly' => [
            'current_year_revenue' => round((float)$currentYear['revenue'], 2),
            'current_year_orders' => (int)$currentYear['order_count'],
            'last_year_revenue' => round((float)$lastYear['revenue'], 2),
            'last_year_orders' => (int)$lastYear['order_count'],
            'growth_percentage' => round($yearlyGrowth, 2)
        ],
        'quarterly' => $processedQuarterly
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get revenue growth error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to calculate revenue growth");
}
?>