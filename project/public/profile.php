<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$order_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

include 'includes/header.php';
?>

<style>
.profile-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.profile-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 20px;
    padding: 3rem;
    margin-bottom: 2rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 2rem;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    border: 4px solid rgba(255, 255, 255, 0.3);
}

.profile-header-info h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.profile-header-info p {
    opacity: 0.9;
    font-size: 1.1rem;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.profile-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 2rem;
}

.profile-section h2 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.info-value {
    color: var(--text-primary);
    font-weight: 600;
}

.address-item {
    background: var(--bg-dark);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.address-item:hover {
    border-color: var(--primary);
}

.address-item.default {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.address-header strong {
    color: var(--text-primary);
}

.badge-default {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.address-body {
    color: var(--text-secondary);
    line-height: 1.6;
}

.btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 968px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            👤
        </div>
        <div class="profile-header-info">
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <p>📧 <?php echo htmlspecialchars($user['email']); ?></p>
            <p>📱 <?php echo htmlspecialchars($user['phone'] ?? 'Chưa cập nhật'); ?></p>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="profile-stats">
        <div class="stat-card">
            <div class="stat-icon">🛍️</div>
            <div class="stat-value"><?php echo $order_count; ?></div>
            <div class="stat-label">Đơn hàng</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📍</div>
            <div class="stat-value"><?php echo count($addresses); ?></div>
            <div class="stat-label">Địa chỉ</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
            <div class="stat-label">Ngày tham gia</div>
        </div>
    </div>
    
    <!-- Profile Grid -->
    <div class="profile-grid">
        <!-- Personal Info -->
        <div class="profile-section">
            <h2>👤 Thông tin cá nhân</h2>
            
            <div class="info-row">
                <span class="info-label">Họ và tên</span>
                <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Số điện thoại</span>
                <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
            </div>
            
            <?php if (!empty($user['date_of_birth'])): ?>
            <div class="info-row">
                <span class="info-label">Ngày sinh</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($user['date_of_birth'])); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($user['id_card'])): ?>
            <div class="info-row">
                <span class="info-label">CMND/CCCD</span>
                <span class="info-value"><?php echo htmlspecialchars($user['id_card']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($user['address'])): ?>
            <div class="info-row">
                <span class="info-label">Địa chỉ</span>
                <span class="info-value"><?php echo htmlspecialchars($user['address']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <a href="edit_profile.php" class="btn btn-primary">✏️ Chỉnh sửa thông tin</a>
            </div>
        </div>
        
        <!-- Addresses -->
        <div class="profile-section">
            <h2>📍 Địa chỉ giao hàng</h2>
            
            <?php if (empty($addresses)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>Chưa có địa chỉ nào</p>
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $addr): ?>
                <div class="address-item <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                    <div class="address-header">
                        <div>
                            <strong><?php echo htmlspecialchars($addr['fullname']); ?></strong>
                            <?php if ($addr['is_default']): ?>
                                <span class="badge-default">Mặc định</span>
                            <?php endif; ?>
                        </div>
                        <span>📞 <?php echo htmlspecialchars($addr['phone']); ?></span>
                    </div>
                    <div class="address-body">
                        <?php echo htmlspecialchars($addr['address_detail']); ?>,
                        <?php echo htmlspecialchars($addr['ward']); ?>,
                        <?php echo htmlspecialchars($addr['district']); ?>,
                        <?php echo htmlspecialchars($addr['province']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="btn-group">
                <a href="addresses.php" class="btn btn-primary">📍 Quản lý địa chỉ</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
