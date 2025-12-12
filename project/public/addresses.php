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

// Handle delete address
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
    $success = "Đã xóa địa chỉ thành công!";
}

// Handle set default
if (isset($_GET['set_default'])) {
    // Remove default from all addresses
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Set new default
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['set_default'], $_SESSION['user_id']]);
    $success = "Đã đặt làm địa chỉ mặc định!";
}

// Handle add/edit address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $province = trim($_POST['province']);
    $district = trim($_POST['district']);
    $ward = trim($_POST['ward']);
    $address_detail = trim($_POST['address_detail']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (isset($_POST['address_id']) && !empty($_POST['address_id'])) {
        // Update existing address
        $stmt = $pdo->prepare("UPDATE user_addresses SET fullname = ?, phone = ?, province = ?, district = ?, ward = ?, address_detail = ?, is_default = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$fullname, $phone, $province, $district, $ward, $address_detail, $is_default, $_POST['address_id'], $_SESSION['user_id']]);
        $success = "Cập nhật địa chỉ thành công!";
    } else {
        // Add new address
        if ($is_default) {
            // Remove default from all addresses
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, fullname, phone, province, district, ward, address_detail, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $fullname, $phone, $province, $district, $ward, $address_detail, $is_default]);
        $success = "Thêm địa chỉ mới thành công!";
    }
}

