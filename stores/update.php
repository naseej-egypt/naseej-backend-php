<?php
/**
 * Naseej Admin - Update Store
 * POST: /stores/update.php
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
    
    // Get input
    $storeId = filterRequest("store_id");
    $name = filterRequest("name");
    $address = filterRequest("address");
    $phone = filterRequest("phone");
    $email = filterRequest("email");
    $latitude = filterRequest("latitude");
    $longitude = filterRequest("longitude");
    $managerName = filterRequest("manager_name");
    $managerPhone = filterRequest("manager_phone");
    $openingTime = filterRequest("opening_time");
    $closingTime = filterRequest("closing_time");
    $workingDaysJson = filterRequest("working_days");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    // Validate required fields
    $errors = validateRequired([
        'store_id', 'name', 'address', 'phone', 'email', 'latitude', 'longitude',
        'manager_name', 'manager_phone', 'opening_time', 'closing_time', 'working_days'
    ]);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Check if store exists
    $checkStmt = $conn->prepare("SELECT id, name FROM stores WHERE id = ?");
    $checkStmt->execute([$storeId]);
    $existingStore = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingStore) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    // Validate name
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Store name must be between 2 and 100 characters");
    }
    
    // Validate email
    if (!isValidEmail($email)) {
        sendResponse(STATUS_FAIL, null, "Invalid email format");
    }
    
    // Check email uniqueness
    $emailCheckStmt = $conn->prepare("SELECT id FROM stores WHERE email = ? AND id != ?");
    $emailCheckStmt->execute([$email, $storeId]);
    if ($emailCheckStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Email already exists");
    }
    
    // Validate phones
    if (!isValidPhone($phone)) {
        sendResponse(STATUS_FAIL, null, "Invalid store phone number format");
    }
    
    if (!isValidPhone($managerPhone)) {
        sendResponse(STATUS_FAIL, null, "Invalid manager phone number format");
    }
    
    // Validate coordinates
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        sendResponse(STATUS_FAIL, null, "Invalid latitude or longitude");
    }
    
    // CRITICAL: Use the new validation function
    $workingDays = validateAndParseWorkingDays($workingDaysJson);
    if ($workingDays === false) {
        sendResponse(STATUS_FAIL, null, "Working days must be a valid non-empty array. Received: " . $workingDaysJson);
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $openingTime)) {
        sendResponse(STATUS_FAIL, null, "Invalid opening time format");
    }
    
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $closingTime)) {
        sendResponse(STATUS_FAIL, null, "Invalid closing time format");
    }
    
    // Update store
    $updateFields = [
        "name = ?",
        "address = ?",
        "phone = ?",
        "email = ?",
        "latitude = ?",
        "longitude = ?",
        "manager_name = ?",
        "manager_phone = ?",
        "opening_time = ?",
        "closing_time = ?",
        "working_days = ?",
        "updated_at = NOW()"
    ];
    
    $params = [
        $name,
        $address,
        $phone,
        $email,
        (float)$latitude,
        (float)$longitude,
        $managerName,
        $managerPhone,
        $openingTime,
        $closingTime,
        json_encode($workingDays)
    ];
    
    if ($isActive !== null) {
        $updateFields[] = "is_active = ?";
        $params[] = $isActive;
    }
    
    $params[] = $storeId;
    
    $stmt = $conn->prepare("
        UPDATE stores 
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    // Log activity
    logActivity($conn, $admin['admin_id'], 'update', 'store', $storeId, "Updated store: $name");
    
    // Get updated store
    $getStmt = $conn->prepare("
        SELECT 
            id, name, address, phone, email, latitude, longitude,
            image_url, is_active, manager_name, manager_phone,
            opening_time, closing_time, working_days, updated_at
        FROM stores WHERE id = ?
    ");
    $getStmt->execute([$storeId]);
    $store = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    $imageUrl = null;
    if (!empty($store['image_url'])) {
        $imageUrl = getImageUrl($store['image_url'], 'stores');
    }
    
    $responseData = [
        'id' => $store['id'],
        'name' => $store['name'],
        'address' => $store['address'],
        'phone' => $store['phone'],
        'email' => $store['email'],
        'latitude' => (float)$store['latitude'],
        'longitude' => (float)$store['longitude'],
        'image_url' => $imageUrl,
        'is_active' => (bool)$store['is_active'],
        'manager_name' => $store['manager_name'],
        'manager_phone' => $store['manager_phone'],
        'opening_time' => $store['opening_time'],
        'closing_time' => $store['closing_time'],
        'working_days' => parseJsonArray($store['working_days']),
        'updated_at' => formatDateTime($store['updated_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Store updated successfully");
    
} catch (PDOException $e) {
    error_log("Update store error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to update store");
}
?>