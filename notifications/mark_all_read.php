<?php
/**
 * Naseej Admin - Mark All Notifications as Read
 * POST: /notifications/mark_all_read.php
 * Headers: Authorization: Bearer <token>
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
        ], "No unread notifications to mark");
    }
    
    // Mark all notifications as read for this admin
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
        "Marked {$markedCount} notifications as read"
    );
    
    $responseData = [
        'marked_count' => $markedCount,
        'unread_count' => 0
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "All notifications marked as read");
    
} catch (PDOException $e) {
    error_log("Mark all notifications as read error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to mark all notifications as read");
}
?>