<?php
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'E-Commerce System');

// Queue names
define('QUEUE_ORDER_CREATED', 'order_created');
define('QUEUE_EMAIL', 'email_queue');
define('QUEUE_INVENTORY', 'inventory_queue');
define('QUEUE_LOG', 'log_queue');
define('QUEUE_NOTIFICATION', 'notification_queue');

// Exchange
define('EXCHANGE_ORDERS', 'orders_exchange');

// Order status
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPING', 'shipping');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Payment status
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');

// User roles
define('ROLE_USER', 'user');
define('ROLE_ADMIN', 'admin');
