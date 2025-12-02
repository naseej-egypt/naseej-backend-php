<?php
/**
 * Naseej Admin - Toggle Customer Active Status
 * POST: /customers/toggle_status.php
 * Headers: Authorization: Bearer <token>
 * Body: customer_id, is_active (1 or 0)
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
    
    // Get input
    $customerId = filterRequest("customer_id");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($customerId)) {
        sendResponse(STATUS_FAIL, null, "Customer ID is required");
    }
    
    if ($isActive === null) {
        sendResponse(STATUS_FAIL, null, "Status value is required");
    }
    
    // Validate status value
    if ($isActive !== 0 && $isActive !== 1) {
        sendResponse(STATUS_FAIL, null, "Status must be 0 or 1");
    }
    
    // Check if customer exists
    $checkStmt = $conn->prepare("SELECT id, name, is_active FROM customers WHERE id = ?");
    $checkStmt->execute([$customerId]);
    $customer = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Check if status is already the same
    if ((int)$customer['is_active'] === $isActive) {
        sendResponse(STATUS_SUCCESS, null, "Customer status is already " . ($isActive ? "active" : "inactive"));
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE customers 
        SET is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $customerId]);
    
    // Log activity
    $statusText = $isActive ? "activated" : "deactivated";
    logActivity(
        $conn,
        $admin['admin_id'],
        'update',
        'customer',
        $customerId,
        "Customer {$statusText}: {$customer['name']}"
    );
    
    $responseData = [
        'id' => $customerId,
        'is_active' => (bool)$isActive
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Customer status updated successfully");
    
} catch (PDOException $e) {
    error_log("Toggle customer status error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update customer status");
}
?>