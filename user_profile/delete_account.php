<?php
/**
 * Naseej User App - Delete Account
 * POST: /user_profile/delete_account.php
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Begin transaction for data integrity
    $conn->beginTransaction();
    
    $customerId = $customer['customer_id'];
    
    // 1. Delete customer sessions
    $deleteSessionsStmt = $conn->prepare("DELETE FROM customer_sessions WHERE customer_id = ?");
    $deleteSessionsStmt->execute([$customerId]);
    
    // 2. Delete customer addresses
    $deleteAddressesStmt = $conn->prepare("DELETE FROM customer_addresses WHERE customer_id = ?");
    $deleteAddressesStmt->execute([$customerId]);
    
    // 3. Delete customer favorites
    $deleteFavoritesStmt = $conn->prepare("DELETE FROM favorites WHERE customer_id = ?");
    $deleteFavoritesStmt->execute([$customerId]);
    
    // 4. Delete customer cart items
    $deleteCartStmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
    $deleteCartStmt->execute([$customerId]);
    
    // 5. Update orders to remove customer reference (set customer_id to NULL)
    $updateOrdersStmt = $conn->prepare("UPDATE orders SET customer_id = NULL, customer_name = 'Deleted User', customer_email = NULL, customer_phone = NULL WHERE customer_id = ?");
    $updateOrdersStmt->execute([$customerId]);
    
    // 6. Finally delete the customer account
    $deleteCustomerStmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $deleteCustomerStmt->execute([$customerId]);
    
    if ($deleteCustomerStmt->rowCount() > 0) {
        // Commit transaction
        $conn->commit();
        
        // Log the activity
        logActivity($conn, null, 'delete_account', 'customer', $customerId, 
                   "Customer deleted their account", getClientIp());
        
        sendResponse(STATUS_SUCCESS, null, "Account deleted successfully");
    } else {
        $conn->rollBack();
        sendResponse(STATUS_FAIL, null, "Failed to delete account");
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete account error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete account");
} finally {
    $db->disconnect();
}
?>