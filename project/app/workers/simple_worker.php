<?php
/**
 * Simple Worker - Processes all queues using HTTP API
 * No dependencies required
 * Run: php app/workers/simple_worker.php
 */

// Load environment
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once __DIR__ . '/../../queue/rabbitmq_simple.php';

class SimpleWorker {
    private $rabbit;
    private $running = true;
    private $processedCount = 0;
    
    public function __construct() {
        $this->rabbit = getSimpleRabbitMQ();
        
        echo "=================================\n";
        echo "Simple Worker Started\n";
        echo "=================================\n";
        echo "Processing all queues...\n\n";
        
        // Handle Ctrl+C gracefully
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
        }
    }
    
    public function shutdown() {
        echo "\n\nShutting down gracefully...\n";
        echo "Processed {$this->processedCount} messages\n";
        $this->running = false;
        exit(0);
    }
    
    public function start() {
        if (!$this->rabbit->isAvailable()) {
            die("✗ Cannot connect to RabbitMQ!\n");
        }
        
        echo "✓ Connected to RabbitMQ\n";
        echo "✓ Waiting for messages...\n\n";
        
        $queues = [
            'order_processing' => [$this, 'processOrder'],
            'inventory_update' => [$this, 'processInventory'],
            'email_notification' => [$this, 'processEmail'],
            'analytics_queue' => [$this, 'processAnalytics']
        ];
        
        while ($this->running) {
            foreach ($queues as $queueName => $callback) {
                $messages = $this->rabbit->getMessages($queueName, 1);
                
                if (!empty($messages)) {
                    foreach ($messages as $msg) {
                        try {
                            $payload = json_decode($msg['payload'], true);
                            
                            echo "[" . date('H:i:s') . "] [{$queueName}] ";
                            call_user_func($callback, $payload);
                            echo "✓\n";
                            
                            $this->processedCount++;
                            
                            if ($this->processedCount % 10 === 0) {
                                echo "\n[STATS] Processed {$this->processedCount} messages\n\n";
                            }
                            
                        } catch (Exception $e) {
                            echo "✗ Error: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
            
            // Small delay to avoid hammering the API
            usleep(100000); // 100ms
            
            // Allow signal handling
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }
    
    private function processOrder($payload) {
        $data = $payload['data'];
        $orderId = $data['order_id'];
        
        echo "Order #{$orderId} ";
        
        switch ($data['event_type']) {
            case 'order_created':
                echo "(processing) ";
                error_log("Order #{$orderId} set to processing status");
                break;
                
            case 'order_paid':
                echo "(paid) ";
                error_log("Order #{$orderId} marked as paid");
                break;
                
            case 'order_cancelled':
                echo "(cancelled) ";
                error_log("Order #{$orderId} cancelled");
                break;
        }
    }
    
    private function processInventory($payload) {
        $data = $payload['data'];
        $orderId = $data['order_id'];
        
        echo "Inventory for order #{$orderId} ";
        
        if ($data['event_type'] === 'order_created') {
            foreach ($data['items'] as $item) {
                error_log("Inventory tracked: Product #{$item['product_id']} qty -{$item['quantity']} for order #{$orderId}");
            }
            echo "(tracked) ";
            
        } elseif ($data['event_type'] === 'order_cancelled') {
            foreach ($data['items'] as $item) {
                error_log("Inventory restored: Product #{$item['product_id']} qty +{$item['quantity']} for order #{$orderId}");
            }
            echo "(restored) ";
        }
    }
    
    private function processEmail($payload) {
        $data = $payload['data'];
        $orderId = $data['order_id'];
        
        echo "Email for order #{$orderId} ";
        
        $email = $data['email'] ?? 'customer@example.com';
        $subject = "Đơn hàng #{$orderId}";
        $message = "Cảm ơn bạn đã đặt hàng!";
        
        // Log email (in production, actually send it)
        error_log("Email to {$email}: {$subject}");
        
        echo "(sent to {$email}) ";
    }
    
    private function processAnalytics($payload) {
        $data = $payload['data'];
        $orderId = $data['order_id'];
        
        echo "Analytics for order #{$orderId} ";
        
        if ($data['event_type'] === 'order_created') {
            $date = date('Y-m-d');
            $total = $data['total'] ?? 0;
            
            error_log("Analytics: Order #{$orderId} on {$date} - Revenue: {$total}");
            
            foreach ($data['items'] as $item) {
                error_log("Analytics: Product #{$item['product_id']} sold qty {$item['quantity']}");
            }
            
            echo "(tracked) ";
        }
    }
}

// Start worker
try {
    $worker = new SimpleWorker();
    $worker->start();
} catch (Exception $e) {
    echo "Worker error: " . $e->getMessage() . "\n";
    exit(1);
}
