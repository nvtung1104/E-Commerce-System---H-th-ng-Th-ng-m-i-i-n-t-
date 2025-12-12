<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT o.*, u.name as fullname, u.email, p.status as payment_status
                       FROM orders o
                       LEFT JOIN users u ON o.user_id = u.id
                       LEFT JOIN payments p ON o.id = p.order_id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// Get order items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.thumbnail 
                       FROM order_items oi
                       LEFT JOIN products p ON oi.product_id = p.id
                       WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
.order-success-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.success-animation {
    text-align: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
    border: 2px solid rgba(16, 185, 129, 0.3);
    border-radius: 16px;
    margin-bottom: 30px;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.success-icon {
    font-size: 80px;
    margin-bottom: 20px;
    animation: scaleIn 0.6s ease;
}

@keyframes scaleIn {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.success-title {
    font-size: 32px;
    font-weight: 700;
    color: #10b981;
    margin-bottom: 12px;
}

.success-subtitle {
    font-size: 16px;
    color: #64748b;
    margin-bottom: 8px;
}

.processing-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    margin-top: 16px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.order-info-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 20px;
}

.order-info-card h2 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.info-label {
    color: #94a3b8;
    font-size: 14px;
}

.info-value {
    color: #fff;
    font-weight: 600;
    font-size: 14px;
}

.order-items-list {
    margin-top: 20px;
}

.order-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    margin-bottom: 12px;
}

.item-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.item-details {
    flex: 1;
}

.item-name {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.item-quantity {
    font-size: 14px;
    color: #94a3b8;
}

.item-price {
    font-size: 18px;
    font-weight: 700;
    color: #10b981;
    text-align: right;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn {
    flex: 1;
    padding: 16px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}



.btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}


</style>

<div class="order-success-container">
    <div class="success-animation">
        <div class="success-icon">✓</div>
        <h1 class="success-title">Đặt hàng thành công!</h1>
        <p class="success-subtitle">Đơn hàng #<?php echo $orderId; ?> đã được tạo</p>
        <div class="processing-badge">
            <span>🔄</span>
            <span>Đơn hàng đang được xử lý</span>
        </div>
    </div>

    <div class="order-info-card">
        <h2>📋 Thông tin đơn hàng</h2>
        
        <div class="info-row">
            <span class="info-label">Mã đơn hàng</span>
            <span class="info-value">#<?php echo $orderId; ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Khách hàng</span>
            <span class="info-value"><?php echo htmlspecialchars($order['fullname']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Phương thức thanh toán</span>
            <span class="info-value"><?php echo strtoupper($order['payment_method']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Trạng thái đơn hàng</span>
            <span class="info-value" style="color: #3b82f6;">
                <?php 
                $statusText = [
                    'pending' => 'Chờ xác nhận',
                    'processing' => 'Đang xử lý',
                    'shipping' => 'Đang giao',
                    'completed' => 'Hoàn thành'
                ];
                echo $statusText[$order['status']] ?? $order['status'];
                ?>
            </span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Ngày đặt</span>
            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
        </div>
        
        <div class="info-row" style="border-bottom: none; padding-top: 20px; margin-top: 12px; border-top: 2px solid rgba(16, 185, 129, 0.3);">
            <span class="info-label" style="font-size: 18px; color: #fff;">Tổng tiền</span>
            <span class="info-value" style="font-size: 24px; color: #10b981;"><?php echo number_format($order['total_price']); ?>₫</span>
        </div>
    </div>

    <div class="order-info-card">
        <h2>📦 Sản phẩm đã đặt</h2>
        <div class="order-items-list">
            <?php foreach ($items as $item): ?>
            <div class="order-item">
                <img src="assets/images/<?php echo htmlspecialchars($item['thumbnail'] ?? 'default.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                     class="item-image"
                     onerror="this.src='assets/images/default.jpg'">
                <div class="item-details">
                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="item-quantity">Số lượng: <?php echo $item['quantity']; ?></div>
                </div>
                <div class="item-price">
                    <?php echo number_format($item['price'] * $item['quantity']); ?>₫
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="action-buttons">
        <a href="my_orders.php" class="btn btn-primary">Xem đơn hàng của tôi</a>
        <a href="index.php" class="btn btn-secondary">Tiếp tục mua sắm</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
