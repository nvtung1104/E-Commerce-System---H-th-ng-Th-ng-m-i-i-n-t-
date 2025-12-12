<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Get all recent orders with processing status
$stmt = $pdo->query("SELECT o.id, o.status, o.created_at, o.total_price,
                     u.name as fullname, u.email
                     FROM orders o
                     LEFT JOIN users u ON o.user_id = u.id
                     ORDER BY o.created_at DESC
                     LIMIT 50");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check processing status for each order
foreach ($orders as &$order) {
    // Check email sent
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_status_history 
                           WHERE order_id = ? AND note LIKE '%email%'");
    $stmt->execute([$order['id']]);
    $order['email_sent'] = $stmt->fetchColumn() > 0;
    
    // Check inventory updated
    $order['inventory_updated'] = ($order['status'] !== 'pending');
    
    // Calculate processing time
    $stmt = $pdo->prepare("SELECT MIN(created_at) as first_time, MAX(created_at) as last_time 
                           FROM order_status_history WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $times = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($times['first_time'] && $times['last_time']) {
        $order['processing_time'] = strtotime($times['last_time']) - strtotime($times['first_time']);
    } else {
        $order['processing_time'] = 0;
    }
}

include 'includes/header.php';
?>

<style>
.processing-dashboard {
    padding: 30px;
    background: #0a0e27;
    min-height: 100vh;
}

.dashboard-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.dashboard-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.dashboard-header p {
    color: #94a3b8;
    font-size: 14px;
}

.orders-table-wrapper {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
}

.table-header {
    padding: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.table-header h2 {
    font-size: 20px;
    font-weight: 600;
    color: #fff;
}

.processing-table {
    width: 100%;
    border-collapse: collapse;
}

.processing-table thead {
    background: rgba(255, 255, 255, 0.03);
}

.processing-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.processing-table td {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 14px;
    color: #cbd5e1;
}

.processing-table tbody tr {
    transition: all 0.2s ease;
}

.processing-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.check-icon {
    font-size: 18px;
}

.check-success {
    color: #10b981;
}

.check-pending {
    color: #f59e0b;
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

.btn-view {
    padding: 8px 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}
</style>

<div class="processing-dashboard">
    <div class="dashboard-header">
        <h1>⚙️ Trạng thái xử lý đơn hàng</h1>
        <p>Theo dõi chi tiết quá trình xử lý từng đơn hàng</p>
    </div>

    <div class="orders-table-wrapper">
        <div class="table-header">
            <h2>📊 Bảng theo dõi xử lý</h2>
        </div>

        <table class="processing-table">
            <thead>
                <tr>
                    <th>Mã ĐH</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Đã lưu</th>
                    <th>Email</th>
                    <th>Tồn kho</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong style="color: #6366f1;">#<?php echo $order['id']; ?></strong></td>
                    <td>
                        <div><?php echo htmlspecialchars($order['fullname']); ?></div>
                        <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($order['email']); ?></div>
                    </td>
                    <td><strong style="color: #fff;"><?php echo number_format($order['total_price']); ?>₫</strong></td>
                    <td>
                        <i class="fas fa-check-circle check-icon check-success"></i>
                    </td>
                    <td>
                        <i class="fas fa-<?php echo $order['email_sent'] ? 'check-circle check-success' : 'clock check-pending'; ?> check-icon"></i>
                    </td>
                    <td>
                        <i class="fas fa-<?php echo $order['inventory_updated'] ? 'check-circle check-success' : 'clock check-pending'; ?> check-icon"></i>
                    </td>
                    <td style="color: #94a3b8;">
                        <?php echo $order['processing_time']; ?>s
                    </td>
                    <td>
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
                    </td>
                    <td>
                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i> Xem
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
