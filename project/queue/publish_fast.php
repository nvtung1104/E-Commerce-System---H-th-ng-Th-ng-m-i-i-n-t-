<?php
/**
 * Fast Publisher - Optimized for high-throughput order processing
 * Non-blocking queue publishing for background tasks
 */

require_once __DIR__ . '/rabbitmq_simple.php';

class FastOrderPublisher {
    private $rabbit;
    private static $instance = null;
    
    public function __construct() {
        $this->rabbit = getSimpleRabbitMQ();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Publish email task (non-blocking)
     */
    public function publishEmailTask($orderId, $data) {
        try {
            $payload = [
                'task_id' => uniqid('email_', true),
                'order_id' => $orderId,
                'data' => $data,
                'created_at' => microtime(true),
                'priority' => 'high' // Email is high priority
            ];
            
            return $this->rabbit->publishMessage('email_fast', json_encode($payload));
        } catch (Exception $e) {
            error_log("Email queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Publish inventory logging task (low priority)
     */
    public function publishInventoryTask($orderId, $data) {
        try {
            $payload = [
                'task_id' => uniqid('inv_', true),
                'order_id' => $orderId,
                'data' => $data,
                'created_at' => microtime(true),
                'priority' => 'low' // Inventory logging is low priority
            ];
            
            return $this->rabbit->publishMessage('inventory_fast', json_encode($payload));
        } catch (Exception $e) {
            error_log("Inventory queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Publish analytics task (low priority)
     */
    public function publishAnalyticsTask($orderId, $data) {
        try {
            $payload = [
                'task_id' => uniqid('analytics_', true),
                'order_id' => $orderId,
                'data' => $data,
                'created_at' => microtime(true),
                'priority' => 'low' // Analytics is low priority
            ];
            
            return $this->rabbit->publishMessage('analytics_fast', json_encode($payload));
        } catch (Exception $e) {
            error_log("Analytics queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Publish multiple tasks at once (batch)
     */
    public function publishBatch($tasks) {
        $results = [];
        
        foreach ($tasks as $task) {
            switch ($task['type']) {
                case 'email':
                    $results[] = $this->publishEmailTask($task['order_id'], $task['data']);
                    break;
                case 'inventory':
                    $results[] = $this->publishInventoryTask($task['order_id'], $task['data']);
                    break;
                case 'analytics':
                    $results[] = $this->publishAnalyticsTask($task['order_id'], $task['data']);
                    break;
            }
        }
        
        return $results;
    }
    
    /**
     * Health check - verify queue connection
     */
    public function healthCheck() {
        try {
            return $this->rabbit->isAvailable();
        } catch (Exception $e) {
            return false;
        }
    }
}

// Global function for easy access
function getFastPublisher() {
    return FastOrderPublisher::getInstance();
}