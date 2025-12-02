<?php
/**
 * Naseej Admin - Add New Store
 * POST: /stores/add.php
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
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Validate required fields
    $errors = validateRequired([
        'name', 'address', 'phone', 'email', 'latitude', 'longitude',
        'manager_name', 'manager_phone', 'opening_time', 'closing_time', 'working_days'
    ]);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate name
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Store name must be between 2 and 100 characters");
    }
    
    // Validate email
    if (!isValidEmail($email)) {
        sendResponse(STATUS_FAIL, null, "Invalid email format");
    }
    
    // Check if email exists
    $checkStmt = $conn->prepare("SELECT id FROM stores WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
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
    
    // Handle image upload
    $imageName = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $imageName = uploadImage('file', STORES_DIR);
        if ($imageName === 'fail') {
            sendResponse(STATUS_FAIL, null, "Failed to upload image. Check file size and format.");
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert store
        $stmt = $conn->prepare("
            INSERT INTO stores 
            (name, address, phone, email, latitude, longitude, image_url, is_active,
             manager_name, manager_phone, opening_time, closing_time, working_days,
             created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $name,
            $address,
            $phone,
            $email,
            (float)$latitude,
            (float)$longitude,
            $imageName,
            $isActive,
            $managerName,
            $managerPhone,
            $openingTime,
            $closingTime,
            json_encode($workingDays)
        ]);
        
        $storeId = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        logActivity($conn, $admin['admin_id'], 'create', 'store', $storeId, "Created store: $name");
        
        // Get created store
        $getStmt = $conn->prepare("
            SELECT 
                id, name, address, phone, email, latitude, longitude,
                image_url, is_active, manager_name, manager_phone,
                opening_time, closing_time, working_days, created_at
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
            'created_at' => formatDateTime($store['created_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Store added successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded image if transaction failed
        if ($imageName) {
            deleteFile(STORES_DIR, $imageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Add store error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add store");
}
?>