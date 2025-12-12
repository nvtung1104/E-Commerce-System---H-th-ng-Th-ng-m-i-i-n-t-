<?php
/**
 * Simple RabbitMQ Client using HTTP API
 * No need for php-amqplib extension
 */

class SimpleRabbitMQ {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $vhost;
    private $apiPort = 15672;
    
    public function __construct() {
        $this->host = $_ENV['RABBITMQ_HOST'] ?? 'localhost';
        $this->port = $_ENV['RABBITMQ_PORT'] ?? 5672;
        $this->user = $_ENV['RABBITMQ_USER'] ?? 'admin';
        $this->pass = $_ENV['RABBITMQ_PASS'] ?? '123456';
        $this->vhost = $_ENV['RABBITMQ_VHOST'] ?? '/';
    }
    
    /**
     * Publish message to exchange using HTTP API
     */
    public function publish($exchange, $routingKey, $message, $properties = []) {
        $vhost = urlencode($this->vhost);
        $url = "http://{$this->host}:{$this->apiPort}/api/exchanges/{$vhost}/{$exchange}/publish";
        
        $payload = [
            'properties' => array_merge([
                'delivery_mode' => 2, // persistent
                'content_type' => 'application/json'
            ], $properties),
            'routing_key' => $routingKey,
            'payload' => json_encode($message),
            'payload_encoding' => 'string'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("RabbitMQ publish failed: HTTP {$httpCode} - {$response}");
            return false;
        }
    }
    
    /**
     * Create exchange
     */
    public function createExchange($name, $type = 'topic', $durable = true) {
        $vhost = urlencode($this->vhost);
        $url = "http://{$this->host}:{$this->apiPort}/api/exchanges/{$vhost}/{$name}";
        
        $payload = [
            'type' => $type,
            'durable' => $durable,
            'auto_delete' => false
        ];
        
        return $this->httpRequest($url, 'PUT', $payload);
    }
    
    /**
     * Create queue
     */
    public function createQueue($name, $durable = true, $arguments = []) {
        $vhost = urlencode($this->vhost);
        $url = "http://{$this->host}:{$this->apiPort}/api/queues/{$vhost}/{$name}";
        
        $payload = [
            'durable' => $durable,
            'auto_delete' => false,
            'arguments' => $arguments
        ];
        
        return $this->httpRequest($url, 'PUT', $payload);
    }
    
    /**
     * Bind queue to exchange
     */
    public function bindQueue($queue, $exchange, $routingKey = '') {
        $vhost = urlencode($this->vhost);
        $url = "http://{$this->host}:{$this->apiPort}/api/bindings/{$vhost}/e/{$exchange}/q/{$queue}";
        
        $payload = [
            'routing_key' => $routingKey
        ];
        
        return $this->httpRequest($url, 'POST', $payload);
    }
    
    /**
     * Get messages from queue
     */
    public function getMessages($queue, $count = 1, $ackMode = 'ack_requeue_false') {
        $vhost = urlencode($this->vhost);
        $url = "http://{$this->host}:{$this->apiPort}/api/queues/{$vhost}/{$queue}/get";
        
        $payload = [
            'count' => $count,
            'ackmode' => $ackMode,
            'encoding' => 'auto'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return [];
    }
    
    /**
     * Check if RabbitMQ is available
     */
    public function isAvailable() {
        $url = "http://{$this->host}:{$this->apiPort}/api/overview";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    /**
     * Helper for HTTP requests
     */
    private function httpRequest($url, $method = 'GET', $payload = null) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
        
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
}

/**
 * Global helper function
 */
function getSimpleRabbitMQ() {
    static $instance = null;
    if ($instance === null) {
        $instance = new SimpleRabbitMQ();
    }
    return $instance;
}
