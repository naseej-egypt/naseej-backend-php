<?php
/**
 * Naseej Admin - Delete Product Image
 * POST: /products/delete_image.php
 * Headers: Authorization: Bearer <token>
 * Body: product_id, image_name (filename to delete)
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
    
    // Get input
    $productId = filterRequest("product_id");
    $imageName = filterRequest("image_name");
    
    if (empty($productId) || empty($imageName)) {
        sendResponse(STATUS_FAIL, null, "Product ID and image name are required");
    }
    
    // Get product
    $stmt = $conn->prepare("SELECT id, name, images FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendResponse(STATUS_FAIL, null, "Product not found");
    }
    
    // Parse existing images
    $existingImages = parseJsonArray($product['images']);
    
    // Check if image exists
    if (!in_array($imageName, $existingImages)) {
        sendResponse(STATUS_FAIL, null, "Image not found in product");
    }
    
    // Remove image from array
    $newImages = array_values(array_filter($existingImages, function($img) use ($imageName) {
        return $img !== $imageName;
    }));
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update product
        $updateStmt = $conn->prepare("
            UPDATE products 
            SET images = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($newImages), $productId]);
        
        // Commit transaction
        $conn->commit();
        
        // Delete image file
        deleteFile(PRODUCTS_DIR, $imageName);
        
        // Generate remaining image URLs
        $imageUrls = [];
        foreach ($newImages as $img) {
            $imageUrls[] = getImageUrl($img, 'products');
        }
        
        // Log activity
        logActivity(
            $conn,
            $admin['admin_id'],
            'update',
            'product',
            $productId,
            "Deleted image from product: {$product['name']}"
        );
        
        $responseData = [
            'product_id' => $productId,
            'deleted_image' => $imageName,
            'remaining_images' => $imageUrls
        ];
        
        sendResponse(STATUS_SUCCESS, $responseData, "Image deleted successfully");
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete product image error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to delete image");
}
?>