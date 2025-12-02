<?php
/**
 * Naseej User App - Set Default Payment Method
 * POST: /payment_methods/set_default_payment_method.php
 * Body: payment_method_id
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    
    // Verify payment method belongs to customer
    $checkStmt = $conn->prepare("
        SELECT id FROM payment_methods 
        WHERE id = ? AND customer_id = ? AND is_active = 1
    ");
    $checkStmt->execute([$paymentMethodId, $customer['customer_id']]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Payment method not found or access denied");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Unset all defaults for this customer
    $updateStmt = $conn->prepare("
        UPDATE payment_methods 
        SET is_default = 0 
        WHERE customer_id = ?
    ");
    $updateStmt->execute([$customer['customer_id']]);
    
    // Set the specified method as default
    $stmt = $conn->prepare("
        UPDATE payment_methods 
        SET is_default = 1, updated_at = NOW()
        WHERE id = ? AND customer_id = ?
    ");
    
    $stmt->execute([$paymentMethodId, $customer['customer_id']]);
    
    $affectedRows = $stmt->rowCount();
    
    // Commit transaction
    $conn->commit();
    
    if ($affectedRows > 0) {
        // Get updated payment method
        $getStmt = $conn->prepare("
            SELECT 
                id, customer_id, card_type, card_holder_name, card_number_hash,
                last_four_digits, expiry_month, expiry_year, nickname, 
                is_default, token, created_at, updated_at
            FROM payment_methods 
            WHERE id = ?
        ");
        
        $getStmt->execute([$paymentMethodId]);
        $updatedPaymentMethod = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        // Format response
        $responseData = [
            'id' => (string)$updatedPaymentMethod['id'],
            'customer_id' => (string)$updatedPaymentMethod['customer_id'],
            'card_type' => $updatedPaymentMethod['card_type'],
            'card_holder_name' => $updatedPaymentMethod['card_holder_name'],
            'card_number' => '**** **** **** ' . $updatedPaymentMethod['last_four_digits'],
            'last_four_digits' => $updatedPaymentMethod['last_four_digits'],
            'expiry_month' => $updatedPaymentMethod['expiry_month'],
            'expiry_year' => $updatedPaymentMethod['expiry_year'],
            'expiry_date' => $updatedPaymentMethod['expiry_month'] . '/' . substr($updatedPaymentMethod['expiry_year'], -2),
            'nickname' => $updatedPaymentMethod['nickname'],
            'is_default' => (bool)$updatedPaymentMethod['is_default'],
            'token' => $updatedPaymentMethod['token'],
            'created_at' => formatDateTime($updatedPaymentMethod['created_at']),
            'updated_at' => formatDateTime($updatedPaymentMethod['updated_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Default payment method updated successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to set default payment method");
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Set default payment method error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Failed to set default payment method: " . $e->getMessage());
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Set default payment method error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to set default payment method");
} finally {
    $db->disconnect();
}
?>