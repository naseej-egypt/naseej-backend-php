<?php
/**
 * Naseej Admin - Get Unread Notifications Count
 * GET/POST: /notifications/get_unread_count.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns the count of unread notifications for the authenticated admin
 */

// Add headers to prevent connection issues
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    
    // DEBUG: Log the admin ID
    error_log("🔔 Getting unread count for admin_id: " . $admin['admin_id']);
    
    // Get unread count - FIXED: Only count notifications for this admin OR broadcast
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
    ");
    $stmt->execute([$admin['admin_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // DEBUG: Log the raw count
    error_log("🔔 Raw unread count from DB: " . $result['unread_count']);
    
    // Get unread count by type
    $typeStmt = $conn->prepare("
        SELECT 
            type,
            COUNT(*) as count
        FROM notifications
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
        GROUP BY type
    ");
    $typeStmt->execute([$admin['admin_id']]);
    $typeResults = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countByType = [
        'order' => 0,
        'system' => 0,
        'promotion' => 0,
        'delivery' => 0
    ];
    
    foreach ($typeResults as $typeResult) {
        $countByType[$typeResult['type']] = (int)$typeResult['count'];
    }
    
    $responseData = [
        'unread_count' => (int)$result['unread_count'],
        'by_type' => $countByType
    ];
    
    // DEBUG: Log final response
    error_log("🔔 Final unread count response: " . json_encode($responseData));
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("❌ Get unread count error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Database error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("❌ Get unread count general error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Server error: " . $e->getMessage());
}
?>