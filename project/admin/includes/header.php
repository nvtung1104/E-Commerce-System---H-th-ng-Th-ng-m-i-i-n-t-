<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo APP_NAME ?? 'E-Commerce'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0e27;
            color: #e2e8f0;
            overflow-x: hidden;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }

        .sidebar-logo h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo p {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 12px;
        }

        .menu-item {
            margin-bottom: 4px;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-item a i {
            width: 20px;
            font-size: 18px;
        }

        .menu-item a:hover {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        .menu-item a.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        /* Top Bar */
        .admin-topbar {
            position: fixed;
            left: 280px;
            top: 0;
            right: 0;
            height: 70px;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 999;
        }

        .topbar-left h2 {
            font-size: 20px;
            font-weight: 600;
            color: #fff;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }

        .user-role {
            font-size: 12px;
            color: #64748b;
        }

        .btn-logout {
            padding: 10px 20px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
        }

        /* Main Content */
        .admin-content {
            margin-left: 280px;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.5);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.7);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.active {
                transform: translateX(0);
            }

            .admin-topbar {
                left: 0;
            }

            .admin-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <h1><i class="fas fa-store"></i> Admin Panel</h1>
            <p>Quản trị hệ thống</p>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Đơn hàng</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="completed_orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'completed_orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Đơn hàng hoàn thành</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="cancelled_orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'cancelled_orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i>
                    <span>Đơn hàng đã hủy</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="processing_status.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'processing_status.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Trạng thái xử lý</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Sản phẩm</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Kho hàng</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Top Bar -->
    <header class="admin-topbar">
        <div class="topbar-left">
            <h2><?php 
                $page_titles = [
                    'index.php' => 'Dashboard',
                    'orders.php' => 'Quản lý đơn hàng',
                    'order_detail.php' => 'Chi tiết đơn hàng',
                    'completed_orders.php' => 'Đơn hàng hoàn thành',
                    'cancelled_orders.php' => 'Đơn hàng đã hủy',
                    'processing_status.php' => 'Trạng thái xử lý',
                    'products.php' => 'Quản lý sản phẩm',
                    'inventory.php' => 'Quản lý kho hàng'
                ];
                echo $page_titles[basename($_SERVER['PHP_SELF'])] ?? 'Admin Panel';
            ?></h2>
        </div>
        
        <div class="topbar-right">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                Đăng xuất
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-content">
