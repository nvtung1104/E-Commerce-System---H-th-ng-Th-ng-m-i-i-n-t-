<?php
session_start();

// Fix path for config files
$config_path = dirname(__DIR__) . '/public/config/';
require_once $config_path . 'constants.php';
require_once $config_path . 'db.php';

// If already logged in as admin, redirect to admin dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if user exists and is admin
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log admin login
                $logStmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'success')");
                $logStmt->execute([
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Email hoặc mật khẩu không đúng hoặc bạn không có quyền admin';
                
                // Log failed attempt
                if ($user) {
                    $logStmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'failed')");
                    $logStmt->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                }
            }
        } catch (Exception $e) {
            $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
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
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            padding: 40px 20px;
            overflow-y: auto;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(-5px);
        }
        
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }
        
        .login-container {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            padding: 48px 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .admin-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }
        
        .admin-icon i {
            font-size: 36px;
            color: white;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }
        
        .subtitle {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.3);
            font-size: 16px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 28px 0;
            color: rgba(255, 255, 255, 0.4);
            font-size: 13px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .divider span {
            padding: 0 16px;
            font-weight: 500;
        }
        
        .register-section {
            text-align: center;
        }
        
        .btn-register {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-2px);
        }
        
        .security-note {
            margin-top: 24px;
            padding: 16px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            text-align: center;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px 16px;
            }
            
            .login-container {
                padding: 36px 28px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .back-btn {
                top: 12px;
                left: 12px;
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <a href="../public/index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Trang chủ
    </a>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo-section">
                <div class="admin-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1>Đăng nhập Admin</h1>
                <p class="subtitle">Khu vực quản trị hệ thống</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="admin@example.com" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-right-to-bracket"></i>
                    Đăng nhập
                </button>
            </form>
            
            <div class="divider">
                <span>hoặc</span>
            </div>
            
            <div class="register-section">
                <a href="register.php" class="btn-register">
                    <i class="fas fa-user-plus"></i>
                    Đăng ký tài khoản Admin
                </a>
            </div>
            
            <div class="security-note">
                <i class="fas fa-shield-check"></i>
                <span>Trang này chỉ dành cho quản trị viên được ủy quyền</span>
            </div>
        </div>
    </div>
</body>
</html>
