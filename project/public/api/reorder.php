<?php
session_start();
require_once '../config/constants.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin đơn hàng']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Check if order belongs to user
    $stmt = $pdo->prepare("SELECT id, user_id FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại']);
        exit;
    }
    
    if ($order['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đặt lại đơn hàng này']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT oi.product_id, oi.quantity, p.stock_quantity, p.name
                           FROM order_items oi
                           LEFT JOIN products p ON oi.product_id = p.id
                           WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không có sản phẩm']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    $addedCount = 0;
    $outOfStockProducts = [];
    
    foreach ($items as $item) {
        // Check if product still exists and has stock
        if (!$item['name']) {
            continue; // Product deleted
        }
        
        if ($item['stock_quantity'] < $item['quantity']) {
            $outOfStockProducts[] = $item['name'];
            continue;
        }
        
        // Check if already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $item['product_id']]);
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cartItem) {
            // Update quantity
            $newQuantity = min($cartItem['quantity'] + $item['quantity'], $item['stock_quantity']);
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newQuantity, $cartItem['id']]);
        } else {
            // Add to cart
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at) 
                                   VALUES (?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $item['product_id'], $item['quantity']]);
        }
        
        $addedCount++;
    }
    
    $pdo->commit();
    
    if ($addedCount === 0) {
        if (!empty($outOfStockProducts)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Các sản phẩm đã hết hàng: ' . implode(', ', $outOfStockProducts)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể thêm sản phẩm vào giỏ hàng']);
        }
        exit;
    }
    
    $message = "Đã thêm {$addedCount} sản phẩm vào giỏ hàng";
    if (!empty($outOfStockProducts)) {
        $message .= ". Một số sản phẩm đã hết hàng: " . implode(', ', $outOfStockProducts);
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
