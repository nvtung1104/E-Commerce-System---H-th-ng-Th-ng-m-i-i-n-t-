<?php
session_start();
require_once '../public/config/constants.php';
require_once '../public/config/db.php';
require_once 'includes/auth_check.php';

include 'includes/header.php';
?>

<div class="dark-orders-container">
    <!-- Beautiful Balanced Header -->
    <div class="orders-header">
        <div class="header-main">
            <div class="header-left">
                <div class="title-section">
                    <div class="icon-wrapper">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="title-content">
                        <h1>Đơn hàng đang xử lý</h1>
                        <p class="subtitle">Theo dõi và quản lý đơn hàng đang hoạt động real-time</p>
                    </div>
                </div>
            </div>
            
            <div class="header-right">
                <div class="quick-actions">
                    <a href="final_bulk.php" class="action-btn bulk-btn">
                        <i class="fas fa-bolt"></i>
                        <span>Xử lý hàng loạt</span>
                    </a>
                    <a href="completed_orders.php" class="action-btn success-btn">
                        <i class="fas fa-check-circle"></i>
                        <span>Đơn hoàn thành</span>
                    </a>
                    <a href="cancelled_orders.php" class="action-btn danger-btn">
                        <i class="fas fa-times-circle"></i>
                        <span>Đơn đã hủy</span>
                    </a>
                </div>
                
                <div class="realtime-section">
                    <div class="realtime-badge" id="realtimeStatus">
                        <span class="pulse-dot"></span>
                        <span id="statusText">Đang kết nối...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-pending">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="pendingCount">0</div>
                <div class="stat-label">Chờ xử lý</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +12%
                </div>
            </div>
        </div>

        <div class="stat-card stat-processing">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-spinner"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="processingCount">0</div>
                <div class="stat-label">Đang xử lý</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +8%
                </div>
            </div>
        </div>

        <div class="stat-card stat-shipping">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-truck-fast"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="shippingCount">0</div>
                <div class="stat-label">Đang giao</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> +15%
                </div>
            </div>
        </div>

        <div class="stat-card stat-completed">
            <div class="stat-icon-wrapper">
                <div class="stat-icon">
                    <i class="fas fa-circle-check"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="completedCount">0</div>
                <div class="stat-label">Hoàn thành</div>
                <div class="stat-trend success">
                    <i class="fas fa-arrow-up"></i> +23%
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="orders-table-wrapper">
        <div class="table-header">
            <h2><i class="fas fa-list"></i> Danh sách đơn hàng</h2>
            <div class="table-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Tìm kiếm đơn hàng..." id="searchInput">
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="dark-table" id="ordersTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-user"></i> Khách hàng</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-money-bill-wave"></i> Tổng tiền</th>
                        <th><i class="fas fa-credit-card"></i> Thanh toán</th>
                        <th><i class="fas fa-info-circle"></i> Trạng thái</th>
                        <th><i class="fas fa-calendar"></i> Ngày tạo</th>
                        <th><i class="fas fa-cog"></i> Thao tác</th>
                    </tr>
                </thead>
                <tbody id="ordersBody">
                    <tr class="loading-row">
                        <td colspan="8">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p>Đang tải dữ liệu real-time...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.dark-orders-container {
    background: #0a0e27;
    min-height: 100vh;
    padding: 30px;
    color: #e2e8f0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Beautiful Balanced Header */
.orders-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 40px;
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.orders-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
}

.header-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
    gap: 40px;
}

.title-section {
    display: flex;
    align-items: center;
    gap: 20px;
}

.icon-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: white;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.title-content h1 {
    font-size: 36px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #fff, #6366f1);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.subtitle {
    color: #94a3b8;
    font-size: 16px;
    font-weight: 500;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 30px;
}

.quick-actions {
    display: flex;
    gap: 12px;
}

.action-btn {
    padding: 12px 20px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: 1px solid transparent;
    backdrop-filter: blur(10px);
}

.bulk-btn {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
    color: #8b5cf6;
    border-color: rgba(139, 92, 246, 0.4);
}

.bulk-btn:hover {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(124, 58, 237, 0.3));
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
}

.success-btn {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
}

.success-btn:hover {
    background: rgba(16, 185, 129, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.danger-btn {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

.danger-btn:hover {
    background: rgba(239, 68, 68, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.realtime-section {
    display: flex;
    align-items: center;
}

.realtime-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 24px;
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);
    transition: all 0.3s ease;
}

.realtime-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
}

.pulse-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #f59e0b;
    animation: pulse-animation 2s infinite;
    box-shadow: 0 0 0 0 currentColor;
}

.realtime-badge.connected .pulse-dot {
    background: #22c55e;
}

.realtime-badge.disconnected .pulse-dot {
    background: #ef4444;
}

.realtime-badge.connected #statusText {
    color: #22c55e;
}

.realtime-badge.disconnected #statusText {
    color: #ef4444;
}

@keyframes pulse-animation {
    0%, 100% { 
        opacity: 1; 
        transform: scale(1);
        box-shadow: 0 0 0 0 currentColor;
    }
    50% { 
        opacity: 0.7; 
        transform: scale(1.1);
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.3);
    }
}

