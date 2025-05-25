<?php
/**
 * SALES REPORTS
 * File: modules/reports/sales_report.php
 * Purpose: Comprehensive sales reporting with analytics and visualizations
 * 
 * Features:
 * - Sales by date range
 * - Product performance analysis
 * - Customer analysis
 * - Monthly/yearly trends
 * - Export functionality
 * - Interactive charts
 * - Multi-language support
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('reports.view');

// Set page title
$page_title = __('sales_report');

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$customer_id = $_GET['customer_id'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$report_type = $_GET['report_type'] ?? 'summary';

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Export logic would go here
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    // Implementation would generate CSV output
    exit;
}

// Build base query conditions
$where_conditions = ["so.status != 'cancelled'"];
$params = [];

if (!empty($date_from)) {
    $where_conditions[] = "so.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "so.order_date <= ?";
    $params[] = $date_to;
}

if (!empty($customer_id)) {
    $where_conditions[] = "so.customer_id = ?";
    $params[] = $customer_id;
}

if (!empty($status_filter)) {
    if ($status_filter !== 'all') {
        $where_conditions[] = "so.status = ?";
        $params[] = $status_filter;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get sales summary data
$summary_query = "SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(so.total_amount), 0) as total_sales,
    COALESCE(AVG(so.total_amount), 0) as avg_order_value,
    COALESCE(SUM(so.subtotal), 0) as total_subtotal,
    COALESCE(SUM(so.tax_amount), 0) as total_tax,
    COUNT(DISTINCT so.customer_id) as unique_customers
    FROM sales_orders so 
    WHERE {$where_clause}";

$summary = fetchRow($summary_query, $params);

// Get daily sales data for chart
$daily_sales_query = "SELECT 
    DATE(so.order_date) as sale_date,
    COUNT(*) as order_count,
    COALESCE(SUM(so.total_amount), 0) as daily_total
    FROM sales_orders so 
    WHERE {$where_clause}
    GROUP BY DATE(so.order_date)
    ORDER BY sale_date ASC";

$daily_sales = fetchAll($daily_sales_query, $params);

// Get top products
$top_products_query = "SELECT 
    p.name,
    p.sku,
    SUM(soi.quantity) as total_quantity,
    SUM(soi.total_price) as total_revenue,
    COUNT(DISTINCT so.id) as order_count
    FROM sales_order_items soi
    JOIN sales_orders so ON soi.order_id = so.id
    JOIN products p ON soi.product_id = p.id
    WHERE {$where_clause}
    GROUP BY p.id, p.name, p.sku
    ORDER BY total_revenue DESC
    LIMIT 10";

$top_products = fetchAll($top_products_query, $params);

// Get top customers
$top_customers_query = "SELECT 
    c.name,
    c.company,
    COUNT(so.id) as order_count,
    COALESCE(SUM(so.total_amount), 0) as total_spent,
    COALESCE(AVG(so.total_amount), 0) as avg_order_value
    FROM customers c
    JOIN sales_orders so ON c.id = so.customer_id
    WHERE {$where_clause}
    GROUP BY c.id, c.name, c.company
    ORDER BY total_spent DESC
    LIMIT 10";

$top_customers = fetchAll($top_customers_query, $params);

// Get monthly trends (last 12 months)
$monthly_trends_query = "SELECT 
    DATE_FORMAT(so.order_date, '%Y-%m') as month,
    COUNT(*) as order_count,
    COALESCE(SUM(so.total_amount), 0) as monthly_total
    FROM sales_orders so
    WHERE so.order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND so.status != 'cancelled'
    GROUP BY DATE_FORMAT(so.order_date, '%Y-%m')
    ORDER BY month ASC";

$monthly_trends = fetchAll($monthly_trends_query);

// Get status breakdown
$status_breakdown_query = "SELECT 
    so.status,
    COUNT(*) as order_count,
    COALESCE(SUM(so.total_amount), 0) as status_total
    FROM sales_orders so
    WHERE {$where_clause}
    GROUP BY so.status
    ORDER BY status_total DESC";

$status_breakdown = fetchAll($status_breakdown_query, $params);

// Get data for dropdowns
$customers = fetchAll("SELECT id, name, company FROM customers WHERE status = 'active' ORDER BY name");
$products = fetchAll("SELECT id, name, sku FROM products WHERE status = 'active' ORDER BY name");

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
                    <i class="fas fa-chart-line"></i>
                    <?php _e('sales_report'); ?>
                </h1>
                <p class="page-description"><?php _e('analyze_sales_performance'); ?></p>
            </div>
            <div class="page-actions">
                <button class="btn btn-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i>
                    <?php _e('print'); ?>
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="dateFrom" class="filter-label"><?php _e('from_date'); ?></label>
                    <input type="date" id="dateFrom" name="date_from" class="form-input" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label for="dateTo" class="filter-label"><?php _e('to_date'); ?></label>
                    <input type="date" id="dateTo" name="date_to" class="form-input" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-group">
                    <label for="customerId" class="filter-label"><?php _e('customer'); ?></label>
                    <select id="customerId" name="customer_id" class="form-select">
                        <option value=""><?php _e('all_customers'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                                <?php if ($customer['company']): ?>
                                    - <?php echo htmlspecialchars($customer['company']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="statusFilter" class="filter-label"><?php _e('status'); ?></label>
                    <select id="statusFilter" name="status" class="form-select">
                        <option value=""><?php _e('all_statuses'); ?></option>
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>><?php _e('all_except_cancelled'); ?></option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>><?php _e('pending'); ?></option>
                        <option value="confirmed" <?php echo ($status_filter === 'confirmed') ? 'selected' : ''; ?>><?php _e('confirmed'); ?></option>
                        <option value="shipped" <?php echo ($status_filter === 'shipped') ? 'selected' : ''; ?>><?php _e('shipped'); ?></option>
                        <option value="delivered" <?php echo ($status_filter === 'delivered') ? 'selected' : ''; ?>><?php _e('delivered'); ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        <?php _e('apply_filters'); ?>
                    </button>
                    <a href="sales_report.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i>
                        <?php _e('reset'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card primary">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($summary['total_sales']); ?></div>
                    <div class="card-label"><?php _e('total_sales'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="card-footer">
                    <span class="period-text"><?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?></span>
                </div>
            </div>
            
            <div class="summary-card success">
                <div class="card-content">
                    <div class="card-number"><?php echo number_format($summary['total_orders']); ?></div>
                    <div class="card-label"><?php _e('total_orders'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="card-footer">
                    <span class="period-text"><?php _e('orders_processed'); ?></span>
                </div>
            </div>
            
            <div class="summary-card info">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($summary['avg_order_value']); ?></div>
                    <div class="card-label"><?php _e('avg_order_value'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="card-footer">
                    <span class="period-text"><?php _e('average_per_order'); ?></span>
                </div>
            </div>
            
            <div class="summary-card warning">
                <div class="card-content">
                    <div class="card-number"><?php echo number_format($summary['unique_customers']); ?></div>
                    <div class="card-label"><?php _e('unique_customers'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-footer">
                    <span class="period-text"><?php _e('customers_served'); ?></span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Daily Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-area"></i>
                        <?php _e('daily_sales_trend'); ?>
                    </h3>
                    <div class="chart-controls">
                        <button class="btn btn-sm" onclick="toggleChartType('dailySalesChart')">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Trends Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        <?php _e('monthly_trends'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>
            
            <!-- Status Breakdown Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        <?php _e('order_status_breakdown'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="statusBreakdownChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Section -->
        <div class="tables-grid">
            <!-- Top Products -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-star"></i>
                        <?php _e('top_selling_products'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_products)): ?>
                        <div class="data-table-container">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php _e('product'); ?></th>
                                        <th><?php _e('quantity_sold'); ?></th>
                                        <th><?php _e('revenue'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $index => $product): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="product-info">
                                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <div class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="quantity-sold"><?php echo number_format($product['total_quantity']); ?></span>
                                            </td>
                                            <td>
                                                <span class="revenue-amount"><?php echo formatCurrency($product['total_revenue']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box"></i>
                            <p><?php _e('no_product_data'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-crown"></i>
                        <?php _e('top_customers'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_customers)): ?>
                        <div class="data-table-container">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php _e('customer'); ?></th>
                                        <th><?php _e('orders'); ?></th>
                                        <th><?php _e('total_spent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $index => $customer): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="customer-info">
                                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                    <?php if ($customer['company']): ?>
                                                        <div class="customer-company"><?php echo htmlspecialchars($customer['company']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="order-count"><?php echo number_format($customer['order_count']); ?></span>
                                            </td>
                                            <td>
                                                <span class="spent-amount"><?php echo formatCurrency($customer['total_spent']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p><?php _e('no_customer_data'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Sales Report Styles */
    .filter-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-color);
    }

    .summary-card.success::before { background: var(--success-color); }
    .summary-card.info::before { background: var(--info-color); }
    .summary-card.warning::before { background: var(--warning-color); }

    .card-content {
        position: relative;
        z-index: 2;
    }

    .card-number {
        font-size: 2.2rem;
        font-weight: bold;
        color: var(--text-primary);
        line-height: 1;
        margin-bottom: 8px;
    }

    .card-label {
        color: var(--text-secondary);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 15px;
    }

    .card-icon {
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
        z-index: 1;
    }

    .summary-card.success .card-icon { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
    .summary-card.info .card-icon { background: rgba(6, 182, 212, 0.1); color: var(--info-color); }
    .summary-card.warning .card-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }

    .card-footer {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        margin-top: 15px;
    }

    .period-text {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    /* Charts Grid */
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .chart-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .chart-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chart-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .chart-controls {
        display: flex;
        gap: 8px;
    }

    .chart-body {
        padding: 20px;
        height: 300px;
    }

    .chart-body canvas {
        width: 100% !important;
        height: 100% !important;
    }

    /* Tables Grid */
    .tables-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .data-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border-color);
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

    .card-body {
        padding: 0;
    }

    .data-table-container {
        overflow-x: auto;
    }

    .data-table.compact {
        font-size: 0.9rem;
    }

    .data-table.compact th,
    .data-table.compact td {
        padding: 12px 15px;
    }

    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-weight: bold;
        font-size: 0.8rem;
        color: white;
    }

    .rank-badge.rank-1 { background: #ffd700; color: #b45309; }
    .rank-badge.rank-2 { background: #c0c0c0; color: #374151; }
    .rank-badge.rank-3 { background: #cd7f32; color: white; }
    .rank-badge:not(.rank-1):not(.rank-2):not(.rank-3) { background: var(--text-secondary); }

    .product-info,
    .customer-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .product-name,
    .customer-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .product-sku,
    .customer-company {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .quantity-sold,
    .order-count {
        font-weight: 600;
        color: var(--info-color);
    }

    .revenue-amount,
    .spent-amount {
        font-weight: 600;
        color: var(--success-color);
    }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
        
        .tables-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filters-form {
            flex-direction: column;
        }
        
        .filter-group {
            min-width: auto;
        }
    }

    @media (max-width: 480px) {
        .summary-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
    // Chart data
    const dailySalesData = <?php echo json_encode($daily_sales); ?>;
    const monthlyTrendsData = <?php echo json_encode($monthly_trends); ?>;
    const statusBreakdownData = <?php echo json_encode($status_breakdown); ?>;
    
    // Chart instances
    let dailySalesChart, monthlyTrendsChart, statusBreakdownChart;
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        initializeDailySalesChart();
        initializeMonthlyTrendsChart();
        initializeStatusBreakdownChart();
    });
    
    function initializeDailySalesChart() {
        const ctx = document.getElementById('dailySalesChart').getContext('2d');
        
        dailySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailySalesData.map(item => formatDate(item.sale_date)),
                datasets: [{
                    label: '<?php _e('daily_sales'); ?>',
                    data: dailySalesData.map(item => item.daily_total),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + context.parsed.y.toLocaleString();
                            }
                        }
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
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }
    
    function initializeMonthlyTrendsChart() {
        const ctx = document.getElementById('monthlyTrendsChart').getContext('2d');
        
        monthlyTrendsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyTrendsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('<?php echo $current_lang; ?>', { 
                        month: 'short', 
                        year: 'numeric' 
                    });
                }),
                datasets: [{
                    label: '<?php _e('monthly_sales'); ?>',
                    data: monthlyTrendsData.map(item => item.monthly_total),
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + context.parsed.y.toLocaleString();
                            }
                        }
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
                }
            }
        });
    }
    
    function initializeStatusBreakdownChart() {
        const ctx = document.getElementById('statusBreakdownChart').getContext('2d');
        
        const statusColors = {
            'pending': '#f59e0b',
            'confirmed': '#06b6d4',
            'shipped': '#2563eb',
            'delivered': '#10b981',
            'cancelled': '#ef4444'
        };
        
        statusBreakdownChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusBreakdownData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                datasets: [{
                    data: statusBreakdownData.map(item => item.order_count),
                    backgroundColor: statusBreakdownData.map(item => statusColors[item.status] || '#6b7280'),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    function toggleChartType(chartId) {
        if (chartId === 'dailySalesChart') {
            const currentType = dailySalesChart.config.type;
            const newType = currentType === 'line' ? 'bar' : 'line';
            
            dailySalesChart.destroy();
            const ctx = document.getElementById('dailySalesChart').getContext('2d');
            
            dailySalesChart = new Chart(ctx, {
                type: newType,
                data: {
                    labels: dailySalesData.map(item => formatDate(item.sale_date)),
                    datasets: [{
                        label: '<?php _e('daily_sales'); ?>',
                        data: dailySalesData.map(item => item.daily_total),
                        borderColor: '#2563eb',
                        backgroundColor: newType === 'bar' ? '#2563eb' : 'rgba(37, 99, 235, 0.1)',
                        borderWidth: newType === 'bar' ? 1 : 3,
                        fill: newType === 'line',
                        tension: newType === 'line' ? 0.4 : 0,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: newType === 'line' ? 5 : 0,
                        pointHoverRadius: newType === 'line' ? 7 : 0,
                        borderRadius: newType === 'bar' ? 4 : 0
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
                    }
                }
            });
        }
    }
    
    function printReport() {
        window.print();
    }
    
    function exportReport() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.open('?' + params.toString(), '_blank');
    }
    
    // Date formatting function
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('<?php echo $current_lang; ?>');
    }
</script>

<?php include '../../includes/footer.php'; ?>