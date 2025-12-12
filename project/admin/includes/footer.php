    </div> <!-- End container -->
    
    <!-- Admin Footer -->
    <footer class="admin-footer">
        <div class="admin-footer-container">
            <div class="admin-footer-left">
                <div class="admin-logo">
                    <span class="admin-icon">⚡</span>
                    <span class="admin-text">Admin Panel</span>
                </div>
                <p class="admin-copyright">© 2024 E-Commerce System. Phiên bản 2.0</p>
            </div>
            
            <div class="admin-footer-center">
                <div class="admin-stats">
                    <div class="admin-stat-item">
                        <span class="stat-icon">🚀</span>
                        <span class="stat-text">Hiệu suất cao</span>
                    </div>
                    <div class="admin-stat-item">
                        <span class="stat-icon">🔒</span>
                        <span class="stat-text">Bảo mật tối ưu</span>
                    </div>
                    <div class="admin-stat-item">
                        <span class="stat-icon">⚡</span>
                        <span class="stat-text">Xử lý nhanh</span>
                    </div>
                </div>
            </div>
            
            <div class="admin-footer-right">
                <div class="admin-links">
                    <a href="#help" class="admin-link">
                        <i class="fas fa-question-circle"></i>
                        Trợ giúp
                    </a>
                    <a href="#docs" class="admin-link">
                        <i class="fas fa-book"></i>
                        Tài liệu
                    </a>
                    <a href="#support" class="admin-link">
                        <i class="fas fa-headset"></i>
                        Hỗ trợ
                    </a>
                </div>
                <div class="admin-version">
                    <span class="version-badge">v2.0.1</span>
                </div>
            </div>
        </div>
        
        <!-- Performance Indicator -->
        <div class="performance-indicator" id="performanceIndicator">
            <div class="perf-item">
                <span class="perf-label">Uptime:</span>
                <span class="perf-value" id="uptime">99.9%</span>
            </div>
            <div class="perf-item">
                <span class="perf-label">Response:</span>
                <span class="perf-value" id="responseTime">< 200ms</span>
            </div>
            <div class="perf-item">
                <span class="perf-label">Status:</span>
                <span class="perf-value status-online">🟢 Online</span>
            </div>
        </div>
    </footer>

    <style>
    .admin-footer {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        margin-top: 40px;
        position: relative;
        overflow: hidden;
    }

    .admin-footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6, #ec4899, transparent);
    }

    .admin-footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 30px;
        align-items: center;
    }

    .admin-footer-left {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .admin-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 20px;
        font-weight: 700;
    }

    .admin-icon {
        font-size: 24px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .admin-text {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .admin-copyright {
        color: #64748b;
        font-size: 13px;
        margin: 0;
    }

    .admin-footer-center {
        display: flex;
        justify-content: center;
    }

    .admin-stats {
        display: flex;
        gap: 24px;
    }

    .admin-stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .stat-icon {
        font-size: 16px;
    }

    .stat-text {
        color: #94a3b8;
    }

    .admin-footer-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
    }

    .admin-links {
        display: flex;
        gap: 16px;
    }

    .admin-link {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 8px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .admin-link:hover {
        color: #6366f1;
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.2);
        transform: translateY(-1px);
    }

    .admin-link i {
        font-size: 12px;
    }

    .version-badge {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .performance-indicator {
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 12px 30px;
        display: flex;
        justify-content: center;
        gap: 30px;
    }

    .perf-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }

    .perf-label {
        color: #64748b;
        font-weight: 500;
    }

    .perf-value {
        color: #10b981;
        font-weight: 700;
    }

    .status-online {
        color: #10b981;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .admin-footer-container {
            grid-template-columns: 1fr;
            gap: 20px;
            text-align: center;
            padding: 20px;
        }

        .admin-footer-right {
            align-items: center;
        }

        .admin-stats {
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .admin-links {
            flex-wrap: wrap;
            justify-content: center;
        }

        .performance-indicator {
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px;
        }
    }

    @media (max-width: 480px) {
        .admin-footer-container {
            padding: 15px;
        }

        .admin-stats {
            flex-direction: column;
            align-items: center;
        }

        .admin-links {
            flex-direction: column;
            gap: 8px;
        }

        .performance-indicator {
            flex-direction: column;
            gap: 8px;
        }
    }
    </style>

    <script>
        // Update performance indicators
        function updatePerformanceIndicators() {
            const uptimeElement = document.getElementById('uptime');
            const responseTimeElement = document.getElementById('responseTime');
            
            if (uptimeElement && responseTimeElement) {
                // Simulate real-time updates (replace with actual API calls)
                const uptime = (99.5 + Math.random() * 0.5).toFixed(1) + '%';
                const responseTime = Math.floor(50 + Math.random() * 150) + 'ms';
                
                uptimeElement.textContent = uptime;
                responseTimeElement.textContent = '< ' + responseTime;
            }
        }

        // Update every 30 seconds
        setInterval(updatePerformanceIndicators, 30000);
        
        // Initial update
        updatePerformanceIndicators();
    </script>
</body>
</html>