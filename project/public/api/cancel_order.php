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
    
    // Check if order belongs to user and can be cancelled
    $stmt = $pdo->prepare("SELECT id, status, user_id FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại']);
        exit;
    }
    
    if ($order['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền hủy đơn hàng này']);
        exit;
    }
    
    // Cannot cancel if shipping or completed
    if (in_array($order['status'], [ORDER_STATUS_SHIPPING, ORDER_STATUS_COMPLETED])) {
        echo json_encode(['success' => false, 'message' => 'Không thể hủy đơn hàng đang giao hoặc đã hoàn thành']);
        exit;
    }
    
    if ($order['status'] === ORDER_STATUS_CANCELLED) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng đã được hủy trước đó']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Get order items to restore stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Restore stock
    foreach ($items as $item) {
        $stmt = $pdo->prepare("UPDATE products 
                               SET stock_quantity = stock_quantity + ?,
                                   sales_count = GREATEST(0, sales_count - ?),
                                   updated_at = NOW() 
                               WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
    }
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([ORDER_STATUS_CANCELLED, $orderId]);
    
    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE order_id = ?");
    $stmt->execute([PAYMENT_STATUS_FAILED, $orderId]);
    
    // Log status
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_at) 
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$orderId, ORDER_STATUS_CANCELLED, 'Khách hàng đã hủy đơn hàng']);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Đã hủy đơn hàng thành công']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
