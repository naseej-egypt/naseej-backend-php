<?php
/**
 * Naseej Admin - Get All Notifications
 * GET/POST: /notifications/get_all.php
 * Headers: Authorization: Bearer <token>
 * QueryParams (optional): 
 *   - type: Filter by notification type (order, system, promotion, delivery)
 *   - is_read: Filter by read status (1/0)
 *   - page: Page number for pagination (default 1)
 *   - limit: Items per page (default 100) // CHANGED FROM 20 TO 100
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
    
    // DEBUG: Log the request
    error_log("🔔 Get all notifications for admin_id: " . $admin['admin_id']);
    
    // Get filter parameters
    $type = isset($_GET['type']) ? $_GET['type'] : (isset($_POST['type']) ? filterRequest('type') : null);
    $isRead = isset($_GET['is_read']) ? $_GET['is_read'] : (isset($_POST['is_read']) ? $_POST['is_read'] : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 100); // CHANGED: Default to 100
    
    // Validate parameters
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 500) $limit = 100; // CHANGED: Increased max limit to 500
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = ["(n.admin_id = ? OR n.admin_id IS NULL)"];
    $params = [$admin['admin_id']];
    
    if ($type !== null && $type !== '') {
        $whereClauses[] = "n.type = ?";
        $params[] = $type;
    }
    
    if ($isRead !== null && $isRead !== '') {
        $whereClauses[] = "n.is_read = ?";
        $params[] = (int)$isRead;
    }
    
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM notifications n
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // DEBUG: Log counts
    error_log("🔔 Total notifications in DB: " . $totalCount);
    error_log("🔔 Fetching with limit: " . $limit . ", offset: " . $offset);
    
    // Get notifications
    $sql = "
        SELECT 
            n.id,
            n.admin_id,
            n.title,
            n.message,
            n.type,
            n.data,
            n.is_read,
            n.created_at
        FROM notifications n
        $whereSQL
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Log fetched count
    error_log("🔔 Fetched " . count($notifications) . " notifications");
    
    // Process each notification
    $processedNotifications = [];
    foreach ($notifications as $notification) {
        // Parse data JSON if exists
        $data = null;
        if (!empty($notification['data'])) {
            // Try to decode JSON, but don't fail if invalid
            $decoded = json_decode($notification['data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            } else {
                // If JSON is invalid, return as string
                $data = $notification['data'];
            }
        }
        
        $processedNotifications[] = [
            'id' => (int)$notification['id'],
            'admin_id' => $notification['admin_id'] ? (int)$notification['admin_id'] : null,
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'data' => $data,
            'is_read' => (bool)$notification['is_read'],
            'created_at' => formatDateTime($notification['created_at'])
        ];
    }
    
    // Get unread count - FIXED: Use same logic as get_unread_count.php
    $unreadStmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
    ");
    $unreadStmt->execute([$admin['admin_id']]);
    $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'notifications' => $processedNotifications,
        'unread_count' => (int)$unreadCount,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    // DEBUG: Log final response summary
    error_log("🔔 Final response - Notifications: " . count($processedNotifications) . ", Unread: " . $unreadCount . ", Total: " . $totalCount);
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("❌ Get all notifications error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Database error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("❌ Get all notifications general error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Server error: " . $e->getMessage());
}
?>