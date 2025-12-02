<?php
/**
 * Naseej Admin - Mark Notification as Read
 * POST: /notifications/mark_read.php
 * Headers: Authorization: Bearer <token>
 * Body: notification_id
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
    
    // Get notification ID
    $notificationId = filterRequest("notification_id");
    
    if (empty($notificationId)) {
        sendResponse(STATUS_FAIL, null, "Notification ID is required");
    }
    
    // Check if notification exists and belongs to this admin
    $checkStmt = $conn->prepare("
        SELECT 
            id, 
            admin_id,
            title,
            is_read
        FROM notifications 
        WHERE id = ? AND (admin_id = ? OR admin_id IS NULL)
    ");
    $checkStmt->execute([$notificationId, $admin['admin_id']]);
    $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notification) {
        sendResponse(STATUS_FAIL, null, "Notification not found or access denied");
    }
    
    // Check if already read
    if ($notification['is_read']) {
        sendResponse(STATUS_SUCCESS, [
            'id' => $notificationId,
            'is_read' => true
        ], "Notification is already marked as read");
    }
    
    // Mark as read
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ?
    ");
    $stmt->execute([$notificationId]);
    
    // Get updated unread count
    $unreadStmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
    ");
    $unreadStmt->execute([$admin['admin_id']]);
    $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    $responseData = [
        'id' => $notificationId,
        'is_read' => true,
        'unread_count' => (int)$unreadCount
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Notification marked as read");
    
} catch (PDOException $e) {
    error_log("Mark notification as read error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to mark notification as read");
}
?>