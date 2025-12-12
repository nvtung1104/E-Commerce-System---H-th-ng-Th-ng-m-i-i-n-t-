<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Get statistics
$stats = [];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch()['total'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(total_price) as revenue FROM orders WHERE status = 'completed'");
$stats['total_revenue'] = $stmt->fetch()['revenue'] ?? 0;

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $stmt->fetch()['total'];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch()['total'];

// Recent orders
$stmt = $pdo->query("SELECT o.*, u.name as customer_name 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     ORDER BY o.created_at DESC 
                     LIMIT 5");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Orders by status
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$orders_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="dashboard-container">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tổng đơn hàng</div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +12% so với tháng trước
                </div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Doanh thu</div>
                <div class="stat-value"><?php echo number_format($stats['total_revenue']); ?> ₫</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +18% so với tháng trước
                </div>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Sản phẩm</div>
                <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +5 sản phẩm mới
                </div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Khách hàng</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +8% so với tháng trước
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Recent Orders -->
    <div class="dashboard-grid">
        <!-- Orders Chart -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Đơn hàng theo trạng thái</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <?php foreach ($orders_by_status as $status): ?>
                        <div class="chart-item">
                            <div class="chart-bar">
                                <div class="chart-fill status-<?php echo $status['status']; ?>" 
                                     style="width: <?php echo ($status['count'] / $stats['total_orders']) * 100; ?>%">
                                </div>
                            </div>
                            <div class="chart-label">
                                <span><?php echo ucfirst($status['status']); ?></span>
                                <strong><?php echo $status['count']; ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Đơn hàng gần đây</h3>
                <a href="orders.php" class="btn-view-all">Xem tất cả <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="orders-list">
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <div class="order-id">#<?php echo $order['id']; ?></div>
                                <div class="order-customer"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            </div>
                            <div class="order-details">
                                <div class="order-price"><?php echo number_format($order['total_price']); ?> ₫</div>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    padding: 30px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.stat-primary { color: #6366f1; }
.stat-success { color: #22c55e; }
.stat-info { color: #3b82f6; }
.stat-warning { color: #f59e0b; }

.stat-icon-wrapper {
    position: relative;
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: rgba(255, 255, 255, 0.05);
}

.stat-primary .stat-icon {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.stat-success .stat-icon {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.stat-info .stat-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.stat-warning .stat-icon {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 14px;
    color: #94a3b8;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.stat-trend {
    font-size: 12px;
    color: #22c55e;
    display: flex;
    align-items: center;
    gap: 4px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
}

.dashboard-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
}

.card-header {
    padding: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: #6366f1;
}

.btn-view-all {
    color: #6366f1;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-view-all:hover {
    gap: 10px;
}

.card-body {
    padding: 24px;
}

.chart-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.chart-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chart-bar {
    height: 40px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.chart-fill {
    height: 100%;
    border-radius: 8px;
    transition: width 1s ease;
}

.chart-fill.status-pending {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.chart-fill.status-processing {
    background: linear-gradient(90deg, #3b82f6, #2563eb);
}

.chart-fill.status-shipping {
    background: linear-gradient(90deg, #8b5cf6, #7c3aed);
}

.chart-fill.status-completed {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}

.chart-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #94a3b8;
}

.chart-label strong {
    color: #fff;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.order-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.3);
}

.order-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-id {
    font-weight: 700;
    color: #6366f1;
    font-size: 14px;
}

.order-customer {
    font-size: 13px;
    color: #94a3b8;
}

.order-details {
    display: flex;
    align-items: center;
    gap: 12px;
}

.order-price {
    font-weight: 600;
    color: #fff;
    font-size: 14px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.status-badge.status-processing {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.status-badge.status-shipping {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.status-badge.status-completed {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 20px;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
