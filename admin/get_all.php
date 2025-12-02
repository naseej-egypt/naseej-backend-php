<?php
/**
 * Naseej Admin - Get All Admins
 * GET/POST: /admin/get_all.php
 * Headers: Authorization: Bearer <token>
 * 
 * Returns all admins (super_admin only)
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

// Allow GET and POST methods
if (!isRequestMethod('GET') && !isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

// Create database connection
$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication and require super_admin role
    $admin = requireRole($conn, ['super_admin']);
    
    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : ITEMS_PER_PAGE;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role = isset($_GET['role']) ? trim($_GET['role']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    $offset = ($page - 1) * $limit;
    
    // Build query
    $query = "
        SELECT 
            id,
            email,
            name,
            phone_number,
            profile_image,
            role,
            is_active,
            last_login,
            created_at,
            updated_at
        FROM admins 
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add search condition
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Add role filter
    if (!empty($role) && in_array($role, ['super_admin', 'admin', 'manager'])) {
        $query .= " AND role = ?";
        $params[] = $role;
    }
    
    // Add status filter
    if ($status === 'active') {
        $query .= " AND is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND is_active = 0";
    }
    
    // Add ordering
    $query .= " ORDER BY created_at DESC";
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM admins WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (name LIKE ? OR email LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    if (!empty($role) && in_array($role, ['super_admin', 'admin', 'manager'])) {
        $countQuery .= " AND role = ?";
        $countParams[] = $role;
    }
    
    if ($status === 'active') {
        $countQuery .= " AND is_active = 1";
    } elseif ($status === 'inactive') {
        $countQuery .= " AND is_active = 0";
    }
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalData = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalAdmins = $totalData['total'];
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute main query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $formattedAdmins = [];
    foreach ($admins as $adminData) {
        $profileImageUrl = null;
        if (!empty($adminData['profile_image'])) {
            $profileImageUrl = getImageUrl($adminData['profile_image'], 'profiles');
        }
        
        $formattedAdmins[] = [
            'id' => $adminData['id'],
            'email' => $adminData['email'],
            'name' => $adminData['name'],
            'phone_number' => $adminData['phone_number'],
            'profile_image' => $profileImageUrl,
            'role' => $adminData['role'],
            'is_active' => (bool)$adminData['is_active'],
            'last_login' => formatDateTime($adminData['last_login']),
            'created_at' => formatDateTime($adminData['created_at']),
            'updated_at' => formatDateTime($adminData['updated_at'])
        ];
    }
    
    // Prepare pagination info
    $totalPages = ceil($totalAdmins / $limit);
    
    $responseData = [
        'admins' => $formattedAdmins,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $totalAdmins,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    // Log activity
    logActivity(
        $conn,
        $admin['admin_id'],
        'view',
        'admin_list',
        null,
        "Viewed admin list"
    );
    
    sendResponse(STATUS_SUCCESS, $responseData, "Admins retrieved successfully");
    
} catch (PDOException $e) {
    error_log("Get admins error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve admins");
}
?>