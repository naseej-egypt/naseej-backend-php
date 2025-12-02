<?php
/**
 * Naseej Admin - Clear All Notifications (Mark as Read)
 * POST: /notifications/clear_all.php
 * Headers: Authorization: Bearer <token>
 * 
 * Marks all notifications as read for the authenticated admin (both specific and broadcast)
 * This clears them from the device while preserving them in the database
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
    
    // Get count of unread notifications before marking
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
    ");
    $countStmt->execute([$admin['admin_id']]);
    $unreadBefore = $countStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    if ($unreadBefore == 0) {
        sendResponse(STATUS_SUCCESS, [
            'marked_count' => 0,
            'unread_count' => 0
        ], "No unread notifications to clear");
    }
    
    // Mark all notifications as read for this admin (including broadcast)
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0
    ");
    $stmt->execute([$admin['admin_id']]);
    
    $markedCount = $stmt->rowCount();
    
    // Log activity
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'notification',
        null,
        "Marked {$markedCount} notifications as read (cleared from device)"
    );
    
    $responseData = [
        'marked_count' => $markedCount,
        'unread_count' => 0
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "All notifications marked as read and cleared from device");
    
} catch (PDOException $e) {
    error_log("Clear all notifications error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to clear all notifications");
}
?>