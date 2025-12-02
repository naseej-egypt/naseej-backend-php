<?php
/**
 * Naseej User App - Delete Payment Method
 * POST: /payment_methods/delete_payment_method.php
 * Body: payment_method_id
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
    $paymentMethodId = filterRequest("payment_method_id");
    
    if (empty($paymentMethodId)) {
        sendResponse(STATUS_FAIL, null, "Payment method ID is required");
    }
    
    // Verify payment method belongs to customer and get its data
    $checkStmt = $conn->prepare("
        SELECT id, is_default FROM payment_methods 
        WHERE id = ? AND customer_id = ? AND is_active = 1
    ");
    $checkStmt->execute([$paymentMethodId, $customer['customer_id']]);
    $paymentMethodData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paymentMethodData) {
        sendResponse(STATUS_FAIL, null, "Payment method not found or access denied");
    }
    
    // Check if there are other payment methods
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM payment_methods 
        WHERE customer_id = ? AND is_active = 1
    ");
    $countStmt->execute([$customer['customer_id']]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Soft delete the payment method (set is_active = 0)
    $deleteStmt = $conn->prepare("
        UPDATE payment_methods 
        SET is_active = 0, updated_at = NOW()
        WHERE id = ? AND customer_id = ?
    ");
    $deleteStmt->execute([$paymentMethodId, $customer['customer_id']]);
    
    // If this was the default payment method and there are other methods, set a new default
    if ($paymentMethodData['is_default'] == 1 && $count > 1) {
        $setDefaultStmt = $conn->prepare("
            UPDATE payment_methods 
            SET is_default = 1 
            WHERE customer_id = ? AND is_active = 1
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $setDefaultStmt->execute([$customer['customer_id']]);
    }
    
    // Commit transaction
    $conn->commit();
    
    if ($deleteStmt->rowCount() > 0) {
        sendResponse(STATUS_SUCCESS, [
            'deleted_id' => $paymentMethodId
        ], "Payment method deleted successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to delete payment method");
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete payment method error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete payment method");
} finally {
    $db->disconnect();
}
?>