// Get all addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get address for editing
$edit_address = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $_SESSION['user_id']]);
    $edit_address = $stmt->fetch(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="addresses-container">
    <h1>📍 Địa chỉ giao hàng</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="addresses-grid">
        <!-- Address Form -->
        <div class="address-form-card">
            <h2><?php echo $edit_address ? '✏️ Chỉnh sửa địa chỉ' : '➕ Thêm địa chỉ mới'; ?></h2>
            
            <form method="POST" class="address-form">
                <?php if ($edit_address): ?>
                    <input type="hidden" name="address_id" value="<?php echo $edit_address['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Họ và tên người nhận</label>
                        <input type="text" name="fullname" value="<?php echo $edit_address['fullname'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="tel" name="phone" value="<?php echo $edit_address['phone'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tỉnh/Thành phố</label>
                        <select name="province" id="province" required>
                            <option value="">-- Chọn Tỉnh/Thành phố --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quận/Huyện</label>
                        <select name="district" id="district" required disabled>
                            <option value="">-- Chọn Quận/Huyện --</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Phường/Xã</label>
                    <select name="ward" id="ward" required disabled>
                        <option value="">-- Chọn Phường/Xã --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Địa chỉ chi tiết</label>
                    <textarea name="address_detail" rows="3" placeholder="Số nhà, tên đường..." required><?php echo $edit_address['address_detail'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="is_default" <?php echo ($edit_address['is_default'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Đặt làm địa chỉ mặc định</span>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_address ? '💾 Cập nhật' : '➕ Thêm địa chỉ'; ?>
                    </button>
                    <?php if ($edit_address): ?>
                        <a href="addresses.php" class="btn btn-secondary">Hủy</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Address List -->
        <div class="address-list-card">
            <h2>📋 Danh sách địa chỉ</h2>
            
            <?php if (empty($addresses)): ?>
                <div class="empty-state">
                    <p>📭 Chưa có địa chỉ nào</p>
                    <p>Thêm địa chỉ giao hàng để đặt hàng nhanh hơn!</p>
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
                        <span class="phone">📞 <?php echo htmlspecialchars($addr['phone']); ?></span>
                    </div>
                    
                    <div class="address-body">
                        <p><?php echo htmlspecialchars($addr['address_detail']); ?></p>
                        <p><?php echo htmlspecialchars($addr['ward']); ?>, <?php echo htmlspecialchars($addr['district']); ?></p>
                        <p><?php echo htmlspecialchars($addr['province']); ?></p>
                    </div>
                    
                    <div class="address-actions">
                        <?php if (!$addr['is_default']): ?>
                            <a href="?set_default=<?php echo $addr['id']; ?>" class="btn-link">Đặt mặc định</a>
                        <?php endif; ?>
                        <a href="?edit=<?php echo $addr['id']; ?>" class="btn-link">Sửa</a>
                        <a href="?delete=<?php echo $addr['id']; ?>" class="btn-link danger" onclick="return confirm('Bạn có chắc muốn xóa địa chỉ này?')">Xóa</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.addresses-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.addresses-container h1 {
    font-size: 2rem;
    margin-bottom: 2rem;
    color: var(--text-primary);
}

.addresses-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.address-form-card,
.address-list-card {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 2rem;
    border: 1px solid var(--border);
}

.address-form-card h2,
.address-list-card h2 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.address-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.address-form .form-group {
    margin-bottom: 1rem;
}

.address-form label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.address-form input,
.address-form textarea,
.address-form select {
    width: 100%;
    padding: 0.75rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.address-form select {
    cursor: pointer;
}

.address-form select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.address-form input:focus,
.address-form textarea:focus,
.address-form select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
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
    transform: translateY(-2px);
}

.address-item.default {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.address-header strong {
    font-size: 1.1rem;
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

.phone {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.address-body {
    margin-bottom: 1rem;
}

.address-body p {
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
    line-height: 1.6;
}

.address-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.btn-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.btn-link.danger {
    color: var(--danger);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state p:first-child {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

@media (max-width: 968px) {
    .addresses-grid {
        grid-template-columns: 1fr;
    }
    
    .address-form .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// API endpoint
const API_URL = 'https://provinces.open-api.vn/api';

// Store data
let provinces = [];
let districts = [];
let wards = [];

// Load provinces on page load
document.addEventListener('DOMContentLoaded', async function() {
    await loadProvinces();
    
    // Set edit values if editing
    <?php if ($edit_address): ?>
        setTimeout(() => {
            setEditValues(
                '<?php echo addslashes($edit_address['province']); ?>',
                '<?php echo addslashes($edit_address['district']); ?>',
                '<?php echo addslashes($edit_address['ward']); ?>'
            );
        }, 500);
    <?php endif; ?>
});

// Load provinces
async function loadProvinces() {
    try {
        const response = await fetch(`${API_URL}/p/`);
        provinces = await response.json();
        
        const provinceSelect = document.getElementById('province');
        provinceSelect.innerHTML = '<option value="">-- Chọn Tỉnh/Thành phố --</option>';
        
        provinces.forEach(province => {
            const option = document.createElement('option');
            option.value = province.name;
            option.dataset.code = province.code;
            option.textContent = province.name;
            provinceSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading provinces:', error);
    }
}

// Load districts when province changes
document.getElementById('province').addEventListener('change', async function() {
    const selectedOption = this.options[this.selectedIndex];
    const provinceCode = selectedOption.dataset.code;
    
    const districtSelect = document.getElementById('district');
    const wardSelect = document.getElementById('ward');
    
    // Reset district and ward
    districtSelect.innerHTML = '<option value="">-- Chọn Quận/Huyện --</option>';
    wardSelect.innerHTML = '<option value="">-- Chọn Phường/Xã --</option>';
    wardSelect.disabled = true;
    
    if (!provinceCode) {
        districtSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/p/${provinceCode}?depth=2`);
        const data = await response.json();
        districts = data.districts;
        
        districts.forEach(district => {
            const option = document.createElement('option');
            option.value = district.name;
            option.dataset.code = district.code;
            option.textContent = district.name;
            districtSelect.appendChild(option);
        });
        
        districtSelect.disabled = false;
    } catch (error) {
        console.error('Error loading districts:', error);
    }
});

// Load wards when district changes
document.getElementById('district').addEventListener('change', async function() {
    const selectedOption = this.options[this.selectedIndex];
    const districtCode = selectedOption.dataset.code;
    
    const wardSelect = document.getElementById('ward');
    wardSelect.innerHTML = '<option value="">-- Chọn Phường/Xã --</option>';
    
    if (!districtCode) {
        wardSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/d/${districtCode}?depth=2`);
        const data = await response.json();
        wards = data.wards;
        
        wards.forEach(ward => {
            const option = document.createElement('option');
            option.value = ward.name;
            option.textContent = ward.name;
            wardSelect.appendChild(option);
        });
        
        wardSelect.disabled = false;
    } catch (error) {
        console.error('Error loading wards:', error);
    }
});

// Set values when editing
async function setEditValues(provinceName, districtName, wardName) {
    // Set province
    const provinceSelect = document.getElementById('province');
    for (let i = 0; i < provinceSelect.options.length; i++) {
        if (provinceSelect.options[i].value === provinceName) {
            provinceSelect.selectedIndex = i;
            provinceSelect.dispatchEvent(new Event('change'));
            break;
        }
    }
    
    // Wait for districts to load, then set district
    setTimeout(() => {
        const districtSelect = document.getElementById('district');
        for (let i = 0; i < districtSelect.options.length; i++) {
            if (districtSelect.options[i].value === districtName) {
                districtSelect.selectedIndex = i;
                districtSelect.dispatchEvent(new Event('change'));
                break;
            }
        }
        
        // Wait for wards to load, then set ward
        setTimeout(() => {
            const wardSelect = document.getElementById('ward');
            for (let i = 0; i < wardSelect.options.length; i++) {
                if (wardSelect.options[i].value === wardName) {
                    wardSelect.selectedIndex = i;
                    break;
                }
            }
        }, 500);
    }, 500);
}
</script>

<?php include 'includes/footer.php'; ?>
