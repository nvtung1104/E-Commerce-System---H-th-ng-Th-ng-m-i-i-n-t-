<?php
/**
 * ULTRA SIMPLE GET ORDERS - Absolutely no complex SQL
 */
session_start();
require_once '../../public/config/db.php';

// Skip auth check for testing
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Most basic query possible
    $stmt = $pdo->query("SELECT * FROM orders");
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Take first 20 orders
    $orders = array_slice($allOrders, 0, 20);
    
    // Add basic customer name
    foreach ($orders as &$order) {
        $order['customer_name'] = 'Khách hàng #' . $order['user_id'];
        $order['email'] = '';
        $order['item_count'] = 1;
    }
    
    // Basic stats
    $stats = [
        'pending' => 0,
        'processing' => 0,
        'shipping' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($allOrders as $order) {
        if (isset($stats[$order['status']])) {
            $stats[$order['status']]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats,
        'count' => count($orders),
        'total' => count($allOrders)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>