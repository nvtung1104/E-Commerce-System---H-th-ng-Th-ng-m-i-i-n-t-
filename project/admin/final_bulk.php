<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';

// Enhanced authentication check with better error handling
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If it's an AJAX request, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    // Otherwise redirect to login
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_POST && isset($_POST['complete_orders'])) {
    $orderIds = $_POST['order_ids'] ?? [];
    
    if (!empty($orderIds)) {
        try {
            $pdo = getDBConnection();
            
            // Validate all order IDs are numeric
            $validOrderIds = array_filter($orderIds, function($id) {
                return is_numeric($id) && intval($id) > 0;
            });
            
            if (empty($validOrderIds)) {
                throw new Exception("Invalid order IDs provided");
            }
            
            $pdo->beginTransaction();
            
            $updated = 0;
            $errors = [];
            
            foreach ($validOrderIds as $orderId) {
                $orderId = intval($orderId);
                
                // First check if order exists and is in shipping status
                $checkStmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ?");
                $checkStmt->execute([$orderId]);
                $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    $errors[] = "Đơn hàng #$orderId không tồn tại";
                    continue;
                }
                
                if ($order['status'] !== 'shipping') {
                    $errors[] = "Đơn hàng #$orderId không ở trạng thái đang giao (hiện tại: {$order['status']})";
                    continue;
                }
                
                // Update the order
                $stmt = $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ? AND status = 'shipping'");
                if ($stmt->execute([$orderId])) {
                    if ($stmt->rowCount() > 0) {
                        $updated++;
                    }
                } else {
                    $errors[] = "Không thể cập nhật đơn hàng #$orderId";
                }
            }
            
            if (empty($errors)) {
                $pdo->commit();
                $message = "🎉 Đã hoàn thành $updated đơn hàng thành công!";
                $messageType = 'success';
            } else {
                $pdo->rollBack();
                $message = "❌ Có lỗi xảy ra: " . implode(', ', $errors);
                $messageType = 'error';
            }
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "❌ Lỗi hệ thống: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "⚠️ Vui lòng chọn ít nhất một đơn hàng";
        $messageType = 'warning';
    }
}

// Get shipping orders
$orders = [];
$stats = [];

try {
    $pdo = getDBConnection();
    
    // Get stats
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $statsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statsData as $stat) {
        $stats[$stat['status']] = $stat['count'];
    }
    
    // Get shipping orders with user names
    $stmt = $pdo->query("
        SELECT o.id, o.user_id, o.total_price, o.created_at, u.name as customer_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'shipping' 
        ORDER BY o.created_at DESC 
        LIMIT 50
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no shipping orders, create some test data
    if (empty($orders)) {
        // Get or create a user
        $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['Test Customer', 'customer@test.com', password_hash('123456', PASSWORD_DEFAULT), 'user']);
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $user['id'];
        }
        
        // Create test shipping orders
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_price, status, created_at) VALUES (?, ?, 'shipping', NOW() - INTERVAL ? HOUR)");
            $stmt->execute([$userId, rand(100000, 500000), rand(1, 48)]);
        }
        
        // Reload orders
        $stmt = $pdo->query("
            SELECT o.id, o.user_id, o.total_price, o.created_at, u.name as customer_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.status = 'shipping' 
            ORDER BY o.created_at DESC 
            LIMIT 50
        ");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $message = "❌ Lỗi kết nối database: " . $e->getMessage();
    $messageType = 'error';
}

include 'includes/header.php';
?>

<style>
.bulk-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.bulk-header {
    text-align: center;
    margin-bottom: 30px;
}

.bulk-header h1 {
    font-size: 36px;
    color: #fff;
    margin-bottom: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #fff;
}

.stat-label {
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
    margin-top: 5px;
}

.message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 600;
}

