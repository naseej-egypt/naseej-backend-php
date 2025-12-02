<?php
/**
 * Naseej Admin - Upload Store Image
 * POST: /stores/upload_image.php
 * Headers: Authorization: Bearer <token>
 * Body: store_id
 * Files: file (store image)
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
    
    // Get store ID
    $storeId = filterRequest("store_id");
    
    if (empty($storeId)) {
        sendResponse(STATUS_FAIL, null, "Store ID is required");
    }
    
    // Check if store exists
    $stmt = $conn->prepare("SELECT id, name, image_url FROM stores WHERE id = ?");
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        sendResponse(STATUS_FAIL, null, "Store not found");
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(STATUS_FAIL, null, "No file uploaded or upload error occurred");
    }
    
    // Get current image
    $currentImage = $store['image_url'];
    
    // Upload new image - let the uploadImage function handle validation
    $newImageName = uploadImage('file', STORES_DIR);
    
    if ($newImageName === 'fail') {
        sendResponse(STATUS_FAIL, null, "Failed to upload image. Check file size and format. Allowed types: " . implode(', ', ALLOWED_IMAGES));
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update database with new image
        $updateStmt = $conn->prepare("
            UPDATE stores 
            SET image_url = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newImageName, $storeId]);
        
        // Delete old image if exists
        if (!empty($currentImage) && $currentImage !== 'fail') {
            deleteFile(STORES_DIR, $currentImage);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Generate image URL
        $imageUrl = getImageUrl($newImageName, 'stores');
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'store',
            $storeId,
            "Updated image for store: {$store['name']}"
        );
        
        // Get updated store details
        $getStmt = $conn->prepare("
            SELECT 
                id, name, address, phone, email, latitude, longitude,
                image_url, is_active, manager_name, manager_phone,
                opening_time, closing_time, working_days, updated_at
            FROM stores WHERE id = ?
        ");
        $getStmt->execute([$storeId]);
        $updatedStore = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        $workingDays = parseJsonArray($updatedStore['working_days']);
        
        // Check if store is currently open
        $now = new DateTime();
        $currentDay = $now->format('l');
        $currentTime = $now->format('H:i:s');
        
        $isOpen = $updatedStore['is_active'] && 
                  in_array($currentDay, $workingDays) && 
                  $currentTime >= $updatedStore['opening_time'] && 
                  $currentTime <= $updatedStore['closing_time'];
        
        $responseData = [
            'id' => $updatedStore['id'],
            'name' => $updatedStore['name'],
            'address' => $updatedStore['address'],
            'phone' => $updatedStore['phone'],
            'email' => $updatedStore['email'],
            'latitude' => (float)$updatedStore['latitude'],
            'longitude' => (float)$updatedStore['longitude'],
            'image_url' => $imageUrl,
            'image_name' => $newImageName,
            'is_active' => (bool)$updatedStore['is_active'],
            'manager_name' => $updatedStore['manager_name'],
            'manager_phone' => $updatedStore['manager_phone'],
            'opening_time' => $updatedStore['opening_time'],
            'closing_time' => $updatedStore['closing_time'],
            'working_days' => $workingDays,
            'is_open' => $isOpen,
            'updated_at' => formatDateTime($updatedStore['updated_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Store image uploaded successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded image if database update failed
        if (!empty($newImageName) && $newImageName !== 'fail') {
            deleteFile(STORES_DIR, $newImageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Upload store image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to upload store image");
}
?>