<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

$orderId = $_GET['id'] ?? null;

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

$pdo = getDBConnection();

// Get order details
$stmt = $pdo->prepare("SELECT o.*, 
                       u.name as fullname, u.email, u.phone,
                       ua.address_detail, ua.ward, ua.district, ua.province,
                       sm.name as shipping_method,
                       p.status as payment_status
                       FROM orders o
                       LEFT JOIN users u ON o.user_id = u.id
                       LEFT JOIN user_addresses ua ON o.address_id = ua.id
                       LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
                       LEFT JOIN payments p ON o.id = p.order_id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// Get order items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.thumbnail, p.sku
                       FROM order_items oi
                       LEFT JOIN products p ON oi.product_id = p.id
                       WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status history
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC");
$stmt->execute([$orderId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get processing status
$emailSent = false;
$inventoryUpdated = false;

// Check email sent
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE order_id = ? AND status = 'sent'");
    $stmt->execute([$orderId]);
    $emailSent = $stmt->fetchColumn() > 0;
} catch (Exception $e) {
    // Check in history
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_status_history WHERE order_id = ? AND note LIKE '%email%'");
    $stmt->execute([$orderId]);
    $emailSent = $stmt->fetchColumn() > 0;
}

// Check inventory updated
$inventoryUpdated = ($order['status'] !== 'pending');

include 'includes/header.php';
?>

<style>
.order-detail-container {
    padding: 30px;
    background: #0a0e27;
    min-height: 100vh;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.order-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
}

.status-actions {
    display: flex;
    gap: 12px;
}

.btn-status {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-confirm {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-ship {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    pointer-events: auto;
    z-index: 1;
}

.btn-complete {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-cancel {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-status:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.order-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

.order-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
}

.order-card h2 {
    font-size: 20px;
    font-weight: 600;
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

.item-info {
    font-size: 14px;
    color: #94a3b8;
}

.item-price {
    font-size: 18px;
    font-weight: 700;
    color: #10b981;
    text-align: right;
}

.history-item {
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-left: 3px solid #6366f1;
    border-radius: 8px;
    margin-bottom: 12px;
}

.history-status {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.history-note {
    font-size: 14px;
    color: #94a3b8;
    margin-bottom: 8px;
}

.history-time {
    font-size: 12px;
    color: #64748b;
}

.status-badge {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.status-processing { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.status-shipping { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.status-completed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.status-cancelled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

/* Processing Status */
.processing-status-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.processing-status-card h2 {
    font-size: 20px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-checks {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.status-check-item {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.status-check-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.icon-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.icon-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-check-label {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.status-check-value {
    font-size: 13px;
    color: #94a3b8;
}

.performance-indicator {
    font-size: 14px;
    padding: 4px 8px;
    border-radius: 6px;
    margin-left: 8px;
    font-weight: 500;
}

.performance-excellent {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.performance-good {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.performance-slow {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}
</style>

<div class="order-detail-container">
    <div class="order-header">
        <h1>Đơn hàng #<?php echo $orderId; ?></h1>
        <div class="status-actions">
            <?php if ($order['status'] === ORDER_STATUS_PENDING): ?>
                <button class="btn-status btn-confirm" onclick="updateStatus('processing')">
                    ✓ Xác nhận đơn
                </button>
                <button class="btn-status btn-cancel" onclick="updateStatus('cancelled')">
                    ✗ Hủy đơn
                </button>
            <?php elseif ($order['status'] === ORDER_STATUS_PROCESSING): ?>
                <button class="btn-status btn-ship" onclick="updateStatus('shipping')">
                    🚚 Giao hàng
                </button>
                <button class="btn-status btn-cancel" onclick="updateStatus('cancelled')">
                    ✗ Hủy đơn
                </button>
            <?php elseif ($order['status'] === ORDER_STATUS_SHIPPING): ?>
                <button class="btn-status btn-complete" onclick="updateStatus('completed')">
                    ✓ Hoàn thành
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Processing Status Card -->
    <div class="processing-status-card">
        <h2>⚙️ Trạng thái xử lý đơn hàng <span id="performanceIndicator" class="performance-indicator"></span></h2>
        <div class="status-checks">
            <div class="status-check-item" data-status="order_saved">
                <div class="status-check-icon icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-check-label">Đã lưu đơn</div>
                <div class="status-check-value">
                    ✓ <?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?>
                </div>
            </div>

            <div class="status-check-item" data-status="email_sent">
                <div class="status-check-icon <?php echo $emailSent ? 'icon-success' : 'icon-pending'; ?>">
                    <i class="fas fa-<?php echo $emailSent ? 'check-circle' : 'clock'; ?>"></i>
                </div>
                <div class="status-check-label">Gửi email xác nhận</div>
                <div class="status-check-value">
                    <?php echo $emailSent ? '✓ Đã gửi' : '⏳ Chưa gửi'; ?>
                </div>
            </div>

            <div class="status-check-item" data-status="inventory_updated">
                <div class="status-check-icon <?php echo $inventoryUpdated ? 'icon-success' : 'icon-pending'; ?>">
                    <i class="fas fa-<?php echo $inventoryUpdated ? 'check-circle' : 'clock'; ?>"></i>
                </div>
                <div class="status-check-label">Cập nhật tồn kho</div>
                <div class="status-check-value">
                    <?php echo $inventoryUpdated ? '✓ Đã cập nhật' : '⏳ Chưa cập nhật'; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="order-grid">
        <div>
            <!-- Order Items -->
            <div class="order-card">
                <h2>📦 Sản phẩm</h2>
                <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <img src="../public/assets/images/<?php echo htmlspecialchars($item['thumbnail'] ?? 'default.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="item-image"
                         onerror="this.src='../public/assets/images/default.jpg'">
                    <div class="item-details">
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="item-info">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></div>
                        <div class="item-info">Số lượng: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="item-price">
                        <?php echo number_format($item['price'] * $item['quantity']); ?>₫
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="info-row" style="border-top: 2px solid rgba(16, 185, 129, 0.3); margin-top: 16px; padding-top: 16px;">
                    <span class="info-label" style="font-size: 18px; color: #fff;">Tổng tiền</span>
                    <span class="info-value" style="font-size: 24px; color: #10b981;"><?php echo number_format($order['total_price']); ?>₫</span>
                </div>
            </div>

            <!-- Status History -->
            <div class="order-card" style="margin-top: 24px;">
                <h2>📋 Lịch sử đơn hàng</h2>
                <?php foreach ($history as $h): ?>
                <div class="history-item">
                    <div class="history-status">
                        <span class="status-badge status-<?php echo $h['status']; ?>">
                            <?php 
                            $statusText = [
                                'pending' => 'Chờ xác nhận',
                                'processing' => 'Đang xử lý',
                                'shipping' => 'Đang giao',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Đã hủy'
                            ];
                            echo $statusText[$h['status']] ?? $h['status'];
                            ?>
                        </span>
                    </div>
                    <div class="history-note"><?php echo htmlspecialchars($h['note']); ?></div>
                    <div class="history-time"><?php echo date('d/m/Y H:i:s', strtotime($h['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <!-- Order Info -->
            <div class="order-card">
                <h2>ℹ️ Thông tin đơn hàng</h2>
                
                <div class="info-row">
                    <span class="info-label">Trạng thái</span>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
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
                
                <div class="info-row">
                    <span class="info-label">Thanh toán</span>
                    <span class="info-value"><?php echo strtoupper($order['payment_method']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Vận chuyển</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['shipping_method']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Phí ship</span>
                    <span class="info-value"><?php echo number_format($order['shipping_fee']); ?>₫</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Ngày đặt</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="order-card" style="margin-top: 24px;">
                <h2>👤 Thông tin khách hàng</h2>
                
                <div class="info-row">
                    <span class="info-label">Họ tên</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['fullname']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Số điện thoại</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                </div>
                
                <div class="info-row" style="border-bottom: none;">
                    <span class="info-label">Địa chỉ</span>
                    <span class="info-value" style="text-align: right; max-width: 60%;">
                        <?php echo htmlspecialchars($order['address_detail']); ?>, 
                        <?php echo htmlspecialchars($order['ward']); ?>, 
                        <?php echo htmlspecialchars($order['district']); ?>, 
                        <?php echo htmlspecialchars($order['province']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="status-modal">
    <div class="status-modal-content">
        <div class="status-modal-header">
            <div class="status-icon">⚠️</div>
            <h3>E-Commerce System cho biết</h3>
            <button class="close-modal" onclick="closeStatusModal()">&times;</button>
        </div>
        <div class="status-modal-body">
            <p>Bạn có chắc chắn muốn chuyển trạng thái đơn hàng <span id="statusOrderId">#<?php echo $orderId; ?></span> sang <span id="statusNewText" style="color: #6366f1; font-weight: 600;"></span>?</p>
        </div>
        <div class="status-modal-footer">
            <button class="btn-modal-cancel" onclick="closeStatusModal()">Hủy</button>
            <button class="btn-modal-confirm" onclick="confirmStatusUpdate()">OK</button>
        </div>
    </div>
</div>

<style>
.status-modal {
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

.status-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
    overflow: hidden;
}

.status-modal-header {
    padding: 24px 24px 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.status-icon {
    font-size: 24px;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.status-modal-header h3 {
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

.status-modal-body {
    padding: 0 24px 24px 24px;
}

.status-modal-body p {
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.status-modal-footer {
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

.btn-modal-confirm.btn-confirm-action {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-modal-confirm.btn-confirm-action:hover {
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-modal-confirm.btn-cancel-action {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-modal-confirm.btn-cancel-action:hover {
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
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
    .status-modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .status-modal-footer {
        flex-direction: column;
    }
    
    .btn-modal-cancel,
    .btn-modal-confirm {
        width: 100%;
    }
}
</style>

<script>
let currentNewStatus = null;

function updateStatus(newStatus) {
    const statusNames = {
        'pending': 'Chờ xác nhận',
        'processing': 'Đang xử lý',
        'shipping': 'Đang giao',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy'
    };
    
    currentNewStatus = newStatus;
    document.getElementById('statusNewText').textContent = statusNames[newStatus];
    
    // Update confirm button style based on action
    const confirmBtn = document.querySelector('.btn-modal-confirm');
    confirmBtn.className = 'btn-modal-confirm';
    
    if (newStatus === 'cancelled') {
        confirmBtn.classList.add('btn-cancel-action');
    } else if (newStatus === 'completed') {
        confirmBtn.classList.add('btn-confirm-action');
    }
    
    document.getElementById('statusModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentNewStatus = null;
}

function confirmStatusUpdate() {
    if (!currentNewStatus) return;
    
    const confirmBtn = document.querySelector('.btn-modal-confirm');
    const originalText = confirmBtn.textContent;
    confirmBtn.textContent = 'Đang xử lý...';
    confirmBtn.disabled = true;
    
    fetch('api/update_order_status_fast.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'order_id=<?php echo $orderId; ?>&status=' + currentNewStatus
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            confirmBtn.textContent = '✓ Thành công';
            confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            setTimeout(() => {
                closeStatusModal();
                location.reload();
            }, 1000);
        } else {
            confirmBtn.textContent = '✗ Lỗi';
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            
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
        
        setTimeout(() => {
            confirmBtn.textContent = originalText;
            confirmBtn.style.background = 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
            confirmBtn.disabled = false;
        }, 2000);
    });
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target === modal) {
        closeStatusModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeStatusModal();
    }
});





// Optimistic UI update - update status immediately without waiting for reload
function updateUIStatus(newStatus) {
    const statusNames = {
        'pending': 'Chờ xác nhận',
        'processing': 'Đang xử lý',
        'shipping': 'Đang giao',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy'
    };
    
    // Update status badges
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.className = `status-badge status-${newStatus}`;
        badge.textContent = statusNames[newStatus];
    });
    
    // Update action buttons
    const statusActions = document.querySelector('.status-actions');
    if (statusActions) {
        let newButtons = '';
        
        if (newStatus === 'pending') {
            newButtons = `
                <button class="btn-status btn-confirm" onclick="updateStatus('processing')">✓ Xác nhận đơn</button>
                <button class="btn-status btn-cancel" onclick="updateStatus('cancelled')">✗ Hủy đơn</button>
            `;
        } else if (newStatus === 'processing') {
            newButtons = `
                <button class="btn-status btn-ship" onclick="updateStatus('shipping')">🚚 Giao hàng</button>
                <button class="btn-status btn-cancel" onclick="updateStatus('cancelled')">✗ Hủy đơn</button>
            `;
        } else if (newStatus === 'shipping') {
            newButtons = `
                <button class="btn-status btn-complete" onclick="updateStatus('completed')">✓ Hoàn thành</button>
            `;
        }
        
        statusActions.innerHTML = newButtons;
    }
    
    // Add success animation to the page
    document.body.style.background = 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05))';
    setTimeout(() => {
        document.body.style.background = '';
    }, 2000);
}

function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${type === 'success' ? '✓' : (type === 'error' ? '✗' : 'ℹ️')}</span>
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
        
        .notification-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(29, 78, 216, 0.9));
            border: 1px solid rgba(59, 130, 246, 0.3);
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

// Auto-refresh processing status with enhanced error handling
function refreshProcessingStatus() {
    const startTime = performance.now();
    
    fetch(`api/get_processing_status.php?order_id=<?php echo $orderId; ?>`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Get response text first to check if it's JSON
            return response.text();
        })
        .then(text => {
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                // If not JSON, it might be an HTML error page
                throw new Error('Server returned non-JSON response');
            }
            
            if (data.success) {
                updateProcessingCards(data.status);
                
                // Show performance info and update indicator
                const clientTime = Math.round(performance.now() - startTime);
                const serverTime = data.processing_time || 'N/A';
                
                // Update performance indicator
                updatePerformanceIndicator(clientTime);
            } else {
                // If unauthorized, stop auto-refresh
                if (data.message && data.message.includes('Unauthorized')) {
                    clearInterval(window.statusRefreshInterval);
                }
            }
        })
        .catch(error => {
            // If it's a persistent error, reduce refresh frequency
            if (error.message.includes('non-JSON') || error.message.includes('HTTP 5')) {
                clearInterval(window.statusRefreshInterval);
                // Retry with longer interval
                window.statusRefreshInterval = setInterval(refreshProcessingStatus, 30000); // 30 seconds
            }
        });
}

function updateProcessingCards(status) {
    Object.keys(status).forEach(key => {
        const card = document.querySelector(`[data-status="${key}"]`);
        if (card) {
            const icon = card.querySelector('.status-check-icon');
            const value = card.querySelector('.status-check-value');
            
            // Add smooth transition animation
            card.style.transition = 'all 0.3s ease';
            
            if (status[key].completed) {
                icon.className = 'status-check-icon icon-success';
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                
                // Add success animation
                card.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    card.style.transform = 'scale(1)';
                }, 200);
            } else {
                icon.className = 'status-check-icon icon-pending';
                icon.innerHTML = '<i class="fas fa-clock"></i>';
            }
            
            if (value) {
                value.textContent = status[key].value;
            }
        }
    });
}

// Update performance indicator
function updatePerformanceIndicator(responseTime) {
    const indicator = document.getElementById('performanceIndicator');
    if (!indicator) return;
    
    let className = 'performance-indicator ';
    let text = '';
    
    if (responseTime < 100) {
        className += 'performance-excellent';
        text = '⚡ Cực nhanh';
    } else if (responseTime < 300) {
        className += 'performance-good';
        text = '✓ Nhanh';
    } else {
        className += 'performance-slow';
        text = '⏳ Chậm';
    }
    
    indicator.className = className;
    indicator.textContent = text;
}

// Auto-refresh every 5 seconds for active orders
<?php if (in_array($order['status'], ['pending', 'processing', 'shipping'])): ?>
window.statusRefreshInterval = setInterval(refreshProcessingStatus, 5000);
// Initial performance check
setTimeout(refreshProcessingStatus, 1000);

// Stop auto-refresh when order is completed or cancelled
window.addEventListener('beforeunload', function() {
    if (window.statusRefreshInterval) {
        clearInterval(window.statusRefreshInterval);
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
