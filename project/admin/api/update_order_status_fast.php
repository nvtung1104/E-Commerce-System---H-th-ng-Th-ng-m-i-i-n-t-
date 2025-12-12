<?php
/**
 * FAST ORDER STATUS UPDATE API - Clean JSON Response
 * Target: <200ms response time
 */

// Prevent any output before JSON
ob_start();

session_start();
require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

// AJAX-friendly authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - session expired']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$newStatus = $_POST['status'] ?? null;

if (!$orderId || !$newStatus || !is_numeric($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin hoặc dữ liệu không hợp lệ']);
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
    exit;
}

try {
    $startTime = microtime(true);
    $pdo = getDBConnection();
    
    // ============================================
    // ULTRA-FAST CORE UPDATE (<50ms target)
    // ============================================
    
    // Get current order with optimized query (single query with index)
    $stmt = $pdo->prepare("SELECT id, status, user_id FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'Đơn hàng không tồn tại',
            'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ]);
        exit;
    }
    
    // Skip if status is already the same (optimization)
    if ($order['status'] === $newStatus) {
        echo json_encode([
            'success' => true, 
            'message' => 'Trạng thái đã được cập nhật trước đó',
            'new_status' => $newStatus,
            'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'skipped' => true
        ]);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Update order status (single query)
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
    
    // Log status (single query)
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_at) 
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$orderId, $newStatus, $notes[$newStatus]]);
    
    // ============================================
    // IMMEDIATE CRITICAL UPDATES (<50ms target)
    // ============================================
    
    // If completed, update payment status immediately
    if ($newStatus === ORDER_STATUS_COMPLETED) {
        $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE order_id = ?");
        $stmt->execute([PAYMENT_STATUS_PAID, $orderId]);
    }
    
    // If cancelled, restore stock immediately (critical for inventory)
    if ($newStatus === ORDER_STATUS_CANCELLED && $order['status'] !== ORDER_STATUS_CANCELLED) {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Batch update stock
        $updateStockStmt = $pdo->prepare("UPDATE products 
                                          SET stock_quantity = stock_quantity + ?,
                                              sales_count = GREATEST(0, sales_count - ?),
                                              updated_at = NOW() 
                                          WHERE id = ?");
        
        foreach ($items as $item) {
            $updateStockStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
    }
    
    $pdo->commit();
    
    // ============================================
    // BACKGROUND PROCESSING (Non-blocking)
    // ============================================
    
    // Queue heavy tasks for background processing
    if ($newStatus === ORDER_STATUS_COMPLETED) {
        try {
            // Queue completion email (non-blocking)
            if (file_exists('../../queue/publish_fast.php')) {
                require_once '../../queue/publish_fast.php';
                $publisher = getFastPublisher();
                
                $publisher->publishEmailTask($orderId, [
                    'type' => 'order_completed',
                    'order_id' => $orderId,
                    'user_id' => $order['user_id']
                ]);
            }
        } catch (Exception $e) {
            // Log error but don't fail the status update
            error_log("Background email queue error: " . $e->getMessage());
        }
    }
    
    // ============================================
    // IMMEDIATE RESPONSE (<200ms total)
    // ============================================
    
    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Đã cập nhật trạng thái thành công',
        'new_status' => $newStatus,
        'processing_time' => $processingTime . 'ms',
        'performance' => [
            'target' => '<200ms',
            'actual' => $processingTime . 'ms',
            'status' => $processingTime < 200 ? 'excellent' : ($processingTime < 500 ? 'good' : 'needs_optimization')
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clear any output that might have been generated
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
        'processing_time' => isset($startTime) ? round((microtime(true) - $startTime) * 1000, 2) . 'ms' : 'N/A'
    ]);
}

// Ensure output is flushed
ob_end_flush();
?>