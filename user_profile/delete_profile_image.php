<?php
/**
 * Naseej User App - Delete Profile Image
 * POST: /user_profile/delete_profile_image.php
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
    
    // Get current profile image
    $stmt = $conn->prepare("SELECT profile_image FROM customers WHERE id = ?");
    $stmt->execute([$customer['customer_id']]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentData || empty($currentData['profile_image'])) {
        sendResponse(STATUS_FAIL, null, "No profile image to delete");
    }
    
    $oldImage = $currentData['profile_image'];
    
    // Update database (set profile_image to NULL)
    $updateStmt = $conn->prepare("
        UPDATE customers 
        SET profile_image = NULL, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $updateStmt->execute([$customer['customer_id']]);
    
    // Delete file from server
    if ($oldImage && $oldImage !== "fail") {
        deleteFile(PROFILES_DIR, $oldImage);
    }
    
    sendResponse(STATUS_SUCCESS, null, "Profile image deleted successfully");
    
} catch (PDOException $e) {
    error_log("Delete profile image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete profile image");
}
?>