.message.success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.message.error {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.message.warning {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.form-container {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.controls {
    text-align: center;
    margin-bottom: 20px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    margin: 0 5px;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.orders-table th,
.orders-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.orders-table th {
    background: rgba(255, 255, 255, 0.05);
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    font-size: 12px;
}

.orders-table tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

.order-id {
    color: #6366f1;
    font-weight: bold;
}

.order-total {
    color: #10b981;
    font-weight: bold;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state .icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
}

.back-link:hover {
    color: #1d4ed8;
}
</style>

<div class="bulk-container">
    <div class="bulk-header">
        <h1>🚚 Hoàn thành đơn giao hàng</h1>
        <p>Xử lý nhanh các đơn hàng đang được giao</p>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Chờ xử lý</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['processing'] ?? 0; ?></div>
            <div class="stat-label">Đang xử lý</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['shipping'] ?? 0; ?></div>
            <div class="stat-label">Đang giao</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
            <div class="stat-label">Hoàn thành</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
            <div class="stat-label">Đã hủy</div>
        </div>
    </div>
    
    <!-- Message -->
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Form -->
    <div class="form-container">
        <form method="POST" id="bulkForm">
            <div class="controls">
                <button type="submit" name="complete_orders" class="btn btn-success" onclick="return confirmCompletion()">
                    ✅ Hoàn thành đã chọn
                </button>
                <button type="button" class="btn btn-secondary" onclick="toggleAll()">
                    ☑️ Chọn tất cả
                </button>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <h3>Không có đơn hàng đang giao</h3>
                    <p>Tất cả đơn hàng đã được xử lý hoặc chưa đến giai đoạn giao hàng</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="checkbox" onchange="toggleAll()">
                            </th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="checkbox order-checkbox">
                                </td>
                                <td>
                                    <span class="order-id">#<?php echo $order['id']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name'] ?? 'Khách hàng #' . $order['user_id']); ?>
                                </td>
                                <td>
                                    <span class="order-total"><?php echo number_format($order['total_price']); ?>₫</span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </form>
    </div>
    
    <a href="orders.php" class="back-link">← Quay lại danh sách đơn hàng</a>
</div>

<script>
// Clear any existing modals or overlays on page load
document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing modals
    const existingModals = document.querySelectorAll('.status-modal, .modal, .overlay');
    existingModals.forEach(modal => modal.remove());
    
    // Reset body overflow
    document.body.style.overflow = 'auto';
    
    // Clear any error notifications
    const errorNotifications = document.querySelectorAll('.notification, .alert, .toast');
    errorNotifications.forEach(notification => notification.remove());
});

function toggleAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function confirmCompletion() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    if (checkboxes.length === 0) {
        showCustomAlert('⚠️ Vui lòng chọn ít nhất một đơn hàng', 'warning');
        return false;
    }
    
    // Use custom confirm instead of browser confirm
    return showCustomConfirm(`✅ Xác nhận hoàn thành ${checkboxes.length} đơn hàng đã giao thành công?`);
}

function showCustomAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alert = document.createElement('div');
    alert.className = 'custom-alert';
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'warning' ? '#f59e0b' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
    `;
    alert.textContent = message;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

function showCustomConfirm(message) {
    return new Promise((resolve) => {
        // Remove existing confirms
        const existingConfirms = document.querySelectorAll('.custom-confirm');
        existingConfirms.forEach(confirm => confirm.remove());
        
        const overlay = document.createElement('div');
        overlay.className = 'custom-confirm';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10001;
        `;
        
        overlay.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #1a1f3a, #0a0e27);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                padding: 24px;
                max-width: 400px;
                text-align: center;
                color: white;
            ">
                <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                <h3 style="margin-bottom: 16px; color: #fff;">Xác nhận hoàn thành</h3>
                <p style="margin-bottom: 24px; color: #94a3b8;">${message}</p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button onclick="resolveConfirm(false)" style="
                        padding: 10px 20px;
                        border: none;
                        border-radius: 6px;
                        background: rgba(255, 255, 255, 0.1);
                        color: #94a3b8;
                        cursor: pointer;
                        font-weight: 600;
                    ">Hủy</button>
                    <button onclick="resolveConfirm(true)" style="
                        padding: 10px 20px;
                        border: none;
                        border-radius: 6px;
                        background: #10b981;
                        color: white;
                        cursor: pointer;
                        font-weight: 600;
                    ">Xác nhận</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        window.resolveConfirm = function(result) {
            overlay.remove();
            delete window.resolveConfirm;
            resolve(result);
        };
    });
}

// Override the form submission to use custom confirm
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bulkForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkboxes.length === 0) {
                showCustomAlert('⚠️ Vui lòng chọn ít nhất một đơn hàng', 'warning');
                return;
            }
            
            const confirmed = await showCustomConfirm(`✅ Xác nhận hoàn thành ${checkboxes.length} đơn hàng đã giao thành công?`);
            if (confirmed) {
                // Show loading
                showCustomAlert('🔄 Đang xử lý...', 'info');
                form.submit();
            }
        });
    }
});

// Add slide animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>