<?php
/**
 * Naseej User App - Update Payment Method
 * POST: /payment_methods/update_payment_method.php
 * Body: payment_method_id, card_type, card_holder_name, expiry_month, expiry_year, nickname, is_default
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
    $cardType = filterRequest("card_type");
    $cardHolderName = filterRequest("card_holder_name");
    $expiryMonth = filterRequest("expiry_month");
    $expiryYear = filterRequest("expiry_year");
    $nickname = filterRequest("nickname") ?: null;
    $isDefault = filterRequest("is_default") ?: '0';
    
    // Log received data for debugging
    error_log("Update payment method - Received card_type: " . $cardType);
    
    // Validate required fields
    $errors = validateRequired(['payment_method_id', 'card_type', 'card_holder_name', 'expiry_month', 'expiry_year']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate card type
    $allowedCardTypes = ['Visa', 'Mastercard', 'Amex'];
    if (!in_array($cardType, $allowedCardTypes)) {
        sendResponse(STATUS_FAIL, null, "Invalid card type: " . $cardType);
    }
    
    // Validate expiry date
    $currentYear = date('Y');
    $currentMonth = date('m');
    
    if (!is_numeric($expiryMonth) || $expiryMonth < 1 || $expiryMonth > 12) {
        sendResponse(STATUS_FAIL, null, "Invalid expiry month");
    }
    
    if (!is_numeric($expiryYear) || $expiryYear < $currentYear || $expiryYear > ($currentYear + 20)) {
        sendResponse(STATUS_FAIL, null, "Invalid expiry year");
    }
    
    // Check if card is expired
    if ($expiryYear == $currentYear && $expiryMonth < $currentMonth) {
        sendResponse(STATUS_FAIL, null, "Card is expired");
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
    
    // If setting as default, unset other defaults for this customer
    if ($isDefault == '1') {
        $updateStmt = $conn->prepare("
            UPDATE payment_methods 
            SET is_default = 0 
            WHERE customer_id = ? AND id != ?
        ");
        $updateStmt->execute([$customer['customer_id'], $paymentMethodId]);
    }
    
    // Update payment method - now including card_type
    $stmt = $conn->prepare("
        UPDATE payment_methods 
        SET 
            card_type = ?,
            card_holder_name = ?,
            expiry_month = ?,
            expiry_year = ?,
            nickname = ?,
            is_default = ?,
            updated_at = NOW()
        WHERE id = ? AND customer_id = ?
    ");
    
    $stmt->execute([
        $cardType,
        $cardHolderName,
        $expiryMonth,
        $expiryYear,
        $nickname,
        $isDefault,
        $paymentMethodId,
        $customer['customer_id']
    ]);
    
    $affectedRows = $stmt->rowCount();
    
    // Log update result
    error_log("Update payment method - Affected rows: " . $affectedRows);
    
    // Commit transaction
    $conn->commit();
    
    if ($affectedRows > 0 || $isDefault == '1') {
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
        
        // Log the card type from database
        error_log("Update payment method - DB card_type: " . $updatedPaymentMethod['card_type']);
        
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
        
        sendResponse(STATUS_SUCCESS, $responseData, "Payment method updated successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "No changes were made to the payment method");
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Update payment method error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Failed to update payment method: " . $e->getMessage());
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Update payment method error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update payment method");
} finally {
    $db->disconnect();
}
?>