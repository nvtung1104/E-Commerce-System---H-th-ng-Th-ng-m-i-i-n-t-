<!-- Top Bar -->
<div class="top-bar">
    <div class="top-bar-container">
        <div class="top-bar-right">
            <a href="#" class="top-link">
                <i class="far fa-bell"></i> Thông báo
            </a>
            <a href="#" class="top-link">
                <i class="far fa-question-circle"></i> Hỗ trợ
            </a>
            <a href="#" class="top-link">
                <i class="fas fa-globe"></i> Tiếng Việt
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-dropdown">
                    <a href="profile.php" class="top-link">
                        <i class="far fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                    </a>
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="far fa-user"></i> Tài khoản của tôi</a>
                        <a href="my_orders.php"><i class="fas fa-shopping-bag"></i> Đơn hàng của tôi</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="auth.php" class="top-link">
                    <i class="far fa-user"></i> Đăng nhập
                </a>
                <span class="top-divider">|</span>
                <a href="auth.php" class="top-link">Đăng ký</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="header-container">
        <!-- Logo -->
        <div class="header-logo">
            <a href="index.php">
                <i class="fas fa-shopping-bag"></i>
                <span><?php echo APP_NAME; ?></span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="header-search">
            <form action="product.php" method="GET" class="search-form-header">
                <input type="text" 
                       name="search" 
                       placeholder="Tìm kiếm sản phẩm, thương hiệu..." 
                       class="search-input-header"
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-btn-header">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            <div class="search-suggestions-keywords">
                <a href="product.php?search=iPhone">iPhone</a>
                <a href="product.php?search=Samsung">Samsung</a>
                <a href="product.php?search=Laptop">Laptop</a>
                <a href="product.php?search=Tai nghe">Tai nghe</a>
                <a href="product.php?search=Airpods">Airpods</a>
            </div>
        </div>

        <!-- Cart -->
        <div class="header-cart">
            <a href="cart.php" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <?php 
                // Get cart count from database
                $cartCount = 0;
                if (isset($_SESSION['user_id'])) {
                    try {
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $cartCount = $result['count'];
                    } catch (Exception $e) {
                        $cartCount = 0;
                    }
                }
                
                if ($cartCount > 0): 
                ?>
                    <span class="cart-badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>

<!-- Navigation Links -->
<nav class="nav-links">
    <div class="nav-links-container">
        <a href="index.php" class="nav-link-item">Trang chủ</a>
        <a href="product.php" class="nav-link-item">Sản phẩm</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="my_orders.php" class="nav-link-item">Đơn hàng của tôi</a>
        <?php endif; ?>
    </div>
</nav>
