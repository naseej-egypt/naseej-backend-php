<?php
/**
 * Naseej User App - Upload Profile Image
 * POST: /user_profile/upload_profile_image.php
 * Files: profile_image
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
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(STATUS_FAIL, null, "No image file uploaded or upload error occurred");
    }
    
    $file = $_FILES['profile_image'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        sendResponse(STATUS_FAIL, null, "Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed");
    }
    
    // Validate file size (max 2MB for profile images)
    if ($file['size'] > MAX_PROFILE_SIZE) {
        sendResponse(STATUS_FAIL, null, "File size too large. Maximum size is 2MB");
    }
    
    // Get current profile image to delete later
    $getCurrentStmt = $conn->prepare("SELECT profile_image FROM customers WHERE id = ?");
    $getCurrentStmt->execute([$customer['customer_id']]);
    $currentData = $getCurrentStmt->fetch(PDO::FETCH_ASSOC);
    $oldImage = $currentData['profile_image'] ?? null;
    
    // Upload new image
    $filename = uploadImage('profile_image', PROFILES_DIR, MAX_PROFILE_SIZE);
    
    if ($filename === "fail") {
        sendResponse(STATUS_FAIL, null, "Failed to upload image");
    }
    
    // Update database
    $stmt = $conn->prepare("
        UPDATE customers 
        SET profile_image = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $stmt->execute([$filename, $customer['customer_id']]);
    
    // Delete old image if exists
    if ($oldImage && $oldImage !== "fail") {
        deleteFile(PROFILES_DIR, $oldImage);
    }
    
    // Get image URL
    $imageUrl = getImageUrl($filename, 'profiles');
    
    $responseData = [
        'profile_image' => $imageUrl,
        'profile_image_filename' => $filename
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Profile image uploaded successfully");
    
} catch (PDOException $e) {
    error_log("Upload profile image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to upload profile image");
}
?>