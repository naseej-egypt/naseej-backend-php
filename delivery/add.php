<?php
/**
 * FILE: delivery/add.php
 * Add New Delivery Man
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
    
    $name = filterRequest("name");
    $email = filterRequest("email");
    $phone = filterRequest("phone");
    $password = filterRequest("password");
    $vehicleType = filterRequest("vehicle_type");
    $vehicleNumber = filterRequest("vehicle_number");
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Validate required fields
    $errors = validateRequired(['name', 'email', 'phone', 'password', 'vehicle_type', 'vehicle_number']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate name
    if (strlen($name) < 2 || strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Name must be between 2 and 100 characters");
    }
    
    // Validate email
    if (!isValidEmail($email)) {
        sendResponse(STATUS_FAIL, null, "Invalid email format");
    }
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM delivery_men WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Email already exists");
    }
    
    // Validate phone
    if (!isValidPhone($phone)) {
        sendResponse(STATUS_FAIL, null, "Invalid phone number format");
    }
    
    // Validate password
    if (strlen($password) < 6) {
        sendResponse(STATUS_FAIL, null, "Password must be at least 6 characters");
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Handle profile image upload
    $profileImage = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $profileImage = uploadImage('file', PROFILES_DIR, MAX_PROFILE_SIZE);
        if ($profileImage === 'fail') {
            sendResponse(STATUS_FAIL, null, "Failed to upload profile image");
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO delivery_men 
            (name, email, phone, password, profile_image, vehicle_type, vehicle_number, 
             status, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $name,
            $email,
            $phone,
            $hashedPassword,
            $profileImage,
            $vehicleType,
            $vehicleNumber,
            $isActive
        ]);
        
        $deliveryManId = $conn->lastInsertId();
        $conn->commit();
        
        logActivity($conn, $admin['admin_id'], 'create', 'delivery_man', $deliveryManId, "Created delivery man: $name");
        
        // Get created delivery man
        $getStmt = $conn->prepare("
            SELECT id, name, email, phone, profile_image, vehicle_type, 
                   vehicle_number, status, is_active, created_at
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
            'created_at' => formatDateTime($deliveryMan['created_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Delivery man added successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        if ($profileImage) {
            deleteFile(PROFILES_DIR, $profileImage);
        }
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Add delivery man error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to add delivery man");
}
?>