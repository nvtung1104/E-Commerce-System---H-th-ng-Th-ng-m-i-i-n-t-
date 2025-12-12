<?php
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

// Get cart items from database
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

// Load RabbitMQ only if available
if (file_exists('../queue/send_order.php')) {
    @include_once '../queue/send_order.php';
}

// Get user addresses
$addresses = [];
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get shipping methods
$shippingMethods = $pdo->query("SELECT * FROM shipping_methods")->fetchAll(PDO::FETCH_ASSOC);

// Get active vouchers
$vouchers = $pdo->query("SELECT * FROM vouchers WHERE status = 1 AND quantity > 0 AND expires_at > NOW()")->fetchAll(PDO::FETCH_ASSOC);

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

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $pdo->beginTransaction();
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $addressId = $_POST['address_id'] ?? null;
        $shippingMethodId = $_POST['shipping_method_id'];
        $paymentMethod = $_POST['payment_method'];
        $voucherCode = $_POST['voucher_code'] ?? null;
        
        // Get shipping fee
        $stmt = $pdo->prepare("SELECT fee FROM shipping_methods WHERE id = ?");
        $stmt->execute([$shippingMethodId]);
        $shippingFee = $stmt->fetchColumn();
        
        // Apply voucher
        $discount = 0;
        if ($voucherCode) {
            $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 1 AND quantity > 0 AND expires_at > NOW()");
            $stmt->execute([$voucherCode]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($voucher && $subtotal >= $voucher['min_order']) {
                if ($voucher['type'] == 'percent') {
                    $discount = $subtotal * ($voucher['discount_value'] / 100);
                } else {
                    $discount = $voucher['discount_value'];
                }
                
                // Decrease voucher quantity
                $pdo->prepare("UPDATE vouchers SET quantity = quantity - 1 WHERE code = ?")->execute([$voucherCode]);
            }
        }
        
        $totalPrice = $subtotal - $discount + $shippingFee;
        
        // Create order
        if (!$userId) {
            // Guest checkout - create temporary user or save guest info
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$_POST['fullname'], $_POST['email'], $_POST['phone']]);
            $userId = $pdo->lastInsertId();
            
            // Create address
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
        
        // Save order items và trừ tồn kho ngay lập tức (chỉ 1 lần)
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            // Check stock availability first
            $stockStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stockStmt->execute([$item['product_id']]);
            $currentStock = $stockStmt->fetchColumn();
            
            if ($currentStock === false) {
                throw new Exception("Sản phẩm ID {$item['product_id']} không tồn tại");
            }
            
            if ($currentStock < $item['quantity']) {
                throw new Exception("Sản phẩm không đủ số lượng trong kho (còn {$currentStock})");
            }
            
            // Insert order item
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            
            // Trừ tồn kho ngay lập tức (chỉ 1 lần duy nhất tại đây)
            $updateStockStmt = $pdo->prepare("UPDATE products 
                                              SET stock_quantity = GREATEST(0, stock_quantity - ?),
                                                  sales_count = sales_count + ?,
                                                  updated_at = NOW() 
                                              WHERE id = ?");
            $updateStockStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            
            // Log inventory change
            try {
                $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_type, quantity_change, reason, order_id, created_at) 
                                          VALUES (?, 'decrease', ?, 'order_placed', ?, NOW())");
                $logStmt->execute([$item['product_id'], -$item['quantity'], $orderId]);
            } catch (Exception $e) {
                // Table doesn't exist, skip logging
            }
        }
        
        // Create payment record
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, method, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $totalPrice, $paymentMethod, PAYMENT_STATUS_PENDING]);
        
        // Log order status
        $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)");
        $stmt->execute([$orderId, ORDER_STATUS_PENDING, 'Đơn hàng đã được tạo']);
        
        $pdo->commit();
        
        // ============================================
        // XỬ LÝ NGAY LẬP TỨC (<1s)
        // ============================================
        
        // 1. Tồn kho đã được trừ ở trên khi lưu order items
        
        // 2. Chuyển sang trạng thái PROCESSING ngay
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([ORDER_STATUS_PROCESSING, $orderId]);
        
        $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)");
        $stmt->execute([$orderId, ORDER_STATUS_PROCESSING, 'Đơn hàng đang được xử lý']);
        
        // 2.1. Gửi email xác nhận thật
        // Use user's registered email
        
        // Prepare order data for email
        $orderData = [
            'order_id' => $orderId,
            'customer_name' => $_POST['fullname'] ?? $userInfo['name'] ?? 'Khách hàng',
            'order_date' => date('d/m/Y H:i'),
            'total_price' => $totalPrice,
            'items' => []
        ];
        
        // Get product details for email
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $productName = $stmt->fetchColumn() ?: 'Sản phẩm';
            
            $orderData['items'][] = [
                'name' => $productName,
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ];
        }
        
        // Send real email
        try {
            require_once __DIR__ . '/config/simple_mailer.php';
            $mailer = getSimpleMailer();
            $emailSent = $mailer->sendOrderConfirmation($customerEmail, $orderData);
            
            if ($emailSent) {
                error_log("Order confirmation email sent to: {$customerEmail}");
                
                // Save to database
                try {
                    $stmt = $pdo->prepare("INSERT INTO email_logs (order_id, recipient, subject, status, sent_at) 
                                           VALUES (?, ?, ?, 'sent', NOW())");
                    $stmt->execute([$orderId, $customerEmail, "Xác nhận đơn hàng #{$orderId}"]);
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Log to history
                $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)");
                $stmt->execute([$orderId, ORDER_STATUS_PROCESSING, "Email xác nhận đã được gửi đến {$customerEmail}"]);
            } else {
                error_log("Failed to send email to: {$customerEmail}");
                // Still continue with order processing
            }
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            // Continue with order processing even if email fails
        }
        
        // 3. Publish to RabbitMQ (optional - for email, analytics)
        try {
            require_once __DIR__ . '/../queue/publish_simple.php';
            
            $orderData = [
                'user_id' => $userId,
                'email' => $_POST['email'] ?? '',
                'total' => $totalPrice,
                'items' => $items,
                'payment_method' => $paymentMethod,
                'shipping_method_id' => $shippingMethodId,
                'address_id' => $addressId
            ];
            
            $publisher = new SimpleOrderPublisher();
            $publisher->publishNewOrder($orderId, $orderData);
            
        } catch (Exception $e) {
            // RabbitMQ không bắt buộc, tiếp tục
            error_log("RabbitMQ Error: " . $e->getMessage());
        }
        
        // 4. Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Redirect
        header('Location: order_status.php?order_id=' . $orderId);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Lỗi: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<h1>Đặt hàng</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST" class="checkout-form">
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
            
            <button type="submit" class="btn btn-success btn-large">Đặt hàng</button>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
