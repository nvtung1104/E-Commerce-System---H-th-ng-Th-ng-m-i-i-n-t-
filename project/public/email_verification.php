<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';
require_once 'config/simple_mailer.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?redirect=cart.php');
    exit;
}

$pdo = getDBConnection();

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: auth.php');
    exit;
}

// Check if cart is empty
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cartCount = $stmt->fetchColumn();

if ($cartCount == 0) {
    header('Location: cart.php');
    exit;
}

// Generate and send verification code
if (!isset($_SESSION['checkout_verification_code']) || !isset($_SESSION['checkout_verification_expires']) || time() > $_SESSION['checkout_verification_expires']) {
    $code = sprintf('%06d', mt_rand(0, 999999));
    $_SESSION['checkout_verification_code'] = $code;
    $_SESSION['checkout_verification_expires'] = time() + 600; // 10 minutes
    
    // Send email
    try {
        $mailer = getSimpleMailer();
        $sent = $mailer->sendVerificationCode($user['email'], $code);
        
        if ($sent) {
            $emailSent = true;
            error_log("Checkout verification code sent to: " . $user['email']);
        } else {
            $emailError = "Không thể gửi email xác thực. Vui lòng thử lại.";
        }
    } catch (Exception $e) {
        $emailError = "Lỗi hệ thống: " . $e->getMessage();
        error_log("Email error: " . $e->getMessage());
    }
}

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    $inputCode = trim($_POST['verification_code']);
    
    if (!isset($_SESSION['checkout_verification_code'])) {
        $error = "Mã xác thực đã hết hạn. Vui lòng thử lại.";
    } elseif (time() > $_SESSION['checkout_verification_expires']) {
        unset($_SESSION['checkout_verification_code'], $_SESSION['checkout_verification_expires']);
        $error = "Mã xác thực đã hết hạn. Vui lòng thử lại.";
    } elseif ($_SESSION['checkout_verification_code'] !== $inputCode) {
        $error = "Mã xác thực không đúng. Vui lòng kiểm tra lại email.";
    } else {
        // Verification successful
        $_SESSION['checkout_verified'] = true;
        $_SESSION['checkout_verified_time'] = time();
        unset($_SESSION['checkout_verification_code'], $_SESSION['checkout_verification_expires']);
        
        header('Location: checkout.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực Email - Tech Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(59, 130, 246, 0.15) 0%, transparent 50%);
            z-index: 0;
            pointer-events: none;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Floating particles */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(2px 2px at 20px 30px, rgba(255,255,255,0.1), transparent),
                radial-gradient(2px 2px at 40px 70px, rgba(255,255,255,0.1), transparent),
                radial-gradient(1px 1px at 90px 40px, rgba(255,255,255,0.1), transparent),
                radial-gradient(1px 1px at 130px 80px, rgba(255,255,255,0.1), transparent),
                radial-gradient(2px 2px at 160px 30px, rgba(255,255,255,0.1), transparent);
            background-repeat: repeat;
            background-size: 200px 100px;
            z-index: 0;
            pointer-events: none;
            animation: float 25s linear infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px) translateX(0px); }
            33% { transform: translateY(-10px) translateX(10px); }
            66% { transform: translateY(-5px) translateX(-5px); }
            100% { transform: translateY(0px) translateX(0px); }
        }

        .verification-container {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 32px;
            padding: 56px 48px;
            max-width: 520px;
            width: 100%;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.08);
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

        .verification-icon {
            width: 96px;
            height: 96px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            font-size: 40px;
            color: white;
            box-shadow: 
                0 20px 40px rgba(99, 102, 241, 0.4),
                0 0 0 8px rgba(99, 102, 241, 0.1),
                inset 0 2px 0 rgba(255, 255, 255, 0.2);
            animation: iconPulse 3s ease-in-out infinite;
            position: relative;
        }

        .verification-icon::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
            animation: iconGlow 3s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes iconGlow {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .verification-title {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .verification-subtitle {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 40px;
            line-height: 1.6;
            font-weight: 400;
            opacity: 0.9;
        }

        .user-email {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            color: #e2e8f0;
            padding: 18px 24px;
            border-radius: 16px;
            font-weight: 600;
            margin-bottom: 32px;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 16px;
            box-shadow: 
                0 4px 12px rgba(99, 102, 241, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .verification-form {
            margin-bottom: 32px;
        }

        .code-input {
            width: 100%;
            padding: 24px;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            margin-bottom: 24px;
            font-family: 'Inter', monospace;
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
            backdrop-filter: blur(10px);
            box-shadow: 
                inset 0 2px 4px rgba(0, 0, 0, 0.3),
                0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .code-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 
                0 0 0 4px rgba(99, 102, 241, 0.3),
                0 8px 24px rgba(99, 102, 241, 0.4),
                inset 0 2px 4px rgba(0, 0, 0, 0.3);
            background: rgba(15, 23, 42, 0.9);
            transform: translateY(-2px);
        }

        .code-input::placeholder {
            color: #64748b;
            font-weight: 400;
            opacity: 0.7;
        }

        .verify-btn {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 
                0 8px 24px rgba(99, 102, 241, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .verify-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .verify-btn:hover::before {
            left: 100%;
        }

        .verify-btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 32px rgba(99, 102, 241, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .verify-btn:active {
            transform: translateY(-1px);
        }

        .message {
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 15px;
            animation: fadeIn 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 
                0 4px 12px rgba(239, 68, 68, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .success-message {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1));
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
            box-shadow: 
                0 4px 12px rgba(34, 197, 94, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .resend-section {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .resend-text {
            color: #94a3b8;
            font-size: 15px;
            margin-bottom: 16px;
            font-weight: 400;
            opacity: 0.8;
        }

        .resend-btn {
            background: rgba(99, 102, 241, 0.1);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 14px 28px;
            border-radius: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 15px;
            backdrop-filter: blur(10px);
        }

        .resend-btn:hover {
            background: rgba(99, 102, 241, 0.2);
            color: #c7d2fe;
            transform: translateY(-2px);
            box-shadow: 
                0 8px 20px rgba(99, 102, 241, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 15px;
            opacity: 0.8;
        }

        .back-link:hover {
            color: #c7d2fe;
            transform: translateY(-1px);
            opacity: 1;
        }

        .countdown {
            font-size: 14px;
            color: #fca5a5;
            font-weight: 600;
            margin-top: 16px;
            padding: 16px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 16px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: 
                0 4px 12px rgba(239, 68, 68, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .loading-dots {
            display: inline-block;
        }

        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }

        @media (max-width: 768px) {
            .verification-container {
                padding: 40px 32px;
                margin: 16px;
                border-radius: 24px;
            }
            
            .verification-title {
                font-size: 28px;
            }
            
            .code-input {
                font-size: 24px;
                letter-spacing: 8px;
                padding: 20px;
            }
            
            .verification-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }

        @media (max-width: 480px) {
            .verification-container {
                padding: 32px 24px;
            }
            
            .code-input {
                font-size: 20px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>

<div class="verification-container">
    <div class="verification-icon">
        🔐
    </div>
    
    <h1 class="verification-title">Xác Thực Email</h1>
    <p class="verification-subtitle">
        Chúng tôi đã gửi mã xác thực 6 số đến email của bạn.<br>
        Vui lòng nhập mã để tiếp tục đặt hàng.
    </p>
    
    <div class="user-email">
        📧 <?php echo htmlspecialchars($user['email']); ?>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="message error-message">
            <strong>❌ Lỗi:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($emailSent)): ?>
        <div class="message success-message">
            <strong>✅ Thành công:</strong> Mã xác thực đã được gửi đến email của bạn!
        </div>
    <?php endif; ?>
    
    <?php if (isset($emailError)): ?>
        <div class="message error-message">
            <strong>❌ Lỗi email:</strong> <?php echo htmlspecialchars($emailError); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="verification-form" id="verificationForm">
        <input 
            type="text" 
            name="verification_code" 
            class="code-input" 
            placeholder="000000" 
            maxlength="6" 
            pattern="[0-9]{6}"
            required
            autocomplete="off"
            autofocus
            id="codeInput"
        >
        <button type="submit" class="verify-btn" id="verifyBtn">
            <span class="btn-text">Xác Thực & Tiếp Tục</span>
        </button>
    </form>
    
    <div class="resend-section">
        <p class="resend-text">Không nhận được mã?</p>
        <button onclick="resendCode()" class="resend-btn" id="resendBtn">
            Gửi Lại Mã
        </button>
    </div>
    
    <div class="countdown" id="countdown" style="display: none;"></div>
    
    <a href="cart.php" class="back-link">
        ← Quay lại giỏ hàng
    </a>
</div>

<script>
// Auto-submit when 6 digits entered
const codeInput = document.getElementById('codeInput');
const verifyBtn = document.getElementById('verifyBtn');
const verificationForm = document.getElementById('verificationForm');

codeInput.addEventListener('input', function() {
    let value = this.value.replace(/\D/g, ''); // Only numbers
    this.value = value;
    
    // Update button text based on input
    if (value.length === 6) {
        verifyBtn.innerHTML = '<span class="btn-text">Đang xác thực<span class="loading-dots"></span></span>';
        setTimeout(() => {
            verificationForm.submit();
        }, 500);
    } else {
        verifyBtn.innerHTML = '<span class="btn-text">Xác Thực & Tiếp Tục</span>';
    }
});

// Prevent multiple submissions
verificationForm.addEventListener('submit', function() {
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<span class="btn-text">Đang xác thực<span class="loading-dots"></span></span>';
});

// Resend code function
function resendCode() {
    const resendBtn = document.getElementById('resendBtn');
    resendBtn.disabled = true;
    resendBtn.textContent = 'Đang gửi...';
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Countdown timer
<?php if (isset($_SESSION['checkout_verification_expires'])): ?>
let expiresAt = <?php echo $_SESSION['checkout_verification_expires']; ?>;
let countdownElement = document.getElementById('countdown');

function updateCountdown() {
    let now = Math.floor(Date.now() / 1000);
    let remaining = expiresAt - now;
    
    if (remaining <= 0) {
        countdownElement.textContent = '⏰ Mã xác thực đã hết hạn. Vui lòng tải lại trang.';
        countdownElement.style.display = 'block';
        codeInput.disabled = true;
        verifyBtn.disabled = true;
        return;
    }
    
    let minutes = Math.floor(remaining / 60);
    let seconds = remaining % 60;
    
    if (remaining <= 60) {
        countdownElement.textContent = `⏰ Mã hết hạn sau: ${seconds} giây`;
        countdownElement.style.display = 'block';
    } else {
        countdownElement.textContent = `⏰ Mã hết hạn sau: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        countdownElement.style.display = 'block';
    }
    
    setTimeout(updateCountdown, 1000);
}

// Start countdown after 5 minutes (show when 5 minutes left)
setTimeout(() => {
    updateCountdown();
}, (600 - 300) * 1000);
<?php endif; ?>

// Add smooth entrance animation
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.verification-container');
    
    // Initial state
    container.style.opacity = '0';
    container.style.transform = 'translateY(30px) scale(0.95)';
    
    // Animate in
    setTimeout(() => {
        container.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        container.style.opacity = '1';
        container.style.transform = 'translateY(0) scale(1)';
    }, 100);
    
    // Focus animation
    codeInput.addEventListener('focus', function() {
        this.style.transform = 'translateY(-2px) scale(1.02)';
    });
    
    codeInput.addEventListener('blur', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
    
    // Add typing sound effect (visual feedback)
    codeInput.addEventListener('input', function() {
        this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.2), 0 8px 24px rgba(102, 126, 234, 0.3)';
        setTimeout(() => {
            this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.15), 0 8px 24px rgba(102, 126, 234, 0.2)';
        }, 200);
    });
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape to go back
    if (e.key === 'Escape') {
        window.location.href = 'cart.php';
    }
    
    // Enter to submit (if 6 digits)
    if (e.key === 'Enter' && codeInput.value.length === 6) {
        verificationForm.submit();
    }
});
</script>

</body>
</html>