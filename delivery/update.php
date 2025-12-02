<?php
/**
 * FILE: delivery/update.php
 * Update Delivery Man
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    $admin = verifyAuth($conn);
    
    $deliveryManId = filterRequest("delivery_man_id");
    $name = filterRequest("name");
    $email = filterRequest("email");
    $phone = filterRequest("phone");
    $vehicleType = filterRequest("vehicle_type");
    $vehicleNumber = filterRequest("vehicle_number");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    $errors = validateRequired(['delivery_man_id', 'name', 'email', 'phone', 'vehicle_type', 'vehicle_number']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if exists
    $checkStmt = $conn->prepare("SELECT id, name FROM delivery_men WHERE id = ?");
    $checkStmt->execute([$deliveryManId]);
    if (!$checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    // Validate
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Name must be between 2 and 100 characters");
    }
    
    if (!isValidEmail($email)) {
        sendResponse(STATUS_FAIL, null, "Invalid email format");
    }
    
    // Check email uniqueness
    $emailCheckStmt = $conn->prepare("SELECT id FROM delivery_men WHERE email = ? AND id != ?");
    $emailCheckStmt->execute([$email, $deliveryManId]);
    if ($emailCheckStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Email already exists");
    }
    
    if (!isValidPhone($phone)) {
        sendResponse(STATUS_FAIL, null, "Invalid phone number format");
    }
    
    // Build update query
    $updateFields = [
        "name = ?",
        "email = ?",
        "phone = ?",
        "vehicle_type = ?",
        "vehicle_number = ?",
        "updated_at = NOW()"
    ];
    
    $params = [$name, $email, $phone, $vehicleType, $vehicleNumber];
    
    if ($isActive !== null) {
        $updateFields[] = "is_active = ?";
        $params[] = $isActive;
    }
    
    $params[] = $deliveryManId;
    
    $stmt = $conn->prepare("
        UPDATE delivery_men 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    logActivity($conn, $admin['admin_id'], 'update', 'delivery_man', $deliveryManId, "Updated delivery man: $name");
    
    // Get updated data
    $getStmt = $conn->prepare("
        SELECT id, name, email, phone, profile_image, vehicle_type, 
               vehicle_number, status, is_active, rating, total_deliveries, updated_at
        FROM delivery_men WHERE id = ?
    ");
    $getStmt->execute([$deliveryManId]);
    $deliveryMan = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $profileImageUrl = null;
    if (!empty($deliveryMan['profile_image'])) {
        $profileImageUrl = getImageUrl($deliveryMan['profile_image'], 'profiles');
    }
    
    $responseData = [
        'id' => $deliveryMan['id'],
        'name' => $deliveryMan['name'],
        'email' => $deliveryMan['email'],
        'phone' => $deliveryMan['phone'],
        'profile_image' => $profileImageUrl,
        'vehicle_type' => $deliveryMan['vehicle_type'],
        'vehicle_number' => $deliveryMan['vehicle_number'],
        'status' => $deliveryMan['status'],
        'is_active' => (bool)$deliveryMan['is_active'],
        'rating' => (float)$deliveryMan['rating'],
        'total_deliveries' => (int)$deliveryMan['total_deliveries'],
        'updated_at' => formatDateTime($deliveryMan['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Delivery man updated successfully");
    
} catch (PDOException $e) {
    error_log("Update delivery man error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update delivery man");
}
?>