<?php
/**
 * Naseej Admin - Get Order Status Distribution
 * GET/POST: /dashboard/get_order_distribution.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns the count of orders by status
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
    
    // Get order status distribution
    $stmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as total_value,
            COALESCE(AVG(total), 0) as average_value
        FROM orders
        GROUP BY status
        ORDER BY 
            CASE status
                WHEN 'pending' THEN 1
                WHEN 'confirmed' THEN 2
                WHEN 'processing' THEN 3
                WHEN 'shipped' THEN 4
                WHEN 'delivered' THEN 5
                WHEN 'cancelled' THEN 6
                ELSE 7
            END
    ");
    
    $stmt->execute();
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare response data
    $statusData = [
        'pending' => 0,
        'confirmed' => 0,
        'processing' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'cancelled' => 0
    ];
    
    $detailedData = [];
    $totalOrders = 0;
    $totalValue = 0;
    
    foreach ($distribution as $item) {
        $status = $item['status'];
        $count = (int)$item['count'];
        $value = (float)$item['total_value'];
        
        $statusData[$status] = $count;
        $totalOrders += $count;
        $totalValue += $value;
        
        $detailedData[$status] = [
            'count' => $count,
            'total_value' => round($value, 2),
            'average_value' => round((float)$item['average_value'], 2)
        ];
    }
    
    // Calculate percentages
    $percentages = [];
    foreach ($statusData as $status => $count) {
        $percentages[$status] = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 2) : 0;
    }
    
    $responseData = [
        'distribution' => $statusData,
        'percentages' => $percentages,
        'detailed' => $detailedData,
        'summary' => [
            'total_orders' => $totalOrders,
            'total_value' => round($totalValue, 2),
            'average_order_value' => $totalOrders > 0 ? round($totalValue / $totalOrders, 2) : 0
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get order distribution error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve order distribution");
}
?>