<?php
/**
 * Naseej Admin - Update Admin Profile
 * POST: /admin/update_profile.php
 * Headers: Authorization: Bearer <token>
 * Body: name, phone_number (optional: file for profile image)
 * 
 * Updates the authenticated admin's profile information
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
    $name = filterRequest("name");
    $phoneNumber = filterRequest("phone_number");
    
    // Validate name is required
    if (empty($name)) {
        sendResponse(STATUS_FAIL, null, "Name is required");
    }
    
    // Validate name length
    if (strlen($name) < 2) {
        sendResponse(STATUS_FAIL, null, "Name must be at least 2 characters");
    }
    
    if (strlen($name) > 100) {
        sendResponse(STATUS_FAIL, null, "Name must not exceed 100 characters");
    }
    
    // Validate phone number if provided
    if (!empty($phoneNumber) && !isValidPhone($phoneNumber)) {
        sendResponse(STATUS_FAIL, null, "Invalid phone number format");
    }
    
    // Get current profile data
    $stmt = $conn->prepare("SELECT name, phone_number FROM admins WHERE id = ?");
    $stmt->execute([$admin['admin_id']]);
    $currentProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentProfile) {
        sendResponse(STATUS_FAIL, null, "Admin not found");
    }
    
    // Check if anything changed
    if ($currentProfile['name'] === $name && 
        $currentProfile['phone_number'] === $phoneNumber) {
        sendResponse(STATUS_SUCCESS, null, "No changes detected");
    }
    
    // Update profile
    $updateStmt = $conn->prepare("
        UPDATE admins 
        SET 
            name = ?,
            phone_number = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([
        $name,
        $phoneNumber ?: null,
        $admin['admin_id']
    ]);
    
    if ($updateStmt->rowCount() > 0) {
        // Get updated profile
        $profileStmt = $conn->prepare("
            SELECT 
                id,
                email,
                name,
                phone_number,
                role,
                updated_at
            FROM admins 
            WHERE id = ?
        ");
        $profileStmt->execute([$admin['admin_id']]);
        $updatedProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'admin',
            $admin['admin_id'],
            "Updated profile information"
        );
        
        // Prepare response
        $responseData = [
            'id' => $updatedProfile['id'],
            'email' => $updatedProfile['email'],
            'name' => $updatedProfile['name'],
            'phone_number' => $updatedProfile['phone_number'],
            'role' => $updatedProfile['role'],
            'updated_at' => formatDateTime($updatedProfile['updated_at'])
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Profile updated successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to update profile");
    }
    
} catch (PDOException $e) {
    error_log("Update profile error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while updating profile");
}
?>