/* Beautiful Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 40px;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

.stat-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, currentColor, transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.stat-pending { color: #f59e0b; }
.stat-processing { color: #3b82f6; }
.stat-shipping { color: #8b5cf6; }
.stat-completed { color: #22c55e; }

.stat-icon-wrapper {
    position: relative;
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    position: relative;
}

.stat-pending .stat-icon {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-processing .stat-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.stat-shipping .stat-icon {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.stat-completed .stat-icon {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #94a3b8;
    margin-bottom: 8px;
}

.stat-trend {
    font-size: 12px;
    color: #f59e0b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-trend.success {
    color: #22c55e;
}

/* Table */
.orders-table-wrapper {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
}

.table-header {
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.table-header h2 {
    font-size: 20px;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h2 i {
    color: #6366f1;
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
    padding: 10px 16px 10px 44px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
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

.table-container {
    overflow-x: auto;
}

.dark-table {
    width: 100%;
    border-collapse: collapse;
}

.dark-table thead {
    background: rgba(255, 255, 255, 0.03);
}

.dark-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.dark-table th i {
    margin-right: 6px;
    opacity: 0.6;
}

.dark-table td {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 14px;
    color: #cbd5e1;
}

.dark-table tbody tr {
    transition: all 0.2s ease;
}

.dark-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.dark-table tbody tr.new-order {
    animation: new-order-flash 2s ease;
}

@keyframes new-order-flash {
    0% { background: rgba(99, 102, 241, 0.3); }
    100% { background: transparent; }
}

.loading-row td {
    padding: 60px 20px;
    text-align: center;
}

.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-spinner p {
    color: #64748b;
    font-size: 14px;
}

/* Status Badges */
.status-badge {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-processing {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-shipping {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.status-completed {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-cancelled {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-paid {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-failed {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Action Button */
.btn-view {
    padding: 8px 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

.btn-quick-complete {
    padding: 8px 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
}

.btn-quick-complete:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.btn-quick-complete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Bulk Processing Button Hover Effect */
a[href="bulk_orders.php"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(124, 58, 237, 0.3)) !important;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .header-main {
        flex-direction: column;
        gap: 24px;
        align-items: flex-start;
    }
    
    .header-right {
        width: 100%;
        justify-content: space-between;
    }
    
    .quick-actions {
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    .dark-orders-container {
        padding: 20px;
    }
    
    .orders-header {
        padding: 20px;
    }
    
    .title-section {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    
    .icon-wrapper {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .title-content h1 {
        font-size: 28px;
    }
    
    .header-right {
        flex-direction: column;
        gap: 20px;
        width: 100%;
    }
    
    .quick-actions {
        width: 100%;
        justify-content: center;
    }
    
    .action-btn {
        flex: 1;
        justify-content: center;
        min-width: 120px;
    }
    
    .table-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .search-box input {
        width: 100%;
    }
}
</style>

<script>
let ordersData = [];
let eventSource = null;
let reconnectTimeout = null;
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 5;

document.addEventListener('DOMContentLoaded', function() {
    connectSSE();
    requestNotificationPermission();
    setupSearch();
});

function connectSSE() {
    updateStatus('connecting', 'Đang kết nối...');
    
    // Close existing connection
    if (eventSource) {
        eventSource.close();
    }
    
    // Create new SSE connection
    eventSource = new EventSource('api/orders_stream.php');
    
    eventSource.onopen = function() {
        updateStatus('connected', 'Real-time');
        reconnectAttempts = 0;
    };
    
    eventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            
            switch(data.type) {
                case 'initial':
                    ordersData = data.orders || [];
                    renderOrders(ordersData);
                    updateStats(ordersData);
                    break;
                    
                case 'new_order':
                    handleNewOrder(data.order);
                    break;
                    
                case 'heartbeat':
                    break;
                    
                case 'error':
                    showError('Lỗi server: ' + data.message);
                    break;
            }
        } catch (error) {
            // Parse error - ignore
        }
    };
    
    eventSource.onerror = function(error) {
        updateStatus('disconnected', 'Mất kết nối');
        eventSource.close();
        
        // Try to reconnect
        if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
            reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
            
            clearTimeout(reconnectTimeout);
            reconnectTimeout = setTimeout(() => {
                connectSSE();
            }, delay);
        } else {
            showError('Không thể kết nối. Vui lòng tải lại trang.');
        }
    };
}

function handleNewOrder(order) {
    playNotificationSound();
    showNotification(order);
    
    ordersData.unshift(order);
    renderOrders(ordersData);
    updateStats(ordersData);
    
    setTimeout(() => {
        const row = document.querySelector(`tr[data-order-id="${order.id}"]`);
        if (row) {
            row.classList.add('new-order');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 100);
}

function showError(message) {
    const tbody = document.getElementById('ordersBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 60px 20px;">
                <div style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px;"></i>
                    <p>${message}</p>
                    <button onclick="location.reload()" style="margin-top: 16px; padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Tải lại trang
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersBody');
    
    if (orders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px 20px;">
                    <div style="color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>Chưa có đơn hàng nào</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = orders.map(order => `
        <tr data-order-id="${order.id}">
            <td><strong style="color: #6366f1;">#${order.id}</strong></td>
            <td>${escapeHtml(order.fullname || 'N/A')}</td>
            <td style="color: #94a3b8;">${escapeHtml(order.email || 'N/A')}</td>
            <td><strong style="color: #fff;">${formatMoney(order.total_price)} ₫</strong></td>
            <td><span class="status-badge status-${order.payment_status}">${translateStatus(order.payment_status)}</span></td>
            <td><span class="status-badge status-${order.status}">${translateStatus(order.status)}</span></td>
            <td style="color: #94a3b8;">${formatDate(order.created_at)}</td>
            <td>
                <div style="display: flex; gap: 8px;">
                    <a href="order_detail.php?id=${order.id}" class="btn-view">
                        <i class="fas fa-cog"></i> Quản lý
                    </a>
                    ${order.status === 'shipping' ? `
                        <button class="btn-quick-complete" onclick="quickComplete(${order.id})" title="Hoàn thành nhanh">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function updateStats(orders) {
    const stats = { pending: 0, processing: 0, shipping: 0, completed: 0 };
    orders.forEach(order => {
        if (stats.hasOwnProperty(order.status)) stats[order.status]++;
    });
    
    document.getElementById('pendingCount').textContent = stats.pending;
    document.getElementById('processingCount').textContent = stats.processing;
    document.getElementById('shippingCount').textContent = stats.shipping;
    document.getElementById('completedCount').textContent = stats.completed;
}

function updateStatus(status, text) {
    const el = document.getElementById('realtimeStatus');
    const textEl = document.getElementById('statusText');
    el.className = 'realtime-badge ' + status;
    textEl.textContent = text;
}

function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        const filtered = ordersData.filter(order => 
            order.id.toString().includes(query) ||
            (order.fullname && order.fullname.toLowerCase().includes(query)) ||
            (order.email && order.email.toLowerCase().includes(query))
        );
        renderOrders(filtered);
    });
}

function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIGGS57OihUBELTKXh8bllHAU2jdXvzn0pBSh+zPDajzsKElyx6OyrWBUIQ5zd8sFuJAUuhM/z24k2CBdju+zooVARC0yl4fG5ZRwFNo3V7859KQUofsz');
    audio.volume = 0.3;
    audio.play().catch(e => {});
}

function showNotification(order) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Đơn hàng mới! 🎉', {
            body: `Đơn #${order.id} - ${order.fullname}\nTổng: ${formatMoney(order.total_price)} ₫`,
            icon: '/project/public/assets/images/logo.png',
            tag: 'order-' + order.id
        });
    }
}

function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function translateStatus(status) {
    const translations = {
        'pending': 'Chờ xử lý',
        'processing': 'Đang xử lý',
        'shipping': 'Đang giao',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy',
        'paid': 'Đã thanh toán',
        'failed': 'Thất bại'
    };
    return translations[status] || status;
}

// Quick Complete Function
function quickComplete(orderId) {
    const btn = document.querySelector(`button[onclick="quickComplete(${orderId})"]`);
    
    if (!confirm(`Hoàn thành đơn hàng #${orderId}?`)) {
        return;
    }
    
    // Show loading state
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    const startTime = performance.now();
    
    fetch('api/update_order_status_fast.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${orderId}&status=completed`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const processingTime = Math.round(performance.now() - startTime);
            
            // Success animation
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            
            // Show notification
            showQuickNotification(`✓ Hoàn thành đơn #${orderId} (${data.processing_time})`, 'success');
            
            // Update row status
            const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
            if (row) {
                const statusCell = row.querySelector('.status-badge');
                statusCell.className = 'status-badge status-completed';
                statusCell.textContent = 'Hoàn thành';
                
                // Remove quick complete button
                btn.remove();
                
                // Add success animation to row
                row.style.background = 'rgba(16, 185, 129, 0.1)';
                setTimeout(() => {
                    row.style.background = '';
                }, 2000);
            }
            
        } else {
            // Error state
            btn.innerHTML = '<i class="fas fa-times"></i>';
            btn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            showQuickNotification(`✗ Lỗi: ${data.message}`, 'error');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                btn.disabled = false;
            }, 2000);
        }
    })
    .catch(error => {
        btn.innerHTML = '<i class="fas fa-times"></i>';
        btn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showQuickNotification(`✗ Lỗi kết nối: ${error.message}`, 'error');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            btn.disabled = false;
        }, 2000);
    });
}

function showQuickNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-weight: 500;
        color: white;
        ${type === 'success' ? 
            'background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.9)); border: 1px solid rgba(16, 185, 129, 0.3);' :
            'background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9)); border: 1px solid rgba(239, 68, 68, 0.3);'
        }
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 4000);
}

window.addEventListener('beforeunload', function() {
    if (eventSource) {
        eventSource.close();
    }
    if (reconnectTimeout) {
        clearTimeout(reconnectTimeout);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
