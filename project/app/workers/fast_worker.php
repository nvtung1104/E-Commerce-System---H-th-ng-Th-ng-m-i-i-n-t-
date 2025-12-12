<?php
/**
 * Fast Background Worker - Optimized for high throughput
 * Handles: Email sending, Inventory logging, Analytics
 * Target: Process 100+ tasks/second
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
require_once __DIR__ . '/../../public/config/db.php';
require_once __DIR__ . '/../../public/config/constants.php';

class FastWorker {
    private $rabbit;
    private $pdo;
    private $running = true;
    private $processedCount = 0;
    private $startTime;
    private $emailBatch = [];
    private $inventoryBatch = [];
    private $analyticsBatch = [];
    
    const BATCH_SIZE = 50; // Process in batches for better performance
    const BATCH_TIMEOUT = 5; // Process batch every 5 seconds
    
    public function __construct() {
        $this->rabbit = getSimpleRabbitMQ();
        $this->pdo = getDBConnection();
        $this->startTime = time();
        
        // Optimize PDO for high performance
        $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        
        echo "=================================\n";
        echo "Fast Worker Started (High Performance Mode)\n";
        echo "=================================\n";
        echo "Target: 100+ tasks/second\n";
        echo "Batch size: " . self::BATCH_SIZE . "\n\n";
        
        // Handle Ctrl+C gracefully
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
        }
    }
    
    public function shutdown() {
        echo "\n\nShutting down gracefully...\n";
        
        // Process remaining batches
        $this->processBatches(true);
        
        $runtime = time() - $this->startTime;
        $rate = $runtime > 0 ? round($this->processedCount / $runtime, 2) : 0;
        
        echo "Processed {$this->processedCount} tasks in {$runtime}s\n";
        echo "Average rate: {$rate} tasks/second\n";
        
        $this->running = false;
        exit(0);
    }
    
    public function start() {
        if (!$this->rabbit->isAvailable()) {
            die("✗ Cannot connect to RabbitMQ!\n");
        }
        
        echo "✓ Connected to RabbitMQ\n";
        echo "✓ High-performance mode enabled\n";
        echo "✓ Waiting for tasks...\n\n";
        
        $queues = [
            'email_fast' => [$this, 'queueEmail'],
            'inventory_fast' => [$this, 'queueInventory'],
            'analytics_fast' => [$this, 'queueAnalytics']
        ];
        
        $lastBatchTime = time();
        
        while ($this->running) {
            $hasWork = false;
            
            // Pull messages from all queues
            foreach ($queues as $queueName => $callback) {
                $messages = $this->rabbit->getMessages($queueName, 10); // Pull 10 at once
                
                if (!empty($messages)) {
                    $hasWork = true;
                    foreach ($messages as $msg) {
                        try {
                            $payload = json_decode($msg['payload'], true);
                            call_user_func($callback, $payload);
                            $this->processedCount++;
                            
                        } catch (Exception $e) {
                            error_log("Fast worker error: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Process batches when full or timeout
            $now = time();
            if ($now - $lastBatchTime >= self::BATCH_TIMEOUT || 
                count($this->emailBatch) >= self::BATCH_SIZE ||
                count($this->inventoryBatch) >= self::BATCH_SIZE ||
                count($this->analyticsBatch) >= self::BATCH_SIZE) {
                
                $this->processBatches();
                $lastBatchTime = $now;
            }
            
            // Show stats every 100 tasks
            if ($this->processedCount > 0 && $this->processedCount % 100 === 0) {
                $runtime = time() - $this->startTime;
                $rate = $runtime > 0 ? round($this->processedCount / $runtime, 2) : 0;
                echo "[STATS] Processed {$this->processedCount} tasks | Rate: {$rate}/s\n";
            }
            
            // Small delay only if no work
            if (!$hasWork) {
                usleep(50000); // 50ms
            }
            
            // Allow signal handling
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }
    
    private function queueEmail($payload) {
        $this->emailBatch[] = $payload;
    }
    
    private function queueInventory($payload) {
        $this->inventoryBatch[] = $payload;
    }
    
    private function queueAnalytics($payload) {
        $this->analyticsBatch[] = $payload;
    }
    
    private function processBatches($force = false) {
        if (!empty($this->emailBatch)) {
            $this->processEmailBatch();
        }
        
        if (!empty($this->inventoryBatch)) {
            $this->processInventoryBatch();
        }
        
        if (!empty($this->analyticsBatch)) {
            $this->processAnalyticsBatch();
        }
    }
    
    private function processEmailBatch() {
        if (empty($this->emailBatch)) return;
        
        $startTime = microtime(true);
        $count = count($this->emailBatch);
        
        try {
            // Load mailer once for batch
            require_once __DIR__ . '/../../public/config/simple_mailer.php';
            $mailer = getSimpleMailer();
            
            $this->pdo->beginTransaction();
            
            // Prepare batch insert for email logs
            $logStmt = null;
            if ($this->tableExists('email_logs')) {
                $logStmt = $this->pdo->prepare("INSERT INTO email_logs (order_id, recipient, subject, status, sent_at) VALUES (?, ?, ?, ?, NOW())");
            }
            
            foreach ($this->emailBatch as $task) {
                $data = $task['data'];
                $orderId = $data['order_id'];
                $email = $data['email'];
                $orderData = $data['order_data'];
                
                // Send email
                $emailSent = false;
                if ($data['type'] === 'order_confirmation') {
                    $emailSent = $mailer->sendOrderConfirmation($email, $orderData);
                }
                
                // Log result
                if ($logStmt) {
                    $status = $emailSent ? 'sent' : 'failed';
                    $logStmt->execute([$orderId, $email, "Xác nhận đơn hàng #{$orderId}", $status]);
                }
                
                // Update order status history
                $historyStmt = $this->pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_at) VALUES (?, ?, ?, NOW())");
                $note = $emailSent ? "Email xác nhận đã được gửi đến {$email}" : "Gửi email thất bại đến {$email}";
                $historyStmt->execute([$orderId, ORDER_STATUS_PROCESSING, $note]);
            }
            
            $this->pdo->commit();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "[EMAIL] Processed {$count} emails in {$duration}ms\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Email batch error: " . $e->getMessage());
        }
        
        $this->emailBatch = [];
    }
    
    private function processInventoryBatch() {
        if (empty($this->inventoryBatch)) return;
        
        $startTime = microtime(true);
        $count = count($this->inventoryBatch);
        
        try {
            if (!$this->tableExists('inventory_logs')) {
                $this->inventoryBatch = [];
                return;
            }
            
            $this->pdo->beginTransaction();
            
            // Batch insert inventory logs
            $stmt = $this->pdo->prepare("INSERT INTO inventory_logs (product_id, change_type, quantity_change, reason, order_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            foreach ($this->inventoryBatch as $task) {
                $data = $task['data'];
                $orderId = $data['order_id'];
                
                foreach ($data['items'] as $item) {
                    $stmt->execute([
                        $item['product_id'],
                        'decrease',
                        -$item['quantity'],
                        'order_placed_fast',
                        $orderId
                    ]);
                }
            }
            
            $this->pdo->commit();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "[INVENTORY] Processed {$count} logs in {$duration}ms\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Inventory batch error: " . $e->getMessage());
        }
        
        $this->inventoryBatch = [];
    }
    
    private function processAnalyticsBatch() {
        if (empty($this->analyticsBatch)) return;
        
        $startTime = microtime(true);
        $count = count($this->analyticsBatch);
        
        try {
            $this->pdo->beginTransaction();
            
            // Batch update daily metrics
            if ($this->tableExists('daily_metrics')) {
                $metricsStmt = $this->pdo->prepare("INSERT INTO daily_metrics (date, order_count, revenue, created_at) 
                                                    VALUES (?, 1, ?, NOW()) 
                                                    ON DUPLICATE KEY UPDATE 
                                                    order_count = order_count + 1, 
                                                    revenue = revenue + ?");
                
                foreach ($this->analyticsBatch as $task) {
                    $data = $task['data'];
                    $date = date('Y-m-d', strtotime($data['data']['created_at']));
                    $revenue = $data['data']['total_price'];
                    
                    $metricsStmt->execute([$date, $revenue, $revenue]);
                }
            }
            
            // Batch update product sales count (already done in checkout, skip for performance)
            
            $this->pdo->commit();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "[ANALYTICS] Processed {$count} records in {$duration}ms\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Analytics batch error: " . $e->getMessage());
        }
        
        $this->analyticsBatch = [];
    }
    
    private function tableExists($tableName) {
        try {
            $result = $this->pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Start fast worker
try {
    $worker = new FastWorker();
    $worker->start();
} catch (Exception $e) {
    echo "Fast worker error: " . $e->getMessage() . "\n";
    exit(1);
}