<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Get cancelled orders
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email, sm.name as shipping_method
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
          WHERE o.status = 'cancelled'
          ORDER BY o.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
.admin-content {
    padding: 30px;
}

.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.orders-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 24px;
    color: white;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #ef4444;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #94a3b8;
}

.orders-table-container {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(10px);
    opacity: 0.9;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    font-weight: 600;
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid rgba(239, 68, 68, 0.2);
    font-size: 14px;
}

.orders-table td {
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    font-size: 14px;
}

.orders-table tr:hover {
    background: rgba(239, 68, 68, 0.05);
}

.order-id {
    font-weight: 600;
    color: #ef4444;
    text-decoration: line-through;
}

.customer-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.customer-name {
    font-weight: 500;
    color: #94a3b8;
}

.customer-email {
    font-size: 12px;
    color: #64748b;
}

.order-total {
    font-weight: 600;
    color: #64748b;
    text-decoration: line-through;
}

.order-date {
    color: #64748b;
    font-size: 13px;
}

.cancelled-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.order-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-view {
    background: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-view:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #ef4444;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 12px;
    color: #fff;
}

.empty-state p {
    font-size: 16px;
}

.cancellation-reason {
    font-size: 12px;
    color: #64748b;
    font-style: italic;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .orders-table-container {
        overflow-x: auto;
    }
    
    .orders-table {
        min-width: 800px;
    }
}
</style>

<div class="orders-header">
    <div>
        <h1>Đơn hàng đã hủy</h1>
        <p>Quản lý các đơn hàng đã bị hủy bỏ</p>
    </div>
</div>

<?php
// Calculate stats
$totalCancelled = count($orders);
$totalLostRevenue = array_sum(array_column($orders, 'total_price'));
$avgLostValue = $totalCancelled > 0 ? $totalLostRevenue / $totalCancelled : 0;
?>

<div class="orders-stats">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalCancelled); ?></div>
        <div class="stat-label">Đơn hàng đã hủy</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalLostRevenue); ?>₫</div>
        <div class="stat-label">Doanh thu bị mất</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value"><?php echo number_format($avgLostValue); ?>₫</div>
        <div class="stat-label">Giá trị trung bình</div>
    </div>
</div>

<div class="orders-table-container">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-times-circle"></i>
            <h3>Không có đơn hàng bị hủy</h3>
            <p>Tuyệt vời! Chưa có đơn hàng nào bị hủy</p>
        </div>
    <?php else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Mã đơn hàng</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Ngày hủy</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <span class="order-id">#<?php echo $order['id']; ?></span>
                    </td>
                    <td>
                        <div class="customer-info">
                            <span class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            <span class="customer-email"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                            <div class="cancellation-reason">Lý do: Khách hàng hủy đơn</div>
                        </div>
                    </td>
                    <td>
                        <span class="order-total"><?php echo number_format($order['total_price']); ?>₫</span>
                    </td>
                    <td>
                        <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></span>
                    </td>
                    <td>
                        <span class="cancelled-badge">
                            <i class="fas fa-times"></i>
                            Đã hủy
                        </span>
                    </td>
                    <td>
                        <div class="order-actions">
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i>
                                Xem chi tiết
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Auto refresh every 30 seconds
setTimeout(() => {
    location.reload();
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>