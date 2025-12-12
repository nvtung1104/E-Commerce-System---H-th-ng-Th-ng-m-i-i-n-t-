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

// Get completed orders
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email, sm.name as shipping_method
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.id
          WHERE o.status = 'completed'
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
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #10b981, #059669);
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
    color: #10b981;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #94a3b8;
}

.orders-table-container {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    font-weight: 600;
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid rgba(16, 185, 129, 0.2);
    font-size: 14px;
}

.orders-table td {
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: #e2e8f0;
    font-size: 14px;
}

.orders-table tr:hover {
    background: rgba(16, 185, 129, 0.05);
}

.order-id {
    font-weight: 600;
    color: #10b981;
}

.customer-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.customer-name {
    font-weight: 500;
    color: #fff;
}

.customer-email {
    font-size: 12px;
    color: #94a3b8;
}

.order-total {
    font-weight: 600;
    color: #10b981;
}

.order-date {
    color: #94a3b8;
    font-size: 13px;
}

.completed-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #10b981;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 12px;
    color: #fff;
}

.empty-state p {
    font-size: 16px;
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
        <h1>Đơn hàng hoàn thành</h1>
        <p>Quản lý các đơn hàng đã giao thành công</p>
    </div>
</div>

<?php
// Calculate stats
$totalCompleted = count($orders);
$totalRevenue = array_sum(array_column($orders, 'total_price'));
$avgOrderValue = $totalCompleted > 0 ? $totalRevenue / $totalCompleted : 0;
?>

<div class="orders-stats">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalCompleted); ?></div>
        <div class="stat-label">Đơn hàng hoàn thành</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalRevenue); ?>₫</div>
        <div class="stat-label">Tổng doanh thu</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value"><?php echo number_format($avgOrderValue); ?>₫</div>
        <div class="stat-label">Giá trị trung bình</div>
    </div>
</div>

<div class="orders-table-container">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>Chưa có đơn hàng hoàn thành</h3>
            <p>Các đơn hàng đã hoàn thành sẽ xuất hiện tại đây</p>
        </div>
    <?php else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Mã đơn hàng</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Ngày hoàn thành</th>
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
                        </div>
                    </td>
                    <td>
                        <span class="order-total"><?php echo number_format($order['total_price']); ?>₫</span>
                    </td>
                    <td>
                        <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></span>
                    </td>
                    <td>
                        <span class="completed-badge">
                            <i class="fas fa-check"></i>
                            Hoàn thành
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