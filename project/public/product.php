<?php
session_start();
require_once 'config/constants.php';
require_once 'config/db.php';

$pdo = getDBConnection();

// Get filters
$categoryId = $_GET['category'] ?? null;
$searchQuery = $_GET['search'] ?? null;

// Build query with average rating
$sql = "SELECT p.id, p.name, p.description, p.price, p.sale_price, p.sku, 
        p.thumbnail, p.status, p.created_at, p.category_id,
        p.stock_quantity as stock, c.name as category_name,
        COALESCE(p.sale_price, p.price) as final_price,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.id) as review_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.status = 1";

if ($categoryId) {
    // Check if it's a parent category
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$categoryId]);
    $childCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($childCategories)) {
        // Parent category - show all products from child categories
        $placeholders = implode(',', array_fill(0, count($childCategories), '?'));
        $sql .= " AND p.category_id IN ($placeholders)";
    } else {
        // Child category - show only products from this category
        $sql .= " AND p.category_id = :category_id";
    }
}

// Smart search with multiple keywords
if ($searchQuery) {
    $keywords = explode(' ', trim($searchQuery));
    $searchConditions = [];
    
    foreach ($keywords as $index => $keyword) {
        if (!empty($keyword)) {
            $paramName = ':search' . $index;
            $searchConditions[] = "(p.name LIKE $paramName OR p.description LIKE $paramName OR p.sku LIKE $paramName OR c.name LIKE $paramName)";
        }
    }
    
    if (!empty($searchConditions)) {
        $sql .= " AND (" . implode(' AND ', $searchConditions) . ")";
    }
}

$sql .= " GROUP BY p.id, p.name, p.description, p.price, p.sale_price, p.sku, 
                  p.thumbnail, p.status, p.created_at, p.category_id,
                  p.stock_quantity, c.name";

// Order by relevance if searching
if ($searchQuery) {
    $sql .= " ORDER BY 
              CASE 
                WHEN p.name LIKE :search_exact THEN 1
                WHEN p.name LIKE :search_start THEN 2
                ELSE 3
              END,
              p.created_at DESC";
} else {
    $sql .= " ORDER BY p.created_at DESC";
}

$stmt = $pdo->prepare($sql);

if ($categoryId) {
    // Check if it's a parent category
    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $checkStmt->execute([$categoryId]);
    $childCategories = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($childCategories)) {
        // Bind child category IDs
        foreach ($childCategories as $index => $childId) {
            $stmt->bindValue($index + 1, $childId, PDO::PARAM_INT);
        }
    } else {
        // Bind single category ID
        $stmt->bindParam(':category_id', $categoryId);
    }
}

if ($searchQuery) {
    $keywords = explode(' ', trim($searchQuery));
    foreach ($keywords as $index => $keyword) {
        if (!empty($keyword)) {
            $paramName = ':search' . $index;
            $searchParam = '%' . $keyword . '%';
            $stmt->bindParam($paramName, $searchParam);
        }
    }
    
    // For relevance ordering
    $exactMatch = $searchQuery;
    $startMatch = $searchQuery . '%';
    $stmt->bindParam(':search_exact', $exactMatch);
    $stmt->bindParam(':search_start', $startMatch);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parent categories with their children
$parentCategories = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter (both parent and child)
$allCategories = [];
foreach ($parentCategories as $parent) {
    $allCategories[] = $parent;
    
    // Get child categories
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
    $stmt->execute([$parent['id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($children as $child) {
        $child['is_child'] = true;
        $allCategories[] = $child;
    }
}

include 'includes/header.php';
?>

<style>
.search-section {
    margin-bottom: 2rem;
}

.search-form {
    display: flex;
    gap: 1rem;
    max-width: 700px;
    margin: 0 auto;
}

.search-input-wrapper {
    flex: 1;
    position: relative;
}

.search-icon-input {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    z-index: 1;
}

.search-form input {
    width: 100%;
    padding: 1rem 3.5rem 1rem 3rem;
    border: 2px solid var(--border);
    border-radius: 50px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.search-form input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    transform: translateY(-2px);
}

.clear-search {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    transition: all 0.3s ease;
}

.clear-search:hover {
    color: var(--danger);
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-card);
    border: 2px solid var(--border);
    border-radius: 15px;
    margin-top: 0.5rem;
    max-height: 300px;
    overflow-y: auto;
    display: none;
    z-index: 100;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.search-suggestions.show {
    display: block;
}

.suggestion-item {
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--border);
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item:hover,
.suggestion-item.active {
    background: var(--bg-hover);
}

.suggestion-item strong {
    color: var(--primary);
}

.search-form button {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.search-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.search-result-info {
    text-align: center;
    margin: 1.5rem 0;
    padding: 1rem;
    background: var(--bg-card);
    border-radius: 15px;
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.search-result-info strong {
    color: var(--primary);
}

.clear-search-link {
    color: var(--danger);
    text-decoration: none;
    margin-left: 1rem;
    transition: all 0.3s ease;
}

.clear-search-link:hover {
    text-decoration: underline;
}

/* Category Filter with Icons */
.category-filter-wrapper {
    margin: 2rem 0;
    padding: 2rem;
    background: var(--bg-card);
    border-radius: 20px;
    border: 1px solid var(--border);
}

.filter-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.5rem 1rem;
    background: var(--bg-dark);
    border: 2px solid var(--border);
    border-radius: 15px;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all 0.3s ease;
    cursor: pointer;
}

.category-item:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    background: var(--bg-hover);
}

.category-item.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: var(--primary);
    color: white;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.category-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border-radius: 50%;
    font-size: 1.8rem;
    color: var(--primary);
    transition: all 0.3s ease;
}

.category-item:hover .category-icon {
    transform: scale(1.1);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
}

.category-item.active .category-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.category-item span {
    font-size: 0.9rem;
    font-weight: 600;
    text-align: center;
}

/* Subcategory Section */
.subcategory-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border);
}

