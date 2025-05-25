<?php
/**
 * MAIN DASHBOARD
 * File: modules/dashboard/index.php
 * Purpose: Main dashboard displaying system overview, statistics, and quick actions
 * 
 * Features:
 * - Real-time statistics cards
 * - Sales charts and analytics
 * - Recent orders and activities
 * - Low stock alerts
 * - Quick action buttons
 * - Multi-language support
 * - Responsive design
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('dashboard.view');

// Set page title
$page_title = __('dashboard');

// Get dashboard statistics
$stats = getDashboardStats();
$recent_orders = getRecentOrders(5);
$low_stock_products = getLowStockProducts(5);
$top_products = getTopSellingProducts(5);
$monthly_sales = getMonthlySalesData(6);

// Get current language settings
$current_lang = getCurrentLanguage();
$is_rtl = ($current_lang === 'ar');
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i>
                    <?php _e('dashboard'); ?>
                </h1>
                <p class="page-description">
                    <?php _e('welcome_back'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 
                    <?php _e('dashboard_title'); ?>
                </p>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" onclick="location.href='../sales/orders.php'">
                    <i class="fas fa-plus"></i>
                    <?php _e('new_sale'); ?>
                </button>
                <button class="btn btn-secondary" onclick="location.href='../inventory/products.php'">
                    <i class="fas fa-box"></i>
                    <?php _e('add_product'); ?>
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label"><?php _e('total_products'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12%</span>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_customers']); ?></div>
                    <div class="stat-label"><?php _e('total_customers'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+8%</span>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number"><?php echo formatCurrency($stats['sales_today']); ?></div>
                    <div class="stat-label"><?php _e('sales_today'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+23%</span>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['low_stock_items']; ?></div>
                    <div class="stat-label"><?php _e('low_stock_items'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i>
                    <span>-5%</span>
                </div>
            </div>
        </div>

        <!-- Charts and Data Section -->
        <div class="dashboard-grid">
            <!-- Sales Chart -->
            <div class="dashboard-card chart-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        <?php _e('sales_overview'); ?>
                    </h3>
                    <div class="card-actions">
                        <select class="form-select small" onchange="updateSalesChart(this.value)">
                            <option value="6"><?php _e('last_6_months'); ?></option>
                            <option value="12"><?php _e('last_12_months'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-receipt"></i>
                        <?php _e('recent_orders'); ?>
                    </h3>
                    <a href="../sales/orders.php" class="card-link"><?php _e('view_all'); ?></a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="order-list">
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                        <div class="order-customer"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                                        <div class="order-date"><?php echo formatDate($order['order_date']); ?></div>
                                    </div>
                                    <div class="order-amount">
                                        <?php echo formatCurrency($order['total_amount']); ?>
                                    </div>
                                    <div class="order-status">
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php _e($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p><?php _e('no_recent_orders'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="dashboard-card alert-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php _e('low_stock_items'); ?>
                    </h3>
                    <a href="../inventory/stock.php" class="card-link"><?php _e('manage_stock'); ?></a>
                </div>
                <div class="card-body">
                    <?php if (!empty($low_stock_products)): ?>
                        <div class="stock-list">
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="stock-item">
                                    <div class="stock-info">
                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                                    </div>
                                    <div class="stock-quantity">
                                        <span class="quantity-value"><?php echo $product['stock_quantity']; ?></span>
                                        <span class="quantity-unit"><?php echo $product['unit_of_measure']; ?></span>
                                        <div class="quantity-progress">
                                            <div class="progress-bar" style="width: <?php echo min(100, ($product['stock_quantity'] / $product['min_stock_level']) * 100); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state success">
                            <i class="fas fa-check-circle"></i>
                            <p><?php _e('all_products_in_stock'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Products -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-star"></i>
                        <?php _e('top_products'); ?>
                    </h3>
                    <a href="../reports/sales_report.php" class="card-link"><?php _e('view_report'); ?></a>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_products)): ?>
                        <div class="product-list">
                            <?php foreach ($top_products as $index => $product): ?>
                                <div class="product-item">
                                    <div class="product-rank"><?php echo $index + 1; ?></div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="product-sold"><?php echo $product['total_sold']; ?> <?php _e('sold'); ?></div>
                                    </div>
                                    <div class="product-price">
                                        <?php echo formatCurrency($product['unit_price']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box"></i>
                            <p><?php _e('no_sales_data'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title"><?php _e('quick_actions'); ?></h3>
            <div class="action-grid">
                <a href="../sales/orders.php" class="action-card">
                    <div class="action-icon sales">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php _e('new_sale'); ?></h4>
                        <p><?php _e('create_new_sales_order'); ?></p>
                    </div>
                </a>

                <a href="../inventory/products.php" class="action-card">
                    <div class="action-icon product">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php _e('add_product'); ?></h4>
                        <p><?php _e('add_new_product_to_inventory'); ?></p>
                    </div>
                </a>

                <a href="../sales/customers.php" class="action-card">
                    <div class="action-icon customer">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php _e('add_customer'); ?></h4>
                        <p><?php _e('register_new_customer'); ?></p>
                    </div>
                </a>

                <a href="../reports/sales_report.php" class="action-card">
                    <div class="action-icon report">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php _e('view_reports'); ?></h4>
                        <p><?php _e('generate_business_reports'); ?></p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Dashboard Specific Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .page-header-content h1 {
        font-size: 2rem;
        color: var(--text-primary);
        font-weight: bold;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-description {
        color: var(--text-secondary);
        font-size: 1rem;
        margin: 0;
    }

    .page-actions {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
    }

    .btn-secondary {
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
    }

    .stat-card.success::before { background: var(--success-color); }
    .stat-card.warning::before { background: var(--warning-color); }
    .stat-card.danger::before { background: var(--danger-color); }

    .stat-content {
        flex: 1;
    }

    .stat-number {
        font-size: 2.2rem;
        font-weight: bold;
        color: var(--text-primary);
        line-height: 1;
    }

    .stat-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-top: 5px;
        font-weight: 500;
    }

    .stat-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background: rgba(37, 99, 235, 0.1);
    }

    .stat-card.success .stat-icon { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
    .stat-card.warning .stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
    .stat-card.danger .stat-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }

    .stat-change {
        position: absolute;
        bottom: 15px;
        right: 20px;
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .stat-change.positive { color: var(--success-color); }
    .stat-change.negative { color: var(--danger-color); }

    /* Dashboard Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .chart-card {
        grid-row: span 2;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .card-link {
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .card-link:hover {
        text-decoration: underline;
    }

    .card-body {
        padding: 20px 25px;
    }

    /* Order List */
    .order-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .order-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: var(--light-color);
        border-radius: 8px;
    }

    .order-info {
        flex: 1;
    }

    .order-number {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .order-customer {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin: 2px 0;
    }

    .order-date {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .order-amount {
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 15px;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #dbeafe; color: #2563eb; }
    .status-shipped { background: #e0e7ff; color: #7c3aed; }
    .status-delivered { background: #d1fae5; color: #059669; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* Stock List */
    .stock-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .stock-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #fef2f2;
        border-radius: 8px;
        border-left: 4px solid var(--danger-color);
    }

    .stock-info {
        flex: 1;
    }

    .product-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .product-sku {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .stock-quantity {
        text-align: center;
        min-width: 80px;
    }

    .quantity-value {
        font-weight: bold;
        color: var(--danger-color);
        font-size: 1.1rem;
    }

    .quantity-unit {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .quantity-progress {
        width: 60px;
        height: 4px;
        background: #fee2e2;
        border-radius: 2px;
        margin-top: 4px;
    }

    .progress-bar {
        height: 100%;
        background: var(--danger-color);
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    /* Product List */
    .product-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .product-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px;
    }

    .product-rank {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
    }

    .product-info {
        flex: 1;
    }

    .product-sold {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .product-price {
        font-weight: 600;
        color: var(--success-color);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .empty-state.success i {
        color: var(--success-color);
        opacity: 1;
    }

    /* Quick Actions */
    .quick-actions {
        margin-top: 40px;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .action-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: var(--shadow);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .action-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .action-icon.sales { background: linear-gradient(135deg, #10b981, #059669); }
    .action-icon.product { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .action-icon.customer { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .action-icon.report { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

    .action-content h4 {
        margin: 0 0 5px 0;
        font-size: 1.1rem;
        color: var(--text-primary);
    }

    .action-content p {
        margin: 0;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    /* Form Elements */
    .form-select {
        padding: 6px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.9rem;
        background: white;
    }

    .form-select.small {
        padding: 4px 8px;
        font-size: 0.8rem;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-card {
            grid-row: auto;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 15px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            padding: 20px;
        }
    }
</style>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
    // Sales Chart
    const salesData = <?php echo json_encode($monthly_sales); ?>;
    
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('<?php echo $current_lang; ?>', { 
                    month: 'short', 
                    year: 'numeric' 
                });
            }),
            datasets: [{
                label: '<?php _e('sales'); ?>',
                data: salesData.map(item => item.total_sales),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '<?php echo CURRENCY_SYMBOL; ?>' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    hoverBackgroundColor: '#2563eb'
                }
            }
        }
    });

    // Update chart function
    function updateSalesChart(months) {
        // In a real application, you would fetch new data via AJAX
        // For demo purposes, we'll just update the existing chart
        console.log('Updating chart for ' + months + ' months');
    }

    // Auto-refresh dashboard data every 5 minutes
    setInterval(function() {
        // In a real application, you would refresh the statistics via AJAX
        console.log('Refreshing dashboard data...');
    }, 300000);

    // Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
        // Animate statistics cards
        const statCards = document.querySelectorAll('.stat-card');
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        entry.target.style.transition = 'all 0.6s ease';
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, 100);
                    
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        statCards.forEach(card => {
            observer.observe(card);
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>