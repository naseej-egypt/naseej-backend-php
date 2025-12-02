<?php
/**
 * Naseej Admin - Core Functions
 * Helper functions for the application
 */

/**
 * Filter and sanitize input data - FIXED VERSION
 * @param string $requestName POST parameter name
 * @param bool $skipHtmlSpecialChars Skip HTML encoding for JSON data
 * @return string Sanitized value
 */
function filterRequest($requestName, $skipHtmlSpecialChars = false) {
    if (isset($_POST[$requestName])) {
        $value = strip_tags(trim($_POST[$requestName]));
        
        // CRITICAL FIX: Don't HTML-encode JSON arrays (working_days)
        if ($skipHtmlSpecialChars || $requestName === 'working_days') {
            return $value;
        }
        
        return htmlspecialchars($value);
    }
    return "";
}

/**
 * Send JSON response
 */
function sendResponse($status, $data = null, $message = null) {
    header('Content-Type: application/json');
    $response = ["status" => $status];
    
    if ($data !== null) {
        $response["data"] = $data;
    }
    
    if ($message !== null) {
        $response["message"] = $message;
    }
    
    echo json_encode($response);
    exit();
}

/**
 * Upload image file
 */
function uploadImage($fileKey, $uploadDir, $maxSize = MAX_IMAGE_SIZE) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return "fail";
    }

    $file = $_FILES[$fileKey];
    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    if (!in_array($extension, ALLOWED_IMAGES)) {
        return "fail";
    }
    
    if ($file['size'] > $maxSize) {
        return "fail";
    }
    
    $filename = uniqid() . "_" . time() . "." . $extension;
    $targetPath = $uploadDir . $filename;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    return "fail";
}

/**
 * Delete file from server
 */
function deleteFile($directory, $filename) {
    if (empty($filename) || $filename === "fail") {
        return false;
    }
    
    $filepath = $directory . $filename;
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}

/**
 * Generate authentication token
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    return preg_match('/^\+?[\d\s-]{10,15}$/', $phone);
}

/**
 * Generate image URL
 */
function getImageUrl($filename, $directory) {
    if (empty($filename) || $filename === "fail") {
        return null;
    }
    return IMAGE_BASE_URL . $directory . "/" . $filename;
}

/**
 * CRITICAL: Validate and parse working days from POST request
 */
function validateAndParseWorkingDays($jsonString) {
    if (empty($jsonString)) {
        error_log("validateAndParseWorkingDays: Empty input");
        return false;
    }
    
    error_log("validateAndParseWorkingDays - Raw input: " . $jsonString);
    
    // If it's already an array
    if (is_array($jsonString)) {
        error_log("validateAndParseWorkingDays: Already an array");
        return !empty($jsonString) ? $jsonString : false;
    }
    
    // CRITICAL FIX: Decode HTML entities first (&quot; -> ")
    $cleanString = html_entity_decode($jsonString, ENT_QUOTES, 'UTF-8');
    error_log("validateAndParseWorkingDays - After html_entity_decode: " . $cleanString);
    
    // Try to decode as JSON
    $decoded = json_decode($cleanString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
        error_log("validateAndParseWorkingDays: Success! Decoded: " . print_r($decoded, true));
        return $decoded;
    }
    
    error_log("validateAndParseWorkingDays: JSON decode failed - " . json_last_error_msg());
    
    // Try with stripslashes and urldecode
    $cleanString = stripslashes($cleanString);
    $cleanString = urldecode($cleanString);
    
    error_log("validateAndParseWorkingDays - After cleaning: " . $cleanString);
    
    $decoded = json_decode($cleanString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
        error_log("validateAndParseWorkingDays: Success after cleaning! Decoded: " . print_r($decoded, true));
        return $decoded;
    }
    
    error_log("validateAndParseWorkingDays: All attempts failed");
    return false;
}

/**
 * Parse JSON array from database
 */
function parseJsonArray($jsonString) {
    if (empty($jsonString)) {
        return [];
    }
    
    if (is_array($jsonString)) {
        return $jsonString;
    }
    
    $cleanString = stripslashes(trim($jsonString, '"'));
    
    $decoded = json_decode($cleanString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }
    
    if (strpos($cleanString, ',') !== false) {
        $days = array_map('trim', explode(',', $cleanString));
        $cleanedDays = array_map(function($day) {
            return trim(stripslashes($day), '"\'');
        }, $days);
        return array_filter($cleanedDays);
    }
    
    if (preg_match('/\[.*\]/', $cleanString)) {
        preg_match_all('/"([^"]+)"/', $cleanString, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
    }
    
    $singleDay = trim(stripslashes($cleanString), '"\'');
    if (!empty($singleDay)) {
        return [$singleDay];
    }
    
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
}

/**
 * Format date for response
 */
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return null;
    }
    return date('Y-m-d H:i:s', strtotime($datetime));
}

/**
 * Get client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Log activity
 */
function logActivity($conn, $adminId, $action, $entityType, $entityId = null, $description = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (admin_id, action, entity_type, entity_id, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $adminId,
            $action,
            $entityType,
            $entityId,
            $description,
            getClientIp()
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Validate required fields
 */
function validateRequired($fields, $source = null) {
    $source = $source ?? $_POST;
    $errors = [];
    
    foreach ($fields as $field) {
        if (!isset($source[$field]) || trim($source[$field]) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }
    
    return $errors;
}

/**
 * Check if request method matches
 */
function isRequestMethod($method) {
    return $_SERVER['REQUEST_METHOD'] === strtoupper($method);
}

/**
 * Handle OPTIONS request for CORS
 */
function handleCorsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Enable CORS for all requests
handleCorsHeaders();
?>