.subcategory-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.subcategory-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.subcategory-item {
    padding: 0.75rem 1.5rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 25px;
    text-decoration: none;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.subcategory-item i {
    font-size: 0.7rem;
    color: var(--primary);
}

.subcategory-item:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateX(5px);
}

.subcategory-item:hover i {
    color: white;
}

.subcategory-item.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-color: var(--primary);
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.subcategory-item.active i {
    color: white;
}

@media (max-width: 768px) {
    .category-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.75rem;
    }
    
    .category-item {
        padding: 1rem 0.5rem;
    }
    
    .category-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .category-item span {
        font-size: 0.8rem;
    }
}
</style>

<h1>Danh sách sản phẩm</h1>

<!-- Search Section -->
<div class="search-section">
    <form method="GET" class="search-form" id="searchForm">
        <div class="search-input-wrapper">
            <i class="fas fa-search search-icon-input"></i>
            <input type="text" 
                   name="search" 
                   id="searchInput"
                   placeholder="Tìm kiếm sản phẩm theo tên, mô tả, danh mục..." 
                   value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>"
                   autocomplete="off">
            <?php if ($searchQuery): ?>
                <button type="button" class="clear-search" onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            <?php endif; ?>
            <div id="searchSuggestions" class="search-suggestions"></div>
        </div>
        <button type="submit">
            <i class="fas fa-search"></i> Tìm kiếm
        </button>
    </form>
    
    <?php if ($searchQuery): ?>
        <div class="search-result-info">
            <i class="fas fa-info-circle"></i>
            Tìm thấy <strong><?php echo count($products); ?></strong> sản phẩm cho "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
            <a href="product.php" class="clear-search-link">
                <i class="fas fa-times-circle"></i> Xóa tìm kiếm
            </a>
        </div>
    <?php elseif (empty($products)): ?>
        <div class="search-result-info">
            <i class="fas fa-box-open"></i> Không tìm thấy sản phẩm nào
        </div>
    <?php endif; ?>
</div>

