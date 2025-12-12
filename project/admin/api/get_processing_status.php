<?php
/**
 * Get Order Processing Status - Fast API
 * Returns processing status for order detail cards
 */
session_start();
require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

header('Content-Type: application/json');

// AJAX-friendly authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - session expired']);
    exit;
}

$orderId = $_GET['order_id'] ?? null;

if (!$orderId || !is_numeric($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    $startTime = microtime(true);
    $pdo = getDBConnection();
    
    // Get order details with optimized query
    $stmt = $pdo->prepare("SELECT status, created_at, email_sent, inventory_updated, analytics_processed FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Check email sent status
    $emailSent = $order['email_sent'] ?? false;
    if (!$emailSent) {
        // Check in email logs
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE order_id = ? AND status = 'sent'");
            $stmt->execute([$orderId]);
            $emailSent = $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            // Check in history
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_status_history WHERE order_id = ? AND note LIKE '%email%'");
            $stmt->execute([$orderId]);
            $emailSent = $stmt->fetchColumn() > 0;
        }
    }
    
    // Check inventory updated
    $inventoryUpdated = $order['inventory_updated'] ?? ($order['status'] !== 'pending');
    
    // Check analytics processed
    $analyticsProcessed = $order['analytics_processed'] ?? false;
    
    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'processing_time' => $processingTime . 'ms',
        'status' => [
            'order_saved' => [
                'completed' => true,
                'timestamp' => $order['created_at'],
                'label' => 'Đã lưu đơn',
                'value' => '✓ ' . date('d/m/Y H:i:s', strtotime($order['created_at']))
            ],
            'email_sent' => [
                'completed' => $emailSent,
                'label' => 'Gửi email xác nhận',
                'value' => $emailSent ? '✓ Đã gửi' : '⏳ Chưa gửi'
            ],
            'inventory_updated' => [
                'completed' => $inventoryUpdated,
                'label' => 'Cập nhật tồn kho',
                'value' => $inventoryUpdated ? '✓ Đã cập nhật' : '⏳ Chưa cập nhật'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>