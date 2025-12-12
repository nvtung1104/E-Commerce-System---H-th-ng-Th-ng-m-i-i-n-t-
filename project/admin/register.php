<?php
session_start();

// Fix path for config files
$config_path = dirname(__DIR__) . '/public/config/';
require_once $config_path . 'constants.php';
require_once $config_path . 'db.php';

// Super Admin credentials for verification
define('SUPER_ADMIN_PHONE', '0338944015');
define('SUPER_ADMIN_EMAIL', 'ema03106@gmail.com');
define('ADMIN_SECRET_KEY', 'ECOM2025ADMIN');

$error = '';
$success = '';
$step = $_POST['step'] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    
    // Step 1: Verify secret key and super admin info
    if ($step == 1) {
        $secret_key = trim($_POST['secret_key'] ?? '');
        $verify_phone = trim($_POST['verify_phone'] ?? '');
        $verify_email = trim($_POST['verify_email'] ?? '');
        
        if ($secret_key !== ADMIN_SECRET_KEY) {
            $error = 'Secret key không đúng!';
        } elseif ($verify_phone !== SUPER_ADMIN_PHONE) {
            $error = 'Số điện thoại xác thực không đúng!';
        } elseif ($verify_email !== SUPER_ADMIN_EMAIL) {
            $error = 'Email xác thực không đúng!';
        } else {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $_SESSION['admin_register_otp'] = $otp;
            $_SESSION['admin_register_time'] = time();
            $_SESSION['admin_register_data'] = [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'password' => $_POST['password']
            ];
            
            $step = 2;
            $success = "Mã OTP đã được gửi đến email: " . SUPER_ADMIN_EMAIL . "<br><strong style='color: #a78bfa; font-size: 1.2rem;'>Mã OTP (demo): " . $otp . "</strong>";
        }
    }
    
    // Step 2: Verify OTP and create admin account
    elseif ($step == 2) {
        $input_otp = trim($_POST['otp'] ?? '');
        
        if (!isset($_SESSION['admin_register_otp'])) {
            $error = 'Phiên đăng ký đã hết hạn. Vui lòng thử lại!';
            $step = 1;
        } elseif (time() - $_SESSION['admin_register_time'] > 300) {
            $error = 'Mã OTP đã hết hạn (5 phút). Vui lòng thử lại!';
            unset($_SESSION['admin_register_otp']);
            unset($_SESSION['admin_register_time']);
            unset($_SESSION['admin_register_data']);
            $step = 1;
        } elseif ($input_otp !== $_SESSION['admin_register_otp']) {
            $error = 'Mã OTP không đúng!';
        } else {
            $data = $_SESSION['admin_register_data'];
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            
            if ($stmt->fetch()) {
                $error = 'Email đã được sử dụng!';
            } else {
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())");
                $stmt->execute([
                    $data['name'],
                    $data['email'],
                    $data['phone'],
                    $hashed_password
                ]);
                
                unset($_SESSION['admin_register_otp']);
                unset($_SESSION['admin_register_time']);
                unset($_SESSION['admin_register_data']);
                
                $success = 'Tài khoản Admin đã được tạo thành công! Đang chuyển đến trang đăng nhập...';
                header("refresh:3;url=login.php");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký Admin - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .register-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
        }
        
        .register-container {
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
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
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
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
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
        
        .form-group label .required {
            color: #fca5a5;
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
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .info-box {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            gap: 10px;
        }
        
        .info-box i {
            color: #818cf8;
            margin-top: 2px;
        }
        
        .info-box strong {
            color: white;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .divider {
            margin: 28px 0;
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        
        .back-link a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link a:hover {
            color: #a78bfa;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px 16px;
            }
            
            .register-container {
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
    <a href="login.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Đăng nhập
    </a>

    <div class="register-wrapper">
        <div class="register-container">
            <div class="logo-section">
                <div class="admin-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1>Đăng ký Admin</h1>
                <p class="subtitle">Restricted Registration</p>
            </div>
            
            <div class="steps">
                <div class="step <?php echo $step == 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step == 2 ? 'active' : ''; ?>">2</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-success">
                    <i class="fas fa-circle-check"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
            <form method="POST">
                <input type="hidden" name="step" value="1">
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Yêu cầu:</strong> Để đăng ký tài khoản Admin, bạn cần có Secret Key và thông tin xác thực từ Super Admin.
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Họ tên <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" placeholder="Nhập họ tên" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="admin@example.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Số điện thoại <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" placeholder="0123456789" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mật khẩu <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Tối thiểu 6 ký tự" required minlength="6">
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <div class="form-group">
                    <label>Secret Key <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="text" name="secret_key" placeholder="Nhập secret key" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Số điện thoại xác thực <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-mobile-alt"></i>
                        <input type="tel" name="verify_phone" placeholder="Số điện thoại Super Admin" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email xác thực <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope-open"></i>
                        <input type="email" name="verify_email" placeholder="Email Super Admin" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-arrow-right"></i> Tiếp tục
                </button>
            </form>
            
            <?php elseif ($step == 2): ?>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                
                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        Mã OTP đã được gửi đến email: <strong><?php echo SUPER_ADMIN_EMAIL; ?></strong><br>
                        Vui lòng kiểm tra email và nhập mã OTP để hoàn tất đăng ký.
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mã OTP (6 số) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" placeholder="Nhập 6 số" required autofocus>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-check"></i> Xác nhận & Tạo tài khoản
                </button>
            </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Quay lại đăng nhập
                </a>
            </div>
        </div>
    </div>
</body>
</html>
