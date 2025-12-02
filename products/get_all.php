<?php
/**
 * Naseej Admin - Get All Products
 * GET/POST: /products/get_all.php
 * Headers: Authorization: Bearer <token>
 * QueryParams (optional): 
 *   - category_id: Filter by category
 *   - is_active: Filter by status (1/0)
 *   - page: Page number for pagination
 *   - limit: Items per page (default 20)
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
    // Verify authentication
    $admin = verifyAuth($conn);
    
    // Get filter parameters
    $categoryId = isset($_GET['category_id']) ? filterRequest('category_id') : (isset($_POST['category_id']) ? filterRequest('category_id') : null);
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : (isset($_POST['is_active']) ? $_POST['is_active'] : null);
    $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : ITEMS_PER_PAGE);
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if ($categoryId !== null && $categoryId !== '') {
        $whereClauses[] = "p.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($isActive !== null && $isActive !== '') {
        $whereClauses[] = "p.is_active = ?";
        $params[] = (int)$isActive;
    }
    
    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM products p
        $whereSQL
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get products
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.discount_price,
            p.stock,
            p.category_id,
            c.name as category_name,
            p.images,
            p.colors,
            p.sizes,
            p.is_active,
            p.specifications,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $whereSQL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each product
    $processedProducts = [];
    foreach ($products as $product) {
        // Parse images JSON
        $images = parseJsonArray($product['images']);
        $imageUrls = [];
        foreach ($images as $imageName) {
            $imageUrls[] = getImageUrl($imageName, 'products');
        }
        
        // Parse colors
        $colors = parseJsonArray($product['colors']);
        
        // Parse sizes
        $sizes = parseJsonArray($product['sizes']);
        
        // Parse specifications
        $specifications = parseJsonArray($product['specifications']);
        
        $processedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'discount_price' => $product['discount_price'] ? (float)$product['discount_price'] : null,
            'stock' => (int)$product['stock'],
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'images' => $imageUrls,
            'colors' => $colors,
            'sizes' => $sizes,
            'is_active' => (bool)$product['is_active'],
            'specifications' => $specifications,
            'created_at' => formatDateTime($product['created_at']),
            'updated_at' => formatDateTime($product['updated_at'])
        ];
    }
    
    // Prepare pagination data
    $totalPages = ceil($totalCount / $limit);
    
    $responseData = [
        'products' => $processedProducts,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => (int)$totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData);
    
} catch (PDOException $e) {
    error_log("Get all products error: " . $e->getMessage());
    sendResponse(STATUS_ERROR, null, "Failed to retrieve products");
}
?>