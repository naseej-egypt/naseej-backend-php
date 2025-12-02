<?php
/**
 * Naseej Admin - Authentication Middleware
 * Validates admin authentication tokens
 */

/**
 * Verify admin authentication token
 * @param PDO $conn Database connection
 * @return array Admin data if valid, exits if invalid
 */
function verifyAuth($conn) {
    // Get token from Authorization header
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        // Expected format: "Bearer <token>"
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // If no token in header, check POST data
    if (!$token && isset($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    if (!$token) {
        sendResponse(STATUS_FAIL, null, ERR_UNAUTHORIZED);
    }
    
    try {
        // Validate token
        $stmt = $conn->prepare("
            SELECT 
                s.admin_id,
                a.email,
                a.name,
                a.role,
                a.is_active
            FROM admin_sessions s
            INNER JOIN admins a ON s.admin_id = a.id
            WHERE s.token = ? 
            AND s.expires_at > NOW()
            AND a.is_active = 1
        ");
        
        $stmt->execute([$token]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            sendResponse(STATUS_FAIL, null, ERR_UNAUTHORIZED);
        }
        
        return $admin;
        
    } catch (PDOException $e) {
        error_log("Auth verification error: " . $e->getMessage());
        sendResponse(STATUS_ERROR, null, ERR_DATABASE);
    }
}

/**
 * Check if admin has required role
 * @param array $admin Admin data from verifyAuth()
 * @param array $allowedRoles Array of allowed roles
 * @return bool
 */
function hasRole($admin, $allowedRoles = ['super_admin', 'admin', 'manager']) {
    return in_array($admin['role'], $allowedRoles);
}

/**
 * Require specific role(s)
 * @param PDO $conn Database connection
 * @param array $roles Required roles
 * @return array Admin data if authorized
 */
function requireRole($conn, $roles = ['super_admin', 'admin']) {
    $admin = verifyAuth($conn);
    
    if (!hasRole($admin, $roles)) {
        sendResponse(STATUS_FAIL, null, ERR_PERMISSION_DENIED);
    }
    
    return $admin;
}

/**
 * Create new session for admin
 * @param PDO $conn Database connection
 * @param int $adminId Admin ID
 * @return string Session token
 */
/**
 * Create new session for admin - 24 HOURS
 */
function createSession($conn, $adminId) {
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . ADMIN_TOKEN_EXPIRY_HOURS . ' hours'));
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO admin_sessions 
            (admin_id, token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $adminId,
            $token,
            getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $expiresAt
        ]);
        
        // Update last login
        $updateStmt = $conn->prepare("
            UPDATE admins 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$adminId]);
        
        return $token;
        
    } catch (PDOException $e) {
        error_log("Session creation error: " . $e->getMessage());
        sendResponse(STATUS_ERROR, null, ERR_DATABASE);
    }
}

/**
 * Delete session (logout)
 * @param PDO $conn Database connection
 * @param string $token Session token
 */
function deleteSession($conn, $token) {
    try {
        $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("Session deletion error: " . $e->getMessage());
    }
}

/**
 * Clean expired sessions
 * @param PDO $conn Database connection
 */
function cleanExpiredSessions($conn) {
    try {
        $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Session cleanup error: " . $e->getMessage());
    }
}
/**
 * Naseej User App - Authentication Middleware
 * Validates customer authentication tokens
 * 
 * Add to core/middleware.php or create separate user_middleware.php
 */

/**
 * Verify customer authentication token
 * @param PDO $conn Database connection
 * @return array Customer data if valid, exits if invalid
 */
function verifyCustomerAuth($conn) {
    // Get token from Authorization header or POST
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    if (!$token && isset($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    if (!$token) {
        sendResponse(STATUS_FAIL, null, "Authentication required. Please login.");
    }
    
    try {
        // Validate token
        $stmt = $conn->prepare("
            SELECT 
                s.customer_id,
                c.email,
                c.name,
                c.phone,
                c.profile_image,
                c.is_active,
                c.email_verified
            FROM customer_sessions s
            INNER JOIN customers c ON s.customer_id = c.id
            WHERE s.token = ? 
            AND s.expires_at > NOW()
            AND c.is_active = 1
            AND c.email_verified = 1
        ");
        
        $stmt->execute([$token]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            sendResponse(STATUS_FAIL, null, "Invalid or expired session. Please login again.");
        }
        
        return $customer;
        
    } catch (PDOException $e) {
        error_log("Customer auth verification error: " . $e->getMessage());
        sendResponse(STATUS_ERROR, null, "Authentication failed");
    }
}

/**
 * Create new session for customer
 * @param PDO $conn Database connection
 * @param int $customerId Customer ID
 * @return string Session token
 */
/**
 * Create new session for customer - 7 DAYS
 */
function createCustomerSession($conn, $customerId) {
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . USER_TOKEN_EXPIRY_HOURS . ' hours'));
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO customer_sessions 
            (customer_id, token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $customerId,
            $token,
            getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $expiresAt
        ]);
        
        return $token;
        
    } catch (PDOException $e) {
        error_log("Customer session creation error: " . $e->getMessage());
        sendResponse(STATUS_ERROR, null, "Failed to create session");
    }
}

/**
 * Delete customer session (logout)
 * @param PDO $conn Database connection
 * @param string $token Session token
 */
function deleteCustomerSession($conn, $token) {
    try {
        $stmt = $conn->prepare("DELETE FROM customer_sessions WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("Customer session deletion error: " . $e->getMessage());
    }
}

/**
 * Clean expired customer sessions
 * @param PDO $conn Database connection
 */
function cleanExpiredCustomerSessions($conn) {
    try {
        $stmt = $conn->prepare("DELETE FROM customer_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Customer session cleanup error: " . $e->getMessage());
    }
}
?>