<!-- Category Filter with Icons -->
<div class="category-filter-wrapper">
    <h3 class="filter-title">DANH MỤC</h3>
    <div class="category-grid">
        <a href="product.php" class="category-item <?php echo !$categoryId ? 'active' : ''; ?>">
            <div class="category-icon">
                <i class="fas fa-th-large"></i>
            </div>
            <span>Tất cả</span>
        </a>
        
        <?php 
        // Define icons for each parent category
        $categoryIcons = [
            'Điện thoại' => 'fa-mobile-alt',
            'Máy tính' => 'fa-laptop',
            'Tablet' => 'fa-tablet-alt',
            'Phụ kiện' => 'fa-headphones',
            'Âm thanh' => 'fa-volume-up'
        ];
        
        // Get selected category info to determine parent
        $selectedParentId = null;
        if ($categoryId) {
            $selectedCatStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $selectedCatStmt->execute([$categoryId]);
            $selectedCategory = $selectedCatStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($selectedCategory) {
                // If it's a child category, get parent ID
                if ($selectedCategory['parent_id'] !== null) {
                    $selectedParentId = $selectedCategory['parent_id'];
                } else {
                    // It's a parent category
                    $selectedParentId = $selectedCategory['id'];
                }
            }
        }
        
        foreach ($parentCategories as $parent): 
            $icon = $categoryIcons[$parent['name']] ?? 'fa-box';
            $isActive = ($selectedParentId == $parent['id']);
        ?>
            <a href="product.php?category=<?php echo $parent['id']; ?>" 
               class="category-item <?php echo $isActive ? 'active' : ''; ?>">
                <div class="category-icon">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <span><?php echo htmlspecialchars($parent['name']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Sub-categories (always show for selected parent) -->
    <?php if ($selectedParentId): 
        // Get parent category info
        $parentStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $parentStmt->execute([$selectedParentId]);
        $parentCategory = $parentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($parentCategory):
            // Get child categories
            $childStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
            $childStmt->execute([$selectedParentId]);
            $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($children)):
    ?>
        <div class="subcategory-section">
            <h4 class="subcategory-title">
                <i class="fas fa-angle-right"></i> <?php echo htmlspecialchars($parentCategory['name']); ?>
            </h4>
            <div class="subcategory-list">
                <?php foreach ($children as $child): ?>
                    <a href="product.php?category=<?php echo $child['id']; ?>" 
                       class="subcategory-item <?php echo $categoryId == $child['id'] ? 'active' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                        <?php echo htmlspecialchars($child['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php 
            endif;
        endif;
    endif; 
    ?>
</div>

<div class="products-grid">
    <?php foreach ($products as $product): ?>
    <div class="product-card">
        <div class="product-image-wrapper">
            <img src="assets/images/<?php echo $product['thumbnail']; ?>" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>">
            <?php if (isset($_SESSION['user_id'])): ?>
                <button class="quick-view-btn" onclick='openModal(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                    🔍
                </button>
            <?php else: ?>
                <a href="auth.php?redirect=product.php" class="quick-view-btn" title="Đăng nhập để mua hàng">
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

<script>
// Autocomplete functionality
const searchInput = document.getElementById('searchInput');
const searchSuggestions = document.getElementById('searchSuggestions');
let debounceTimer;

// Debounce function to limit API calls
function debounce(func, delay) {
    return function(...args) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => func.apply(this, args), delay);
    };
}

// Fetch search suggestions
async function fetchSuggestions(query) {
    if (query.length < 2) {
        searchSuggestions.classList.remove('show');
        return;
    }
    
    try {
        const response = await fetch(`api/search_suggestions.php?q=${encodeURIComponent(query)}`);
        const suggestions = await response.json();
        
        if (suggestions.length > 0) {
            displaySuggestions(suggestions);
        } else {
            searchSuggestions.classList.remove('show');
        }
    } catch (error) {
        console.error('Error fetching suggestions:', error);
    }
}

// Display suggestions in dropdown
function displaySuggestions(suggestions) {
    searchSuggestions.innerHTML = suggestions.map(item => `
        <div class="suggestion-item" onclick="selectSuggestion('${item.name.replace(/'/g, "\\'")}')">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <img src="assets/images/${item.thumbnail}" 
                     alt="${item.name}" 
                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: var(--text-primary);">${item.highlight}</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                        ${item.category} • ${item.price} VNĐ
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    searchSuggestions.classList.add('show');
}

// Select a suggestion
function selectSuggestion(name) {
    searchInput.value = name;
    searchSuggestions.classList.remove('show');
    document.getElementById('searchForm').submit();
}

// Clear search
function clearSearch() {
    window.location.href = 'product.php';
}

// Event listeners
searchInput.addEventListener('input', debounce(function(e) {
    fetchSuggestions(e.target.value);
}, 300));

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
        searchSuggestions.classList.remove('show');
    }
});

// Keyboard navigation
searchInput.addEventListener('keydown', function(e) {
    const items = searchSuggestions.querySelectorAll('.suggestion-item');
    const activeItem = searchSuggestions.querySelector('.suggestion-item.active');
    let currentIndex = Array.from(items).indexOf(activeItem);
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (currentIndex < items.length - 1) {
            if (activeItem) activeItem.classList.remove('active');
            items[currentIndex + 1].classList.add('active');
        } else if (items.length > 0) {
            if (activeItem) activeItem.classList.remove('active');
            items[0].classList.add('active');
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentIndex > 0) {
            activeItem.classList.remove('active');
            items[currentIndex - 1].classList.add('active');
        } else if (items.length > 0) {
            if (activeItem) activeItem.classList.remove('active');
            items[items.length - 1].classList.add('active');
        }
    } else if (e.key === 'Enter' && activeItem) {
        e.preventDefault();
        activeItem.click();
    } else if (e.key === 'Escape') {
        searchSuggestions.classList.remove('show');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
