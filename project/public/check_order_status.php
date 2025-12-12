<?php
/**
 * Demo: Kiểm tra trạng thái xử lý đơn hàng
 */
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

$orderId = $_GET['order_id'] ?? null;
$statusData = null;

if ($orderId) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/api/order_processing_status.php?order_id=" . $orderId;
    $response = @file_get_contents($url);
    if ($response) {
        $statusData = json_decode($response, true);
    }
}

include 'includes/header.php';
?>

<style>
.status-checker {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
}

.search-box {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    padding: 30px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 30px;
}

.search-box h1 {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 12px;
}

.search-form input {
    flex: 1;
    padding: 14px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #fff;
    font-size: 16px;
}

.search-form button {
    padding: 14px 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.status-result {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    padding: 30px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.status-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.badge-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: rgba(255, 255, 255, 0.03);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.status-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.status-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.icon-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.icon-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-card h3 {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
}

.status-card p {
    font-size: 14px;
    color: #94a3b8;
}

.timeline {
    margin-top: 30px;
}

.timeline h3 {
    font-size: 20px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 20px;
}

.timeline-item {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border-left: 3px solid #6366f1;
}

.timeline-time {
    font-size: 12px;
    color: #64748b;
    min-width: 140px;
}

.timeline-content {
    flex: 1;
}

.timeline-status {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}

.timeline-note {
    font-size: 14px;
    color: #94a3b8;
}

.error-message {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}
</style>

<div class="status-checker">
    <div class="search-box">
        <h1>🔍 Kiểm tra trạng thái xử lý đơn hàng</h1>
        <form class="search-form" method="GET">
            <input type="number" name="order_id" placeholder="Nhập mã đơn hàng (VD: 1, 2, 3...)" 
                   value="<?php echo htmlspecialchars($orderId ?? ''); ?>" required>
            <button type="submit">Kiểm tra</button>
        </form>
    </div>

    <?php if ($statusData): ?>
        <?php if ($statusData['success']): ?>
            <div class="status-result">
                <div class="status-header">
                    <h2>Đơn hàng #<?php echo $statusData['order_id']; ?></h2>
                    <span class="status-badge <?php echo $statusData['summary']['all_tasks_done'] ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $statusData['summary']['all_tasks_done'] ? '✓ Đã xử lý xong' : '⏳ Đang xử lý'; ?>
                    </span>
                </div>

                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon icon-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3>Đã lưu đơn</h3>
                        </div>
                        <p><?php echo $statusData['processing_status']['order_saved'] ? '✓ Đã lưu' : '✗ Chưa lưu'; ?></p>
                        <p style="font-size: 12px; margin-top: 8px;">
                            <?php echo date('d/m/Y H:i:s', strtotime($statusData['processing_status']['order_saved_at'])); ?>
                        </p>
                    </div>

                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon <?php echo $statusData['processing_status']['email_sent'] ? 'icon-success' : 'icon-pending'; ?>">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h3>Gửi email</h3>
                        </div>
                        <p><?php echo $statusData['processing_status']['email_sent'] ? '✓ Đã gửi' : '⏳ Chưa gửi'; ?></p>
                        <p style="font-size: 12px; margin-top: 8px;">
                            <?php echo $statusData['processing_status']['email_sent_at']; ?>
                        </p>
                    </div>

                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon <?php echo $statusData['processing_status']['inventory_updated'] ? 'icon-success' : 'icon-pending'; ?>">
                                <i class="fas fa-box"></i>
                            </div>
                            <h3>Cập nhật kho</h3>
                        </div>
                        <p><?php echo $statusData['processing_status']['inventory_updated'] ? '✓ Đã cập nhật' : '⏳ Chưa cập nhật'; ?></p>
                        <p style="font-size: 12px; margin-top: 8px;">
                            <?php echo $statusData['processing_status']['inventory_updated_at']; ?>
                        </p>
                    </div>
                </div>

                <div class="timeline">
                    <h3>📋 Lịch sử xử lý</h3>
                    <?php foreach ($statusData['processing_steps'] as $step): ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo date('d/m/Y H:i:s', strtotime($step['created_at'])); ?>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-status"><?php echo htmlspecialchars($step['status']); ?></div>
                            <div class="timeline-note"><?php echo htmlspecialchars($step['note']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 30px; padding: 20px; background: rgba(99, 102, 241, 0.1); border-radius: 12px;">
                    <strong style="color: #6366f1;">Tổng kết:</strong>
                    <ul style="margin-top: 12px; color: #94a3b8; line-height: 1.8;">
                        <li>Tổng số bước: <?php echo $statusData['summary']['total_steps']; ?></li>
                        <li>Thời gian xử lý: <?php echo $statusData['processing_status']['processing_time_seconds']; ?> giây</li>
                        <li>Trạng thái hiện tại: <?php echo $statusData['processing_status']['current_status']; ?></li>
                        <li>Hoàn thành: <?php echo $statusData['summary']['completed'] ? 'Có' : 'Chưa'; ?></li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px;"></i>
                <p><?php echo htmlspecialchars($statusData['message']); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
