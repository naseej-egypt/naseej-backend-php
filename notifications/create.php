<?php
/**
 * Naseej Admin - Create Notification (Helper Function)
 * POST: /notifications/create.php
 * Headers: Authorization: Bearer <token>
 * Body: title, message, type, admin_id (optional), data (optional JSON)
 * 
 * This endpoint allows creating notifications
 * Used internally by the system or by super admins
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
    // Verify authentication and require admin role
    $admin = requireRole($conn, ['super_admin', 'admin']);
    
    // Get and validate input
    $title = filterRequest("title");
    $message = filterRequest("message");
    $type = filterRequest("type");
    $targetAdminId = filterRequest("admin_id"); // NULL means broadcast to all
    $dataJson = filterRequest("data");
    
    // Validate required fields
    $errors = validateRequired(['title', 'message', 'type']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate title length
    if (strlen($title) < 3 || strlen($title) > 200) {
        sendResponse(STATUS_FAIL, null, "Title must be between 3 and 200 characters");
    }
    
    // Validate type
    $validTypes = ['order', 'system', 'promotion', 'delivery'];
    if (!in_array($type, $validTypes)) {
        sendResponse(STATUS_FAIL, null, "Invalid notification type. Valid types: " . implode(', ', $validTypes));
    }
    
    // Validate JSON data if provided
    $data = null;
    if (!empty($dataJson)) {
        $decoded = json_decode($dataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(STATUS_FAIL, null, "Invalid JSON data format");
        }
        $data = $dataJson;
    }
    
    // Validate target admin if specified
    if (!empty($targetAdminId)) {
        $adminCheckStmt = $conn->prepare("SELECT id FROM admins WHERE id = ? AND is_active = 1");
        $adminCheckStmt->execute([$targetAdminId]);
        if (!$adminCheckStmt->fetch()) {
            sendResponse(STATUS_FAIL, null, "Target admin not found or inactive");
        }
    }
    
    // Insert notification
    $stmt = $conn->prepare("
        INSERT INTO notifications 
        (admin_id, title, message, type, data, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        !empty($targetAdminId) ? $targetAdminId : null,
        $title,
        $message,
        $type,
        $data
    ]);
    
    $notificationId = $conn->lastInsertId();
    
    // Log activity
    $logMessage = "Created notification: {$title}";
    if (!empty($targetAdminId)) {
        $logMessage .= " (for admin ID: {$targetAdminId})";
    } else {
        $logMessage .= " (broadcast to all admins)";
    }
    
    logActivity(
        $conn,
        $admin['admin_id'],
        'create',
        'notification',
        $notificationId,
        $logMessage
    );
    
    // Get created notification
    $getStmt = $conn->prepare("
        SELECT 
            id,
            admin_id,
            title,
            message,
            type,
            data,
            is_read,
            created_at
        FROM notifications 
        WHERE id = ?
    ");
    $getStmt->execute([$notificationId]);
    $notification = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $responseData = [
        'id' => $notification['id'],
        'admin_id' => $notification['admin_id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'type' => $notification['type'],
        'data' => $notification['data'] ? json_decode($notification['data'], true) : null,
        'is_read' => (bool)$notification['is_read'],
        'created_at' => formatDateTime($notification['created_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Notification created successfully");
    
} catch (PDOException $e) {
    error_log("Create notification error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to create notification");
}
?>