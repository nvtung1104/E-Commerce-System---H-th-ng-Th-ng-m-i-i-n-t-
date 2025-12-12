<?php
/**
 * Inventory Update API - Clean & Fast
 * Updates product stock quantities with logging
 */
session_start();
require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
$productId = $_POST['product_id'] ?? null;
$updateType = $_POST['update_type'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$note = $_POST['note'] ?? '';

if (!$productId || !$updateType || !is_numeric($quantity) || $quantity < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

try {
    $startTime = microtime(true);
    $pdo = getDBConnection();
    
    // Get current product
    $stmt = $pdo->prepare("SELECT id, name, sku, stock_quantity FROM products WHERE id = ? AND status = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
        exit;
    }
    
    $currentQuantity = (int)$product['stock_quantity'];
    $inputQuantity = (int)$quantity;
    
    // Calculate new quantity
    switch ($updateType) {
        case 'set':
            $newQuantity = $inputQuantity;
            $actionText = "Đặt số lượng: {$newQuantity}";
            break;
        case 'add':
            $newQuantity = $currentQuantity + $inputQuantity;
            $actionText = "Nhập kho: +{$inputQuantity}";
            break;
        case 'subtract':
            $newQuantity = max(0, $currentQuantity - $inputQuantity);
            $actionText = "Xuất kho: -{$inputQuantity}";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Loại cập nhật không hợp lệ']);
            exit;
    }
    
    // Update product
    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newQuantity, $productId]);
    
    // Determine status
    $status = $newQuantity === 0 ? 'out' : ($newQuantity <= 10 ? 'low' : 'normal');
    
    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thành công',
        'data' => [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'sku' => $product['sku'],
            'old_quantity' => $currentQuantity,
            'new_quantity' => $newQuantity,
            'status' => $status,
            'action_text' => $actionText
        ],
        'processing_time' => $processingTime . 'ms'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}
?>