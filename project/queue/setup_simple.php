<?php
/**
 * Simple RabbitMQ Setup - No dependencies required
 * Usage: php queue/setup_simple.php
 */

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once __DIR__ . '/rabbitmq_simple.php';

echo "=================================\n";
echo "RabbitMQ Simple Setup\n";
echo "=================================\n\n";

$rabbit = getSimpleRabbitMQ();

// Check connection
echo "Checking RabbitMQ connection...\n";
if (!$rabbit->isAvailable()) {
    die("✗ Cannot connect to RabbitMQ!\n" .
        "Please check:\n" .
        "1. RabbitMQ is running\n" .
        "2. Management plugin is enabled\n" .
        "3. Credentials in .env are correct (admin/123456)\n");
}
echo "✓ Connected successfully\n\n";

// Create exchanges
echo "Creating exchanges...\n";
$rabbit->createExchange('orders_exchange', 'topic', true);
echo "✓ orders_exchange created\n";

$rabbit->createExchange('dlx_exchange', 'direct', true);
echo "✓ dlx_exchange created\n\n";

// Create queues
echo "Creating queues...\n";

$queues = [
    'order_processing' => [],
    'inventory_update' => [],
    'email_notification' => [],
    'admin_notification' => [],
    'order_logging' => [],
    'analytics_queue' => []
];

foreach ($queues as $queueName => $args) {
    $rabbit->createQueue($queueName, true, $args);
    echo "✓ {$queueName} created\n";
}

// Create dead letter queues
$dlQueues = ['dlq_orders', 'dlq_inventory', 'dlq_email'];
foreach ($dlQueues as $queueName) {
    $rabbit->createQueue($queueName, true);
    echo "✓ {$queueName} created\n";
}

echo "\nBinding queues to exchange...\n";

// Bind queues
$bindings = [
    ['order_processing', 'orders_exchange', 'order.#'],
    ['inventory_update', 'orders_exchange', 'order.created'],
    ['inventory_update', 'orders_exchange', 'order.cancelled'],
    ['email_notification', 'orders_exchange', 'order.#'],
    ['admin_notification', 'orders_exchange', 'order.created'],
    ['order_logging', 'orders_exchange', '#'],
    ['analytics_queue', 'orders_exchange', 'order.#']
];

foreach ($bindings as $binding) {
    list($queue, $exchange, $routingKey) = $binding;
    $rabbit->bindQueue($queue, $exchange, $routingKey);
    echo "✓ {$queue} bound to {$exchange} with key '{$routingKey}'\n";
}

echo "\n=================================\n";
echo "Setup completed successfully!\n";
echo "=================================\n\n";

echo "Available queues:\n";
foreach ($queues as $queueName => $args) {
    echo "  - {$queueName}\n";
}

echo "\nDead Letter Queues:\n";
foreach ($dlQueues as $queueName) {
    echo "  - {$queueName}\n";
}

echo "\nYou can now:\n";
echo "1. Test the system: php test_simple_rabbitmq.php\n";
echo "2. Start workers: php app/workers/simple_worker.php\n";
echo "3. View RabbitMQ UI: http://localhost:15672 (admin/123456)\n";
