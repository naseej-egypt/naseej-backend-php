<?php
/**
 * Naseej User App - Get User Payment Methods
 * POST: /payment_methods/get_payment_methods.php
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
    
    // Get all payment methods for the customer
    $stmt = $conn->prepare("
        SELECT 
            id,
            customer_id,
            card_type,
            card_holder_name,
            card_number_hash,
            last_four_digits,
            expiry_month,
            expiry_year,
            nickname,
            is_default,
            token,
            created_at,
            updated_at
        FROM payment_methods
        WHERE customer_id = ? AND is_active = 1
        ORDER BY is_default DESC, created_at DESC
    ");
    
    $stmt->execute([$customer['customer_id']]);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response data
    $formattedPaymentMethods = [];
    foreach ($paymentMethods as $method) {
        $formattedPaymentMethods[] = [
            'id' => (string)$method['id'],
            'customer_id' => (string)$method['customer_id'],
            'card_type' => $method['card_type'],
            'card_holder_name' => $method['card_holder_name'],
            'card_number' => '**** **** **** ' . $method['last_four_digits'],
            'last_four_digits' => $method['last_four_digits'],
            'expiry_month' => $method['expiry_month'],
            'expiry_year' => $method['expiry_year'],
            'expiry_date' => $method['expiry_month'] . '/' . substr($method['expiry_year'], -2),
            'nickname' => $method['nickname'],
            'is_default' => (bool)$method['is_default'],
            'token' => $method['token'],
            'created_at' => formatDateTime($method['created_at']),
            'updated_at' => formatDateTime($method['updated_at'])
        ];
    }
    
    sendResponse(STATUS_SUCCESS, [
        'payment_methods' => $formattedPaymentMethods,
        'total' => count($formattedPaymentMethods)
    ], "Payment methods retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get payment methods error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve payment methods");
} finally {
    $db->disconnect();
}
?>