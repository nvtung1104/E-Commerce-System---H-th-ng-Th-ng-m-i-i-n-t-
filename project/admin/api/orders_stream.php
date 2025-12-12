<?php
/**
 * Server-Sent Events (SSE) Stream for Real-time Orders
 * Properly configured for XAMPP/Apache
 */

// Disable output buffering completely
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
@ob_end_clean();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent timeout
set_time_limit(0);
@ini_set('max_execution_time', '0');

// Start output
ob_implicit_flush(true);

require_once '../../public/config/constants.php';
require_once '../../public/config/db.php';

// Function to send SSE message
function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

try {
    $pdo = getDBConnection();
    $lastOrderId = 0;
    $lastCheck = time();
    
    // Get active orders only (pending, processing, shipping)
    $stmt = $pdo->query("SELECT o.*, 
                                u.name as fullname, 
                                u.email,
                                COALESCE(p.status, 'pending') as payment_status
                         FROM orders o
                         LEFT JOIN users u ON o.user_id = u.id
                         LEFT JOIN payments p ON o.id = p.order_id
                         WHERE o.status IN ('pending', 'processing', 'shipping')
                         ORDER BY o.created_at DESC
                         LIMIT 100");
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get last order ID
    if (!empty($allOrders)) {
        $lastOrderId = $allOrders[0]['id'];
    }
    
    // Send initial data
    sendSSE([
        'type' => 'initial',
        'orders' => $allOrders,
        'count' => count($allOrders)
    ]);
    
    // Keep connection alive and check for new orders
    while (true) {
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
        
        // Check for new active orders
        $stmt = $pdo->prepare("SELECT o.*, 
                                      u.name as fullname, 
                                      u.email,
                                      COALESCE(p.status, 'pending') as payment_status
                               FROM orders o
                               LEFT JOIN users u ON o.user_id = u.id
                               LEFT JOIN payments p ON o.id = p.order_id
                               WHERE o.id > ? AND o.status IN ('pending', 'processing', 'shipping')
                               ORDER BY o.created_at DESC");
        $stmt->execute([$lastOrderId]);
        $newOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newOrders)) {
            foreach ($newOrders as $order) {
                sendSSE([
                    'type' => 'new_order',
                    'order' => $order
                ]);
                $lastOrderId = max($lastOrderId, $order['id']);
            }
        }
        
        // Send heartbeat every 15 seconds
        if (time() - $lastCheck >= 15) {
            sendSSE([
                'type' => 'heartbeat',
                'time' => time()
            ]);
            $lastCheck = time();
        }
        
        // Sleep for 1 second before next check
        sleep(1);
    }
    
} catch (Exception $e) {
    sendSSE([
        'type' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("SSE Error: " . $e->getMessage());
}
