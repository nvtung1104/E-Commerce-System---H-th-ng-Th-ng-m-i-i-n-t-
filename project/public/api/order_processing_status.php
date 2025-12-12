<?php
/**
 * API: Xem trạng thái xử lý đơn hàng
 * Trả về chi tiết các bước xử lý:
 * - Đã lưu đơn chưa?
 * - Đã gửi email chưa?
 * - Đã cập nhật kho chưa?
 */

header('Content-Type: application/json');
require_once '../config/constants.php';
require_once '../config/db.php';

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // 1. Kiểm tra đơn hàng đã lưu chưa
    $stmt = $pdo->prepare("SELECT id, status, created_at FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Đơn hàng không tồn tại',
            'processing_status' => [
                'order_saved' => false,
                'email_sent' => false,
                'inventory_updated' => false
            ]
        ]);
        exit;
    }
    
    // 2. Kiểm tra đã gửi email chưa (từ bảng email_logs hoặc order_status_history)
    $emailSent = false;
    if (tableExists($pdo, 'email_logs')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE order_id = ? AND status = 'sent'");
        $stmt->execute([$orderId]);
        $emailSent = $stmt->fetchColumn() > 0;
    } else {
        // Fallback: check nếu có log "email" trong note
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_status_history 
                               WHERE order_id = ? AND note LIKE '%email%'");
        $stmt->execute([$orderId]);
        $emailSent = $stmt->fetchColumn() > 0;
    }
    
    // 3. Kiểm tra đã cập nhật kho chưa (check inventory_logs hoặc status history)
    $inventoryUpdated = false;
    if (tableExists($pdo, 'inventory_logs')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_logs 
                               WHERE reference_id = ? AND reason = 'order_created'");
        $stmt->execute([$orderId]);
        $inventoryUpdated = $stmt->fetchColumn() > 0;
    } else {
        // Fallback: nếu order status != pending thì đã update inventory
        $inventoryUpdated = ($order['status'] !== ORDER_STATUS_PENDING);
    }
    
    // 4. Lấy lịch sử xử lý
    $stmt = $pdo->prepare("SELECT status, note, created_at 
                           FROM order_status_history 
                           WHERE order_id = ? 
                           ORDER BY created_at ASC");
    $stmt->execute([$orderId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Tính thời gian xử lý
    $processingTime = null;
    if (!empty($history)) {
        $firstTime = strtotime($history[0]['created_at']);
        $lastTime = strtotime($history[count($history) - 1]['created_at']);
        $processingTime = $lastTime - $firstTime;
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'processing_status' => [
            'order_saved' => true,
            'order_saved_at' => $order['created_at'],
            'email_sent' => $emailSent,
            'email_sent_at' => $emailSent ? 'Đã gửi' : 'Chưa gửi',
            'inventory_updated' => $inventoryUpdated,
            'inventory_updated_at' => $inventoryUpdated ? 'Đã cập nhật' : 'Chưa cập nhật',
            'current_status' => $order['status'],
            'processing_time_seconds' => $processingTime
        ],
        'processing_steps' => $history,
        'summary' => [
            'total_steps' => count($history),
            'completed' => ($order['status'] === ORDER_STATUS_COMPLETED),
            'all_tasks_done' => ($emailSent && $inventoryUpdated)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}

function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}
