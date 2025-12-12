<?php
/**
 * Simple Gmail SMTP Mailer without dependencies
 */

class SimpleGmailMailer {
    private $smtp_host;
    private $smtp_port;
    private $username;
    private $password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Load environment variables
        if (file_exists(__DIR__ . '/../../.env')) {
            $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
        
        $this->smtp_host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtp_port = $_ENV['SMTP_PORT'] ?? 587;
        $this->username = $_ENV['SMTP_USERNAME'] ?? 'ema03106@gmail.com';
        $this->password = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->from_email = $_ENV['SMTP_FROM_EMAIL'] ?? 'ema03106@gmail.com';
        $this->from_name = $_ENV['SMTP_FROM_NAME'] ?? 'Tech Store';
    }
    
    public function sendEmail($to, $subject, $body) {
        $socket = fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        try {
            // Read initial response
            $this->readResponse($socket);
            
            // EHLO
            fwrite($socket, "EHLO localhost\r\n");
            $this->readResponse($socket);
            
            // STARTTLS
            fwrite($socket, "STARTTLS\r\n");
            $this->readResponse($socket);
            
            // Enable crypto
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // EHLO again after TLS
            fwrite($socket, "EHLO localhost\r\n");
            $this->readResponse($socket);
            
            // AUTH LOGIN
            fwrite($socket, "AUTH LOGIN\r\n");
            $this->readResponse($socket);
            
            // Username
            fwrite($socket, base64_encode($this->username) . "\r\n");
            $this->readResponse($socket);
            
            // Password
            fwrite($socket, base64_encode($this->password) . "\r\n");
            $response = $this->readResponse($socket);
            
            if (strpos($response, '235') === false) {
                error_log("SMTP Auth failed: $response");
                fclose($socket);
                return false;
            }
            
            // MAIL FROM
            fwrite($socket, "MAIL FROM: <{$this->from_email}>\r\n");
            $this->readResponse($socket);
            
            // RCPT TO
            fwrite($socket, "RCPT TO: <$to>\r\n");
            $this->readResponse($socket);
            
            // DATA
            fwrite($socket, "DATA\r\n");
            $this->readResponse($socket);
            
            // Email headers and body
            $email_data = "From: {$this->from_name} <{$this->from_email}>\r\n";
            $email_data .= "To: $to\r\n";
            $email_data .= "Subject: $subject\r\n";
            $email_data .= "MIME-Version: 1.0\r\n";
            $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email_data .= "\r\n";
            $email_data .= $body;
            $email_data .= "\r\n.\r\n";
            
            fwrite($socket, $email_data);
            $response = $this->readResponse($socket);
            
            // QUIT
            fwrite($socket, "QUIT\r\n");
            $this->readResponse($socket);
            
            fclose($socket);
            
            if (strpos($response, '250') !== false) {
                error_log("Email sent successfully to: $to");
                return true;
            } else {
                error_log("Email send failed: $response");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            fclose($socket);
            return false;
        }
    }
    
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
    
    public function sendVerificationCode($email, $code) {
        $subject = 'Mã xác thực đặt hàng - Tech Store';
        $body = $this->getVerificationTemplate($code);
        return $this->sendEmail($email, $subject, $body);
    }
    
    public function sendOrderConfirmation($email, $orderData) {
        $subject = "Xác nhận đơn hàng #{$orderData['order_id']} - Tech Store";
        $body = $this->getOrderTemplate($orderData);
        return $this->sendEmail($email, $subject, $body);
    }
    
    public function sendOrderCompleted($email, $orderData) {
        $subject = "Đơn hàng #{$orderData['order_id']} đã hoàn thành - Tech Store";
        $body = $this->getOrderCompletedTemplate($orderData);
        return $this->sendEmail($email, $subject, $body);
    }
    
    private function getVerificationTemplate($code) {
        return "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Mã Xác Thực - Tech Store</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                    margin: 0;
                    padding: 40px 20px;
                    min-height: 100vh;
                }
                
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: rgba(30, 41, 59, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 
                        0 32px 64px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                }
                
                .header {
                    background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
                    padding: 48px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: 
                        radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
                    pointer-events: none;
                }
                
                .header-icon {
                    width: 80px;
                    height: 80px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 36px;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
                }
                
                .header h1 {
                    color: white;
                    font-size: 32px;
                    font-weight: 700;
                    margin-bottom: 8px;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                }
                
                .header p {
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 18px;
                    font-weight: 500;
                }
                
                .content {
                    padding: 48px 40px;
                    color: #e2e8f0;
                }
                
                .greeting {
                    font-size: 24px;
                    font-weight: 600;
                    color: #f8fafc;
                    margin-bottom: 24px;
                }
                
                .description {
                    font-size: 16px;
                    line-height: 1.6;
                    color: #94a3b8;
                    margin-bottom: 32px;
                }
                
                .code-section {
                    text-align: center;
                    margin: 40px 0;
                }
                
                .code-label {
                    font-size: 16px;
                    color: #cbd5e1;
                    margin-bottom: 16px;
                    font-weight: 500;
                }
                
                .code-box {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
                    border: 2px solid rgba(99, 102, 241, 0.3);
                    border-radius: 20px;
                    padding: 32px;
                    margin: 24px 0;
                    backdrop-filter: blur(10px);
                    box-shadow: 
                        0 8px 24px rgba(99, 102, 241, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
                }
                
                .code {
                    font-size: 48px;
                    font-weight: 700;
                    color: #a5b4fc;
                    letter-spacing: 8px;
                    font-family: 'Courier New', monospace;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                }
                
                .instructions {
                    background: rgba(15, 23, 42, 0.6);
                    border-radius: 16px;
                    padding: 24px;
                    margin: 32px 0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                }
                
                .instructions h3 {
                    color: #f1f5f9;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .instructions ul {
                    list-style: none;
                    padding: 0;
                }
                
                .instructions li {
                    color: #94a3b8;
                    font-size: 15px;
                    line-height: 1.5;
                    margin-bottom: 8px;
                    padding-left: 24px;
                    position: relative;
                }
                
                .instructions li::before {
                    content: '•';
                    color: #6366f1;
                    font-size: 20px;
                    position: absolute;
                    left: 0;
                    top: -2px;
                }
                
                .footer {
                    background: rgba(15, 23, 42, 0.8);
                    padding: 32px 40px;
                    text-align: center;
                    border-top: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .footer-content {
                    color: #64748b;
                    font-size: 14px;
                    line-height: 1.5;
                }
                
                .footer-brand {
                    color: #94a3b8;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                
                .footer-contact {
                    color: #6366f1;
                    text-decoration: none;
                }
                
                @media (max-width: 640px) {
                    body { padding: 20px 10px; }
                    .email-container { border-radius: 16px; }
                    .header, .content { padding: 32px 24px; }
                    .header h1 { font-size: 28px; }
                    .code { font-size: 36px; letter-spacing: 4px; }
                    .code-box { padding: 24px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <div class='header-icon'>🔐</div>
                    <h1>Mã Xác Thực</h1>
                    <p>Tech Store</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Xin chào!</div>
                    
                    <div class='description'>
                        Bạn đã yêu cầu mã xác thực để đặt hàng tại <strong>Tech Store</strong>.<br>
                        Vui lòng sử dụng mã bên dưới để hoàn tất quá trình xác thực email của bạn.
                    </div>
                    
                    <div class='code-section'>
                        <div class='code-label'>Mã xác thực của bạn:</div>
                        <div class='code-box'>
                            <div class='code'>$code</div>
                        </div>
                    </div>
                    
                    <div class='instructions'>
                        <h3>⚠️ Lưu ý quan trọng</h3>
                        <ul>
                            <li>Mã xác thực có hiệu lực trong <strong>10 phút</strong></li>
                            <li>Không chia sẻ mã này với bất kỳ ai khác</li>
                            <li>Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email</li>
                            <li>Để bảo mật, mã chỉ sử dụng được một lần duy nhất</li>
                        </ul>
                    </div>
                </div>
                
                <div class='footer'>
                    <div class='footer-brand'>© 2025 Tech Store</div>
                    <div class='footer-content'>
                        Mọi quyền được bảo lưu.<br>
                        Liên hệ: <a href='mailto:ema03106@gmail.com' class='footer-contact'>ema03106@gmail.com</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getOrderTemplate($orderData) {
        $itemsHtml = '';
        foreach ($orderData['items'] as $item) {
            $itemsHtml .= "
                <tr>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); color: #e2e8f0; font-weight: 500;'>{$item['name']}</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center; color: #94a3b8;'>{$item['quantity']}</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: #94a3b8;'>" . number_format($item['price']) . "₫</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: #a5b4fc; font-weight: 600;'>" . number_format($item['price'] * $item['quantity']) . "₫</td>
                </tr>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Xác Nhận Đơn Hàng - Tech Store</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                    margin: 0;
                    padding: 40px 20px;
                    min-height: 100vh;
                }
                
                .email-container {
                    max-width: 700px;
                    margin: 0 auto;
                    background: rgba(30, 41, 59, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 
                        0 32px 64px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                }
                
                .header {
                    background: linear-gradient(135deg, #059669, #10b981, #34d399);
                    padding: 48px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: 
                        radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
                    pointer-events: none;
                }
                
                .header-icon {
                    width: 80px;
                    height: 80px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 36px;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
                }
                
                .header h1 {
                    color: white;
                    font-size: 32px;
                    font-weight: 700;
                    margin-bottom: 8px;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                }
                
                .header p {
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 18px;
                    font-weight: 500;
                }
                
                .content {
                    padding: 48px 40px;
                    color: #e2e8f0;
                }
                
                .greeting {
                    font-size: 24px;
                    font-weight: 600;
                    color: #f8fafc;
                    margin-bottom: 24px;
                }
                
                .description {
                    font-size: 16px;
                    line-height: 1.6;
                    color: #94a3b8;
                    margin-bottom: 32px;
                }
                
                .order-info {
                    background: rgba(15, 23, 42, 0.6);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 32px 0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                }
                
                .order-info h3 {
                    color: #f1f5f9;
                    font-size: 20px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .order-detail {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                    margin-bottom: 16px;
                }
                
                .order-detail-item {
                    background: rgba(99, 102, 241, 0.1);
                    padding: 16px;
                    border-radius: 12px;
                    border: 1px solid rgba(99, 102, 241, 0.2);
                }
                
                .order-detail-label {
                    color: #94a3b8;
                    font-size: 14px;
                    font-weight: 500;
                    margin-bottom: 4px;
                }
                
                .order-detail-value {
                    color: #f8fafc;
                    font-size: 16px;
                    font-weight: 600;
                }
                
                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
                    color: #86efac;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                    border: 1px solid rgba(34, 197, 94, 0.3);
                }
                
                .products-section {
                    margin: 40px 0;
                }
                
                .products-title {
                    color: #f1f5f9;
                    font-size: 20px;
                    font-weight: 600;
                    margin-bottom: 24px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .products-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: rgba(15, 23, 42, 0.4);
                    border-radius: 16px;
                    overflow: hidden;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .products-table th {
                    background: rgba(99, 102, 241, 0.1);
                    padding: 16px;
                    text-align: left;
                    color: #c7d2fe;
                    font-weight: 600;
                    font-size: 14px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .products-table th:last-child,
                .products-table td:last-child {
                    text-align: right;
                }
                
                .products-table th:nth-child(2),
                .products-table td:nth-child(2) {
                    text-align: center;
                }
                
                .total-row {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
                    border-top: 2px solid rgba(99, 102, 241, 0.3);
                }
                
                .total-row td {
                    padding: 20px 16px !important;
                    font-weight: 700 !important;
                    font-size: 18px !important;
                    color: #a5b4fc !important;
                }
                
                .next-steps {
                    background: rgba(15, 23, 42, 0.6);
                    border-radius: 16px;
                    padding: 24px;
                    margin: 32px 0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                }
                
                .next-steps h3 {
                    color: #f1f5f9;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .next-steps p {
                    color: #94a3b8;
                    font-size: 15px;
                    line-height: 1.6;
                    margin-bottom: 12px;
                }
                
                .contact-info {
                    color: #6366f1;
                    font-weight: 600;
                    text-decoration: none;
                }
                
                .footer {
                    background: rgba(15, 23, 42, 0.8);
                    padding: 32px 40px;
                    text-align: center;
                    border-top: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .footer-content {
                    color: #64748b;
                    font-size: 14px;
                    line-height: 1.5;
                }
                
                .footer-brand {
                    color: #94a3b8;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                
                .footer-contact {
                    color: #6366f1;
                    text-decoration: none;
                }
                
                @media (max-width: 640px) {
                    body { padding: 20px 10px; }
                    .email-container { border-radius: 16px; }
                    .header, .content { padding: 32px 24px; }
                    .header h1 { font-size: 28px; }
                    .order-detail { grid-template-columns: 1fr; }
                    .products-table th, .products-table td { padding: 12px 8px; font-size: 13px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <div class='header-icon'>🎉</div>
                    <h1>Xác Nhận Đơn Hàng</h1>
                    <p>Cảm ơn bạn đã mua sắm tại Tech Store</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Xin chào {$orderData['customer_name']}!</div>
                    
                    <div class='description'>
                        Cảm ơn bạn đã đặt hàng tại <strong>Tech Store</strong>. Đơn hàng của bạn đã được xác nhận thành công và đang được xử lý.
                    </div>
                    
                    <div class='order-info'>
                        <h3>📋 Thông tin đơn hàng</h3>
                        <div class='order-detail'>
                            <div class='order-detail-item'>
                                <div class='order-detail-label'>Mã đơn hàng</div>
                                <div class='order-detail-value'>#{$orderData['order_id']}</div>
                            </div>
                            <div class='order-detail-item'>
                                <div class='order-detail-label'>Ngày đặt hàng</div>
                                <div class='order-detail-value'>{$orderData['order_date']}</div>
                            </div>
                        </div>
                        <div class='status-badge'>
                            ⏳ Đang xử lý
                        </div>
                    </div>
                    
                    <div class='products-section'>
                        <div class='products-title'>🛍️ Sản phẩm đã đặt</div>
                        <table class='products-table'>
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Số lượng</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                $itemsHtml
                                <tr class='total-row'>
                                    <td colspan='3'>Tổng cộng:</td>
                                    <td>" . number_format($orderData['total_price']) . "₫</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class='next-steps'>
                        <h3>📦 Bước tiếp theo</h3>
                        <p>Chúng tôi sẽ thông báo cho bạn ngay khi đơn hàng được giao cho đơn vị vận chuyển.</p>
                        <p>Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ với chúng tôi qua email: <a href='mailto:ema03106@gmail.com' class='contact-info'>ema03106@gmail.com</a></p>
                    </div>
                </div>
                
                <div class='footer'>
                    <div class='footer-brand'>© 2025 Tech Store</div>
                    <div class='footer-content'>
                        Mọi quyền được bảo lưu.<br>
                        Liên hệ: <a href='mailto:ema03106@gmail.com' class='footer-contact'>ema03106@gmail.com</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getOrderCompletedTemplate($orderData) {
        $itemsHtml = '';
        foreach ($orderData['items'] as $item) {
            $itemsHtml .= "
                <tr>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); color: #e2e8f0; font-weight: 500;'>{$item['name']}</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center; color: #94a3b8;'>{$item['quantity']}</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: #94a3b8;'>" . number_format($item['price']) . "₫</td>
                    <td style='padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: #86efac; font-weight: 600;'>" . number_format($item['price'] * $item['quantity']) . "₫</td>
                </tr>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Đơn Hàng Hoàn Thành - Tech Store</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                    margin: 0;
                    padding: 40px 20px;
                    min-height: 100vh;
                }
                
                .email-container {
                    max-width: 700px;
                    margin: 0 auto;
                    background: rgba(30, 41, 59, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 
                        0 32px 64px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                }
                
                .header {
                    background: linear-gradient(135deg, #10b981, #059669, #047857);
                    padding: 48px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: 
                        radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%);
                    pointer-events: none;
                }
                
                .header-icon {
                    width: 96px;
                    height: 96px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 48px;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
                    animation: celebration 2s ease-in-out infinite;
                }
                
                @keyframes celebration {
                    0%, 100% { transform: scale(1) rotate(0deg); }
                    25% { transform: scale(1.05) rotate(-5deg); }
                    75% { transform: scale(1.05) rotate(5deg); }
                }
                
                .header h1 {
                    color: white;
                    font-size: 36px;
                    font-weight: 700;
                    margin-bottom: 8px;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                }
                
                .header p {
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 18px;
                    font-weight: 500;
                }
                
                .content {
                    padding: 48px 40px;
                    color: #e2e8f0;
                }
                
                .greeting {
                    font-size: 28px;
                    font-weight: 600;
                    color: #f8fafc;
                    margin-bottom: 24px;
                    text-align: center;
                }
                
                .celebration-message {
                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
                    border: 1px solid rgba(16, 185, 129, 0.3);
                    border-radius: 20px;
                    padding: 32px;
                    margin: 32px 0;
                    text-align: center;
                    backdrop-filter: blur(10px);
                    box-shadow: 
                        0 8px 24px rgba(16, 185, 129, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
                }
                
                .celebration-message h2 {
                    color: #86efac;
                    font-size: 24px;
                    font-weight: 700;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                }
                
                .celebration-message p {
                    color: #94a3b8;
                    font-size: 16px;
                    line-height: 1.6;
                }
                
                .order-info {
                    background: rgba(15, 23, 42, 0.6);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 32px 0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                }
                
                .order-info h3 {
                    color: #f1f5f9;
                    font-size: 20px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .order-detail {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 16px;
                    margin-bottom: 16px;
                }
                
                .order-detail-item {
                    background: rgba(16, 185, 129, 0.1);
                    padding: 16px;
                    border-radius: 12px;
                    border: 1px solid rgba(16, 185, 129, 0.2);
                    text-align: center;
                }
                
                .order-detail-label {
                    color: #94a3b8;
                    font-size: 14px;
                    font-weight: 500;
                    margin-bottom: 4px;
                }
                
                .order-detail-value {
                    color: #86efac;
                    font-size: 16px;
                    font-weight: 600;
                }
                
                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
                    color: #86efac;
                    padding: 12px 24px;
                    border-radius: 24px;
                    font-size: 16px;
                    font-weight: 700;
                    border: 1px solid rgba(16, 185, 129, 0.3);
                    margin: 0 auto;
                    display: flex;
                    justify-content: center;
                }
                
                .products-section {
                    margin: 40px 0;
                }
                
                .products-title {
                    color: #f1f5f9;
                    font-size: 20px;
                    font-weight: 600;
                    margin-bottom: 24px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .products-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: rgba(15, 23, 42, 0.4);
                    border-radius: 16px;
                    overflow: hidden;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .products-table th {
                    background: rgba(16, 185, 129, 0.1);
                    padding: 16px;
                    text-align: left;
                    color: #86efac;
                    font-weight: 600;
                    font-size: 14px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .products-table th:last-child,
                .products-table td:last-child {
                    text-align: right;
                }
                
                .products-table th:nth-child(2),
                .products-table td:nth-child(2) {
                    text-align: center;
                }
                
                .total-row {
                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
                    border-top: 2px solid rgba(16, 185, 129, 0.3);
                }
                
                .total-row td {
                    padding: 20px 16px !important;
                    font-weight: 700 !important;
                    font-size: 18px !important;
                    color: #86efac !important;
                }
                
                .thank-you-section {
                    background: rgba(15, 23, 42, 0.6);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 32px 0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    text-align: center;
                }
                
                .thank-you-section h3 {
                    color: #f1f5f9;
                    font-size: 24px;
                    font-weight: 700;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                }
                
                .thank-you-section p {
                    color: #94a3b8;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 16px;
                }
                
                .rating-request {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
                    border: 1px solid rgba(99, 102, 241, 0.3);
                    border-radius: 16px;
                    padding: 24px;
                    margin: 24px 0;
                    text-align: center;
                }
                
                .rating-request h4 {
                    color: #a5b4fc;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 12px;
                }
                
                .rating-request p {
                    color: #94a3b8;
                    font-size: 15px;
                    line-height: 1.5;
                }
                
                .contact-info {
                    color: #6366f1;
                    font-weight: 600;
                    text-decoration: none;
                }
                
                .footer {
                    background: rgba(15, 23, 42, 0.8);
                    padding: 32px 40px;
                    text-align: center;
                    border-top: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .footer-content {
                    color: #64748b;
                    font-size: 14px;
                    line-height: 1.5;
                }
                
                .footer-brand {
                    color: #94a3b8;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                
                .footer-contact {
                    color: #6366f1;
                    text-decoration: none;
                }
                
                @media (max-width: 640px) {
                    body { padding: 20px 10px; }
                    .email-container { border-radius: 16px; }
                    .header, .content { padding: 32px 24px; }
                    .header h1 { font-size: 28px; }
                    .order-detail { grid-template-columns: 1fr; }
                    .products-table th, .products-table td { padding: 12px 8px; font-size: 13px; }
                    .greeting { font-size: 24px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <div class='header-icon'>🎊</div>
                    <h1>Đơn Hàng Hoàn Thành!</h1>
                    <p>Cảm ơn bạn đã tin tưởng Tech Store</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Chúc mừng {$orderData['customer_name']}!</div>
                    
                    <div class='celebration-message'>
                        <h2>🎉 Đơn hàng đã được giao thành công!</h2>
                        <p>Đơn hàng <strong>#{$orderData['order_id']}</strong> của bạn đã được giao đến địa chỉ thành công. Hy vọng bạn hài lòng với sản phẩm đã nhận được.</p>
                    </div>
                    
                    <div class='order-info'>
                        <h3>📦 Thông tin giao hàng</h3>
                        <div class='order-detail'>
                            <div class='order-detail-item'>
                                <div class='order-detail-label'>Mã đơn hàng</div>
                                <div class='order-detail-value'>#{$orderData['order_id']}</div>
                            </div>
                            <div class='order-detail-item'>
                                <div class='order-detail-label'>Ngày đặt hàng</div>
                                <div class='order-detail-value'>{$orderData['order_date']}</div>
                            </div>
                            <div class='order-detail-item'>
                                <div class='order-detail-label'>Ngày hoàn thành</div>
                                <div class='order-detail-value'>" . date('d/m/Y H:i') . "</div>
                            </div>
                        </div>
                        <div class='status-badge'>
                            ✅ Đã hoàn thành
                        </div>
                    </div>
                    
                    <div class='products-section'>
                        <div class='products-title'>🛍️ Sản phẩm đã nhận</div>
                        <table class='products-table'>
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Số lượng</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                $itemsHtml
                                <tr class='total-row'>
                                    <td colspan='3'>Tổng cộng đã thanh toán:</td>
                                    <td>" . number_format($orderData['total_price']) . "₫</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class='thank-you-section'>
                        <h3>💝 Cảm ơn bạn!</h3>
                        <p>Cảm ơn bạn đã lựa chọn <strong>Tech Store</strong> làm đối tác mua sắm. Sự tin tưởng của bạn là động lực để chúng tôi không ngừng cải thiện chất lượng dịch vụ.</p>
                        <p>Chúng tôi hy vọng sẽ được phục vụ bạn trong những lần mua sắm tiếp theo!</p>
                        
                        <div class='rating-request'>
                            <h4>⭐ Đánh giá sản phẩm</h4>
                            <p>Hãy chia sẻ trải nghiệm của bạn để giúp chúng tôi phục vụ tốt hơn và hỗ trợ khách hàng khác đưa ra quyết định mua sắm phù hợp.</p>
                        </div>
                        
                        <p>Nếu có bất kỳ thắc mắc nào về sản phẩm, vui lòng liên hệ: <a href='mailto:ema03106@gmail.com' class='contact-info'>ema03106@gmail.com</a></p>
                    </div>
                </div>
                
                <div class='footer'>
                    <div class='footer-brand'>© 2025 Tech Store</div>
                    <div class='footer-content'>
                        Cảm ơn bạn đã tin tưởng và lựa chọn chúng tôi!<br>
                        Liên hệ: <a href='mailto:ema03106@gmail.com' class='footer-contact'>ema03106@gmail.com</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

// Global function to get simple mailer
function getSimpleMailer() {
    return new SimpleGmailMailer();
}