<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

try {
    $pdo = getDBConnection();

    // Get featured products with average rating (using stock_quantity from products table)
    $stmt = $pdo->query("SELECT p.id, p.name, p.description, p.price, p.sale_price, p.sku, 
                         p.thumbnail, p.status, p.created_at, p.category_id,
                         p.stock_quantity as stock, c.name as category_name,
                         COALESCE(p.sale_price, p.price) as final_price,
                         COALESCE(AVG(r.rating), 0) as avg_rating,
                         COUNT(DISTINCT r.id) as review_count
                         FROM products p
                         LEFT JOIN categories c ON p.category_id = c.id
                         LEFT JOIN reviews r ON p.id = r.product_id
                         WHERE p.status = 1
                         GROUP BY p.id, p.name, p.description, p.price, p.sale_price, p.sku, 
                                  p.thumbnail, p.status, p.created_at, p.category_id,
                                  p.stock_quantity, c.name
                         ORDER BY p.created_at DESC
                         LIMIT 8");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get parent categories with product count
    $categories = $pdo->query("SELECT c.id, c.name as category_name, 
                               (SELECT COUNT(*) FROM products p 
                                LEFT JOIN categories child ON p.category_id = child.id 
                                WHERE (child.id = c.id OR child.parent_id = c.id) AND p.status = 1) as count
                               FROM categories c 
                               WHERE c.parent_id IS NULL 
                               ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Error</title><style>body{font-family:Arial;padding:50px;background:#1a1a2e;color:#fff;}h1{color:#ff6b6b;}</style></head><body>";
    echo "<h1>⚠️ Database Error</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='auth.php' style='color:#667eea;'>← Back to Login</a></p>";
    echo "</body></html>";
    exit;
}

include 'includes/header.php';
?>

<style>
/* Banner Slider */
.banner-slider {
    margin: -2rem -2rem 3rem -2rem;
    position: relative;
}

.slider-container {
    position: relative;
    width: 100%;
    height: 400px;
    overflow: hidden;
    background: var(--bg-card);
    border-radius: 0;
}

.slider-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

.slide {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.6s ease-in-out;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.slide.active {
    opacity: 1;
}

.slide:nth-child(2) {
    background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
}

.slide:nth-child(3) {
    background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
}

.slide-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 8rem;
    height: 100%;
}

.slide-text {
    flex: 1;
    color: white;
    animation: slideInLeft 0.8s ease;
    padding-right: 2rem;
}

.slide.active .slide-text {
    animation: slideInLeft 0.8s ease;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.slide-text h2 {
    font-size: 3.5rem;
    font-weight: 900;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.slide-text p {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.95;
}

.slide-btn {
    display: inline-block;
    padding: 1rem 2.5rem;
    background: white;
    color: #6366f1;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.slide-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
}

.slide-image {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15rem;
    color: rgba(255, 255, 255, 0.2);
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

/* Slider Arrows */
.slider-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.25);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    width: 55px;
    height: 55px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.3rem;
    transition: all 0.3s ease;
    z-index: 100;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.4);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-50%) scale(1.15);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.slider-arrow.prev {
    left: 1.5rem;
}

.slider-arrow.next {
    right: 1.5rem;
}

/* Slider Dots */
.slider-dots {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 0.75rem;
    z-index: 10;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot:hover {
    background: rgba(255, 255, 255, 0.7);
}

.dot.active {
    width: 35px;
    border-radius: 6px;
    background: white;
}

@media (max-width: 768px) {
    .slider-container {
        height: 300px;
    }
    
    .slide-content {
        flex-direction: column;
        padding: 2rem 1rem;
        text-align: center;
    }
    
    .slide-text {
        padding-right: 0;
    }
    
    .slide-text h2 {
        font-size: 2rem;
    }
    
    .slide-text p {
        font-size: 1rem;
    }
    
    .slide-image {
        font-size: 8rem;
    }
    
    .slider-arrow {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
    
    .slider-arrow.prev {
        left: 0.75rem;
    }
    
    .slider-arrow.next {
        right: 0.75rem;
    }
}

/* Features Section */
.features-section {
    margin: 4rem 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2.5rem;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.feature-card:hover {
    transform: translateY(-10px);
    border-color: var(--primary);
    box-shadow: 0 15px 40px rgba(99, 102, 241, 0.3);
}

.feature-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
}

.feature-card h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.feature-card p {
    color: var(--text-secondary);
    line-height: 1.8;
}

/* Section Header */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.section-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
}

/* Category Showcase */
.category-showcase {
    margin: 3rem 0;
    padding: 2.5rem;
    background: var(--bg-card);
    border-radius: 24px;
    border: 1px solid var(--border);
}

.category-showcase-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.category-showcase-title i {
    color: var(--primary);
}

.category-showcase-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.category-showcase-item {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--bg-dark);
    border: 2px solid var(--border);
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.category-showcase-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.category-showcase-item:hover::before {
    transform: scaleY(1);
}

.category-showcase-item:hover {
    transform: translateX(10px);
    border-color: var(--primary);
    background: var(--bg-hover);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.category-showcase-icon {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 16px;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.category-showcase-item:hover .category-showcase-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.5);
}

.category-showcase-info {
    flex: 1;
}

.category-showcase-info h4 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.category-showcase-info p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.category-showcase-arrow {
    font-size: 1.2rem;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.category-showcase-item:hover .category-showcase-arrow {
    color: var(--primary);
    transform: translateX(5px);
}

@media (max-width: 768px) {
    .category-showcase-grid {
        grid-template-columns: 1fr;
    }
    
    .category-showcase-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Back to Top */
#backToTop {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.3s ease;
    opacity: 0;
    visibility: hidden;
}

#backToTop.show {
    opacity: 1;
    visibility: visible;
}

#backToTop:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 12px 35px rgba(99, 102, 241, 0.6);
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
    }
    
    .hero-buttons {
        flex-direction: column;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}
</style>

<!-- Banner Slider -->
<section class="banner-slider">
    <div class="slider-container">
        <div class="slider-wrapper">
            <!-- Slide 1 -->
            <div class="slide active">
                <div class="slide-content">
                    <div class="slide-text">
                        <h2>Giảm Giá Sốc 50%</h2>
                        <p>Cho tất cả sản phẩm điện thoại</p>
                        <a href="product.php?category=1" class="slide-btn">Mua ngay</a>
                    </div>
                    <div class="slide-image">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                </div>
            </div>
            
            <!-- Slide 2 -->
            <div class="slide">
                <div class="slide-content">
                    <div class="slide-text">
                        <h2>Laptop Gaming</h2>
                        <p>Hiệu năng mạnh mẽ, giá tốt nhất</p>
                        <a href="product.php?category=2" class="slide-btn">Khám phá</a>
                    </div>
                    <div class="slide-image">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>
            
            <!-- Slide 3 -->
            <div class="slide">
                <div class="slide-content">
                    <div class="slide-text">
                        <h2>Phụ Kiện Hot</h2>
                        <p>Tai nghe, sạc dự phòng giá rẻ</p>
                        <a href="product.php?category=4" class="slide-btn">Xem thêm</a>
                    </div>
                    <div class="slide-image">
                        <i class="fas fa-headphones"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation Arrows -->
        <button class="slider-arrow prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="slider-arrow next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <!-- Dots -->
        <div class="slider-dots">
            <span class="dot active" onclick="currentSlide(0)"></span>
            <span class="dot" onclick="currentSlide(1)"></span>
            <span class="dot" onclick="currentSlide(2)"></span>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <h3>Giao hàng nhanh</h3>
            <p>Đặt hàng và nhận hàng trong vòng 24h với dịch vụ giao hàng tận nơi</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-star"></i>
            </div>
            <h3>Chất lượng đảm bảo</h3>
            <p>Sản phẩm chính hãng 100%, cam kết hoàn tiền nếu không hài lòng</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Thanh toán an toàn</h3>
            <p>Hỗ trợ nhiều hình thức thanh toán, bảo mật thông tin tuyệt đối</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-gift"></i>
            </div>
            <h3>Ưu đãi hấp dẫn</h3>
            <p>Nhiều chương trình khuyến mãi và voucher giảm giá mỗi ngày</p>
        </div>
    </div>
</section>

<!-- Products Section -->
<section class="products-section">
    <div class="section-header">
        <h2 class="section-title">🔥 Sản Phẩm Nổi Bật</h2>
        <p class="section-subtitle">Khám phá những sản phẩm được yêu thích nhất</p>
    </div>
    
    <!-- Category Section -->
    <?php if (!empty($categories)): ?>
    <div class="category-showcase">
        <h3 class="category-showcase-title">
            <i class="fas fa-th-large"></i> Danh Mục Sản Phẩm
        </h3>
        <div class="category-showcase-grid">
            <?php 
            $categoryIcons = [
                'Điện thoại' => 'fa-mobile-alt',
                'Máy tính' => 'fa-laptop',
                'Tablet' => 'fa-tablet-alt',
                'Phụ kiện' => 'fa-headphones',
                'Âm thanh' => 'fa-volume-up'
            ];
            
            foreach ($categories as $cat): 
                $icon = $categoryIcons[$cat['category_name']] ?? 'fa-box';
            ?>
            <a href="product.php?category=<?php echo $cat['id']; ?>" class="category-showcase-item">
                <div class="category-showcase-icon">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="category-showcase-info">
                    <h4><?php echo htmlspecialchars($cat['category_name']); ?></h4>
                    <p><?php echo $cat['count']; ?> sản phẩm</p>
                </div>
                <i class="fas fa-arrow-right category-showcase-arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Products Grid -->
    <div class="products-grid">
        <?php foreach ($products as $product): ?>
        <div class="product-card" data-category="<?php echo htmlspecialchars($product['category_name']); ?>">
            <div class="product-image-wrapper">
                <img src="assets/images/<?php echo $product['thumbnail']; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="quick-view-btn" onclick='openModal(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                        🔍
                    </button>
                <?php else: ?>
                    <a href="auth.php?redirect=index.php" class="quick-view-btn" title="Đăng nhập để mua hàng">
                        🔒
                    </a>
                <?php endif; ?>
            </div>
            <span class="category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            
            <?php if ($product['sale_price']): ?>
                <p class="price">
                    <span class="old-price"><?php echo number_format($product['price']); ?> VNĐ</span>
                    <span class="sale-price"><?php echo number_format($product['sale_price']); ?> VNĐ</span>
                </p>
            <?php else: ?>
                <p class="price"><?php echo number_format($product['price']); ?> VNĐ</p>
            <?php endif; ?>
            
            <p class="stock">Còn: <?php echo $product['stock']; ?> sản phẩm</p>
            
            <div class="product-rating">
                <?php 
                $rating = round($product['avg_rating'], 1);
                $fullStars = floor($rating);
                $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;
                
                for ($i = 0; $i < $fullStars; $i++) {
                    echo '<span class="star filled">★</span>';
                }
                if ($halfStar) {
                    echo '<span class="star half">★</span>';
                }
                for ($i = 0; $i < $emptyStars; $i++) {
                    echo '<span class="star empty">★</span>';
                }
                ?>
                <span class="rating-text"><?php echo number_format($rating, 1); ?> (<?php echo $product['review_count']; ?>)</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 3rem;">
        <a href="product.php" class="btn btn-primary btn-large">
            <i class="fas fa-th"></i> Xem tất cả sản phẩm
        </a>
    </div>
</section>

<!-- Modal Quick View -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">×</button>
        <div class="modal-body">
            <div class="modal-image">
                <span class="modal-badge" id="modalBadge"></span>
                <img id="modalImage" src="" alt="">
            </div>
            <div class="modal-details">
                <h2 id="modalTitle"></h2>
                <p class="modal-sku">SKU: <span id="modalSku"></span></p>
                <div class="modal-price">
                    <span class="current-price" id="modalPrice"></span>
                    <span class="old-price" id="modalOldPrice"></span>
                </div>
                <span class="modal-stock" id="modalStock"></span>
                <div class="modal-description" id="modalDescription"></div>
                <form method="POST" action="cart.php" id="modalForm">
                    <input type="hidden" name="product_id" id="modalProductId">
                    <div class="modal-quantity">
                        <label>Số lượng:</label>
                        <div class="quantity-controls">
                            <button type="button" onclick="decreaseQty()">−</button>
                            <input type="number" name="quantity" id="modalQuantity" value="1" min="1" readonly>
                            <button type="button" onclick="increaseQty()">+</button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-success">THÊM VÀO GIỎ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button id="backToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Banner Slider
let currentSlideIndex = 0;
let slideInterval;

function showSlide(index) {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    
    if (index >= slides.length) currentSlideIndex = 0;
    if (index < 0) currentSlideIndex = slides.length - 1;
    
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    slides[currentSlideIndex].classList.add('active');
    dots[currentSlideIndex].classList.add('active');
}

function changeSlide(direction) {
    currentSlideIndex += direction;
    showSlide(currentSlideIndex);
    resetSlideInterval();
}

function currentSlide(index) {
    currentSlideIndex = index;
    showSlide(currentSlideIndex);
    resetSlideInterval();
}

function autoSlide() {
    currentSlideIndex++;
    showSlide(currentSlideIndex);
}

function resetSlideInterval() {
    clearInterval(slideInterval);
    slideInterval = setInterval(autoSlide, 5000);
}

// Start auto slide
slideInterval = setInterval(autoSlide, 5000);

// Back to Top
const backToTopBtn = document.getElementById('backToTop');
window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        backToTopBtn.classList.add('show');
    } else {
        backToTopBtn.classList.remove('show');
    }
});

backToTopBtn.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
</script>

<?php include 'includes/footer.php'; ?>
