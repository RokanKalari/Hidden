<?php
/**
 * INVENTORY REPORTS
 * File: modules/reports/inventory_report.php
 * Purpose: Comprehensive inventory reporting with analytics and visualizations
 * 
 * Features:
 * - Stock levels and valuation
 * - Low stock alerts
 * - Stock movement analysis
 * - Category-wise breakdown
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
$page_title = __('inventory_report');

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$category_id = $_GET['category_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'summary';

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
    // Export logic would go here
    exit;
}

// Build base query conditions
$where_conditions = ["p.status = 'active'"];
$params = [];

if (!empty($category_id)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

$where_clause = implode(' AND ', $where_conditions);

// Get inventory summary data
$summary_query = "SELECT 
    COUNT(*) as total_products,
    SUM(p.stock_quantity) as total_stock_quantity,
    SUM(p.stock_quantity * p.cost_price) as total_stock_value,
    SUM(p.stock_quantity * p.unit_price) as total_retail_value,
    SUM(CASE WHEN p.stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN p.stock_quantity <= p.min_stock_level AND p.stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
    AVG(p.stock_quantity) as avg_stock_level
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$where_clause}";

$summary = fetchRow($summary_query, $params);

// Get category breakdown
$category_breakdown_query = "SELECT 
    COALESCE(c.name, 'Uncategorized') as category_name,
    COUNT(p.id) as product_count,
    SUM(p.stock_quantity) as total_quantity,
    SUM(p.stock_quantity * p.cost_price) as category_value,
    AVG(p.stock_quantity) as avg_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    " . (!empty($category_id) ? "AND p.category_id = ?" : "") . "
    GROUP BY c.id, c.name
    ORDER BY category_value DESC";

$category_params = !empty($category_id) ? [$category_id] : [];
$category_breakdown = fetchAll($category_breakdown_query, $category_params);

// Get top value products
$top_products_query = "SELECT 
    p.name,
    p.sku,
    p.stock_quantity,
    p.cost_price,
    p.unit_price,
    (p.stock_quantity * p.cost_price) as stock_value,
    c.name as category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$where_clause}
    ORDER BY stock_value DESC
    LIMIT 20";

$top_products = fetchAll($top_products_query, $params);

// Get low stock products
$low_stock_query = "SELECT 
    p.name,
    p.sku,
    p.stock_quantity,
    p.min_stock_level,
    p.cost_price,
    c.name as category_name,
    (p.min_stock_level - p.stock_quantity) as shortage
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active' 
    AND p.stock_quantity <= p.min_stock_level
    " . (!empty($category_id) ? "AND p.category_id = ?" : "") . "
    ORDER BY shortage DESC
    LIMIT 20";

$low_stock_products = fetchAll($low_stock_query, $category_params);

// Get stock movements for the period
$movements_query = "SELECT 
    DATE(sm.created_at) as movement_date,
    SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as stock_in,
    SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as stock_out,
    COUNT(*) as total_movements
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
    " . (!empty($category_id) ? "AND p.category_id = ?" : "") . "
    GROUP BY DATE(sm.created_at)
    ORDER BY movement_date ASC";

$movement_params = [$date_from, $date_to];
if (!empty($category_id)) {
    $movement_params[] = $category_id;
}
$stock_movements = fetchAll($movements_query, $movement_params);

// Get ABC analysis data (products by value contribution)
$abc_analysis_query = "SELECT 
    p.name,
    p.sku,
    (p.stock_quantity * p.cost_price) as stock_value,
    p.stock_quantity,
    c.name as category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$where_clause}
    AND p.stock_quantity > 0
    ORDER BY stock_value DESC";

$abc_products = fetchAll($abc_analysis_query, $params);

// Calculate ABC categories
$total_value = array_sum(array_column($abc_products, 'stock_value'));
$cumulative_value = 0;
$abc_data = [];

foreach ($abc_products as $product) {
    $cumulative_value += $product['stock_value'];
    $percentage = ($cumulative_value / $total_value) * 100;
    
    if ($percentage <= 70) {
        $abc_category = 'A';
    } elseif ($percentage <= 90) {
        $abc_category = 'B';
    } else {
        $abc_category = 'C';
    }
    
    $abc_data[] = array_merge($product, ['abc_category' => $abc_category, 'cumulative_percentage' => $percentage]);
}

// Get categories for filter
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

$current_lang = getCurrentLanguage();
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-chart-pie"></i>
                    <?php _e('inventory_report'); ?>
                </h1>
                <p class="page-description"><?php _e('analyze_inventory_performance'); ?></p>
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
                    <label for="categoryId" class="filter-label"><?php _e('category'); ?></label>
                    <select id="categoryId" name="category_id" class="form-select">
                        <option value=""><?php _e('all_categories'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        <?php _e('apply_filters'); ?>
                    </button>
                    <a href="inventory_report.php" class="btn btn-secondary">
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
                    <div class="card-number"><?php echo number_format($summary['total_products']); ?></div>
                    <div class="card-label"><?php _e('total_products'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            
            <div class="summary-card success">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($summary['total_stock_value']); ?></div>
                    <div class="card-label"><?php _e('total_stock_value'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            
            <div class="summary-card warning">
                <div class="card-content">
                    <div class="card-number"><?php echo number_format($summary['low_stock']); ?></div>
                    <div class="card-label"><?php _e('low_stock_items'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="summary-card danger">
                <div class="card-content">
                    <div class="card-number"><?php echo number_format($summary['out_of_stock']); ?></div>
                    <div class="card-label"><?php _e('out_of_stock'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Category Breakdown Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        <?php _e('inventory_by_category'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            
            <!-- Stock Movement Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        <?php _e('stock_movements'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="movementChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Section -->
        <div class="tables-grid">
            <!-- Top Value Products -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-crown"></i>
                        <?php _e('top_value_products'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_products)): ?>
                        <div class="data-table-container">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th><?php _e('product'); ?></th>
                                        <th><?php _e('stock'); ?></th>
                                        <th><?php _e('value'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($top_products, 0, 10) as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <div class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($product['stock_quantity']); ?></td>
                                            <td><?php echo formatCurrency($product['stock_value']); ?></td>
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
            
            <!-- Low Stock Products -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php _e('low_stock_products'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($low_stock_products)): ?>
                        <div class="data-table-container">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th><?php _e('product'); ?></th>
                                        <th><?php _e('current'); ?></th>
                                        <th><?php _e('minimum'); ?></th>
                                        <th><?php _e('shortage'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($low_stock_products, 0, 10) as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <div class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                                                </div>
                                            </td>
                                            <td class="<?php echo $product['stock_quantity'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </td>
                                            <td><?php echo number_format($product['min_stock_level']); ?></td>
                                            <td class="text-danger"><?php echo number_format($product['shortage']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state success">
                            <i class="fas fa-check-circle"></i>
                            <p><?php _e('all_products_adequately_stocked'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ABC Analysis -->
        <?php if (!empty($abc_data)): ?>
        <div class="abc-analysis-section">
            <div class="section-header">
                <h3><?php _e('abc_analysis'); ?></h3>
                <p><?php _e('abc_analysis_description'); ?></p>
            </div>
            
            <div class="abc-summary">
                <?php
                $abc_summary = [];
                foreach ($abc_data as $item) {
                    if (!isset($abc_summary[$item['abc_category']])) {
                        $abc_summary[$item['abc_category']] = ['count' => 0, 'value' => 0];
                    }
                    $abc_summary[$item['abc_category']]['count']++;
                    $abc_summary[$item['abc_category']]['value'] += $item['stock_value'];
                }
                ?>
                
                <div class="abc-cards">
                    <div class="abc-card category-a">
                        <div class="abc-letter">A</div>
                        <div class="abc-details">
                            <div class="abc-count"><?php echo $abc_summary['A']['count'] ?? 0; ?> <?php _e('products'); ?></div>
                            <div class="abc-value"><?php echo formatCurrency($abc_summary['A']['value'] ?? 0); ?></div>
                            <div class="abc-percentage">~70% <?php _e('of_value'); ?></div>
                        </div>
                    </div>
                    
                    <div class="abc-card category-b">
                        <div class="abc-letter">B</div>
                        <div class="abc-details">
                            <div class="abc-count"><?php echo $abc_summary['B']['count'] ?? 0; ?> <?php _e('products'); ?></div>
                            <div class="abc-value"><?php echo formatCurrency($abc_summary['B']['value'] ?? 0); ?></div>
                            <div class="abc-percentage">~20% <?php _e('of_value'); ?></div>
                        </div>
                    </div>
                    
                    <div class="abc-card category-c">
                        <div class="abc-letter">C</div>
                        <div class="abc-details">
                            <div class="abc-count"><?php echo $abc_summary['C']['count'] ?? 0; ?> <?php _e('products'); ?></div>
                            <div class="abc-value"><?php echo formatCurrency($abc_summary['C']['value'] ?? 0); ?></div>
                            <div class="abc-percentage">~10% <?php _e('of_value'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Inventory Report Styles */
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
    .summary-card.warning::before { background: var(--warning-color); }
    .summary-card.danger::before { background: var(--danger-color); }

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
    .summary-card.warning .card-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
    .summary-card.danger .card-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }

    .charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
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
        background: var(--light-color);
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

    .chart-body {
        padding: 20px;
        height: 300px;
    }

    .tables-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
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
        background: var(--light-color);
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

    .product-info .product-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .product-info .product-sku {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .text-danger { color: var(--danger-color); }
    .text-warning { color: var(--warning-color); }

    .abc-analysis-section {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 25px;
        margin-bottom: 30px;
    }

    .section-header h3 {
        font-size: 1.3rem;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .section-header p {
        color: var(--text-secondary);
        margin-bottom: 20px;
    }

    .abc-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .abc-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        border-radius: 10px;
        border: 2px solid;
    }

    .abc-card.category-a {
        border-color: var(--success-color);
        background: rgba(16, 185, 129, 0.05);
    }

    .abc-card.category-b {
        border-color: var(--warning-color);
        background: rgba(245, 158, 11, 0.05);
    }

    .abc-card.category-c {
        border-color: var(--text-secondary);
        background: rgba(107, 114, 128, 0.05);
    }

    .abc-letter {
        font-size: 2rem;
        font-weight: bold;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .category-a .abc-letter { background: var(--success-color); }
    .category-b .abc-letter { background: var(--warning-color); }
    .category-c .abc-letter { background: var(--text-secondary); }

    .abc-details {
        flex: 1;
    }

    .abc-count {
        font-weight: 600;
        color: var(--text-primary);
    }

    .abc-value {
        font-size: 1.1rem;
        font-weight: bold;
        color: var(--success-color);
        margin: 4px 0;
    }

    .abc-percentage {
        font-size: 0.85rem;
        color: var(--text-secondary);
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

    .empty-state.success i {
        color: var(--success-color);
        opacity: 1;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .charts-grid,
        .tables-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .abc-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
    // Chart data
    const categoryData = <?php echo json_encode($category_breakdown); ?>;
    const movementData = <?php echo json_encode($stock_movements); ?>;
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        initializeCategoryChart();
        initializeMovementChart();
    });
    
    function initializeCategoryChart() {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category_name),
                datasets: [{
                    data: categoryData.map(item => item.category_value),
                    backgroundColor: [
                        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6b7280'
                    ],
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
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return context.label + ': <?php echo CURRENCY_SYMBOL; ?>' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    function initializeMovementChart() {
        const ctx = document.getElementById('movementChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: movementData.map(item => formatDate(item.movement_date)),
                datasets: [{
                    label: '<?php _e('stock_in'); ?>',
                    data: movementData.map(item => item.stock_in),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }, {
                    label: '<?php _e('stock_out'); ?>',
                    data: movementData.map(item => item.stock_out),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
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