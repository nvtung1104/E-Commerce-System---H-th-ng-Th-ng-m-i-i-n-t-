<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Get inventory with product details (using stock_quantity from products table)
$stmt = $pdo->query("SELECT p.id, p.name, p.sku, p.price, p.stock_quantity as quantity, 
                            p.sales_count, c.name as category_name, p.updated_at,
                            CASE 
                                WHEN p.stock_quantity = 0 THEN 'out'
                                WHEN p.stock_quantity < 10 THEN 'low'
                                ELSE 'normal'
                            END as stock_status
                     FROM products p
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE p.status = 1
                     ORDER BY p.stock_quantity ASC");
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="inventory-container">
    <!-- Header -->
    <div class="page-header">
        <div class="header-left">
            <h1><i class="fas fa-warehouse"></i> Quản lý kho hàng</h1>
            <p class="subtitle">Theo dõi tồn kho và cập nhật số lượng</p>
        </div>
        <div class="header-right">
            <div class="filter-group">
                <button class="filter-btn active" data-filter="all">
                    <i class="fas fa-list"></i> Tất cả
                </button>
                <button class="filter-btn" data-filter="low">
                    <i class="fas fa-exclamation-triangle"></i> Sắp hết
                </button>
                <button class="filter-btn" data-filter="out">
                    <i class="fas fa-times-circle"></i> Hết hàng
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="inventory-stats">
        <div class="stat-card stat-total">
            <div class="stat-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($inventory); ?></div>
                <div class="stat-label">Tổng sản phẩm</div>
            </div>
        </div>

        <div class="stat-card stat-low">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php echo count(array_filter($inventory, fn($i) => $i['quantity'] > 0 && $i['quantity'] <= 10)); ?>
                </div>
                <div class="stat-label">Sắp hết hàng</div>
            </div>
        </div>

        <div class="stat-card stat-out">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php echo count(array_filter($inventory, fn($i) => $i['quantity'] == 0)); ?>
                </div>
                <div class="stat-label">Hết hàng</div>
            </div>
        </div>

        <div class="stat-card stat-total-qty">
            <div class="stat-icon">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php echo number_format(array_sum(array_column($inventory, 'quantity'))); ?>
                </div>
                <div class="stat-label">Tổng số lượng</div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="table-wrapper">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                    <th>Cập nhật</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody id="inventoryBody">
                <?php foreach ($inventory as $item): ?>
                    <tr class="inventory-row" data-quantity="<?php echo $item['quantity']; ?>">
                        <td>
                            <div class="sku-cell">
                                <i class="fas fa-barcode"></i>
                                <span><?php echo $item['sku']; ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="product-cell">
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                        </td>
                        <td>
                            <span class="price-cell"><?php echo number_format($item['price']); ?> ₫</span>
                        </td>
                        <td>
                            <div class="quantity-cell">
                                <span class="quantity-value"><?php echo $item['quantity']; ?></span>
                                <span class="quantity-unit">sản phẩm</span>
                            </div>
                        </td>
                        <td>
                            <?php if ($item['quantity'] == 0): ?>
                                <span class="status-badge status-out">
                                    <i class="fas fa-times-circle"></i> Hết hàng
                                </span>
                            <?php elseif ($item['quantity'] <= 10): ?>
                                <span class="status-badge status-low">
                                    <i class="fas fa-exclamation-triangle"></i> Sắp hết
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-ok">
                                    <i class="fas fa-check-circle"></i> Còn hàng
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="update-time">
                                <?php 
                                if ($item['updated_at']) {
                                    echo date('d/m/Y H:i', strtotime($item['updated_at']));
                                } else {
                                    echo 'Chưa cập nhật';
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-update" onclick="updateInventory(<?php echo $item['id']; ?>)">
                                <i class="fas fa-edit"></i> Cập nhật
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.inventory-container {
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
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

.inventory-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-total .stat-icon {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.stat-low .stat-icon {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-out .stat-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.stat-total-qty .stat-icon {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
}

.stat-label {
    font-size: 13px;
    color: #94a3b8;
}

.table-wrapper {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table thead {
    background: rgba(255, 255, 255, 0.03);
}

.inventory-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.inventory-table td {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.inventory-row {
    transition: all 0.2s ease;
}

.inventory-row:hover {
    background: rgba(255, 255, 255, 0.03);
}

.sku-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6366f1;
    font-weight: 600;
}

.sku-cell i {
    opacity: 0.6;
}

.product-cell strong {
    color: #fff;
    font-size: 14px;
}

.category-badge {
    padding: 4px 12px;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.price-cell {
    color: #22c55e;
    font-weight: 600;
}

.quantity-cell {
    display: flex;
    flex-direction: column;
}

.quantity-value {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
}

.quantity-unit {
    font-size: 12px;
    color: #64748b;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-ok {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.status-low {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.status-out {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.update-time {
    color: #64748b;
    font-size: 13px;
}

.btn-update {
    padding: 8px 16px;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-update:hover {
    background: rgba(59, 130, 246, 0.2);
    transform: translateY(-2px);
}

/* Beautiful Update Modal */
.update-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.update-modal.closing {
    animation: fadeOut 0.3s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
}

.modal-container {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 24px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 2;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 30px 30px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
}

.modal-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.modal-title h3 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}

.modal-title p {
    color: #94a3b8;
    font-size: 14px;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 10px;
    width: 40px;
    height: 40px;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.modal-body {
    padding: 30px;
}

.product-info {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-name {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}

.product-sku {
    color: #6366f1;
    font-size: 14px;
    font-weight: 500;
}

.current-stock {
    text-align: right;
}

.stock-label {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    margin-bottom: 4px;
}

.stock-value {
    font-size: 24px;
    font-weight: 700;
    color: #10b981;
}

.update-type-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    background: rgba(255, 255, 255, 0.05);
    padding: 6px;
    border-radius: 12px;
}

.tab-btn {
    flex: 1;
    padding: 12px 16px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: #94a3b8;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.tab-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.tab-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.input-group {
    margin-bottom: 20px;
}

.input-group label {
    display: block;
    color: #fff;
    font-weight: 600;
    margin-bottom: 12px;
    font-size: 16px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 0;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    overflow: hidden;
}

.qty-btn {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.qty-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
}

#quantityInput {
    flex: 1;
    height: 50px;
    background: transparent;
    border: none;
    color: #fff;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    outline: none;
}

.quick-amounts {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.quick-label {
    color: #94a3b8;
    font-size: 14px;
    font-weight: 500;
}

.quick-buttons {
    display: flex;
    gap: 8px;
}

.quick-btn {
    padding: 8px 16px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    color: #6366f1;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 12px;
}

.quick-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

.result-preview {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    font-weight: 600;
}

.result-value {
    font-size: 20px;
    font-weight: 700;
}

.result-value.success {
    color: #10b981;
}

.result-value.warning {
    color: #f59e0b;
}

.result-value.danger {
    color: #ef4444;
}

.preview-change {
    margin-top: 8px;
    text-align: center;
}

.change-positive {
    color: #10b981;
    font-weight: 600;
}

.change-negative {
    color: #ef4444;
    font-weight: 600;
}

.note-section label {
    display: block;
    color: #fff;
    font-weight: 600;
    margin-bottom: 8px;
}

.note-section textarea {
    width: 100%;
    height: 80px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 12px;
    color: #fff;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: all 0.3s ease;
}

.note-section textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.note-section textarea::placeholder {
    color: #64748b;
}

.modal-footer {
    padding: 20px 30px 30px;
    display: flex;
    gap: 16px;
    justify-content: flex-end;
}

.btn-cancel,
.btn-confirm {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

.btn-confirm {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-confirm:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Beautiful Notifications */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    padding: 20px 24px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    animation: slideInRight 0.4s ease;
    max-width: 400px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.notification-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
    color: white;
    border-color: rgba(16, 185, 129, 0.3);
}

.notification-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
    color: white;
    border-color: rgba(239, 68, 68, 0.3);
}

.notification.fade-out {
    animation: slideOutRight 0.4s ease forwards;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 14px;
}

.notification-content i {
    font-size: 18px;
    opacity: 0.9;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
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

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@media (max-width: 768px) {
    .inventory-container {
        padding: 20px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .table-wrapper {
        overflow-x: auto;
    }
    
    .modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header {
        padding: 20px;
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .product-info {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .update-type-tabs {
        flex-direction: column;
    }
    
    .quick-amounts {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .modal-footer {
        flex-direction: column;
    }
}
</style>

<script>
// Beautiful Update Quantity Modal
function updateInventory(productId) {
    // Get product info from the row
    const row = document.querySelector(`button[onclick="updateInventory(${productId})"]`).closest('tr');
    const productName = row.querySelector('.product-cell strong').textContent;
    const currentQuantity = row.querySelector('.quantity-value').textContent;
    const sku = row.querySelector('.sku-cell span').textContent;
    
    showUpdateModal(productId, productName, currentQuantity, sku);
}

function showUpdateModal(productId, productName, currentQuantity, sku) {
    // Remove existing modal
    const existingModal = document.getElementById('updateModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML
    const modalHTML = `
        <div id="updateModal" class="update-modal">
            <div class="modal-overlay"></div>
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="modal-title">
                        <h3>Cập nhật số lượng tồn kho</h3>
                        <p>Điều chỉnh số lượng sản phẩm trong kho</p>
                    </div>
                    <button class="modal-close" onclick="closeUpdateModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="product-info">
                        <div class="product-details">
                            <div class="product-name">${productName}</div>
                            <div class="product-sku">SKU: ${sku}</div>
                        </div>
                        <div class="current-stock">
                            <span class="stock-label">Tồn kho hiện tại</span>
                            <span class="stock-value">${currentQuantity}</span>
                        </div>
                    </div>
                    
                    <div class="update-section">
                        <div class="update-type-tabs">
                            <button class="tab-btn active" data-type="set" onclick="switchUpdateType('set')">
                                <i class="fas fa-edit"></i>
                                Đặt số lượng
                            </button>
                            <button class="tab-btn" data-type="add" onclick="switchUpdateType('add')">
                                <i class="fas fa-plus"></i>
                                Nhập thêm
                            </button>
                            <button class="tab-btn" data-type="subtract" onclick="switchUpdateType('subtract')">
                                <i class="fas fa-minus"></i>
                                Xuất kho
                            </button>
                        </div>
                        
                        <div class="quantity-input-section">
                            <div class="input-group">
                                <label id="quantityLabel">Số lượng mới</label>
                                <div class="quantity-controls">
                                    <button class="qty-btn minus" onclick="adjustQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" id="quantityInput" value="${currentQuantity}" min="0" max="99999">
                                    <button class="qty-btn plus" onclick="adjustQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="quick-amounts">
                                <span class="quick-label">Số lượng nhanh:</span>
                                <div class="quick-buttons">
                                    <button class="quick-btn" onclick="setQuickAmount(10)">+10</button>
                                    <button class="quick-btn" onclick="setQuickAmount(50)">+50</button>
                                    <button class="quick-btn" onclick="setQuickAmount(100)">+100</button>
                                    <button class="quick-btn" onclick="setQuickAmount(500)">+500</button>
                                </div>
                            </div>
                            
                            <div class="result-preview">
                                <div class="preview-item">
                                    <span>Số lượng sau cập nhật:</span>
                                    <span id="resultQuantity" class="result-value">${currentQuantity}</span>
                                </div>
                                <div class="preview-change" id="changeIndicator" style="display: none;">
                                    <span id="changeText"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="note-section">
                            <label for="updateNote">Ghi chú (tùy chọn)</label>
                            <textarea id="updateNote" placeholder="Nhập lý do cập nhật kho..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeUpdateModal()">
                        <i class="fas fa-times"></i>
                        Hủy
                    </button>
                    <button class="btn-confirm" onclick="confirmUpdate(${productId}, ${currentQuantity})">
                        <i class="fas fa-check"></i>
                        Cập nhật
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';
    
    // Initialize
    window.currentUpdateType = 'set';
    window.originalQuantity = parseInt(currentQuantity);
    updatePreview();
}

function switchUpdateType(type) {
    window.currentUpdateType = type;
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-type="${type}"]`).classList.add('active');
    
    // Update label and input
    const label = document.getElementById('quantityLabel');
    const input = document.getElementById('quantityInput');
    
    switch(type) {
        case 'set':
            label.textContent = 'Số lượng mới';
            input.value = window.originalQuantity;
            break;
        case 'add':
            label.textContent = 'Số lượng nhập thêm';
            input.value = 0;
            break;
        case 'subtract':
            label.textContent = 'Số lượng xuất kho';
            input.value = 0;
            break;
    }
    
    updatePreview();
}

function adjustQuantity(change) {
    const input = document.getElementById('quantityInput');
    const newValue = Math.max(0, parseInt(input.value || 0) + change);
    input.value = newValue;
    updatePreview();
}

function setQuickAmount(amount) {
    const input = document.getElementById('quantityInput');
    if (window.currentUpdateType === 'set') {
        input.value = amount;
    } else {
        input.value = amount;
    }
    updatePreview();
}

function updatePreview() {
    const input = document.getElementById('quantityInput');
    const inputValue = parseInt(input.value || 0);
    let resultQuantity;
    let changeAmount = 0;
    
    switch(window.currentUpdateType) {
        case 'set':
            resultQuantity = inputValue;
            changeAmount = inputValue - window.originalQuantity;
            break;
        case 'add':
            resultQuantity = window.originalQuantity + inputValue;
            changeAmount = inputValue;
            break;
        case 'subtract':
            resultQuantity = Math.max(0, window.originalQuantity - inputValue);
            changeAmount = -Math.min(inputValue, window.originalQuantity);
            break;
    }
    
    // Update result quantity
    document.getElementById('resultQuantity').textContent = resultQuantity;
    
    // Update result color
    const resultElement = document.getElementById('resultQuantity');
    if (resultQuantity === 0) {
        resultElement.className = 'result-value danger';
    } else if (resultQuantity <= 10) {
        resultElement.className = 'result-value warning';
    } else {
        resultElement.className = 'result-value success';
    }
    
    // Show change indicator
    const changeIndicator = document.getElementById('changeIndicator');
    const changeText = document.getElementById('changeText');
    
    if (changeAmount !== 0) {
        changeIndicator.style.display = 'block';
        const sign = changeAmount > 0 ? '+' : '';
        const icon = changeAmount > 0 ? '↗️' : '↘️';
        changeText.textContent = `${icon} ${sign}${changeAmount}`;
        changeText.className = changeAmount > 0 ? 'change-positive' : 'change-negative';
    } else {
        changeIndicator.style.display = 'none';
    }
}

function confirmUpdate(productId, originalQuantity) {
    const input = document.getElementById('quantityInput');
    const note = document.getElementById('updateNote').value;
    const inputValue = parseInt(input.value || 0);
    
    // Validate input
    if (isNaN(inputValue) || inputValue < 0) {
        showNotification('error', 'Vui lòng nhập số lượng hợp lệ');
        return;
    }
    
    // Show loading state
    const confirmBtn = document.querySelector('.btn-confirm');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang cập nhật...';
    confirmBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('update_type', window.currentUpdateType);
    formData.append('quantity', inputValue);
    formData.append('note', note);
    
    // Make API call
    fetch('api/update_inventory.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Success animation
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Thành công!';
            confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            // Update the table row with real data
            updateTableRow(productId, data.data.new_quantity, data.data.status);
            
            // Update stats cards
            updateStatsCards();
            
            // Show success notification
            showNotification('success', `${data.data.action_text} - Số lượng mới: ${data.data.new_quantity}`);
            
            // Close modal after delay
            setTimeout(() => {
                closeUpdateModal();
            }, 1000);
            
        } else {
            // Error state
            confirmBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Lỗi';
            confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            showNotification('error', data.message || 'Có lỗi xảy ra khi cập nhật');
            
            // Reset button after delay
            setTimeout(() => {
                confirmBtn.innerHTML = originalText;
                confirmBtn.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
                confirmBtn.disabled = false;
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        
        // Error state
        confirmBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Lỗi kết nối';
        confirmBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showNotification('error', 'Lỗi kết nối: ' + error.message);
        
        // Reset button after delay
        setTimeout(() => {
            confirmBtn.innerHTML = originalText;
            confirmBtn.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
            confirmBtn.disabled = false;
        }, 2000);
    });
}

function updateTableRow(productId, newQuantity, status) {
    const row = document.querySelector(`button[onclick="updateInventory(${productId})"]`).closest('tr');
    const quantityCell = row.querySelector('.quantity-value');
    const statusCell = row.querySelector('.status-badge');
    const updateTimeCell = row.querySelector('.update-time');
    
    // Update quantity with animation
    quantityCell.style.transform = 'scale(1.2)';
    quantityCell.style.color = '#10b981';
    quantityCell.textContent = newQuantity;
    
    setTimeout(() => {
        quantityCell.style.transform = 'scale(1)';
        quantityCell.style.color = '#fff';
    }, 300);
    
    // Update status based on API response
    switch(status) {
        case 'out':
            statusCell.className = 'status-badge status-out';
            statusCell.innerHTML = '<i class="fas fa-times-circle"></i> Hết hàng';
            break;
        case 'low':
            statusCell.className = 'status-badge status-low';
            statusCell.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Sắp hết';
            break;
        default:
            statusCell.className = 'status-badge status-ok';
            statusCell.innerHTML = '<i class="fas fa-check-circle"></i> Còn hàng';
    }
    
    // Update timestamp
    const now = new Date();
    const timeString = now.toLocaleDateString('vi-VN') + ' ' + now.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
    updateTimeCell.textContent = timeString;
    
    // Update row data attribute
    row.setAttribute('data-quantity', newQuantity);
    
    // Add success animation to row
    row.style.background = 'rgba(16, 185, 129, 0.1)';
    setTimeout(() => {
        row.style.background = '';
    }, 2000);
}

function closeUpdateModal() {
    const modal = document.getElementById('updateModal');
    if (modal) {
        modal.classList.add('closing');
        setTimeout(() => {
            modal.remove();
            document.body.style.overflow = 'auto';
        }, 300);
    }
}

function updateStatsCards() {
    // Recalculate stats from current table data
    const rows = document.querySelectorAll('.inventory-row');
    let totalProducts = rows.length;
    let lowStock = 0;
    let outOfStock = 0;
    let totalQuantity = 0;
    
    rows.forEach(row => {
        const quantity = parseInt(row.getAttribute('data-quantity') || 0);
        totalQuantity += quantity;
        
        if (quantity === 0) {
            outOfStock++;
        } else if (quantity <= 10) {
            lowStock++;
        }
    });
    
    // Update stat cards with animation
    updateStatCard('.stat-total .stat-value', totalProducts);
    updateStatCard('.stat-low .stat-value', lowStock);
    updateStatCard('.stat-out .stat-value', outOfStock);
    updateStatCard('.stat-total-qty .stat-value', totalQuantity.toLocaleString());
}

function updateStatCard(selector, newValue) {
    const element = document.querySelector(selector);
    if (element) {
        element.style.transform = 'scale(1.1)';
        element.style.color = '#10b981';
        element.textContent = newValue;
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '#fff';
        }, 300);
    }
}

function showNotification(type, message) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Quantity input change
    document.addEventListener('input', function(e) {
        if (e.target.id === 'quantityInput') {
            updatePreview();
        }
    });
    
    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            closeUpdateModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUpdateModal();
        }
    });
});

// Filter functionality
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const rows = document.querySelectorAll('.inventory-row');
        
        rows.forEach(row => {
            const quantity = parseInt(row.dataset.quantity);
            
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'low' && quantity > 0 && quantity <= 10) {
                row.style.display = '';
            } else if (filter === 'out' && quantity === 0) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
