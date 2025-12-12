    </div>
    
    <!-- Beautiful Footer -->
    <footer class="modern-footer">
        <div class="footer-container">
            <!-- Footer Top -->
            <div class="footer-top">
                <div class="footer-section">
                    <div class="footer-logo">
                        <h3>🛍️ <?php echo APP_NAME; ?></h3>
                        <p>Nền tảng thương mại điện tử hàng đầu Việt Nam</p>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Về chúng tôi</h4>
                    <ul>
                        <li><a href="#about">Giới thiệu</a></li>
                        <li><a href="#careers">Tuyển dụng</a></li>
                        <li><a href="#press">Báo chí</a></li>
                        <li><a href="#blog">Blog</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Hỗ trợ</h4>
                    <ul>
                        <li><a href="#help">Trung tâm trợ giúp</a></li>
                        <li><a href="#contact">Liên hệ</a></li>
                        <li><a href="#shipping">Vận chuyển</a></li>
                        <li><a href="#returns">Đổi trả</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Chính sách</h4>
                    <ul>
                        <li><a href="#privacy">Bảo mật</a></li>
                        <li><a href="#terms">Điều khoản</a></li>
                        <li><a href="#cookies">Cookie</a></li>
                        <li><a href="#security">Bảo mật</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Kết nối với chúng tôi</h4>
                    <div class="social-links">
                        <a href="#facebook" class="social-link facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#instagram" class="social-link instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#twitter" class="social-link twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#youtube" class="social-link youtube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                    <div class="newsletter">
                        <p>Đăng ký nhận tin tức mới nhất</p>
                        <div class="newsletter-form">
                            <input type="email" placeholder="Email của bạn">
                            <button type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="footer-bottom-left">
                    <p>&copy; 2024 <?php echo APP_NAME; ?>. Tất cả quyền được bảo lưu.</p>
                </div>
                <div class="footer-bottom-right">
                    <div class="payment-methods">
                        <span>Phương thức thanh toán:</span>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-paypal"></i>
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Floating Back to Top Button -->
        <button class="back-to-top" id="backToTop">
            <i class="fas fa-arrow-up"></i>
        </button>
    </footer>

    <style>
    .modern-footer {
        background: linear-gradient(135deg, #1a1f3a 0%, #0a0e27 100%);
        color: #e2e8f0;
        margin-top: 60px;
        position: relative;
        overflow: hidden;
    }

    .modern-footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6, #ec4899, transparent);
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .footer-top {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1.5fr;
        gap: 40px;
        padding: 60px 0 40px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .footer-section h3,
    .footer-section h4 {
        margin-bottom: 20px;
        color: #fff;
        font-weight: 700;
    }

    .footer-logo h3 {
        font-size: 28px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 12px;
    }

    .footer-logo p {
        color: #94a3b8;
        font-size: 16px;
        line-height: 1.6;
    }

    .footer-section ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-section ul li {
        margin-bottom: 12px;
    }

    .footer-section ul li a {
        color: #94a3b8;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
        position: relative;
    }

    .footer-section ul li a:hover {
        color: #6366f1;
        transform: translateX(5px);
    }

    .footer-section ul li a::before {
        content: '';
        position: absolute;
        left: -15px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 2px;
        background: #6366f1;
        transition: width 0.3s ease;
    }

    .footer-section ul li a:hover::before {
        width: 10px;
    }

    .social-links {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
    }

    .social-link {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .social-link:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }

    .social-link.facebook {
        background: linear-gradient(135deg, rgba(24, 119, 242, 0.2), rgba(24, 119, 242, 0.1));
        color: #1877f2;
    }

    .social-link.instagram {
        background: linear-gradient(135deg, rgba(225, 48, 108, 0.2), rgba(225, 48, 108, 0.1));
        color: #e1306c;
    }

    .social-link.twitter {
        background: linear-gradient(135deg, rgba(29, 161, 242, 0.2), rgba(29, 161, 242, 0.1));
        color: #1da1f2;
    }

    .social-link.youtube {
        background: linear-gradient(135deg, rgba(255, 0, 0, 0.2), rgba(255, 0, 0, 0.1));
        color: #ff0000;
    }

    .newsletter p {
        color: #94a3b8;
        font-size: 14px;
        margin-bottom: 12px;
    }

    .newsletter-form {
        display: flex;
        gap: 8px;
    }

    .newsletter-form input {
        flex: 1;
        padding: 12px 16px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        font-size: 14px;
        backdrop-filter: blur(10px);
    }

    .newsletter-form input::placeholder {
        color: #64748b;
    }

    .newsletter-form input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .newsletter-form button {
        padding: 12px 16px;
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .newsletter-form button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
    }

    .footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 30px 0;
        flex-wrap: wrap;
        gap: 20px;
    }

    .footer-bottom-left p {
        color: #64748b;
        font-size: 14px;
        margin: 0;
    }

    .payment-methods {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: #94a3b8;
    }

    .payment-icons {
        display: flex;
        gap: 8px;
    }

    .payment-icons i {
        font-size: 24px;
        color: #6366f1;
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }

    .payment-icons i:hover {
        opacity: 1;
    }

    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border: none;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        opacity: 0;
        visibility: hidden;
        z-index: 1000;
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
    }

    .back-to-top.visible {
        opacity: 1;
        visibility: visible;
    }

    .back-to-top:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .footer-top {
            grid-template-columns: 1fr;
            gap: 30px;
            padding: 40px 0 30px;
        }

        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }

        .payment-methods {
            flex-direction: column;
            gap: 8px;
        }

        .social-links {
            justify-content: center;
        }

        .newsletter-form {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .footer-container {
            padding: 0 15px;
        }

        .footer-top {
            padding: 30px 0 20px;
        }

        .footer-bottom {
            padding: 20px 0;
        }
    }
    </style>
    <script src="assets/js/main.js"></script>
    
    <!-- Footer JavaScript -->
    <script>
        // Back to Top Button
        const backToTopBtn = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Newsletter Form
        const newsletterForm = document.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = this.querySelector('input[type="email"]').value;
                
                if (email) {
                    // Show success message
                    const button = this.querySelector('button');
                    const originalHTML = button.innerHTML;
                    
                    button.innerHTML = '<i class="fas fa-check"></i>';
                    button.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
                        this.querySelector('input[type="email"]').value = '';
                    }, 2000);
                    
                    // Here you would typically send the email to your server
                    console.log('Newsletter subscription:', email);
                }
            });
        }
        
        // Social Links Analytics (optional)
        document.querySelectorAll('.social-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const platform = this.classList[1]; // facebook, instagram, etc.
                console.log('Social link clicked:', platform);
                
                // Add your social media URLs here
                const socialUrls = {
                    facebook: 'https://facebook.com/yourpage',
                    instagram: 'https://instagram.com/yourpage',
                    twitter: 'https://twitter.com/yourpage',
                    youtube: 'https://youtube.com/yourchannel'
                };
                
                if (socialUrls[platform]) {
                    window.open(socialUrls[platform], '_blank');
                }
            });
        });
        
        // Footer Links Smooth Scroll (for anchor links)
        document.querySelectorAll('.footer-section a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    
    <script>
        let maxQuantity = 1;
        
        function openModal(product) {
            const modal = document.getElementById('productModal');
            maxQuantity = product.stock;
            
            // Set badge
            if (product.sale_price) {
                const discount = Math.round((1 - product.sale_price / product.price) * 100);
                document.getElementById('modalBadge').textContent = `-${discount}%`;
                document.getElementById('modalBadge').style.display = 'block';
            } else {
                document.getElementById('modalBadge').style.display = 'none';
            }
            
            // Set image
            document.getElementById('modalImage').src = 'assets/images/' + product.thumbnail;
            
            // Set details
            document.getElementById('modalTitle').textContent = product.name;
            document.getElementById('modalSku').textContent = product.sku || 'N/A';
            
            // Set price
            const currentPrice = product.sale_price || product.price;
            document.getElementById('modalPrice').textContent = new Intl.NumberFormat('vi-VN').format(currentPrice) + ' VNĐ';
            
            if (product.sale_price) {
                document.getElementById('modalOldPrice').textContent = new Intl.NumberFormat('vi-VN').format(product.price) + ' VNĐ';
                document.getElementById('modalOldPrice').style.display = 'inline';
            } else {
                document.getElementById('modalOldPrice').style.display = 'none';
            }
            
            // Set stock
            document.getElementById('modalStock').textContent = 'Còn ' + product.stock + ' sản phẩm';
            
            // Set description
            document.getElementById('modalDescription').textContent = product.description || 'Sản phẩm chất lượng cao, chính hãng 100%';
            
            // Set form
            document.getElementById('modalProductId').value = product.id;
            document.getElementById('modalQuantity').value = 1;
            document.getElementById('modalQuantity').max = product.stock;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            const modal = document.getElementById('productModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function increaseQty() {
            const input = document.getElementById('modalQuantity');
            const current = parseInt(input.value);
            if (current < maxQuantity) {
                input.value = current + 1;
            }
        }
        
        function decreaseQty() {
            const input = document.getElementById('modalQuantity');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
