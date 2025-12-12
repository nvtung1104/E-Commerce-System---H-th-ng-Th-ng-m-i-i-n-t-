<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $id_card = trim($_POST['id_card'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, date_of_birth = ?, id_card = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $date_of_birth, $id_card, $address, $_SESSION['user_id']]);
        
        $_SESSION['user_name'] = $name;
        $success = "Cập nhật thông tin thành công!";
    } catch (Exception $e) {
        $error = "Có lỗi xảy ra: " . $e->getMessage();
    }
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="edit-profile-container">
    <h1>Chỉnh sửa thông tin cá nhân</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" class="profile-form">
        <div class="form-group">
            <label>Họ và tên *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Email (không thể thay đổi)</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Số điện thoại *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Ngày sinh</label>
                <input type="date" name="date_of_birth" value="<?php echo $user['date_of_birth']; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>CMND/CCCD</label>
            <input type="text" name="id_card" value="<?php echo htmlspecialchars($user['id_card'] ?? ''); ?>" maxlength="20">
        </div>
        
        <div class="form-group">
            <label>Địa chỉ</label>
            <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            <a href="profile.php" class="btn btn-secondary">Hủy</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
