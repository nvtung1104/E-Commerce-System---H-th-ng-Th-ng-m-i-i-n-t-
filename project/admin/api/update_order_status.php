<?php
session_start();
require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

header('Content-Type: application/json');

// AJAX-friendly authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$newStatus = $_POST['status'] ?? null;

if (!$orderId || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

// Validate status
$validStatuses = [
    ORDER_STATUS_PENDING,
    ORDER_STATUS_PROCESSING,
    ORDER_STATUS_SHIPPING,
    ORDER_STATUS_COMPLETED,
    ORDER_STATUS_CANCELLED
];

if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current order
    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    
    // Determine note based on status
    $notes = [
        ORDER_STATUS_PENDING => 'Admin đã chuyển về chờ xác nhận',
        ORDER_STATUS_PROCESSING => 'Admin đã xác nhận và đang xử lý đơn hàng',
        ORDER_STATUS_SHIPPING => 'Admin đã giao đơn hàng cho đơn vị vận chuyển',
        ORDER_STATUS_COMPLETED => 'Admin đã xác nhận giao hàng thành công',
        ORDER_STATUS_CANCELLED => 'Admin đã hủy đơn hàng'
    ];
    
    // Log status
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_at) 
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$orderId, $newStatus, $notes[$newStatus]]);
    
    // If completed, update payment status and send completion email
    if ($newStatus === ORDER_STATUS_COMPLETED) {
        $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE order_id = ?");
        $stmt->execute([PAYMENT_STATUS_PAID, $orderId]);
        
        // Send completion email
        try {
            // Get order details for email
            $stmt = $pdo->prepare("
                SELECT o.*, u.email, u.name as customer_name, o.created_at as order_date
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($orderDetails) {
                // Get order items
                $stmt = $pdo->prepare("
                    SELECT oi.*, p.name 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Prepare order data for email
                $orderData = [
                    'order_id' => $orderDetails['id'],
                    'customer_name' => $orderDetails['customer_name'],
                    'order_date' => date('d/m/Y H:i', strtotime($orderDetails['order_date'])),
                    'total_price' => $orderDetails['total_price'],
                    'items' => []
                ];
                
                foreach ($orderItems as $item) {
                    $orderData['items'][] = [
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ];
                }
                
                // Send completion email
                require_once '../../public/config/simple_mailer.php';
                $mailer = getSimpleMailer();
                $emailSent = $mailer->sendOrderCompleted($orderDetails['email'], $orderData);
                
                if ($emailSent) {
                    error_log("Order completion email sent to: " . $orderDetails['email']);
                } else {
                    error_log("Failed to send completion email to: " . $orderDetails['email']);
                }
            }
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            // Continue with order processing even if email fails
        }
    }
    
    // If cancelled, restore stock
    if ($newStatus === ORDER_STATUS_CANCELLED && $order['status'] !== ORDER_STATUS_CANCELLED) {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE products 
                                   SET stock_quantity = stock_quantity + ?,
                                       sales_count = GREATEST(0, sales_count - ?),
                                       updated_at = NOW() 
                                   WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Đã cập nhật trạng thái thành công',
        'new_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
