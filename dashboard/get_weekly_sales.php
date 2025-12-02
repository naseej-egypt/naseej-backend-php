<?php
/**
 * Naseej Admin - Get Weekly Sales Data
 * GET/POST: /dashboard/get_weekly_sales.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns sales data for the last 7 days
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
    
    // Get weekly sales data
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            DAYNAME(created_at) as day_name,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as total_sales,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) as paid_sales,
            COALESCE(AVG(total), 0) as average_order_value
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'delivered'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    
    $stmt->execute();
    $weeklyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare arrays for chart data
    $labels = [];
    $salesValues = [];
    $orderCounts = [];
    $averageValues = [];
    
    // Fill in missing days with zero values
    $today = new DateTime();
    for ($i = 6; $i >= 0; $i--) {
        $date = clone $today;
        $date->modify("-{$i} days");
        $dateStr = $date->format('Y-m-d');
        $dayName = $date->format('D'); // Mon, Tue, Wed, etc.
        
        // Find data for this date
        $found = false;
        foreach ($weeklyData as $data) {
            if ($data['date'] === $dateStr) {
                $labels[] = $dayName;
                $salesValues[] = round((float)$data['total_sales'], 2);
                $orderCounts[] = (int)$data['order_count'];
                $averageValues[] = round((float)$data['average_order_value'], 2);
                $found = true;
                break;
            }
        }
        
        // If no data for this date, add zeros
        if (!$found) {
            $labels[] = $dayName;
            $salesValues[] = 0;
            $orderCounts[] = 0;
            $averageValues[] = 0;
        }
    }
    
    // Calculate totals
    $totalSales = array_sum($salesValues);
    $totalOrders = array_sum($orderCounts);
    $averageDailySales = $totalOrders > 0 ? $totalSales / 7 : 0;
    
    $responseData = [
        'labels' => $labels,
        'values' => $salesValues,
        'order_counts' => $orderCounts,
        'average_values' => $averageValues,
        'summary' => [
            'total_sales' => round($totalSales, 2),
            'total_orders' => $totalOrders,
            'average_daily_sales' => round($averageDailySales, 2),
            'period' => 'Last 7 Days'
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get weekly sales error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve weekly sales data");
}
?>