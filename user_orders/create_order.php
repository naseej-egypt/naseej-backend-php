<?php
/**
 * Naseej User App - Create Order
 * POST: /user_orders/create_order.php
 * Body: delivery_method (delivery/pickup), payment_type (cash/online), address_id, store_id, payment_method_id, notes
 * Headers: Authorization: Bearer <token>
 */

include "../config/database.php";
include "../config/constants.php";
include "../core/functions.php";
include "../core/middleware.php";

handleCorsHeaders();

if (!isRequestMethod('POST')) {
    sendResponse(STATUS_FAIL, null, "Invalid request method");
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify authentication
    $customer = verifyCustomerAuth($conn);
    
    // Get input
    $deliveryMethod = filterRequest("delivery_method") ?: 'delivery';
    $paymentType = filterRequest("payment_type") ?: 'cash';
    $addressId = filterRequest("address_id");
    $storeId = filterRequest("store_id");
    $paymentMethodId = filterRequest("payment_method_id");
    $notes = filterRequest("notes");
    
    // Validate delivery method
    if (!in_array($deliveryMethod, ['delivery', 'pickup'])) {
        sendResponse(STATUS_FAIL, null, "Invalid delivery method");
    }
    
    // Validate payment type
    if (!in_array($paymentType, ['cash', 'online'])) {
        sendResponse(STATUS_FAIL, null, "Invalid payment type");
    }
    
    // Validate address for delivery
    if ($deliveryMethod === 'delivery' && empty($addressId)) {
        sendResponse(STATUS_FAIL, null, "Address is required for delivery");
    }
    
    // Validate store for pickup
    if ($deliveryMethod === 'pickup' && empty($storeId)) {
        sendResponse(STATUS_FAIL, null, "Store is required for pickup");
    }
    
    // Validate payment method for online payment
    if ($paymentType === 'online' && empty($paymentMethodId)) {
        sendResponse(STATUS_FAIL, null, "Payment method is required for online payment");
    }
    
    // FIX: Set payment_method_id to NULL for cash payments
    if ($paymentType === 'cash') {
        $paymentMethodId = null;
    }
    
    // Get cart items
    $cartStmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.discount_price, p.stock, p.images, p.is_active
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.customer_id = ?
    ");
    $cartStmt->execute([$customer['customer_id']]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        sendResponse(STATUS_FAIL, null, "Cart is empty");
    }
    
    // Validate cart items
    foreach ($cartItems as $item) {
        if (!$item['is_active']) {
            sendResponse(STATUS_FAIL, null, "Product '{$item['name']}' is no longer available");
        }
        if ($item['stock'] < $item['quantity']) {
            sendResponse(STATUS_FAIL, null, "Insufficient stock for '{$item['name']}'");
        }
    }
    
    // Get delivery address
    $deliveryAddress = null;
    if ($deliveryMethod === 'delivery') {
        $addressStmt = $conn->prepare("
            SELECT * FROM customer_addresses 
            WHERE id = ? AND customer_id = ?
        ");
        $addressStmt->execute([$addressId, $customer['customer_id']]);
        $address = $addressStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$address) {
            sendResponse(STATUS_FAIL, null, "Address not found");
        }
        
        $deliveryAddress = json_encode([
            'street' => $address['street'],
            'city' => $address['city'],
            'state' => $address['state'],
            'zip_code' => $address['zip_code'],
            'country' => $address['country'],
            'latitude' => $address['latitude'],
            'longitude' => $address['longitude']
        ]);
    } else {
        // For pickup, use store address
        $storeStmt = $conn->prepare("SELECT address FROM stores WHERE id = ?");
        $storeStmt->execute([$storeId]);
        $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            sendResponse(STATUS_FAIL, null, "Store not found");
        }
        
        $deliveryAddress = json_encode(['pickup_store' => $store['address']]);
    }
    
    // Calculate order totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $subtotal += $price * $item['quantity'];
    }
    
    // CHANGED: Free shipping threshold from 500 to 300
    $shipping = ($deliveryMethod === 'delivery' && $subtotal < 300) ? 49.99 : 0;
    $tax = $subtotal * 0.08;
    $total = $subtotal + $shipping + $tax;
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Generate order number
    $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Determine payment method string
    $paymentMethod = $paymentType === 'cash' ? 'cash_on_delivery' : 'online_payment';
    
    // Insert order
    $orderStmt = $conn->prepare("
        INSERT INTO orders (
            order_number, customer_id, customer_name, customer_email, customer_phone,
            delivery_man_id, store_id, subtotal, tax, delivery_fee, total,
            status, payment_status, payment_method, payment_method_id,
            delivery_address, notes, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $orderStmt->execute([
        $orderNumber,
        $customer['customer_id'],
        $customer['name'],
        $customer['email'],
        $customer['phone'],
        $deliveryMethod === 'pickup' ? $storeId : null,
        $subtotal,
        $tax,
        $shipping,
        $total,
        $paymentType === 'cash' ? 'pending' : 'paid',
        $paymentMethod,
        $paymentMethodId, // This will be NULL for cash payments now
        $deliveryAddress,
        $notes
    ]);
    
    $orderId = $conn->lastInsertId();
    
    // Insert order items
    $orderItemStmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, product_id, product_name, product_image,
            quantity, price, total, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($cartItems as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $itemTotal = $price * $item['quantity'];
        
        // Get first image
        $images = parseJsonArray($item['images']);
        $imageUrl = !empty($images) ? $images[0] : null;
        
        $orderItemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['name'],
            $imageUrl,
            $item['quantity'],
            $price,
            $itemTotal
        ]);
        
        // Update product stock
        $updateStockStmt = $conn->prepare("
            UPDATE products 
            SET stock = stock - ? 
            WHERE id = ?
        ");
        $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Clear cart
    $clearCartStmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
    $clearCartStmt->execute([$customer['customer_id']]);
    
    // Commit transaction
    $conn->commit();
    
    // Get created order details
    $getOrderStmt = $conn->prepare("
        SELECT * FROM v_order_details WHERE id = ?
    ");
    $getOrderStmt->execute([$orderId]);
    $order = $getOrderStmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse items
    $items = json_decode($order['items'], true) ?: [];
    
    $responseData = [
        'order_id' => (string)$order['id'],
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'subtotal' => (float)$order['subtotal'],
        'tax' => (float)$order['tax'],
        'delivery_fee' => (float)$order['delivery_fee'],
        'total' => (float)$order['total'],
        'items' => $items,
        'item_count' => (int)$order['item_count'],
        'created_at' => formatDateTime($order['created_at'])
    ];
    
    sendResponse(STATUS_SUCCESS, $responseData, "Order placed successfully");
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Create order error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(STATUS_ERROR, null, "Failed to create order: " . $e->getMessage());
} finally {
    $db->disconnect();
}
?>