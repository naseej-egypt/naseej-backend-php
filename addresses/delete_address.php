<?php
/**
 * Naseej User App - Delete Address
 * POST: /addresses/delete_address.php
 * Body: address_id
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
    
    // Get and validate input
    $addressId = filterRequest("address_id");
    
    if (empty($addressId)) {
        sendResponse(STATUS_FAIL, null, "Address ID is required");
    }
    
    // Verify address belongs to customer and get its data
    $checkStmt = $conn->prepare("
        SELECT id, is_default FROM customer_addresses 
        WHERE id = ? AND customer_id = ?
    ");
    $checkStmt->execute([$addressId, $customer['customer_id']]);
    $addressData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$addressData) {
        sendResponse(STATUS_FAIL, null, "Address not found or access denied");
    }
    
    // Check if there are other addresses
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM customer_addresses 
        WHERE customer_id = ?
    ");
    $countStmt->execute([$customer['customer_id']]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Delete the address
    $deleteStmt = $conn->prepare("
        DELETE FROM customer_addresses 
        WHERE id = ? AND customer_id = ?
    ");
    $deleteStmt->execute([$addressId, $customer['customer_id']]);
    
    // If this was the default address and there are other addresses, set a new default
    if ($addressData['is_default'] == 1 && $count > 1) {
        $setDefaultStmt = $conn->prepare("
            UPDATE customer_addresses 
            SET is_default = 1 
            WHERE customer_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $setDefaultStmt->execute([$customer['customer_id']]);
    }
    
    // Commit transaction
    $conn->commit();
    
    if ($deleteStmt->rowCount() > 0) {
        sendResponse(STATUS_SUCCESS, [
            'deleted_id' => $addressId
        ], "Address deleted successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to delete address");
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete address error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete address");
} finally {
    $db->disconnect();
}
?>