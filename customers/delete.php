<?php
/**
 * Naseej Admin - Delete Customer
 * POST: /customers/delete.php
 * Headers: Authorization: Bearer <token>
 * Body: customer_id
 * 
 * Note: Cannot delete customer with existing orders
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
    
    // Get customer ID
    $customerId = filterRequest("customer_id");
    
    if (empty($customerId)) {
        sendResponse(STATUS_FAIL, null, "Customer ID is required");
    }
    
    // Get customer details before deletion
    $stmt = $conn->prepare("
        SELECT 
            c.id, 
            c.name, 
            c.email,
            c.profile_image,
            COUNT(o.id) as order_count
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Check if customer has orders
    if ($customer['order_count'] > 0) {
        sendResponse(STATUS_FAIL, null, "Cannot delete customer with existing orders ({$customer['order_count']} orders). Consider deactivating instead.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Delete customer addresses first (foreign key cascade should handle this, but being explicit)
        $deleteAddressStmt = $conn->prepare("DELETE FROM customer_addresses WHERE customer_id = ?");
        $deleteAddressStmt->execute([$customerId]);
        
        // Delete customer
        $deleteStmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $deleteStmt->execute([$customerId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete profile image from server if exists
        if (!empty($customer['profile_image'])) {
            deleteFile(PROFILES_DIR, $customer['profile_image']);
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'delete',
            'customer',
            $customerId,
            "Deleted customer: {$customer['name']} ({$customer['email']})"
        );
        
        sendResponse(STATUS_SUCCESS, null, "Customer deleted successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete customer error: " . $e->getMessage());
    
    // Check if error is due to foreign key constraint
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        sendResponse(STATUS_FAIL, null, "Cannot delete customer with existing orders");
    }
    
    sendResponse(STATUS_ERROR, null, "Failed to delete customer");
}
?>