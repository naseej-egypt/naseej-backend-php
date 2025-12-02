<?php
/**
 * Naseej User App - Add New Address
 * POST: /addresses/add_address.php
 * Body: street, city, state, zip_code, country, label, latitude (optional), longitude (optional), is_default (optional)
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
    $street = filterRequest("street");
    $city = filterRequest("city");
    $state = filterRequest("state");
    $zipCode = filterRequest("zip_code");
    $country = filterRequest("country");
    $label = filterRequest("label") ?: 'Home'; // Get label from request, default to 'Home'
    $latitude = filterRequest("latitude") ?: null;
    $longitude = filterRequest("longitude") ?: null;
    $isDefault = filterRequest("is_default") ?: '0';
    
    // Validate required fields
    $errors = validateRequired(['street', 'city', 'state', 'zip_code', 'country']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate label
    $allowedLabels = ['Home', 'Work', 'Other'];
    if (!in_array($label, $allowedLabels)) {
        $label = 'Home'; // Default to Home if invalid label
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // If this is set as default, unset all other defaults for this customer
    if ($isDefault == '1') {
        $updateStmt = $conn->prepare("
            UPDATE customer_addresses 
            SET is_default = 0 
            WHERE customer_id = ?
        ");
        $updateStmt->execute([$customer['customer_id']]);
    }
    
    // Insert new address with label
    $stmt = $conn->prepare("
        INSERT INTO customer_addresses 
        (customer_id, label, street, city, state, zip_code, country, latitude, longitude, is_default, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $customer['customer_id'],
        $label,
        $street,
        $city,
        $state,
        $zipCode,
        $country,
        $latitude,
        $longitude,
        $isDefault
    ]);
    
    $addressId = $conn->lastInsertId();
    
    // Commit transaction
    $conn->commit();
    
    // Get the newly created address
    $getStmt = $conn->prepare("
        SELECT 
            id, customer_id, label, street, city, state, zip_code, country, 
            latitude, longitude, is_default, created_at, updated_at
        FROM customer_addresses 
        WHERE id = ?
    ");
    
    $getStmt->execute([$addressId]);
    $newAddress = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $responseData = [
        'id' => (string)$newAddress['id'],
        'customer_id' => (string)$newAddress['customer_id'],
        'label' => $newAddress['label'],
        'streetAddress' => $newAddress['street'],
        'city' => $newAddress['city'],
        'state' => $newAddress['state'],
        'zipCode' => $newAddress['zip_code'],
        'country' => $newAddress['country'],
        'latitude' => $newAddress['latitude'],
        'longitude' => $newAddress['longitude'],
        'isDefault' => (bool)$newAddress['is_default'],
        'created_at' => formatDateTime($newAddress['created_at']),
        'updated_at' => formatDateTime($newAddress['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Address added successfully");
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Add address error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add address");
} finally {
    $db->disconnect();
}
?>