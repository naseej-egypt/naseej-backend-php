<?php
/**
 * Naseej Admin - Add New Admin
 * POST: /admin/add.php
 * Headers: Authorization: Bearer <token>
 * Body: email, password, name, role, phone_number (optional)
 * 
 * Creates a new admin account (super_admin only)
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
    $email = filterRequest("email");
    $password = filterRequest("password");
    $name = filterRequest("name");
    $role = filterRequest("role");
    $phoneNumber = filterRequest("phone_number");
    
    // Validate required fields
    $errors = validateRequired(['email', 'password', 'name', 'role']);
    if (!empty($errors)) {
        sendResponse(STATUS_FAIL, null, implode(", ", $errors));
    }
    
    // Validate email format
    if (!isValidEmail($email)) {
        sendResponse(STATUS_FAIL, null, "Invalid email format");
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        sendResponse(STATUS_FAIL, null, "Password must be at least 6 characters");
    }
    
    // Validate role
    $allowedRoles = ['admin', 'manager'];
    if (!in_array($role, $allowedRoles)) {
        sendResponse(STATUS_FAIL, null, "Invalid role. Allowed roles: " . implode(', ', $allowedRoles));
    }
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $checkStmt->execute([$email]);
    
    if ($checkStmt->fetch()) {
        sendResponse(STATUS_FAIL, null, "Email already exists");
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new admin
    $insertStmt = $conn->prepare("
        INSERT INTO admins 
        (email, password, name, phone_number, role, is_active, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");
    
    $insertStmt->execute([
        $email,
        $passwordHash,
        $name,
        $phoneNumber ?: null,
        $role
    ]);
    
    $newAdminId = $conn->lastInsertId();
    
    if ($newAdminId) {
        // Get the newly created admin
        $selectStmt = $conn->prepare("
            SELECT 
                id,
                email,
                name,
                phone_number,
                role,
                is_active,
                created_at
            FROM admins 
            WHERE id = ?
        ");
        
        $selectStmt->execute([$newAdminId]);
        $newAdmin = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'create',
            'admin',
            $newAdminId,
            "Created new admin: {$newAdmin['name']} ({$newAdmin['email']}) with role: {$newAdmin['role']}"
        );
        
        // Prepare response
        $responseData = [
            'admin' => [
                'id' => $newAdmin['id'],
                'email' => $newAdmin['email'],
                'name' => $newAdmin['name'],
                'phone_number' => $newAdmin['phone_number'],
                'role' => $newAdmin['role'],
                'is_active' => (bool)$newAdmin['is_active'],
                'created_at' => formatDateTime($newAdmin['created_at'])
            ]
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Admin created successfully");
    } else {
        sendResponse(STATUS_FAIL, null, "Failed to create admin");
    }
    
} catch (PDOException $e) {
    error_log("Add admin error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "An error occurred while creating admin");
}
?>