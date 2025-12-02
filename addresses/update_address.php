<?php
/**
 * Naseej User App - Update Address
 * POST: /addresses/update_address.php
 * Body: address_id, street, city, state, zip_code, country, label, latitude (optional), longitude (optional)
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
    $street = filterRequest("street");
    $city = filterRequest("city");
    $state = filterRequest("state");
    $zipCode = filterRequest("zip_code");
    $country = filterRequest("country");
    $label = filterRequest("label") ?: 'Home'; // Get label from request
    $latitude = filterRequest("latitude") ?: null;
    $longitude = filterRequest("longitude") ?: null;
    
    // Validate required fields
    $errors = validateRequired(['address_id', 'street', 'city', 'state', 'zip_code', 'country']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate label
    $allowedLabels = ['Home', 'Work', 'Other'];
    if (!in_array($label, $allowedLabels)) {
        $label = 'Home'; // Default to Home if invalid label
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
    
    // Update address with label
    $stmt = $conn->prepare("
        UPDATE customer_addresses 
        SET street = ?, 
            city = ?, 
            state = ?, 
            zip_code = ?, 
            country = ?, 
            label = ?,
            latitude = ?, 
            longitude = ?, 
            updated_at = NOW()
        WHERE id = ? AND customer_id = ?
    ");
    
    $stmt->execute([
        $street,
        $city,
        $state,
        $zipCode,
        $country,
        $label,
        $latitude,
        $longitude,
        $addressId,
        $customer['customer_id']
    ]);
    
    // Get updated address
    $getStmt = $conn->prepare("
        SELECT 
            id, customer_id, label, street, city, state, zip_code, country, 
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
        'label' => $updatedAddress['label'],
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
    
    sendResponse(STATUS_SUCCESS, $responseData, "Address updated successfully");
    
} catch (PDOException $e) {
    error_log("Update address error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update address");
} finally {
    $db->disconnect();
}
?>