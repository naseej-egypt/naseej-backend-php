<?php
/**
 * FILE: delivery/upload_image.php
 * Upload Profile Image
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
    
    if (empty($deliveryManId)) {
        sendResponse(STATUS_FAIL, null, "Delivery man ID is required");
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(STATUS_FAIL, null, "No file uploaded or upload error occurred");
    }
    
    $stmt = $conn->prepare("SELECT id, name, profile_image FROM delivery_men WHERE id = ?");
    $stmt->execute([$deliveryManId]);
    $deliveryMan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliveryMan) {
        sendResponse(STATUS_FAIL, null, "Delivery man not found");
    }
    
    $currentImage = $deliveryMan['profile_image'];
    
    $newImageName = uploadImage('file', PROFILES_DIR, MAX_PROFILE_SIZE);
    
    if ($newImageName === 'fail') {
        sendResponse(STATUS_FAIL, null, "Failed to upload image");
    }
    
    $conn->beginTransaction();
    
    try {
        $updateStmt = $conn->prepare("
            UPDATE delivery_men 
            SET profile_image = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newImageName, $deliveryManId]);
        
        if (!empty($currentImage) && $currentImage !== 'fail') {
            deleteFile(PROFILES_DIR, $currentImage);
        }
        
        $conn->commit();
        
        $imageUrl = getImageUrl($newImageName, 'profiles');
        
        logActivity($conn, $admin['admin_id'], 'update', 'delivery_man', $deliveryManId, 
                    "Updated profile image for: {$deliveryMan['name']}");
        
        $responseData = [
            'delivery_man_id' => $deliveryManId,
            'profile_image' => $imageUrl,
            'image_name' => $newImageName
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Profile image uploaded successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        if (!empty($newImageName) && $newImageName !== 'fail') {
            deleteFile(PROFILES_DIR, $newImageName);
        }
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Upload image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to upload image");
}
?>