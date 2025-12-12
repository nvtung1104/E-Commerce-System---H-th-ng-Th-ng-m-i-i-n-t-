<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Get all categories (including parent categories) - sorted A-Z
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize categories by parent
$parentCats = [];
$childCats = [];

foreach ($categories as $cat) {
    if ($cat['parent_id'] === null) {
        $parentCats[] = $cat;
    } else {
        if (!isset($childCats[$cat['parent_id']])) {
            $childCats[$cat['parent_id']] = [];
        }
        $childCats[$cat['parent_id']][] = $cat;
    }
}

// Sort parent categories A-Z
usort($parentCats, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Sort child categories A-Z for each parent
foreach ($childCats as &$children) {
    usort($children, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
}

// Get all products with category
$stmt = $pdo->query("SELECT p.*, c.name as category_name, p.stock_quantity as quantity 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id
                     ORDER BY p.created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="products-container">
    <!-- Header Actions -->
    <div class="page-header">
        <div class="header-left">
            <h1><i class="fas fa-box"></i> Quản lý sản phẩm</h1>
            <p class="subtitle"><?php echo count($products); ?> sản phẩm trong hệ thống</p>
        </div>
        <div class="header-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Tìm kiếm sản phẩm..." id="searchInput">
            </div>
            <button class="btn-category" onclick="openCategoryModal()">
                <i class="fas fa-folder-plus"></i> Quản lý danh mục
            </button>
            <button class="btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Thêm sản phẩm
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <div class="filter-group">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-list"></i> Tất cả
            </button>
            <button class="filter-btn" data-filter="sale">
                <i class="fas fa-tag"></i> Đang sale
            </button>
            <button class="filter-btn" data-filter="low-stock">
                <i class="fas fa-exclamation-triangle"></i> Sắp hết
            </button>
        </div>
        
        <div class="category-filter">
            <select id="categoryFilter" class="filter-select">
                <option value="">Tất cả danh mục</option>
                <?php 
                // Reuse the organized categories from product form
                foreach ($parentCats as $parent): 
                ?>
                    <option value="<?php echo $parent['id']; ?>" style="font-weight: bold;">
                        <?php echo htmlspecialchars($parent['name']); ?>
                    </option>
                    <?php 
                    if (isset($childCats[$parent['id']])):
                        foreach ($childCats[$parent['id']] as $child):
                    ?>
                        <option value="<?php echo $child['id']; ?>">
                            &nbsp;&nbsp;&nbsp;&nbsp;↳ <?php echo htmlspecialchars($child['name']); ?>
                        </option>
                    <?php 
                        endforeach;
                    endif;
                endforeach; 
                ?>
            </select>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid" id="productsGrid">
        <?php foreach ($products as $product): ?>
            <div class="product-card" 
                 data-product-id="<?php echo $product['id']; ?>"
                 data-category="<?php echo $product['category_id']; ?>"
                 data-has-sale="<?php echo $product['sale_price'] ? 'true' : 'false'; ?>"
                 data-stock="<?php echo $product['quantity'] ?? 0; ?>">
                <div class="product-image">
                    <?php 
                    $imageUrl = null;
                    
                    if ($product['thumbnail']) {
                        // Try multiple paths - check if file exists
                        if (file_exists("../public/assets/images/" . $product['thumbnail'])) {
                            $imageUrl = "../public/assets/images/" . $product['thumbnail'];
                        } elseif (file_exists("../public/assets/images/products/" . $product['thumbnail'])) {
                            $imageUrl = "../public/assets/images/products/" . $product['thumbnail'];
                        } else {
                            // If file doesn't exist, still try to load it (in case path is correct but file check fails)
                            $imageUrl = "../public/assets/images/products/" . $product['thumbnail'];
                        }
                    }
                    ?>
                    <?php if ($imageUrl): ?>
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="no-image" style="display: none;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="product-badge">
                        <?php if ($product['sale_price']): ?>
                            <span class="badge-sale">
                                <i class="fas fa-tag"></i> SALE
                            </span>
                        <?php endif; ?>
                        <?php if (($product['quantity'] ?? 0) <= 5 && ($product['quantity'] ?? 0) > 0): ?>
                            <span class="badge-low">
                                <i class="fas fa-exclamation-triangle"></i> Sắp hết
                            </span>
                        <?php elseif (($product['quantity'] ?? 0) == 0): ?>
                            <span class="badge-out">
                                <i class="fas fa-times-circle"></i> Hết hàng
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="product-content">
                    <div class="product-category">
                        <i class="fas fa-folder"></i>
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </div>
                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                    
                    <div class="product-meta">
                        <div class="product-sku">
                            <i class="fas fa-barcode"></i>
                            <?php echo $product['sku']; ?>
                        </div>
                        <div class="product-stock">
                            <i class="fas fa-warehouse"></i>
                            <?php echo $product['quantity'] ?? 0; ?> sp
                        </div>
                    </div>
                    
                    <div class="product-price">
                        <?php if ($product['sale_price']): ?>
                            <span class="price-original"><?php echo number_format($product['price']); ?> ₫</span>
                            <span class="price-sale"><?php echo number_format($product['sale_price']); ?> ₫</span>
                        <?php else: ?>
                            <span class="price-current"><?php echo number_format($product['price']); ?> ₫</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-actions">
                        <button class="btn-action btn-edit" onclick='editProduct(<?php echo json_encode($product); ?>)'>
                            <i class="fas fa-edit"></i> Sửa
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                            <i class="fas fa-trash"></i> Xóa
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Category Management Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h2><i class="fas fa-folder-tree"></i> Quản lý danh mục</h2>
            <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="category-management">
                <!-- Add Category Form -->
                <div class="add-category-section">
                    <h3><i class="fas fa-plus-circle"></i> Thêm danh mục mới</h3>
                    <form id="categoryForm" class="category-form">
                        <input type="hidden" id="categoryId" name="id">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Tên danh mục *</label>
                                <input type="text" id="categoryName" name="name" required placeholder="Nhập tên danh mục">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-folder"></i> Danh mục cha</label>
                                <select id="categoryParent" name="parent_id" class="form-select">
                                    <option value="">-- Danh mục gốc --</option>
                                    <?php 
                                    foreach ($categories as $cat): 
                                        if ($cat['parent_id'] === null):
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Mô tả</label>
                            <textarea id="categoryDescription" name="description" rows="2" placeholder="Mô tả danh mục..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="resetCategoryForm()">
                                <i class="fas fa-redo"></i> Làm mới
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> <span id="categoryBtnText">Thêm danh mục</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Categories List -->
                <div class="categories-list-section">
                    <h3><i class="fas fa-list"></i> Danh sách danh mục</h3>
                    <div class="categories-tree">
                        <?php
                        // Organize categories by parent
                        $parentCategories = [];
                        $childCategories = [];
                        
                        foreach ($categories as $cat) {
                            if ($cat['parent_id'] === null) {
                                $parentCategories[] = $cat;
                            } else {
                                if (!isset($childCategories[$cat['parent_id']])) {
                                    $childCategories[$cat['parent_id']] = [];
                                }
                                $childCategories[$cat['parent_id']][] = $cat;
                            }
                        }
                        
                        // Display parent categories
                        foreach ($parentCategories as $parent):
                        ?>
                            <div class="category-item parent-category">
                                <div class="category-info">
                                    <div class="category-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div class="category-details">
                                        <h4><?php echo htmlspecialchars($parent['name']); ?></h4>
                                        <?php if ($parent['description']): ?>
                                            <p><?php echo htmlspecialchars($parent['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="category-actions">
                                    <button class="btn-icon btn-edit-cat" onclick='editCategory(<?php echo json_encode($parent); ?>)' title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-delete-cat" onclick="deleteCategory(<?php echo $parent['id']; ?>)" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (isset($childCategories[$parent['id']])): ?>
                                <div class="child-categories">
                                    <?php foreach ($childCategories[$parent['id']] as $child): ?>
                                        <div class="category-item child-category">
                                            <div class="category-info">
                                                <div class="category-icon">
                                                    <i class="fas fa-folder-open"></i>
                                                </div>
                                                <div class="category-details">
                                                    <h4><?php echo htmlspecialchars($child['name']); ?></h4>
                                                    <?php if ($child['description']): ?>
                                                        <p><?php echo htmlspecialchars($child['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="category-actions">
                                                <button class="btn-icon btn-edit-cat" onclick='editCategory(<?php echo json_encode($child); ?>)' title="Sửa">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon btn-delete-cat" onclick="deleteCategory(<?php echo $child['id']; ?>)" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-plus-circle"></i> Thêm sản phẩm mới</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="productForm" class="modal-body">
            <input type="hidden" id="productId" name="id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tên sản phẩm *</label>
                    <input type="text" id="productName" name="name" required placeholder="Nhập tên sản phẩm">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU *</label>
                    <input type="text" id="productSku" name="sku" required placeholder="Mã SKU">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Danh mục *</label>
                    <select id="productCategory" name="category_id" required class="form-select">
                        <option value="">Chọn danh mục</option>
                        <?php 
                        // Display parent categories and their children (already organized at top of file)
                        foreach ($parentCats as $parent): 
                        ?>
                            <option value="<?php echo $parent['id']; ?>" style="font-weight: bold;">
                                <?php echo htmlspecialchars($parent['name']); ?>
                            </option>
                            <?php 
                            // Display child categories with indentation
                            if (isset($childCats[$parent['id']])):
                                foreach ($childCats[$parent['id']] as $child):
                            ?>
                                <option value="<?php echo $child['id']; ?>">
                                    &nbsp;&nbsp;&nbsp;&nbsp;↳ <?php echo htmlspecialchars($child['name']); ?>
                                </option>
                            <?php 
                                endforeach;
                            endif;
                        endforeach; 
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Giá gốc *</label>
                    <input type="number" id="productPrice" name="price" required placeholder="0" min="0" step="1000">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-percentage"></i> Giảm giá (%)</label>
                    <input type="number" id="productDiscount" placeholder="0" min="0" max="100" step="1">
                    <small id="discountNote" style="color: #64748b; margin-top: 4px;"></small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Giá bán</label>
                    <input type="number" id="productSalePrice" name="sale_price" placeholder="Tự động tính" readonly style="background: rgba(255, 255, 255, 0.03);">
                    <small style="color: #10b981;">Giá này sẽ hiển thị cho khách hàng</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-warehouse"></i> Số lượng *</label>
                    <input type="number" id="productQuantity" name="quantity" required placeholder="0" min="0">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Mô tả</label>
                <textarea id="productDescription" name="description" rows="4" placeholder="Mô tả sản phẩm..."></textarea>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-image"></i> Hình ảnh</label>
                <div class="image-upload-container">
                    <input type="file" id="productImageFile" accept="image/*" style="display: none;">
                    <input type="text" id="productThumbnail" name="thumbnail" placeholder="Chọn ảnh hoặc nhập tên file" readonly>
                    <button type="button" class="btn-upload" onclick="document.getElementById('productImageFile').click()">
                        <i class="fas fa-upload"></i> Chọn ảnh
                    </button>
                </div>
                <div id="imagePreview" class="image-preview" style="display: none;">
                    <img id="previewImg" src="" alt="Preview">
                    <button type="button" class="btn-remove-image" onclick="removeImage()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <small>Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB)</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Hủy
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Lưu sản phẩm
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.products-container {
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.header-left h1 {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.header-left h1 i {
    color: #6366f1;
}

.subtitle {
    color: #94a3b8;
    font-size: 14px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 16px;
    color: #64748b;
}

.search-box input {
    padding: 12px 16px 12px 44px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    width: 300px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #6366f1;
    background: rgba(255, 255, 255, 0.08);
}

.search-box input::placeholder {
    color: #64748b;
}

.btn-primary {
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.btn-category {
    padding: 12px 24px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-category:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.filters-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 20px;
}

.filter-group {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #94a3b8;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.filter-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
}

.filter-select {
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
    min-width: 200px;
}

.filter-select:focus {
    outline: none;
    border-color: #6366f1;
}

.filter-select option,
.form-select option {
    background: #1e293b;
    color: #fff;
    padding: 10px;
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 40px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.product-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-8px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.product-image {
    position: relative;
    width: 100%;
    height: 240px;
    background: rgba(255, 255, 255, 0.03);
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.1);
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #64748b;
    background: rgba(255, 255, 255, 0.02);
}

.product-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.badge-sale, .badge-low, .badge-out {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 4px;
}

.badge-sale {
    background: rgba(239, 68, 68, 0.9);
    color: white;
}

.badge-low {
    background: rgba(245, 158, 11, 0.9);
    color: white;
}

.badge-out {
    background: rgba(100, 116, 139, 0.9);
    color: white;
}

.product-content {
    padding: 20px;
}

.product-category {
    font-size: 12px;
    color: #6366f1;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.product-name {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 48px;
}

.product-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.product-sku, .product-stock {
    font-size: 13px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 6px;
}

.product-sku i, .product-stock i {
    color: #64748b;
}

.product-price {
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.price-original {
    font-size: 14px;
    color: #64748b;
    text-decoration: line-through;
}

.price-sale, .price-current {
    font-size: 24px;
    font-weight: 700;
    color: #22c55e;
}

.product-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.btn-action {
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-edit {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.btn-edit:hover {
    background: rgba(59, 130, 246, 0.2);
    transform: translateY(-2px);
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: translateY(-2px);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    margin: 2% auto;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 24px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header h2 i {
    color: #6366f1;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 10px;
    color: #ef4444;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label i {
    color: #6366f1;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #6366f1;
    background: rgba(255, 255, 255, 0.08);
}

.form-group select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 40px;
}

.form-group select option {
    background: #0f172a;
    color: #fff;
    padding: 12px;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #64748b;
}

.form-group small {
    font-size: 12px;
    color: #64748b;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-secondary {
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: #94a3b8;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
}

@media (max-width: 768px) {
    .products-container {
        padding: 20px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .header-right {
        width: 100%;
        flex-direction: column;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .filters-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-wrap: wrap;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
}

/* Category Management Styles */
.category-management {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.add-category-section,
.categories-list-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
}

.add-category-section h3,
.categories-list-section h3 {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.add-category-section h3 i {
    color: #10b981;
}

.categories-list-section h3 i {
    color: #6366f1;
}

.category-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 10px;
}

.categories-tree {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
}

.categories-tree::-webkit-scrollbar {
    width: 6px;
}

.categories-tree::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.categories-tree::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.5);
    border-radius: 10px;
}

.categories-tree::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.7);
}

.category-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.category-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(99, 102, 241, 0.3);
}

.parent-category {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border-color: rgba(99, 102, 241, 0.3);
}

.child-categories {
    margin-left: 30px;
    margin-bottom: 20px;
    padding-left: 20px;
    border-left: 2px solid rgba(99, 102, 241, 0.3);
}

.child-category {
    background: rgba(255, 255, 255, 0.03);
}

.category-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.category-icon {
    width: 40px;
    height: 40px;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #6366f1;
}

.child-category .category-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.category-details h4 {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}

.category-details p {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}

.category-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-edit-cat {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.btn-edit-cat:hover {
    background: rgba(59, 130, 246, 0.2);
    transform: scale(1.1);
}

.btn-delete-cat {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-delete-cat:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: scale(1.1);
}

/* Image Upload Styles */
.image-upload-container {
    display: flex;
    gap: 8px;
}

.image-upload-container input[type="text"] {
    flex: 1;
}

.btn-upload {
    padding: 12px 20px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-upload:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.image-preview {
    margin-top: 12px;
    position: relative;
    width: 200px;
    height: 200px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.btn-remove-image {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 32px;
    height: 32px;
    background: rgba(239, 68, 68, 0.9);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-image:hover {
    background: rgba(239, 68, 68, 1);
    transform: scale(1.1);
}

@media (max-width: 1024px) {
    .category-management {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .image-upload-container {
        flex-direction: column;
    }
}
</style>

<script>
let currentEditId = null;

// Open Add Modal
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Thêm sản phẩm mới';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productDiscount').value = '';
    document.getElementById('productSalePrice').value = '';
    document.getElementById('discountNote').textContent = '';
    removeImage();
    currentEditId = null;
    document.getElementById('productModal').style.display = 'block';
}

// Calculate Sale Price
function calculateSalePrice() {
    const price = parseFloat(document.getElementById('productPrice').value) || 0;
    const discount = parseFloat(document.getElementById('productDiscount').value) || 0;
    
    if (price > 0 && discount > 0) {
        const salePrice = price - (price * discount / 100);
        document.getElementById('productSalePrice').value = Math.round(salePrice);
        document.getElementById('discountNote').textContent = `Giảm ${discount}% = ${formatCurrency(Math.round(price - salePrice))}`;
    } else {
        document.getElementById('productSalePrice').value = '';
        document.getElementById('discountNote').textContent = '';
    }
}

// Format Currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Edit Product
function editProduct(product) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Chỉnh sửa sản phẩm';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productSku').value = product.sku;
    document.getElementById('productCategory').value = product.category_id;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productQuantity').value = product.quantity || 0;
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productThumbnail').value = product.thumbnail || '';
    
    // Calculate discount percentage if sale_price exists
    if (product.sale_price && product.price) {
        const discount = Math.round(((product.price - product.sale_price) / product.price) * 100);
        document.getElementById('productDiscount').value = discount;
        document.getElementById('productSalePrice').value = product.sale_price;
        document.getElementById('discountNote').textContent = `Giảm ${discount}% = ${formatCurrency(product.price - product.sale_price)}`;
    } else {
        document.getElementById('productDiscount').value = '';
        document.getElementById('productSalePrice').value = '';
        document.getElementById('discountNote').textContent = '';
    }
    
    currentEditId = product.id;
    document.getElementById('productModal').style.display = 'block';
}

// Close Modal
function closeModal() {
    document.getElementById('productModal').style.display = 'none';
}

// Delete Product
let currentDeleteProductId = null;

function deleteProduct(id) {
    currentDeleteProductId = id;
    document.getElementById('deleteProductId').textContent = '#' + id;
    document.getElementById('deleteProductModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteProductModal() {
    document.getElementById('deleteProductModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentDeleteProductId = null;
}

async function confirmDeleteProduct() {
    if (!currentDeleteProductId) return;
    
    // Show loading state
    const confirmBtn = document.querySelector('#deleteProductModal .btn-modal-confirm');
    const originalText = confirmBtn.textContent;
    confirmBtn.textContent = 'Đang xóa...';
    confirmBtn.disabled = true;
    
    try {
        const response = await fetch('api/manage_product.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: currentDeleteProductId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success animation
            confirmBtn.textContent = '✓ Đã xóa';
            confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            setTimeout(() => {
                closeDeleteProductModal();
                showAdminNotification('success', result.message);
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }, 1000);
        } else {
            // Error state
            confirmBtn.textContent = '✗ Lỗi';
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            showAdminNotification('error', 'Lỗi: ' + result.message);
            
            setTimeout(() => {
                confirmBtn.textContent = originalText;
                confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                confirmBtn.disabled = false;
            }, 2000);
        }
    } catch (error) {
        confirmBtn.textContent = '✗ Lỗi';
        confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showAdminNotification('error', 'Có lỗi xảy ra: ' + error.message);
        
        setTimeout(() => {
            confirmBtn.textContent = originalText;
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            confirmBtn.disabled = false;
        }, 2000);
    }
}

// Handle Image Upload
document.getElementById('productImageFile').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('imagePreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
    
    // Upload to server
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        const response = await fetch('api/upload_image.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('productThumbnail').value = result.path;
        } else {
            alert('Lỗi upload: ' + result.message);
            removeImage();
        }
    } catch (error) {
        alert('Có lỗi xảy ra khi upload ảnh: ' + error.message);
        removeImage();
    }
});

// Remove Image
function removeImage() {
    document.getElementById('productImageFile').value = '';
    document.getElementById('productThumbnail').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('previewImg').src = '';
}

// Form Submit
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Remove empty values
    if (data.sale_price === '') {
        delete data.sale_price;
    }
    if (data.description === '') {
        delete data.description;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    
    try {
        const response = await fetch('api/manage_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            location.reload(); // Reload to show new product
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        alert('Có lỗi xảy ra: ' + error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// Search functionality
document.getElementById('searchInput').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    filterProducts();
});

// Filter buttons
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterProducts();
    });
});

// Category filter
document.getElementById('categoryFilter').addEventListener('change', filterProducts);

// Filter Products Function
function filterProducts() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const cards = document.querySelectorAll('.product-card');
    
    cards.forEach(card => {
        const name = card.querySelector('.product-name').textContent.toLowerCase();
        const sku = card.querySelector('.product-sku').textContent.toLowerCase();
        const hasSale = card.dataset.hasSale === 'true';
        const stock = parseInt(card.dataset.stock);
        const category = card.dataset.category;
        
        let show = true;
        
        // Search filter
        if (query && !name.includes(query) && !sku.includes(query)) {
            show = false;
        }
        
        // Type filter
        if (activeFilter === 'sale' && !hasSale) {
            show = false;
        } else if (activeFilter === 'low-stock' && stock > 5) {
            show = false;
        }
        
        // Category filter
        if (categoryFilter && category !== categoryFilter) {
            show = false;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const productModal = document.getElementById('productModal');
    const categoryModal = document.getElementById('categoryModal');
    
    if (event.target == productModal) {
        closeModal();
    }
    if (event.target == categoryModal) {
        closeCategoryModal();
    }
}

// ===== CATEGORY MANAGEMENT FUNCTIONS =====
let currentEditCategoryId = null;

// Open Category Modal
function openCategoryModal() {
    document.getElementById('categoryModal').style.display = 'block';
}

// Close Category Modal
function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
    resetCategoryForm();
}

// Reset Category Form
function resetCategoryForm() {
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryBtnText').textContent = 'Thêm danh mục';
    currentEditCategoryId = null;
}

// Edit Category
function editCategory(category) {
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryName').value = category.name;
    document.getElementById('categoryParent').value = category.parent_id || '';
    document.getElementById('categoryDescription').value = category.description || '';
    document.getElementById('categoryBtnText').textContent = 'Cập nhật danh mục';
    currentEditCategoryId = category.id;
    
    // Scroll to form
    document.querySelector('.add-category-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Reload Categories List
async function reloadCategories() {
    try {
        const response = await fetch('api/get_categories.php');
        const result = await response.json();
        
        if (result.success) {
            // Update category filter dropdown
            updateCategoryFilter(result.categories);
            
            // Update product form category dropdown
            updateProductFormCategories(result.categories);
            
            // Update category parent dropdown in category form
            updateCategoryParentDropdown(result.parentCategories);
            
            // Update categories tree display
            updateCategoriesTree(result.parentCategories, result.childCategories);
        }
    } catch (error) {
        console.error('Error reloading categories:', error);
    }
}

// Update Category Filter Dropdown
function updateCategoryFilter(categories) {
    const filterSelect = document.getElementById('categoryFilter');
    const currentValue = filterSelect.value;
    
    // Organize categories
    const parentCats = [];
    const childCats = {};
    
    categories.forEach(cat => {
        if (cat.parent_id === null) {
            parentCats.push(cat);
        } else {
            if (!childCats[cat.parent_id]) {
                childCats[cat.parent_id] = [];
            }
            childCats[cat.parent_id].push(cat);
        }
    });
    
    // Sort parent categories A-Z
    parentCats.sort((a, b) => a.name.localeCompare(b.name));
    
    // Sort child categories A-Z for each parent
    Object.keys(childCats).forEach(parentId => {
        childCats[parentId].sort((a, b) => a.name.localeCompare(b.name));
    });
    
    // Build dropdown
    filterSelect.innerHTML = '<option value="">Tất cả danh mục</option>';
    
    parentCats.forEach(parent => {
        // Add parent category
        const parentOption = document.createElement('option');
        parentOption.value = parent.id;
        parentOption.textContent = parent.name;
        parentOption.style.fontWeight = 'bold';
        filterSelect.appendChild(parentOption);
        
        // Add child categories with indentation
        if (childCats[parent.id]) {
            childCats[parent.id].forEach(child => {
                const childOption = document.createElement('option');
                childOption.value = child.id;
                childOption.textContent = '\u00A0\u00A0\u00A0\u00A0↳ ' + child.name;
                filterSelect.appendChild(childOption);
            });
        }
    });
    
    filterSelect.value = currentValue;
}

// Update Product Form Categories Dropdown
function updateProductFormCategories(categories) {
    const productCategorySelect = document.getElementById('productCategory');
    const currentValue = productCategorySelect.value;
    
    // Organize categories
    const parentCats = [];
    const childCats = {};
    
    categories.forEach(cat => {
        if (cat.parent_id === null) {
            parentCats.push(cat);
        } else {
            if (!childCats[cat.parent_id]) {
                childCats[cat.parent_id] = [];
            }
            childCats[cat.parent_id].push(cat);
        }
    });
    
    // Sort parent categories A-Z
    parentCats.sort((a, b) => a.name.localeCompare(b.name));
    
    // Sort child categories A-Z for each parent
    Object.keys(childCats).forEach(parentId => {
        childCats[parentId].sort((a, b) => a.name.localeCompare(b.name));
    });
    
    // Build dropdown
    productCategorySelect.innerHTML = '<option value="">Chọn danh mục</option>';
    
    parentCats.forEach(parent => {
        // Add parent category
        const parentOption = document.createElement('option');
        parentOption.value = parent.id;
        parentOption.textContent = parent.name;
        parentOption.style.fontWeight = 'bold';
        productCategorySelect.appendChild(parentOption);
        
        // Add child categories with indentation
        if (childCats[parent.id]) {
            childCats[parent.id].forEach(child => {
                const childOption = document.createElement('option');
                childOption.value = child.id;
                childOption.textContent = '\u00A0\u00A0\u00A0\u00A0↳ ' + child.name;
                productCategorySelect.appendChild(childOption);
            });
        }
    });
    
    productCategorySelect.value = currentValue;
}

// Update Category Parent Dropdown
function updateCategoryParentDropdown(parentCategories) {
    const parentSelect = document.getElementById('categoryParent');
    const currentValue = parentSelect.value;
    
    parentSelect.innerHTML = '<option value="">-- Danh mục gốc --</option>';
    parentCategories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.name;
        parentSelect.appendChild(option);
    });
    
    parentSelect.value = currentValue;
}

// Update Categories Tree Display
function updateCategoriesTree(parentCategories, childCategories) {
    const treeContainer = document.querySelector('.categories-tree');
    treeContainer.innerHTML = '';
    
    parentCategories.forEach(parent => {
        // Create parent category item
        const parentDiv = document.createElement('div');
        parentDiv.className = 'category-item parent-category';
        parentDiv.innerHTML = `
            <div class="category-info">
                <div class="category-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="category-details">
                    <h4>${escapeHtml(parent.name)}</h4>
                    ${parent.description ? `<p>${escapeHtml(parent.description)}</p>` : ''}
                </div>
            </div>
            <div class="category-actions">
                <button class="btn-icon btn-edit-cat" onclick='editCategory(${JSON.stringify(parent)})' title="Sửa">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-delete-cat" onclick="deleteCategory(${parent.id})" title="Xóa">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        treeContainer.appendChild(parentDiv);
        
        // Add child categories if any
        if (childCategories[parent.id]) {
            const childContainer = document.createElement('div');
            childContainer.className = 'child-categories';
            
            childCategories[parent.id].forEach(child => {
                const childDiv = document.createElement('div');
                childDiv.className = 'category-item child-category';
                childDiv.innerHTML = `
                    <div class="category-info">
                        <div class="category-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="category-details">
                            <h4>${escapeHtml(child.name)}</h4>
                            ${child.description ? `<p>${escapeHtml(child.description)}</p>` : ''}
                        </div>
                    </div>
                    <div class="category-actions">
                        <button class="btn-icon btn-edit-cat" onclick='editCategory(${JSON.stringify(child)})' title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon btn-delete-cat" onclick="deleteCategory(${child.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                childContainer.appendChild(childDiv);
            });
            
            treeContainer.appendChild(childContainer);
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Delete Category
let currentDeleteCategoryId = null;

function deleteCategory(id) {
    currentDeleteCategoryId = id;
    document.getElementById('deleteCategoryId').textContent = '#' + id;
    document.getElementById('deleteCategoryModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteCategoryModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentDeleteCategoryId = null;
}

async function confirmDeleteCategory() {
    if (!currentDeleteCategoryId) return;
    
    // Show loading state
    const confirmBtn = document.querySelector('#deleteCategoryModal .btn-modal-confirm');
    const originalText = confirmBtn.textContent;
    confirmBtn.textContent = 'Đang xóa...';
    confirmBtn.disabled = true;
    
    try {
        const response = await fetch('api/manage_category.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: currentDeleteCategoryId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success animation
            confirmBtn.textContent = '✓ Đã xóa';
            confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            setTimeout(async () => {
                closeDeleteCategoryModal();
                showAdminNotification('success', result.message);
                await reloadCategories(); // Reload categories dynamically
            }, 1000);
        } else {
            // Error state
            confirmBtn.textContent = '✗ Lỗi';
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            showAdminNotification('error', 'Lỗi: ' + result.message);
            
            setTimeout(() => {
                confirmBtn.textContent = originalText;
                confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                confirmBtn.disabled = false;
            }, 2000);
        }
    } catch (error) {
        confirmBtn.textContent = '✗ Lỗi';
        confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showAdminNotification('error', 'Có lỗi xảy ra: ' + error.message);
        
        setTimeout(() => {
            confirmBtn.textContent = originalText;
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            confirmBtn.disabled = false;
        }, 2000);
    }
}

// Auto calculate sale price when price or discount changes
document.getElementById('productPrice').addEventListener('input', calculateSalePrice);
document.getElementById('productDiscount').addEventListener('input', calculateSalePrice);

// Category Form Submit
document.getElementById('categoryForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Remove empty values
    if (data.parent_id === '') {
        delete data.parent_id;
    }
    if (data.description === '') {
        delete data.description;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    
    try {
        const response = await fetch('api/manage_category.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            resetCategoryForm();
            await reloadCategories(); // Reload categories dynamically without page refresh
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        alert('Có lỗi xảy ra: ' + error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
</script>

<!-- Delete Category Modal -->
<div id="deleteCategoryModal" class="delete-category-modal">
    <div class="delete-category-modal-content">
        <div class="delete-category-modal-header">
            <div class="delete-icon">🗂️</div>
            <h3>E-Commerce System cho biết</h3>
            <button class="close-modal" onclick="closeDeleteCategoryModal()">&times;</button>
        </div>
        <div class="delete-category-modal-body">
            <p>Bạn có chắc chắn muốn xóa danh mục <span id="deleteCategoryId">#1</span>?</p>
            <p style="color: #f59e0b; font-size: 13px; margin-top: 8px;">⚠️ Không thể xóa danh mục đang có sản phẩm hoặc danh mục con!</p>
        </div>
        <div class="delete-category-modal-footer">
            <button class="btn-modal-cancel" onclick="closeDeleteCategoryModal()">Hủy</button>
            <button class="btn-modal-confirm" onclick="confirmDeleteCategory()">Xóa</button>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div id="deleteProductModal" class="delete-product-modal">
    <div class="delete-product-modal-content">
        <div class="delete-product-modal-header">
            <div class="delete-icon">🗑️</div>
            <h3>E-Commerce System cho biết</h3>
            <button class="close-modal" onclick="closeDeleteProductModal()">&times;</button>
        </div>
        <div class="delete-product-modal-body">
            <p>Bạn có chắc chắn muốn xóa sản phẩm <span id="deleteProductId">#1</span>?</p>
            <p style="color: #f59e0b; font-size: 13px; margin-top: 8px;">⚠️ Hành động này không thể hoàn tác!</p>
        </div>
        <div class="delete-product-modal-footer">
            <button class="btn-modal-cancel" onclick="closeDeleteProductModal()">Hủy</button>
            <button class="btn-modal-confirm" onclick="confirmDeleteProduct()">Xóa</button>
        </div>
    </div>
</div>

<style>
.delete-category-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.delete-category-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
    overflow: hidden;
}

.delete-category-modal-header {
    padding: 24px 24px 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.delete-category-modal-header h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    flex: 1;
}

.delete-category-modal-body {
    padding: 0 24px 24px 24px;
}

.delete-category-modal-body p {
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.delete-category-modal-body #deleteCategoryId {
    color: #ef4444;
    font-weight: 600;
}

.delete-category-modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.delete-category-modal .btn-modal-cancel,
.delete-category-modal .btn-modal-confirm {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.delete-category-modal .btn-modal-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.delete-category-modal .btn-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-1px);
}

.delete-category-modal .btn-modal-confirm {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.delete-category-modal .btn-modal-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

.delete-product-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.delete-product-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
    overflow: hidden;
}

.delete-product-modal-header {
    padding: 24px 24px 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.delete-icon {
    font-size: 24px;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.delete-product-modal-header h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    flex: 1;
}

.delete-product-modal-body {
    padding: 0 24px 24px 24px;
}

.delete-product-modal-body p {
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

.delete-product-modal-body #deleteProductId {
    color: #ef4444;
    font-weight: 600;
}

.delete-product-modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.delete-product-modal .btn-modal-cancel,
.delete-product-modal .btn-modal-confirm {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.delete-product-modal .btn-modal-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.delete-product-modal .btn-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-1px);
}

.delete-product-modal .btn-modal-confirm {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.delete-product-modal .btn-modal-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

@media (max-width: 480px) {
    .delete-product-modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .delete-product-modal-footer {
        flex-direction: column;
    }
    
    .delete-product-modal .btn-modal-cancel,
    .delete-product-modal .btn-modal-confirm {
        width: 100%;
    }
}
</style>

<script>
// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const productModal = document.getElementById('deleteProductModal');
    const categoryModal = document.getElementById('deleteCategoryModal');
    
    if (event.target === productModal) {
        closeDeleteProductModal();
    }
    if (event.target === categoryModal) {
        closeDeleteCategoryModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteProductModal();
        closeDeleteCategoryModal();
    }
});

function showAdminNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `admin-notification admin-notification-${type}`;
    notification.innerHTML = `
        <div class="admin-notification-content">
            <span class="admin-notification-icon">${type === 'success' ? '✓' : '✗'}</span>
            <span class="admin-notification-message">${message}</span>
        </div>
    `;
    
    // Add notification styles
    const style = document.createElement('style');
    style.textContent = `
        .admin-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10001;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s;
            max-width: 400px;
        }
        
        .admin-notification-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.9));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .admin-notification-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9));
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .admin-notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 500;
        }
        
        .admin-notification-icon {
            font-size: 18px;
            font-weight: bold;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(notification);
    
    // Remove notification after animation
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
        if (style.parentNode) {
            style.parentNode.removeChild(style);
        }
    }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
