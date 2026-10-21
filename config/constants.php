<?php
/**
 * Naseej Admin - Constants Configuration
 * Application-wide constants and settings
 */

// API Response Status
define("STATUS_SUCCESS", "success");
define("STATUS_FAIL", "fail");
define("STATUS_ERROR", "error");

// Email Settings
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', );
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');  //actual password
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'Naseej App');

// File Upload Settings
define("MB", 1048576);
define("MAX_IMAGE_SIZE", 5 * MB); // 5MB
define("MAX_PROFILE_SIZE", 2 * MB); // 2MB

// Allowed file extensions
define("ALLOWED_IMAGES", ["jpg", "jpeg", "png", "gif", "webp"]);

// Upload directories (relative to root)
define("UPLOAD_DIR", "../upload/");
define("PRODUCTS_DIR", UPLOAD_DIR . "products/");
define("CATEGORIES_DIR", UPLOAD_DIR . "categories/");
define("PROFILES_DIR", UPLOAD_DIR . "profiles/");
define("STORES_DIR", UPLOAD_DIR . "stores/");

// Base URL for images (UPDATE THIS FOR YOUR SERVER)
define("BASE_URL", "");
define("IMAGE_BASE_URL", BASE_URL . "upload/");

// Session/Token settings
// Session/Token settings - SEPARATE DURATIONS
define("ADMIN_TOKEN_EXPIRY_HOURS", 24); // 24 hours for admin (security)
define("USER_TOKEN_EXPIRY_HOURS", 168); // 7 days for users (convenience)

define("SESSION_NAME", "naseej_admin_session");

// Pagination
define("ITEMS_PER_PAGE", 20);

// Order Status Constants
define("ORDER_PENDING", "pending");
define("ORDER_CONFIRMED", "confirmed");
define("ORDER_PROCESSING", "processing");
define("ORDER_SHIPPED", "shipped");
define("ORDER_DELIVERED", "delivered");
define("ORDER_CANCELLED", "cancelled");

// Payment Status Constants
define("PAYMENT_PENDING", "pending");
define("PAYMENT_PAID", "paid");
define("PAYMENT_FAILED", "failed");
define("PAYMENT_REFUNDED", "refunded");

// Delivery Status Constants
define("DELIVERY_AVAILABLE", "available");
define("DELIVERY_BUSY", "busy");
define("DELIVERY_OFFLINE", "offline");

// Error Messages
define("ERR_UNAUTHORIZED", "Unauthorized access. Please login.");
define("ERR_INVALID_DATA", "Invalid data provided.");
define("ERR_NOT_FOUND", "Resource not found.");
define("ERR_DATABASE", "Database error occurred.");
define("ERR_UPLOAD_FAILED", "File upload failed.");
define("ERR_PERMISSION_DENIED", "Permission denied.");

// Success Messages
define("MSG_SUCCESS", "Operation completed successfully.");
define("MSG_CREATED", "Resource created successfully.");
define("MSG_UPDATED", "Resource updated successfully.");
define("MSG_DELETED", "Resource deleted successfully.");

// Initialize upload directories
function initializeUploadDirectories() {
    $directories = [
        UPLOAD_DIR,
        PRODUCTS_DIR,
        CATEGORIES_DIR,
        PROFILES_DIR,
        STORES_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Call initialization
initializeUploadDirectories();
?>
