<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();

// Get filter status from URL
$filterStatus = $_GET['status'] ?? 'all';

// Build query for active orders only (pending, processing, shipping)
$query = "SELECT o.*, sm.name as shipping_method
          FROM orders o
          LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
          WHERE o.user_id = ? AND o.status IN ('pending', 'processing', 'shipping')";

if ($filterStatus !== 'all' && in_array($filterStatus, ['pending', 'processing', 'shipping'])) {
    $query .= " AND o.status = ?";
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
if ($filterStatus !== 'all' && in_array($filterStatus, ['pending', 'processing', 'shipping'])) {
    $stmt->execute([$_SESSION['user_id'], $filterStatus]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
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

.orders-tabs {
    display: flex;
    gap: 12px;
    background: rgba(255, 255, 255, 0.05);
    padding: 8px;
    border-radius: 12px;
    margin-bottom: 30px;
    overflow-x: auto;
    backdrop-filter: blur(10px);
}

.orders-tabs::-webkit-scrollbar {
    height: 4px;
}

.orders-tabs::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.orders-tabs::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.5);
    border-radius: 10px;
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-size: 14px;
    font-weight: 600;
    color: #94a3b8;
    cursor: pointer;
    position: relative;
    white-space: nowrap;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.tab-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
}

.tab-btn.active {
    color: #fff;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.order-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
    border-color: rgba(99, 102, 241, 0.3);
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.chat-btn {
    padding: 8px 16px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.chat-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.view-shop-btn {
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-shop-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.delivery-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.status-icon {
    color: #10b981;
}

.status-text {
    color: #10b981;
    font-weight: 500;
}

.order-status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-completed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-processing {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-shipping {
    background: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.status-cancelled {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
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

.original-price {
    font-size: 13px;
    color: #64748b;
    text-decoration: line-through;
    margin-bottom: 4px;
}

.sale-price {
    font-size: 18px;
    font-weight: 700;
    color: #10b981;
}

.order-footer {
    padding: 20px 24px;
    background: rgba(255, 255, 255, 0.03);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
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
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

.btn-review:hover {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-contact {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
}

.btn-contact:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-2px);
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

.btn-cancel {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-color: transparent;
}

.btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
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
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-shop-now:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.5);
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
        <h1>Đơn hàng đang xử lý</h1>
        <p>Theo dõi các đơn hàng đang được xử lý và giao hàng</p>
    </div>
    
    <!-- Tabs -->
    <div class="orders-tabs">
        <button class="tab-btn <?php echo $filterStatus === 'all' ? 'active' : ''; ?>" 
                onclick="window.location.href='my_orders.php?status=all'">
            Tất cả
        </button>
        <button class="tab-btn <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>" 
                onclick="window.location.href='my_orders.php?status=pending'">
            Chờ xác nhận
        </button>
        <button class="tab-btn <?php echo $filterStatus === 'processing' ? 'active' : ''; ?>" 
                onclick="window.location.href='my_orders.php?status=processing'">
            Đang xử lý
        </button>
        <button class="tab-btn <?php echo $filterStatus === 'shipping' ? 'active' : ''; ?>" 
                onclick="window.location.href='my_orders.php?status=shipping'">
            Đang giao
        </button>
    </div>
    
    <!-- Links to other order pages -->
    <div style="margin-bottom: 20px; text-align: center;">
        <a href="completed_orders.php" style="color: #10b981; text-decoration: none; margin-right: 20px; font-weight: 500;">
            <i class="fas fa-check-circle"></i> Xem đơn hàng hoàn thành
        </a>
        <a href="cancelled_orders.php" style="color: #ef4444; text-decoration: none; font-weight: 500;">
            <i class="fas fa-times-circle"></i> Xem đơn hàng đã hủy
        </a>
    </div>
    
    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <div class="empty-orders-icon">📦</div>
            <h2>Chưa có đơn hàng nào</h2>
            <p>Hãy khám phá và mua sắm những sản phẩm yêu thích của bạn!</p>
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
                            <div class="shop-icon">🏪</div>
                            <span>Tech Store</span>
                        </div>
                        <button class="chat-btn">💬 Chat</button>
                        <button class="view-shop-btn">👁 Xem Shop</button>
                    </div>
                    <div class="delivery-status">
                        <?php if ($order['status'] === 'completed'): ?>
                            <span class="status-icon">✓</span>
                            <span class="status-text">Giao hàng thành công</span>
                        <?php endif; ?>
                        <span class="order-status-badge status-<?php echo $order['status']; ?>">
                            <?php 
                            $statusText = [
                                'pending' => 'Chờ xác nhận',
                                'processing' => 'Đang xử lý',
                                'shipping' => 'Đang giao',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Đã hủy'
                            ];
                            echo $statusText[$order['status']] ?? $order['status'];
                            ?>
                        </span>
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
                        <?php if ($order['status'] === 'completed'): ?>
                            <button class="btn-action btn-review">Đánh Giá</button>
                            <button class="btn-action btn-reorder" onclick="reorder(<?php echo $order['id']; ?>)">Mua Lại</button>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                            <button class="btn-action btn-reorder" onclick="reorder(<?php echo $order['id']; ?>)">Đặt Lại</button>
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-review">Xem Chi Tiết</a>
                        <?php elseif (in_array($order['status'], ['pending', 'processing'])): ?>
                            <button class="btn-action btn-cancel" onclick="cancelOrder(<?php echo $order['id']; ?>)">Hủy Đơn</button>
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-reorder">Xem Chi Tiết</a>
                        <?php else: ?>
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-review">Xem Chi Tiết</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Cancel Order Modal -->
<div id="cancelModal" class="cancel-modal">
    <div class="cancel-modal-content">
        <div class="cancel-modal-header">
            <div class="cancel-icon">⚠️</div>
            <h3>E-Commerce System cho biết</h3>
            <button class="close-modal" onclick="closeCancelModal()">&times;</button>
        </div>
        <div class="cancel-modal-body">
            <p>Bạn có chắc chắn muốn hủy đơn hàng <span id="cancelOrderId">#217</span>?</p>
        </div>
        <div class="cancel-modal-footer">
            <button class="btn-modal-cancel" onclick="closeCancelModal()">Hủy</button>
            <button class="btn-modal-confirm" onclick="confirmCancelOrder()">OK</button>
        </div>
    </div>
</div>

<style>
.cancel-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.cancel-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 420px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
    overflow: hidden;
}

.cancel-modal-header {
    padding: 24px 24px 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.cancel-icon {
    font-size: 24px;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.cancel-modal-header h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    flex: 1;
}

.close-modal {
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 24px;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.cancel-modal-body {
    padding: 0 24px 24px 24px;
}

.cancel-modal-body p {
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.cancel-modal-body #cancelOrderId {
    color: #6366f1;
    font-weight: 600;
}

.cancel-modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn-modal-cancel,
.btn-modal-confirm {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.btn-modal-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-1px);
}

.btn-modal-confirm {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-modal-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to { 
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

@media (max-width: 480px) {
    .cancel-modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .cancel-modal-footer {
        flex-direction: column;
    }
    
    .btn-modal-cancel,
    .btn-modal-confirm {
        width: 100%;
    }
}
</style>

<script>
let currentOrderId = null;

function cancelOrder(orderId) {
    currentOrderId = orderId;
    document.getElementById('cancelOrderId').textContent = '#' + orderId;
    document.getElementById('cancelModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentOrderId = null;
}

function confirmCancelOrder() {
    if (!currentOrderId) return;
    
    // Show loading state
    const confirmBtn = document.querySelector('.btn-modal-confirm');
    const originalText = confirmBtn.textContent;
    confirmBtn.textContent = 'Đang xử lý...';
    confirmBtn.disabled = true;
    
    fetch('api/cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + currentOrderId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success animation
            confirmBtn.textContent = '✓ Thành công';
            confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            setTimeout(() => {
                closeCancelModal();
                showNotification('success', data.message);
                location.reload();
            }, 1000);
        } else {
            // Error state
            confirmBtn.textContent = '✗ Lỗi';
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            showNotification('error', data.message);
            
            setTimeout(() => {
                confirmBtn.textContent = originalText;
                confirmBtn.style.background = 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
                confirmBtn.disabled = false;
            }, 2000);
        }
    })
    .catch(error => {
        confirmBtn.textContent = '✗ Lỗi';
        confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showNotification('error', 'Lỗi kết nối: ' + error.message);
        
        setTimeout(() => {
            confirmBtn.textContent = originalText;
            confirmBtn.style.background = 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
            confirmBtn.disabled = false;
        }, 2000);
    });
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('cancelModal');
    if (event.target === modal) {
        closeCancelModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCancelModal();
    }
});

function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${type === 'success' ? '✓' : '✗'}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    // Add notification styles
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10001;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s;
            max-width: 400px;
        }
        
        .notification-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.9));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .notification-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9));
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 500;
        }
        
        .notification-icon {
            font-size: 18px;
            font-weight: bold;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(notification);
    
    // Remove notification after animation
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
        if (style.parentNode) {
            style.parentNode.removeChild(style);
        }
    }, 3000);
}

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
            showNotification('success', data.message);
            setTimeout(() => {
                window.location.href = 'cart.php';
            }, 1000);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        showNotification('error', 'Lỗi: ' + error.message);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
