<?php
/**
 * Naseej Admin - Update Admin
 * POST: /admin/update.php
 * Headers: Authorization: Bearer <token>
 * Body: admin_id, name, role, phone_number, is_active
 * 
 * Updates admin information (super_admin only)
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
    // Verify authentication and require super_admin role
    $admin = requireRole($conn, ['super_admin']);
    
    // Get and validate input
    $adminId = filterRequest("admin_id");
    $name = filterRequest("name");
    $role = filterRequest("role");
    $phoneNumber = filterRequest("phone_number");
    $isActive = filterRequest("is_active");
    
    // Validate required fields
    if (empty($adminId)) {
        sendResponse(STATUS_FAIL, null, "Admin ID is required");
    }
    
    // Prevent super_admin from modifying themselves
    if ($adminId == $admin['admin_id']) {
        sendResponse(STATUS_FAIL, null, "Cannot modify your own account from this interface");
    }
    
    // Validate role if provided
    if (!empty($role)) {
        $allowedRoles = ['admin', 'manager'];
        if (!in_array($role, $allowedRoles)) {
            sendResponse(STATUS_FAIL, null, "Invalid role");
        }
    }
    
    // Get current admin data
    $currentStmt = $conn->prepare("SELECT name, role, is_active, email FROM admins WHERE id = ?");
    $currentStmt->execute([$adminId]);
    $currentAdmin = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentAdmin) {
        sendResponse(STATUS_FAIL, null, "Admin not found");
    }
    
    // Build update query dynamically
    $updateFields = [];
    $params = [];
    
    if (!empty($name)) {
        $updateFields[] = "name = ?";
        $params[] = $name;
    }
    
    if (!empty($role)) {
        $updateFields[] = "role = ?";
        $params[] = $role;
    }
    
    if (!empty($phoneNumber)) {
        $updateFields[] = "phone_number = ?";
        $params[] = $phoneNumber;
    }
    
    if ($isActive !== null && $isActive !== '') {
        $updateFields[] = "is_active = ?";
        $params[] = filter_var($isActive, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    
    if (empty($updateFields)) {
        sendResponse(STATUS_SUCCESS, null, "No changes detected");
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    // Add admin_id to params
    $params[] = $adminId;
    
    $updateQuery = "UPDATE admins SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->execute($params);
    
    if ($updateStmt->rowCount() > 0 || !empty($params)) {
        // Get updated admin
        $selectStmt = $conn->prepare("
            SELECT 
                id,
                email,
                name,
                phone_number,
                role,
                is_active,
                updated_at
            FROM admins 
            WHERE id = ?
        ");
        
        $selectStmt->execute([$adminId]);
        $updatedAdmin = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log activity
        $action = isset($params[array_search($adminId, $params) - 1]) && 
                  in_array('is_active = ?', $updateFields) ? 
                 ($updatedAdmin['is_active'] ? 'activated' : 'deactivated') : 
                 'updated';
                 
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'admin',
            $adminId,
            ucfirst($action) . " admin: {$updatedAdmin['name']} ({$updatedAdmin['email']})"
        );
        
        // Prepare response
        $responseData = [
            'admin' => [
                'id' => $updatedAdmin['id'],
                'email' => $updatedAdmin['email'],
                'name' => $updatedAdmin['name'],
                'phone_number' => $updatedAdmin['phone_number'],
                'role' => $updatedAdmin['role'],
                'is_active' => (bool)$updatedAdmin['is_active'],
                'updated_at' => formatDateTime($updatedAdmin['updated_at'])
            ]
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Admin updated successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "No changes made or admin not found");
    }
    
} catch (PDOException $e) {
    error_log("Update admin error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while updating admin");
}
?>