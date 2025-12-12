<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();

// Get completed orders only
$query = "SELECT o.*, sm.name as shipping_method
          FROM orders o
          LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
          WHERE o.user_id = ? AND o.status = 'completed'
          ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for each order
foreach ($orders as &$order) {
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.thumbnail 
                           FROM order_items oi
                           LEFT JOIN products p ON oi.product_id = p.id
                           WHERE oi.order_id = ?");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
body {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    min-height: 100vh;
}

.my-orders-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.orders-header {
    margin-bottom: 30px;
}

.orders-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.orders-header p {
    color: #94a3b8;
    font-size: 14px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.order-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.5);
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: rgba(16, 185, 129, 0.1);
    border-bottom: 1px solid rgba(16, 185, 129, 0.2);
}

.order-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.shop-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #fff;
}

.shop-icon {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.completed-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-items {
    padding: 24px;
}

.order-item {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.order-item:last-child {
    border-bottom: none;
}

.item-images {
    display: flex;
    gap: 8px;
}

.item-image {
    width: 90px;
    height: 90px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-name {
    font-size: 15px;
    color: #fff;
    font-weight: 500;
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
}

.item-variant {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 4px;
}

.item-quantity {
    font-size: 13px;
    color: #94a3b8;
}

.item-price {
    text-align: right;
}

.sale-price {
    font-size: 18px;
    font-weight: 700;
    color: #10b981;
}

.order-footer {
    padding: 20px 24px;
    background: rgba(16, 185, 129, 0.05);
    border-top: 1px solid rgba(16, 185, 129, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-total {
    display: flex;
    align-items: center;
    gap: 12px;
}

.total-label {
    font-size: 14px;
    color: #94a3b8;
}

.total-amount {
    font-size: 24px;
    font-weight: 700;
    background: linear-gradient(135deg, #10b981, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.order-actions {
    display: flex;
    gap: 12px;
}

.btn-action {
    padding: 12px 24px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-review {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
}

.btn-review:hover {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-reorder {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
}

.btn-reorder:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.empty-orders {
    text-align: center;
    padding: 100px 20px;
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.5) 0%, rgba(15, 23, 42, 0.5) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    backdrop-filter: blur(10px);
}

.empty-orders-icon {
    font-size: 80px;
    margin-bottom: 24px;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.empty-orders h2 {
    font-size: 24px;
    color: #fff;
    font-weight: 700;
    margin-bottom: 12px;
}

.empty-orders p {
    font-size: 15px;
    color: #94a3b8;
    margin-bottom: 32px;
}

.btn-shop-now {
    display: inline-block;
    padding: 14px 40px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-shop-now:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.5);
}

@media (max-width: 768px) {
    .order-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .order-item {
        flex-direction: column;
    }
    
    .item-price {
        text-align: left;
    }
    
    .order-footer {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .order-actions {
        flex-direction: column;
    }
}
</style>

<div class="my-orders-container">
    <div class="orders-header">
        <h1>Đơn hàng đã hoàn thành</h1>
        <p>Các đơn hàng đã được giao thành công</p>
    </div>
    
    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <div class="empty-orders-icon">✅</div>
            <h2>Chưa có đơn hàng hoàn thành</h2>
            <p>Các đơn hàng đã hoàn thành sẽ xuất hiện tại đây!</p>
            <a href="index.php" class="btn-shop-now">Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <!-- Header -->
                <div class="order-card-header">
                    <div class="order-info">
                        <div class="shop-name">
                            <div class="shop-icon">✓</div>
                            <span>Tech Store</span>
                        </div>
                    </div>
                    <div class="completed-badge">
                        <span>✓</span>
                        <span>Đã hoàn thành</span>
                    </div>
                </div>
                
                <!-- Items -->
                <div class="order-items">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="order-item">
                        <div class="item-images">
                            <div class="item-image">
                                <?php if (!empty($item['thumbnail'])): ?>
                                    <img src="assets/images/<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width:100%;height:100%;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:32px;\'>📦</div>';">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:32px;">📦</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-variant">Phân loại hàng: Mặc định</div>
                            <div class="item-quantity">x<?php echo $item['quantity']; ?></div>
                        </div>
                        <div class="item-price">
                            <div class="sale-price"><?php echo number_format($item['price']); ?>₫</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Footer -->
                <div class="order-footer">
                    <div class="order-total">
                        <span class="total-label">Thành tiền:</span>
                        <span class="total-amount"><?php echo number_format($order['total_price']); ?>₫</span>
                    </div>
                    <div class="order-actions">
                        <button class="btn-action btn-review">Đánh Giá</button>
                        <button class="btn-action btn-reorder" onclick="reorder(<?php echo $order['id']; ?>)">Mua Lại</button>
                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-review">Xem Chi Tiết</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function reorder(orderId) {
    if (!confirm('Đặt lại đơn hàng #' + orderId + '?\nSản phẩm sẽ được thêm vào giỏ hàng.')) {
        return;
    }
    
    fetch('api/reorder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + orderId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.message);
            window.location.href = 'cart.php';
        } else {
            alert('✗ ' + data.message);
        }
    })
    .catch(error => {
        alert('Lỗi: ' + error.message);
    });
}
</script>

<?php include 'includes/footer.php'; ?>