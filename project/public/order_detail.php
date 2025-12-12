<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

$orderId = $_GET['id'] ?? null;

if (!$orderId) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

// Get order details
$stmt = $pdo->prepare("SELECT o.*, u.name as fullname, u.email, u.phone,
                       ua.address_detail, ua.ward, ua.district, ua.province,
                       sm.name as shipping_method
                       FROM orders o
                       LEFT JOIN users u ON o.user_id = u.id
                       LEFT JOIN user_addresses ua ON o.address_id = ua.id
                       LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// Get order items with product details
$stmt = $pdo->prepare("SELECT oi.*, 
                       COALESCE(p.name, 'Sản phẩm đã bị xóa') as name, 
                       COALESCE(p.thumbnail, 'default.jpg') as thumbnail,
                       p.sku
                       FROM order_items oi
                       LEFT JOIN products p ON oi.product_id = p.id
                       WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status history
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC");
$stmt->execute([$orderId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<h1>Chi tiết đơn hàng #<?php echo $orderId; ?></h1>

<div class="order-detail-grid">
    <div class="order-info">
        <h2>Thông tin đơn hàng</h2>
        <p><strong>Trạng thái:</strong> <span class="status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span></p>
        <p><strong>Ngày đặt:</strong> <?php echo $order['created_at']; ?></p>
        <p><strong>Phương thức thanh toán:</strong> <?php echo $order['payment_method']; ?></p>
        <p><strong>Trạng thái thanh toán:</strong> <span class="status-<?php echo $order['payment_status']; ?>"><?php echo $order['payment_status']; ?></span></p>
        
        <h2>Thông tin người nhận</h2>
        <p><strong>Họ tên:</strong> <?php echo $order['fullname']; ?></p>
        <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
        <p><strong>Số điện thoại:</strong> <?php echo $order['phone']; ?></p>
        <p><strong>Địa chỉ:</strong> <?php echo $order['address_detail']; ?>, <?php echo $order['ward']; ?>, 
           <?php echo $order['district']; ?>, <?php echo $order['province']; ?></p>
        
        <h2>Lịch sử đơn hàng</h2>
        <div class="status-timeline">
            <?php foreach ($history as $h): ?>
            <div class="timeline-item">
                <strong><?php echo $h['status']; ?></strong>
                <p><?php echo $h['note']; ?></p>
                <small><?php echo $h['created_at']; ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="order-items">
        <h2>Sản phẩm</h2>
        <?php foreach ($items as $item): ?>
        <div class="order-item">
            <img src="assets/images/<?php echo $item['thumbnail']; ?>" alt="<?php echo $item['name']; ?>">
            <div class="item-info">
                <h4><?php echo $item['name']; ?></h4>
                <p>Số lượng: <?php echo $item['quantity']; ?></p>
                <p>Giá: <?php echo number_format($item['price']); ?> VNĐ</p>
                <p><strong>Tổng: <?php echo number_format($item['price'] * $item['quantity']); ?> VNĐ</strong></p>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="order-summary">
            <p>Phí vận chuyển: <?php echo number_format($order['shipping_fee']); ?> VNĐ</p>
            <?php if ($order['voucher_code']): ?>
                <p>Mã giảm giá: <?php echo $order['voucher_code']; ?></p>
            <?php endif; ?>
            <h3>Tổng cộng: <?php echo number_format($order['total_price']); ?> VNĐ</h3>
        </div>
    </div>
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
            <p>Bạn có chắc chắn muốn hủy đơn hàng <span id="cancelOrderId">#<?php echo $orderId; ?></span>?</p>
        </div>
        <div class="cancel-modal-footer">
            <button class="btn-modal-cancel" onclick="closeCancelModal()">Hủy</button>
            <button class="btn-modal-confirm" onclick="confirmCancelOrder()">OK</button>
        </div>
    </div>
</div>

<style>
body {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    min-height: 100vh;
    color: #e2e8f0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

h1 {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 30px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    text-align: center;
}

.order-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.order-info, .order-items {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

.order-info h2, .order-items h2 {
    color: #fff;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.order-info p {
    margin-bottom: 8px;
    line-height: 1.6;
}

.order-info strong {
    color: #94a3b8;
    font-weight: 500;
}

.status-pending {
    color: #f59e0b;
    font-weight: 600;
}

.status-processing {
    color: #3b82f6;
    font-weight: 600;
}

.status-shipping {
    color: #8b5cf6;
    font-weight: 600;
}

.status-completed {
    color: #10b981;
    font-weight: 600;
}

.status-cancelled {
    color: #ef4444;
    font-weight: 600;
}

.status-timeline {
    margin-top: 16px;
}

.timeline-item {
    padding: 12px 0;
    border-left: 2px solid rgba(99, 102, 241, 0.3);
    padding-left: 16px;
    margin-left: 8px;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 16px;
    width: 8px;
    height: 8px;
    background: #6366f1;
    border-radius: 50%;
}

.timeline-item strong {
    color: #6366f1;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.timeline-item p {
    margin: 4px 0;
    color: #e2e8f0;
}

.timeline-item small {
    color: #94a3b8;
    font-size: 12px;
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

.order-item img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.item-info {
    flex: 1;
}

.item-info h4 {
    color: #fff;
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 8px;
}

.item-info p {
    color: #94a3b8;
    font-size: 14px;
    margin-bottom: 4px;
}

.order-summary {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.order-summary p {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.order-summary h3 {
    display: flex;
    justify-content: space-between;
    color: #10b981;
    font-size: 20px;
    font-weight: 700;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.order-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 30px;
}

.btn-action {
    padding: 12px 32px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-cancel {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
}

.btn-back {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-back:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-2px);
}

.btn-reorder {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.btn-reorder:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

/* Modal Styles */
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

@media (max-width: 768px) {
    .order-detail-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .order-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-action {
        width: 100%;
        max-width: 300px;
    }
    
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

<!-- Order Actions -->
<div class="order-actions">
    <a href="my_orders.php" class="btn-action btn-back">← Quay lại</a>
    
    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
        <button class="btn-action btn-cancel" onclick="cancelOrder(<?php echo $orderId; ?>)">Hủy đơn hàng</button>
    <?php endif; ?>
    
    <?php if ($order['status'] === 'completed'): ?>
        <button class="btn-action btn-reorder" onclick="reorder(<?php echo $orderId; ?>)">Đặt lại</button>
    <?php endif; ?>
</div>

<script>
let currentOrderId = <?php echo $orderId; ?>;

function cancelOrder(orderId) {
    currentOrderId = orderId;
    document.getElementById('cancelOrderId').textContent = '#' + orderId;
    document.getElementById('cancelModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
    document.body.style.overflow = 'auto';
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
                setTimeout(() => {
                    location.reload();
                }, 1000);
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
</script>

<?php include 'includes/footer.php'; ?>
