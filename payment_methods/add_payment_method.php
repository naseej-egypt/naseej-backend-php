<?php
/**
 * Naseej User App - Add New Payment Method
 * POST: /payment_methods/add_payment_method.php
 * Body: card_type, card_holder_name, card_number, expiry_month, expiry_year, cvv, nickname, is_default
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
    $cardType = filterRequest("card_type");
    $cardHolderName = filterRequest("card_holder_name");
    $cardNumber = filterRequest("card_number");
    $expiryMonth = filterRequest("expiry_month");
    $expiryYear = filterRequest("expiry_year");
    $cvv = filterRequest("cvv");
    $nickname = filterRequest("nickname") ?: null;
    $isDefault = filterRequest("is_default") ?: '0';
    
    // Validate required fields
    $errors = validateRequired(['card_type', 'card_holder_name', 'card_number', 'expiry_month', 'expiry_year', 'cvv']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate card type
    $allowedCardTypes = ['Visa', 'Mastercard', 'Amex'];
    if (!in_array($cardType, $allowedCardTypes)) {
        sendResponse(STATUS_FAIL, null, "Invalid card type");
    }
    
    // Validate card number (basic validation)
    $cleanedCardNumber = preg_replace('/\s+/', '', $cardNumber);
    if (!preg_match('/^\d{13,19}$/', $cleanedCardNumber)) {
        sendResponse(STATUS_FAIL, null, "Invalid card number");
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
    
    // Get last four digits
    $lastFourDigits = substr($cleanedCardNumber, -4);
    
    // Hash sensitive data for security
    $cardNumberHash = hash('sha256', $cleanedCardNumber);
    $cvvHash = hash('sha256', $cvv);
    
    // Generate token for payment gateway (in real scenario, use payment provider SDK)
    $token = 'tok_' . bin2hex(random_bytes(16));
    
    // Begin transaction
    $conn->beginTransaction();
    
    // If setting as default, unset other defaults for this customer
    if ($isDefault == '1') {
        $updateStmt = $conn->prepare("
            UPDATE payment_methods 
            SET is_default = 0 
            WHERE customer_id = ?
        ");
        $updateStmt->execute([$customer['customer_id']]);
    }
    
    // Insert new payment method directly (without stored procedure)
    $stmt = $conn->prepare("
        INSERT INTO payment_methods (
            customer_id, card_type, card_holder_name, card_number_hash,
            last_four_digits, expiry_month, expiry_year, cvv_hash,
            nickname, is_default, token
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $customer['customer_id'],
        $cardType,
        $cardHolderName,
        $cardNumberHash,
        $lastFourDigits,
        $expiryMonth,
        $expiryYear,
        $cvvHash,
        $nickname,
        $isDefault,
        $token
    ]);
    
    $paymentMethodId = $conn->lastInsertId();
    
    // Commit transaction
    $conn->commit();
    
    // Get the newly created payment method
    $getStmt = $conn->prepare("
        SELECT 
            id, customer_id, card_type, card_holder_name, card_number_hash,
            last_four_digits, expiry_month, expiry_year, nickname, 
            is_default, token, created_at, updated_at
        FROM payment_methods 
        WHERE id = ?
    ");
    
    $getStmt->execute([$paymentMethodId]);
    $newPaymentMethod = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $responseData = [
        'id' => (string)$newPaymentMethod['id'],
        'customer_id' => (string)$newPaymentMethod['customer_id'],
        'card_type' => $newPaymentMethod['card_type'],
        'card_holder_name' => $newPaymentMethod['card_holder_name'],
        'card_number' => '**** **** **** ' . $newPaymentMethod['last_four_digits'],
        'last_four_digits' => $newPaymentMethod['last_four_digits'],
        'expiry_month' => $newPaymentMethod['expiry_month'],
        'expiry_year' => $newPaymentMethod['expiry_year'],
        'expiry_date' => $newPaymentMethod['expiry_month'] . '/' . substr($newPaymentMethod['expiry_year'], -2),
        'nickname' => $newPaymentMethod['nickname'],
        'is_default' => (bool)$newPaymentMethod['is_default'],
        'token' => $newPaymentMethod['token'],
        'created_at' => formatDateTime($newPaymentMethod['created_at']),
        'updated_at' => formatDateTime($newPaymentMethod['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Payment method added successfully");
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Add payment method error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Failed to add payment method: " . $e->getMessage());
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Add payment method error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add payment method");
} finally {
    $db->disconnect();
}
?>