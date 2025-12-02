<?php
/**
 * Naseej Admin - Update Customer
 * POST: /customers/update.php
 * Headers: Authorization: Bearer <token>
 * Body: customer_id, name, phone, is_active (optional)
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Only allow POST requests
if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get and validate input
    $customerId = filterRequest("customer_id");
    $name = filterRequest("name");
    $phone = filterRequest("phone");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    // Validate required fields
    $errors = validateRequired(['customer_id', 'name', 'phone']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if customer exists
    $checkStmt = $conn->prepare("SELECT id, name, email FROM customers WHERE id = ?");
    $checkStmt->execute([$customerId]);
    $existingCustomer = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingCustomer) {
        sendResponse(STATUS_FAIL, null, "Customer not found");
    }
    
    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Name must be between 2 and 100 characters");
    }
    
    // Validate phone number
    if (!isValidPhone($phone)) {
        sendResponse(STATUS_FAIL, null, "Invalid phone number format");
    }
    
    // Update customer
    $updateFields = [
        "name = ?",
        "phone = ?",
        "updated_at = NOW()"
    ];
    
    $params = [$name, $phone];
    
    if ($isActive !== null) {
        $updateFields[] = "is_active = ?";
        $params[] = $isActive;
    }
    
    $params[] = $customerId;
    
    $stmt = $conn->prepare("
        UPDATE customers 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    // Log activity
    logActivity($conn, $admin['admin_id'], 'update', 'customer', $customerId, "Updated customer: $name");
    
    // Get updated customer
    $getStmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            profile_image,
            is_active,
            total_orders,
            total_spent,
            updated_at
        FROM customers
        WHERE id = ?
    ");
    $getStmt->execute([$customerId]);
    $customer = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $profileImageUrl = null;
    if (!empty($customer['profile_image'])) {
        $profileImageUrl = getImageUrl($customer['profile_image'], 'profiles');
    }
    
    $responseData = [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'email' => $customer['email'],
        'phone' => $customer['phone'],
        'profile_image' => $profileImageUrl,
        'is_active' => (bool)$customer['is_active'],
        'total_orders' => (int)$customer['total_orders'],
        'total_spent' => (float)$customer['total_spent'],
        'updated_at' => formatDateTime($customer['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Customer updated successfully");
    
} catch (PDOException $e) {
    error_log("Update customer error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update customer");
}
?>