<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

$pdo = getDBConnection();

// Check if user is logged in for cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['user_id'])) {
    header('Location: auth.php?redirect=cart.php');
    exit;
}

// Remove item from cart
if (isset($_GET['remove']) && isset($_SESSION['user_id'])) {
    $productId = (int)$_GET['remove'];
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    
    $_SESSION['cart_message'] = 'Đã xóa sản phẩm khỏi giỏ hàng!';
    header('Location: cart.php');
    exit;
}

// Update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart']) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    foreach ($_POST['quantity'] as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;
        
        if ($quantity > 0) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $userId, $productId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
        }
    }
    
    $_SESSION['cart_message'] = 'Đã cập nhật giỏ hàng!';
    header('Location: cart.php');
    exit;
}

// Add to cart - require login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $userId = $_SESSION['user_id'];
    
    // Check if product already in cart
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update quantity
        $newQuantity = $existing['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$newQuantity, $userId, $productId]);
    } else {
        // Insert new item
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $productId, $quantity]);
    }
    
    $_SESSION['cart_message'] = 'Đã thêm sản phẩm vào giỏ hàng!';
    header('Location: cart.php');
    exit;
}

// Get cart items from database
$cartItems = [];
$total = 0;

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT c.*, p.*, p.stock_quantity as stock, 
                           COALESCE(p.sale_price, p.price) as final_price,
                           c.quantity as cart_quantity
                           FROM cart c
                           INNER JOIN products p ON c.product_id = p.id
                           WHERE c.user_id = ?
                           ORDER BY c.created_at DESC");
    $stmt->execute([$userId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $quantity = $product['cart_quantity'];
        $subtotal = $product['final_price'] * $quantity;
        $total += $subtotal;
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

include 'includes/header.php';
?>

<h1>Giỏ hàng</h1>

<?php if (isset($_SESSION['cart_message'])): ?>
    <div class="alert alert-success">
        <?php 
        echo $_SESSION['cart_message']; 
        unset($_SESSION['cart_message']);
        ?>
    </div>
<?php endif; ?>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="alert alert-error">
        Vui lòng <a href="login.php?redirect=cart.php" style="color: var(--danger); font-weight: 700; text-decoration: underline;">đăng nhập</a> để thêm sản phẩm vào giỏ hàng
    </div>
<?php endif; ?>

<?php if (empty($cartItems)): ?>
    <div class="empty-cart">
        <p>Giỏ hàng trống</p>
        <a href="product.php" class="btn btn-primary">Tiếp tục mua sắm</a>
    </div>
<?php else: ?>
    <form method="POST">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Giá</th>
                    <th>Số lượng</th>
                    <th>Tổng</th>
                    <th>Xóa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                <tr>
                    <td>
                        <div class="cart-product">
                            <img src="assets/images/<?php echo $item['product']['thumbnail']; ?>" 
                                 alt="<?php echo htmlspecialchars($item['product']['name']); ?>">
                            <span><?php echo htmlspecialchars($item['product']['name']); ?></span>
                        </div>
                    </td>
                    <td><?php echo number_format($item['product']['final_price']); ?> VNĐ</td>
                    <td>
                        <input type="number" name="quantity[<?php echo $item['product']['id']; ?>]" 
                               value="<?php echo $item['quantity']; ?>" 
                               min="1" max="<?php echo $item['product']['stock']; ?>" 
                               class="quantity-input">
                    </td>
                    <td><?php echo number_format($item['subtotal']); ?> VNĐ</td>
                    <td>
                        <a href="cart.php?remove=<?php echo $item['product']['id']; ?>" 
                           class="btn-remove">Xóa</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Tổng cộng:</strong></td>
                    <td colspan="2"><strong><?php echo number_format($total); ?> VNĐ</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="cart-actions">
            <button type="submit" name="update_cart" class="btn btn-secondary">Cập nhật giỏ hàng</button>
            <a href="email_verification.php" class="btn btn-success">Tiến hành đặt hàng</a>
        </div>
    </form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
