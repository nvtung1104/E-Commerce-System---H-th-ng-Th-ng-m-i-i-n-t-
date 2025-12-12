<?php
/**
 * Simple Order Publisher - No dependencies
 */

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once __DIR__ . '/rabbitmq_simple.php';
require_once __DIR__ . '/../public/config/db.php';

class SimpleOrderPublisher {
    private $rabbit;
    
    public function __construct() {
        $this->rabbit = getSimpleRabbitMQ();
    }
    
    /**
     * Publish new order
     */
    public function publishNewOrder($orderId, $orderData) {
        try {
            if (!$this->rabbit->isAvailable()) {
                error_log("RabbitMQ not available, skipping queue");
                return false;
            }
            
            $message = [
                'data' => [
                    'order_id' => $orderId,
                    'user_id' => $orderData['user_id'],
                    'email' => $orderData['email'],
                    'total' => $orderData['total'],
                    'items' => $orderData['items'],
                    'payment_method' => $orderData['payment_method'] ?? 'cod',
                    'shipping_method_id' => $orderData['shipping_method_id'] ?? 1,
                    'address_id' => $orderData['address_id'] ?? null,
                    'event_type' => 'order_created'
                ],
                'timestamp' => time(),
                'routing_key' => 'order.created',
                'retry_count' => 0
            ];
            
            $result = $this->rabbit->publish(
                'orders_exchange',
                'order.created',
                $message,
                ['priority' => 8]
            );
            
            if ($result) {
                error_log("Order #{$orderId} published to queue successfully");
                return true;
            } else {
                error_log("Failed to publish order #{$orderId}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Failed to publish order #{$orderId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Publish order paid
     */
    public function publishOrderPaid($orderId) {
        try {
            if (!$this->rabbit->isAvailable()) {
                return false;
            }
            
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            $message = [
                'data' => [
                    'order_id' => $orderId,
                    'user_id' => $order['user_id'],
                    'total' => $order['total_price'],
                    'event_type' => 'order_paid'
                ],
                'timestamp' => time(),
                'routing_key' => 'order.paid',
                'retry_count' => 0
            ];
            
            return $this->rabbit->publish(
                'orders_exchange',
                'order.paid',
                $message,
                ['priority' => 7]
            );
            
        } catch (Exception $e) {
            error_log("Failed to publish order paid #{$orderId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Publish order cancelled
     */
    public function publishOrderCancelled($orderId, $reason = '') {
        try {
            if (!$this->rabbit->isAvailable()) {
                return false;
            }
            
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM orders o 
                                   INNER JOIN order_items oi ON o.id = oi.order_id 
                                   WHERE o.id = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $message = [
                'data' => [
                    'order_id' => $orderId,
                    'items' => array_map(function($item) {
                        return [
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity']
                        ];
                    }, $items),
                    'reason' => $reason,
                    'event_type' => 'order_cancelled'
                ],
                'timestamp' => time(),
                'routing_key' => 'order.cancelled',
                'retry_count' => 0
            ];
            
            return $this->rabbit->publish(
                'orders_exchange',
                'order.cancelled',
                $message,
                ['priority' => 9]
            );
            
        } catch (Exception $e) {
            error_log("Failed to publish order cancelled #{$orderId}: " . $e->getMessage());
            return false;
        }
    }
}
