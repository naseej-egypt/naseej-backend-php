<?php
/**
 * Naseej Admin - Get Monthly Sales Data
 * GET/POST: /dashboard/get_monthly_sales.php
 * Headers: Authorization: Bearer <token>
 * Query: months (optional, default 12)
 * 
 * Returns sales data for the last N months
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
    
    // Get months parameter
    $months = isset($_GET['months']) ? (int)$_GET['months'] : (isset($_POST['months']) ? (int)$_POST['months'] : 12);
    
    // Validate months
    if ($months < 1 || $months > 24) {
        $months = 12;
    }
    
    // Get monthly sales data
    $stmt = $conn->prepare("
        SELECT 
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            MONTHNAME(created_at) as month_name,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as total_sales,
            COALESCE(AVG(total), 0) as average_order_value,
            COUNT(DISTINCT customer_id) as unique_customers
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        AND status = 'delivered'
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year ASC, month ASC
    ");
    
    $stmt->execute([$months]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process data
    $labels = [];
    $salesValues = [];
    $orderCounts = [];
    $averageValues = [];
    $customerCounts = [];
    
    foreach ($monthlyData as $data) {
        $labels[] = substr($data['month_name'], 0, 3) . ' ' . $data['year'];
        $salesValues[] = round((float)$data['total_sales'], 2);
        $orderCounts[] = (int)$data['order_count'];
        $averageValues[] = round((float)$data['average_order_value'], 2);
        $customerCounts[] = (int)$data['unique_customers'];
    }
    
    // Calculate growth
    $currentMonthSales = count($salesValues) > 0 ? $salesValues[count($salesValues) - 1] : 0;
    $previousMonthSales = count($salesValues) > 1 ? $salesValues[count($salesValues) - 2] : 0;
    $growthPercentage = 0;
    
    if ($previousMonthSales > 0) {
        $growthPercentage = (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100;
    }
    
    // Calculate totals
    $totalSales = array_sum($salesValues);
    $totalOrders = array_sum($orderCounts);
    $averageMonthlySales = count($salesValues) > 0 ? $totalSales / count($salesValues) : 0;
    
    $responseData = [
        'labels' => $labels,
        'values' => $salesValues,
        'order_counts' => $orderCounts,
        'average_values' => $averageValues,
        'customer_counts' => $customerCounts,
        'summary' => [
            'total_sales' => round($totalSales, 2),
            'total_orders' => $totalOrders,
            'average_monthly_sales' => round($averageMonthlySales, 2),
            'current_month_sales' => round($currentMonthSales, 2),
            'previous_month_sales' => round($previousMonthSales, 2),
            'growth_percentage' => round($growthPercentage, 2),
            'period' => "Last {$months} Months"
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get monthly sales error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve monthly sales data");
}
?>