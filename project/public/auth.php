<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $pdo = getDBConnection();
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Log login history
        try {
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
            $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'success')");
            $stmt->execute([
                $user['id'],
                $_SERVER['REMOTE_ADDR'],
                $userAgent
            ]);
        } catch (Exception $e) {
            // Ignore login history errors
        }
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            $redirectUrl = '../admin/index.php';
        } else {
            $redirectUrl = $_GET['redirect'] ?? 'index.php';
        }
        
        // Multiple redirect methods for compatibility
        echo '<!DOCTYPE html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl) . '">';
        echo '<script>window.location.href="' . htmlspecialchars($redirectUrl) . '";</script>';
        echo '</head><body>';
        echo '<p>Đăng nhập thành công! Đang chuyển hướng...</p>';
        echo '<p>Nếu không tự động chuyển, <a href="' . htmlspecialchars($redirectUrl) . '">nhấn vào đây</a></p>';
        echo '</body></html>';
        exit;
    } else {
        $error = "Email hoặc mật khẩu không đúng";
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $pdo = getDBConnection();
    
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Email đã được sử dụng";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->execute([$fullname, $email, $phone, $hashed_password]);
            
            $success = "Đăng ký thành công! Vui lòng đăng nhập.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập / Đăng ký - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page">
    
    <!-- Back to Home Button -->
    <a href="index.php" class="btn-back-home">
        <span>←</span>
        <span>Trang chủ</span>
    </a>

    <!-- Main Container -->
    <div class="auth-container">
        <div class="auth-wrapper" id="authWrapper">
            
            <!-- Sign In Form -->
            <div class="form-container sign-in-container">
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="login">
                    <h1>Sign In</h1>
                    
                    <div class="social-container">
                        <a href="#" title="Facebook">f</a>
                        <a href="#" title="Google">G</a>
                        <a href="#" title="LinkedIn">in</a>
                    </div>
                    
                    <span class="divider">or use your account</span>
                    
                    <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'login'): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="#">Forgot your password?</a>
                    </div>
                    
                    <button type="submit" class="btn-auth"><span>Sign In</span></button>
                </form>
            </div>

            <!-- Sign Up Form -->
            <div class="form-container sign-up-container">
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="register">
                    <h1>Create Account</h1>
                    
                    <div class="social-container">
                        <a href="#" title="Facebook">f</a>
                        <a href="#" title="Google">G</a>
                        <a href="#" title="LinkedIn">in</a>
                    </div>
                    
                    <span class="divider">or use your email for registration</span>
                    
                    <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'register'): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <input type="text" name="fullname" placeholder="Họ và tên" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <input type="tel" name="phone" placeholder="Số điện thoại" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Mật khẩu" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn-auth"><span>Sign Up</span></button>
                </form>
            </div>

            <!-- Overlay Container -->
            <div class="overlay-container">
                <div class="overlay">
                    <div class="overlay-panel overlay-left">
                        <h2>Welcome Back!</h2>
                        <p>To keep connected with us please login with your personal info</p>
                        <button class="btn-ghost" id="signIn"><span>Sign In</span></button>
                    </div>
                    <div class="overlay-panel overlay-right">
                        <h2>Hello, Friend!</h2>
                        <p>Enter your personal details and start journey with us</p>
                        <button class="btn-ghost" id="signUp"><span>Sign Up</span></button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Admin Access Button -->
    <a href="../admin/login.php" class="admin-access-btn" title="Admin Login">
        <i class="fas fa-user-shield"></i>
    </a>

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const authWrapper = document.getElementById('authWrapper');

        signUpButton.addEventListener('click', () => {
            authWrapper.classList.add('right-panel-active');
        });

        signInButton.addEventListener('click', () => {
            authWrapper.classList.remove('right-panel-active');
        });
    </script>

</body>
</html>
