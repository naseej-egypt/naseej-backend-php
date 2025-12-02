<?php
/**
 * Naseej Admin - Upload Product Images
 * POST: /products/upload_image.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id
 * Files: file[] (multiple images, max 5 total)
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
    
    // Get product ID
    $productId = filterRequest("product_id");
    
    if (empty($productId)) {
        sendResponse(STATUS_FAIL, null, "Product ID is required");
    }
    
    // Check if product exists
    $stmt = $conn->prepare("SELECT id, name, images FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['file'])) {
        sendResponse(STATUS_FAIL, null, "No files uploaded");
    }
    
    // Get existing images
    $existingImages = parseJsonArray($product['images']);
    
    // Upload new images
    $newImages = [];
    $fileCount = is_array($_FILES['file']['name']) ? count($_FILES['file']['name']) : 1;
    
    // Check total image count
    if (count($existingImages) + $fileCount > 5) {
        sendResponse(STATUS_FAIL, null, "Maximum 5 images allowed per product. Current: " . count($existingImages));
    }
    
    if (is_array($_FILES['file']['name'])) {
        // Multiple files
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                // Create temporary single file array
                $_FILES['temp_file'] = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];
                
                $imageName = uploadImage('temp_file', PRODUCTS_DIR);
                if ($imageName !== 'fail') {
                    $newImages[] = $imageName;
                }
            }
        }
    } else {
        // Single file
        $imageName = uploadImage('file', PRODUCTS_DIR);
        if ($imageName !== 'fail') {
            $newImages[] = $imageName;
        }
    }
    
    if (empty($newImages)) {
        sendResponse(STATUS_FAIL, null, "Failed to upload images. Check file size and format.");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Merge with existing images
        $allImages = array_merge($existingImages, $newImages);
        $imagesJson = json_encode($allImages);
        
        // Update product
        $updateStmt = $conn->prepare("
            UPDATE products 
            SET images = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$imagesJson, $productId]);
        
        // Commit transaction
        $conn->commit();
        
        // Generate image URLs
        $imageUrls = [];
        foreach ($allImages as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'product',
            $productId,
            "Added " . count($newImages) . " image(s) to product: {$product['name']}"
        );
        
        $responseData = [
            'product_id' => $productId,
            'uploaded_count' => count($newImages),
            'total_images' => count($allImages),
            'images' => $imageUrls
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, count($newImages) . " image(s) uploaded successfully");
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        // Delete uploaded images
        foreach ($newImages as $imageName) {
            deleteFile(PRODUCTS_DIR, $imageName);
        }
        
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Upload product images error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to upload images");
}
?>