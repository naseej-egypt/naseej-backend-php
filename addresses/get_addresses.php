<?php
/**
 * Naseej User App - Get User Addresses
 * POST: /addresses/get_addresses.php
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
    
    // Get all addresses for the customer
    $stmt = $conn->prepare("
        SELECT 
            id,
            customer_id,
            label,
            street,
            city,
            state,
            zip_code,
            country,
            latitude,
            longitude,
            is_default,
            created_at,
            updated_at
        FROM customer_addresses
        WHERE customer_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    
    $stmt->execute([$customer['customer_id']]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response data
    $formattedAddresses = [];
    foreach ($addresses as $address) {
        $formattedAddresses[] = [
            'id' => (string)$address['id'],
            'customer_id' => (string)$address['customer_id'],
            'label' => $address['label'],
            'streetAddress' => $address['street'],
            'city' => $address['city'],
            'state' => $address['state'],
            'zipCode' => $address['zip_code'],
            'country' => $address['country'],
            'latitude' => $address['latitude'],
            'longitude' => $address['longitude'],
            'isDefault' => (bool)$address['is_default'],
            'created_at' => formatDateTime($address['created_at']),
            'updated_at' => formatDateTime($address['updated_at'])
        ];
    }
    
    sendResponse(STATUS_SUCCESS, [
        'addresses' => $formattedAddresses,
        'total' => count($formattedAddresses)
    ], "Addresses retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get addresses error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve addresses");
} finally {
    $db->disconnect();
}
?>