<?php
/**
 * FAST CHECKOUT - Optimized for high concurrency
 * Target: <500ms response time for 1000+ concurrent orders
 */
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?redirect=checkout.php');
    exit;
}

// Check email verification for checkout
if (!isset($_SESSION['checkout_verified']) || !$_SESSION['checkout_verified']) {
    header('Location: email_verification.php');
    exit;
}

// Check if verification is still valid (30 minutes)
if (time() - $_SESSION['checkout_verified_time'] > 1800) {
    unset($_SESSION['checkout_verified'], $_SESSION['checkout_verified_time']);
    header('Location: email_verification.php');
    exit;
}

$pdo = getDBConnection();

// Get cart items from database with single query
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT c.*, p.*, COALESCE(p.sale_price, p.price) as final_price,
                       c.quantity as cart_quantity
                       FROM cart c
                       INNER JOIN products p ON c.product_id = p.id
                       WHERE c.user_id = ?");
$stmt->execute([$userId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if cart is empty
if (empty($products)) {
    header('Location: cart.php');
    exit;
}

// Get user addresses, shipping methods, vouchers in parallel
$addresses = [];
$shippingMethods = [];
$vouchers = [];

// Use prepared statements for better performance
$stmt1 = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt2 = $pdo->prepare("SELECT * FROM shipping_methods WHERE status = 1");
$stmt3 = $pdo->prepare("SELECT * FROM vouchers WHERE status = 1 AND quantity > 0 AND expires_at > NOW()");

$stmt1->execute([$userId]);
$addresses = $stmt1->fetchAll(PDO::FETCH_ASSOC);

$stmt2->execute();
$shippingMethods = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$stmt3->execute();
$vouchers = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Calculate cart total
$subtotal = 0;
$items = [];
foreach ($products as $product) {
    $quantity = $product['cart_quantity'];
    $price = $product['final_price'];
    $subtotal += $price * $quantity;
    $items[] = [
        'product_id' => $product['id'],
        'quantity' => $quantity,
        'price' => $price
    ];
}

// Get user info for email
$stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$customerEmail = $userInfo['email'];

// ============================================
// FAST ORDER PROCESSING (<500ms target)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Start transaction with optimistic locking
    $pdo->beginTransaction();
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $addressId = $_POST['address_id'] ?? null;
        $shippingMethodId = $_POST['shipping_method_id'];
        $paymentMethod = $_POST['payment_method'];
        $voucherCode = $_POST['voucher_code'] ?? null;
        
        // STEP 1: Quick validation and calculations (target: <100ms)
        
        // Get shipping fee (cached query)
        $stmt = $pdo->prepare("SELECT fee FROM shipping_methods WHERE id = ?");
        $stmt->execute([$shippingMethodId]);
        $shippingFee = $stmt->fetchColumn();
        
        // Apply voucher (optimized)
        $discount = 0;
        $voucherId = null;
        if ($voucherCode) {
            $stmt = $pdo->prepare("SELECT id, type, discount_value, min_order FROM vouchers 
                                   WHERE code = ? AND status = 1 AND quantity > 0 AND expires_at > NOW() 
                                   FOR UPDATE"); // Lock for update
            $stmt->execute([$voucherCode]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($voucher && $subtotal >= $voucher['min_order']) {
                $voucherId = $voucher['id'];
                if ($voucher['type'] == 'percent') {
                    $discount = $subtotal * ($voucher['discount_value'] / 100);
                } else {
                    $discount = $voucher['discount_value'];
                }
                
                // Decrease voucher quantity atomically
                $pdo->prepare("UPDATE vouchers SET quantity = quantity - 1 WHERE id = ?")->execute([$voucherId]);
            }
        }
        
        $totalPrice = $subtotal - $discount + $shippingFee;
        
        // STEP 2: Create order record ONLY (target: <200ms)
        
        // Handle guest checkout quickly
        if (!$userId) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$_POST['fullname'], $_POST['email'], $_POST['phone']]);
            $userId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, fullname, phone, province, district, ward, address_detail, is_default) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $userId,
                $_POST['fullname'],
                $_POST['phone'],
                $_POST['province'],
                $_POST['district'],
                $_POST['ward'],
                $_POST['address_detail']
            ]);
            $addressId = $pdo->lastInsertId();
        }
        
        // Create order with minimal data
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, address_id, total_price, voucher_code, shipping_method_id, shipping_fee, payment_method, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $addressId,
            $totalPrice,
            $voucherCode,
            $shippingMethodId,
            $shippingFee,
            $paymentMethod,
            ORDER_STATUS_PENDING
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // STEP 3: Batch insert order items + stock update (target: <150ms)
        
        // Prepare batch statements
        $insertItemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $updateStockStmt = $pdo->prepare("UPDATE products 
                                          SET stock_quantity = GREATEST(0, stock_quantity - ?),
                                              sales_count = sales_count + ?,
                                              updated_at = NOW() 
                                          WHERE id = ?");
        
        // Batch process all items
        foreach ($items as $item) {
            // Quick stock check
            $stockStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
            $stockStmt->execute([$item['product_id']]);
            $currentStock = $stockStmt->fetchColumn();
            
            if ($currentStock === false) {
                throw new Exception("Sản phẩm ID {$item['product_id']} không tồn tại");
            }
            
            if ($currentStock < $item['quantity']) {
                throw new Exception("Sản phẩm không đủ số lượng trong kho (còn {$currentStock})");
            }
            
            // Insert order item
            $insertItemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            
            // Update stock immediately
            $updateStockStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
        
        // STEP 4: Create payment record (target: <50ms)
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, method, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $totalPrice, $paymentMethod, PAYMENT_STATUS_PENDING]);
        
        // STEP 5: Clear cart immediately (target: <50ms)
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Commit transaction FAST
        $pdo->commit();
        
        // ============================================
        // BACKGROUND PROCESSING (Non-blocking)
        // ============================================
        
        // Queue background tasks (should be <10ms)
        try {
            // Prepare order data for background processing
            $backgroundData = [
                'order_id' => $orderId,
                'user_id' => $userId,
                'email' => $customerEmail,
                'customer_name' => $_POST['fullname'] ?? $userInfo['name'] ?? 'Khách hàng',
                'total_price' => $totalPrice,
                'items' => $items,
                'payment_method' => $paymentMethod,
                'shipping_method_id' => $shippingMethodId,
                'address_id' => $addressId,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Queue for background processing (non-blocking)
            if (file_exists('../queue/publish_fast.php')) {
                require_once '../queue/publish_fast.php';
                $publisher = getFastPublisher();
                
                // Batch publish all tasks at once (more efficient)
                $tasks = [
                    [
                        'type' => 'email',
                        'order_id' => $orderId,
                        'data' => [
                            'type' => 'order_confirmation',
                            'email' => $customerEmail,
                            'order_data' => $backgroundData
                        ]
                    ],
                    [
                        'type' => 'inventory',
                        'order_id' => $orderId,
                        'data' => [
                            'type' => 'order_placed',
                            'items' => $items,
                            'order_id' => $orderId
                        ]
                    ],
                    [
                        'type' => 'analytics',
                        'order_id' => $orderId,
                        'data' => [
                            'type' => 'order_created',
                            'data' => $backgroundData
                        ]
                    ]
                ];
                
                $publisher->publishBatch($tasks);
            }
            
        } catch (Exception $e) {
            // Log error but don't fail the order
            error_log("Background queue error: " . $e->getMessage());
        }
        
        // ============================================
        // IMMEDIATE RESPONSE TO USER (<500ms total)
        // ============================================
        
        // Redirect immediately - user doesn't wait for email/analytics
        header('Location: order_status.php?order_id=' . $orderId . '&fast=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Lỗi: " . $e->getMessage();
        error_log("Fast checkout error: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<style>
/* Fast checkout loading states */
.checkout-form {
    position: relative;
}

.checkout-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 16px;
}

.loading-spinner {
    text-align: center;
    color: white;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn-checkout {
    position: relative;
    overflow: hidden;
}

.btn-checkout:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-checkout.processing::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>

<h1>Đặt hàng nhanh</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST" class="checkout-form" id="fastCheckoutForm">
    <div class="checkout-loading" id="checkoutLoading">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Đang xử lý đơn hàng...</p>
            <small>Vui lòng không tải lại trang</small>
        </div>
    </div>
    
    <div class="checkout-grid">
        <div class="checkout-left">
            <h2>Thông tin giao hàng</h2>
            
            <?php if (empty($addresses)): ?>
                <div class="form-group">
                    <label>Họ tên:</label>
                    <input type="text" name="fullname" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" value="<?php echo htmlspecialchars($customerEmail); ?>" readonly style="background: #f8f9fa; color: #6c757d;">
                    <small style="color: #6c757d;">Email đã được xác thực</small>
                </div>
                
                <div class="form-group">
                    <label>Số điện thoại:</label>
                    <input type="tel" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label>Tỉnh/Thành phố:</label>
                    <input type="text" name="province" required>
                </div>
                
                <div class="form-group">
                    <label>Quận/Huyện:</label>
                    <input type="text" name="district" required>
                </div>
                
                <div class="form-group">
                    <label>Phường/Xã:</label>
                    <input type="text" name="ward" required>
                </div>
                
                <div class="form-group">
                    <label>Địa chỉ chi tiết:</label>
                    <textarea name="address_detail" required></textarea>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label>Chọn địa chỉ:</label>
                    <select name="address_id" required>
                        <?php foreach ($addresses as $addr): ?>
                            <option value="<?php echo $addr['id']; ?>">
                                <?php echo $addr['fullname']; ?> - <?php echo $addr['phone']; ?> - 
                                <?php echo $addr['address_detail']; ?>, <?php echo $addr['ward']; ?>, 
                                <?php echo $addr['district']; ?>, <?php echo $addr['province']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <h2>Phương thức vận chuyển</h2>
            <?php foreach ($shippingMethods as $method): ?>
                <label class="radio-label">
                    <input type="radio" name="shipping_method_id" value="<?php echo $method['id']; ?>" required>
                    <?php echo $method['name']; ?> - <?php echo number_format($method['fee']); ?> VNĐ 
                    (<?php echo $method['estimated_days']; ?> ngày)
                </label>
            <?php endforeach; ?>
            
            <h2>Phương thức thanh toán</h2>
            <label class="radio-label">
                <input type="radio" name="payment_method" value="cod" required checked>
                Thanh toán khi nhận hàng (COD)
            </label>
            <label class="radio-label">
                <input type="radio" name="payment_method" value="banking" required>
                Chuyển khoản ngân hàng
            </label>
            <label class="radio-label">
                <input type="radio" name="payment_method" value="momo" required>
                Ví MoMo
            </label>
            
            <h2>Mã giảm giá</h2>
            <div class="form-group">
                <input type="text" name="voucher_code" placeholder="Nhập mã giảm giá">
            </div>
            <div class="voucher-list">
                <?php foreach ($vouchers as $v): ?>
                    <div class="voucher-item">
                        <strong><?php echo $v['code']; ?></strong> - 
                        Giảm <?php echo $v['type'] == 'percent' ? $v['discount_value'] . '%' : number_format($v['discount_value']) . ' VNĐ'; ?>
                        (Đơn tối thiểu: <?php echo number_format($v['min_order']); ?> VNĐ)
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="checkout-right">
            <h2>Đơn hàng của bạn</h2>
            <div class="order-summary">
                <?php foreach ($products as $product): ?>
                    <div class="summary-item">
                        <span><?php echo $product['name']; ?> x <?php echo $product['cart_quantity']; ?></span>
                        <span><?php echo number_format($product['final_price'] * $product['cart_quantity']); ?> VNĐ</span>
                    </div>
                <?php endforeach; ?>
                
                <div class="summary-total">
                    <span>Tạm tính:</span>
                    <span><?php echo number_format($subtotal); ?> VNĐ</span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success btn-large btn-checkout" id="checkoutBtn">
                <span class="btn-text">Đặt hàng ngay</span>
            </button>
            
            <div style="margin-top: 16px; text-align: center;">
                <small style="color: #6c757d;">
                    ⚡ Xử lý siêu nhanh - Chỉ mất vài giây
                </small>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('fastCheckoutForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('checkoutBtn');
    const loading = document.getElementById('checkoutLoading');
    const btnText = btn.querySelector('.btn-text');
    
    // Show loading immediately
    loading.style.display = 'flex';
    btn.disabled = true;
    btn.classList.add('processing');
    btnText.textContent = 'Đang xử lý...';
    
    // Prevent double submission
    setTimeout(() => {
        btn.style.pointerEvents = 'none';
    }, 100);
    
    // Timeout fallback (in case server is slow)
    setTimeout(() => {
        if (btn.disabled) {
            loading.style.display = 'none';
            btn.disabled = false;
            btn.classList.remove('processing');
            btnText.textContent = 'Thử lại';
            btn.style.pointerEvents = 'auto';
            alert('Xử lý quá lâu, vui lòng thử lại');
        }
    }, 10000); // 10 second timeout
});

// Prevent back button after submission
window.addEventListener('beforeunload', function(e) {
    const loading = document.getElementById('checkoutLoading');
    if (loading.style.display === 'flex') {
        e.preventDefault();
        e.returnValue = 'Đơn hàng đang được xử lý, vui lòng không tải lại trang';
        return 'Đơn hàng đang được xử lý, vui lòng không tải lại trang';
    }
});
</script>

<?php include 'includes/footer.php'; ?>