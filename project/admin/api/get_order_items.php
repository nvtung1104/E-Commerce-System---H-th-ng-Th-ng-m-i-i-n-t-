<?php
/**
 * Get Order Items API
 * Fetches all items for a specific order with product details
 */

header('Content-Type: application/json');
require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get order items with product details
    $stmt = $pdo->prepare("
        SELECT 
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.quantity,
            oi.price,
            p.name,
            p.thumbnail,
            p.sku
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching order items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
