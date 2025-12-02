<?php
/**
 * Naseej User App - Set Default Address
 * POST: /addresses/set_default_address.php
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
    
    // Verify address belongs to customer
    $checkStmt = $conn->prepare("
        SELECT id FROM customer_addresses 
        WHERE id = ? AND customer_id = ?
    ");
    $checkStmt->execute([$addressId, $customer['customer_id']]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Address not found or access denied");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Unset all defaults for this customer
    $unsetStmt = $conn->prepare("
        UPDATE customer_addresses 
        SET is_default = 0 
        WHERE customer_id = ?
    ");
    $unsetStmt->execute([$customer['customer_id']]);
    
    // Set the new default
    $setStmt = $conn->prepare("
        UPDATE customer_addresses 
        SET is_default = 1, updated_at = NOW() 
        WHERE id = ? AND customer_id = ?
    ");
    $setStmt->execute([$addressId, $customer['customer_id']]);
    
    // Commit transaction
    $conn->commit();
    
    // Get updated address
    $getStmt = $conn->prepare("
        SELECT 
            id, customer_id, street, city, state, zip_code, country, 
            latitude, longitude, is_default, created_at, updated_at
        FROM customer_addresses 
        WHERE id = ?
    ");
    
    $getStmt->execute([$addressId]);
    $updatedAddress = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $responseData = [
        'id' => (string)$updatedAddress['id'],
        'customer_id' => (string)$updatedAddress['customer_id'],
        'label' => 'Home',
        'fullName' => $customer['name'],
        'phone' => $customer['phone'],
        'streetAddress' => $updatedAddress['street'],
        'city' => $updatedAddress['city'],
        'state' => $updatedAddress['state'],
        'zipCode' => $updatedAddress['zip_code'],
        'country' => $updatedAddress['country'],
        'latitude' => $updatedAddress['latitude'],
        'longitude' => $updatedAddress['longitude'],
        'isDefault' => (bool)$updatedAddress['is_default'],
        'created_at' => formatDateTime($updatedAddress['created_at']),
        'updated_at' => formatDateTime($updatedAddress['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Default address updated successfully");
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Set default address error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to set default address");
} finally {
    $db->disconnect();
}
?>