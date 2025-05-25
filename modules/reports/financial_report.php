<?php
/**
 * FINANCIAL REPORTS
 * File: modules/reports/financial_report.php
 * Purpose: Comprehensive financial reporting with analytics and visualizations
 * 
 * Features:
 * - Revenue and expense analysis
 * - Profit and loss statements
 * - Cash flow analysis
 * - Monthly comparisons
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
$page_title = __('financial_report');

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'summary';
$period = $_GET['period'] ?? 'monthly'; // monthly, quarterly, yearly

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.csv"');
    // Export logic would go here
    exit;
}

// Get revenue data (from sales orders)
$revenue_query = "SELECT 
    SUM(CASE WHEN so.status != 'cancelled' THEN so.total_amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN so.status != 'cancelled' THEN so.subtotal ELSE 0 END) as gross_sales,
    SUM(CASE WHEN so.status != 'cancelled' THEN so.tax_amount ELSE 0 END) as tax_collected,
    SUM(CASE WHEN so.status != 'cancelled' THEN so.discount_amount ELSE 0 END) as total_discounts,
    COUNT(CASE WHEN so.status != 'cancelled' THEN 1 END) as completed_orders,
    AVG(CASE WHEN so.status != 'cancelled' THEN so.total_amount END) as avg_order_value
    FROM sales_orders so 
    WHERE so.order_date BETWEEN ? AND ?";

$revenue_data = fetchRow($revenue_query, [$date_from, $date_to]);

// Get expense data (from purchase orders - simplified)
$expense_query = "SELECT 
    SUM(CASE WHEN po.status = 'received' THEN po.total_amount ELSE 0 END) as total_expenses,
    SUM(CASE WHEN po.status = 'received' THEN po.subtotal ELSE 0 END) as gross_expenses,
    COUNT(CASE WHEN po.status = 'received' THEN 1 END) as completed_purchases
    FROM purchase_orders po 
    WHERE po.order_date BETWEEN ? AND ?";

$expense_data = fetchRow($expense_query, [$date_from, $date_to]);

// Calculate key financial metrics
$gross_profit = $revenue_data['gross_sales'] - $expense_data['gross_expenses'];
$net_profit = $revenue_data['total_revenue'] - $expense_data['total_expenses'];
$profit_margin = $revenue_data['total_revenue'] > 0 ? ($net_profit / $revenue_data['total_revenue']) * 100 : 0;

// Get monthly trend data for the last 12 months
$monthly_trend_query = "SELECT 
    DATE_FORMAT(so.order_date, '%Y-%m') as month,
    SUM(CASE WHEN so.status != 'cancelled' THEN so.total_amount ELSE 0 END) as revenue,
    (SELECT SUM(CASE WHEN po.status = 'received' THEN po.total_amount ELSE 0 END) 
     FROM purchase_orders po 
     WHERE DATE_FORMAT(po.order_date, '%Y-%m') = DATE_FORMAT(so.order_date, '%Y-%m')) as expenses
    FROM sales_orders so
    WHERE so.order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(so.order_date, '%Y-%m')
    ORDER BY month ASC";

$monthly_trends = fetchAll($monthly_trend_query);

// Get payment method breakdown
$payment_methods_query = "SELECT 
    p.payment_method,
    COUNT(*) as transaction_count,
    SUM(p.amount) as total_amount
    FROM payments p
    WHERE p.payment_date BETWEEN ? AND ?
    AND p.status = 'completed'
    GROUP BY p.payment_method
    ORDER BY total_amount DESC";

$payment_methods = fetchAll($payment_methods_query, [$date_from, $date_to]);

// Get top revenue customers
$top_customers_query = "SELECT 
    c.name,
    c.company,
    COUNT(so.id) as order_count,
    SUM(CASE WHEN so.status != 'cancelled' THEN so.total_amount ELSE 0 END) as total_revenue,
    AVG(CASE WHEN so.status != 'cancelled' THEN so.total_amount END) as avg_order_value
    FROM customers c
    JOIN sales_orders so ON c.id = so.customer_id
    WHERE so.order_date BETWEEN ? AND ?
    GROUP BY c.id, c.name, c.company
    ORDER BY total_revenue DESC
    LIMIT 10";

$top_customers = fetchAll($top_customers_query, [$date_from, $date_to]);

// Get outstanding invoices/receivables
$receivables_query = "SELECT 
    COUNT(*) as outstanding_invoices,
    SUM(i.total_amount - COALESCE(p.paid_amount, 0)) as total_outstanding,
    AVG(DATEDIFF(CURDATE(), i.due_date)) as avg_days_overdue
    FROM invoices i
    LEFT JOIN (
        SELECT invoice_id, SUM(amount) as paid_amount
        FROM payments 
        WHERE status = 'completed'
        GROUP BY invoice_id
    ) p ON i.id = p.invoice_id
    WHERE i.status IN ('sent', 'overdue')
    AND (i.total_amount - COALESCE(p.paid_amount, 0)) > 0";

$receivables_data = fetchRow($receivables_query);

// Get cash flow data (simplified)
$cash_flow_query = "SELECT 
    DATE(created_at) as transaction_date,
    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as cash_in,
    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as cash_out
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY transaction_date ASC";

$cash_flow = fetchAll($cash_flow_query, [$date_from, $date_to]);

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
                    <i class="fas fa-chart-area"></i>
                    <?php _e('financial_report'); ?>
                </h1>
                <p class="page-description"><?php _e('analyze_financial_performance'); ?></p>
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
                    <label for="reportType" class="filter-label"><?php _e('report_type'); ?></label>
                    <select id="reportType" name="report_type" class="form-select">
                        <option value="summary" <?php echo ($report_type === 'summary') ? 'selected' : ''; ?>><?php _e('summary'); ?></option>
                        <option value="detailed" <?php echo ($report_type === 'detailed') ? 'selected' : ''; ?>><?php _e('detailed'); ?></option>
                        <option value="comparison" <?php echo ($report_type === 'comparison') ? 'selected' : ''; ?>><?php _e('comparison'); ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        <?php _e('apply_filters'); ?>
                    </button>
                    <a href="financial_report.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i>
                        <?php _e('reset'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Financial Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card revenue">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($revenue_data['total_revenue']); ?></div>
                    <div class="card-label"><?php _e('total_revenue'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-footer">
                    <span><?php echo number_format($revenue_data['completed_orders']); ?> <?php _e('orders'); ?></span>
                </div>
            </div>
            
            <div class="summary-card expense">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($expense_data['total_expenses']); ?></div>
                    <div class="card-label"><?php _e('total_expenses'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="card-footer">
                    <span><?php echo number_format($expense_data['completed_purchases']); ?> <?php _e('purchases'); ?></span>
                </div>
            </div>
            
            <div class="summary-card profit">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($net_profit); ?></div>
                    <div class="card-label"><?php _e('net_profit'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="card-footer">
                    <span><?php echo number_format($profit_margin, 1); ?>% <?php _e('margin'); ?></span>
                </div>
            </div>
            
            <div class="summary-card receivables">
                <div class="card-content">
                    <div class="card-number"><?php echo formatCurrency($receivables_data['total_outstanding']); ?></div>
                    <div class="card-label"><?php _e('outstanding_receivables'); ?></div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="card-footer">
                    <span><?php echo number_format($receivables_data['outstanding_invoices']); ?> <?php _e('invoices'); ?></span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Revenue vs Expenses Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        <?php _e('revenue_vs_expenses_trend'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="revenueExpenseChart"></canvas>
                </div>
            </div>
            
            <!-- Payment Methods Breakdown -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-credit-card"></i>
                        <?php _e('payment_methods'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
            
            <!-- Profit Margin Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-percentage"></i>
                        <?php _e('profit_margin_trend'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="profitMarginChart"></canvas>
                </div>
            </div>
            
            <!-- Cash Flow -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-money-bill-wave"></i>
                        <?php _e('cash_flow'); ?>
                    </h3>
                </div>
                <div class="chart-body">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Section -->
        <div class="tables-grid">
            <!-- Top Revenue Customers -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-crown"></i>
                        <?php _e('top_revenue_customers'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_customers)): ?>
                        <div class="data-table-container">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th><?php _e('customer'); ?></th>
                                        <th><?php _e('orders'); ?></th>
                                        <th><?php _e('revenue'); ?></th>
                                        <th><?php _e('avg_order'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                        <tr>
                                            <td>
                                                <div class="customer-info">
                                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                    <?php if ($customer['company']): ?>
                                                        <div class="customer-company"><?php echo htmlspecialchars($customer['company']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($customer['order_count']); ?></td>
                                            <td class="revenue-amount"><?php echo formatCurrency($customer['total_revenue']); ?></td>
                                            <td><?php echo formatCurrency($customer['avg_order_value']); ?></td>
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
            
            <!-- Key Metrics Summary -->
            <div class="data-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calculator"></i>
                        <?php _e('key_metrics'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="metrics-list">
                        <div class="metric-item">
                            <span class="metric-label"><?php _e('gross_profit'); ?>:</span>
                            <span class="metric-value"><?php echo formatCurrency($gross_profit); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label"><?php _e('average_order_value'); ?>:</span>
                            <span class="metric-value"><?php echo formatCurrency($revenue_data['avg_order_value']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label"><?php _e('total_tax_collected'); ?>:</span>
                            <span class="metric-value"><?php echo formatCurrency($revenue_data['tax_collected']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label"><?php _e('total_discounts'); ?>:</span>
                            <span class="metric-value"><?php echo formatCurrency($revenue_data['total_discounts']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label"><?php _e('avg_days_overdue'); ?>:</span>
                            <span class="metric-value"><?php echo number_format($receivables_data['avg_days_overdue'], 1); ?> <?php _e('days'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Statement -->
        <div class="pl-statement">
            <div class="statement-header">
                <h3><?php _e('profit_loss_statement'); ?></h3>
                <p><?php echo formatDate($date_from) . ' - ' . formatDate($date_to); ?></p>
            </div>
            
            <div class="statement-body">
                <table class="statement-table">
                    <tbody>
                        <tr class="section-header">
                            <td colspan="2"><strong><?php _e('revenue'); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('gross_sales'); ?></td>
                            <td class="amount"><?php echo formatCurrency($revenue_data['gross_sales']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('less_discounts'); ?></td>
                            <td class="amount negative">(<?php echo formatCurrency($revenue_data['total_discounts']); ?>)</td>
                        </tr>
                        <tr class="subtotal">
                            <td><strong><?php _e('net_revenue'); ?></strong></td>
                            <td class="amount"><strong><?php echo formatCurrency($revenue_data['total_revenue'] - $revenue_data['total_discounts']); ?></strong></td>
                        </tr>
                        
                        <tr class="section-header">
                            <td colspan="2"><strong><?php _e('expenses'); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('cost_of_goods_sold'); ?></td>
                            <td class="amount"><?php echo formatCurrency($expense_data['gross_expenses']); ?></td>
                        </tr>
                        <tr class="subtotal">
                            <td><strong><?php _e('gross_profit'); ?></strong></td>
                            <td class="amount"><strong><?php echo formatCurrency($gross_profit); ?></strong></td>
                        </tr>
                        
                        <tr class="total">
                            <td><strong><?php _e('net_profit'); ?></strong></td>
                            <td class="amount <?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                                <strong><?php echo formatCurrency($net_profit); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    /* Financial Report Styles */
    .filter-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
    }

    .summary-card.revenue::before { background: var(--success-color); }
    .summary-card.expense::before { background: var(--danger-color); }
    .summary-card.profit::before { background: var(--primary-color); }
    .summary-card.receivables::before { background: var(--warning-color); }

    .card-content {
        position: relative;
        z-index: 2;
    }

    .card-number {
        font-size: 2rem;
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
        background: rgba(37, 99, 235, 0.1);
        z-index: 1;
    }

    .summary-card.revenue .card-icon { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
    .summary-card.expense .card-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
    .summary-card.profit .card-icon { background: rgba(37, 99, 235, 0.1); color: var(--primary-color); }
    .summary-card.receivables .card-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }

    .card-footer {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        margin-top: 15px;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
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
        padding: 20px;
    }

    .data-table.compact {
        font-size: 0.9rem;
    }

    .data-table.compact th,
    .data-table.compact td {
        padding: 12px 15px;
    }

    .customer-info .customer-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .customer-info .customer-company {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .revenue-amount {
        font-weight: 600;
        color: var(--success-color);
    }

    .metrics-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .metric-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .metric-item:last-child {
        border-bottom: none;
    }

    .metric-label {
        font-weight: 500;
        color: var(--text-secondary);
    }

    .metric-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    .pl-statement {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .statement-header {
        padding: 25px;
        border-bottom: 1px solid var(--border-color);
        background: var(--light-color);
    }

    .statement-header h3 {
        font-size: 1.3rem;
        color: var(--text-primary);
        margin-bottom: 5px;
    }

    .statement-header p {
        color: var(--text-secondary);
        margin: 0;
    }

    .statement-body {
        padding: 25px;
    }

    .statement-table {
        width: 100%;
        border-collapse: collapse;
    }

    .statement-table td {
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .statement-table .section-header td {
        padding-top: 20px;
        border-bottom: 2px solid var(--primary-color);
        color: var(--primary-color);
    }

    .statement-table .subtotal td {
        border-bottom: 2px solid var(--border-color);
        padding: 15px 0;
    }

    .statement-table .total td {
        border-bottom: 3px double var(--primary-color);
        padding: 20px 0;
        font-size: 1.1rem;
    }

    .statement-table .amount {
        text-align: right;
        font-weight: 600;
        width: 150px;
    }

    .amount.positive {
        color: var(--success-color);
    }

    .amount.negative {
        color: var(--danger-color);
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
    }
</style>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
    // Chart data
    const monthlyTrends = <?php echo json_encode($monthly_trends); ?>;
    const paymentMethods = <?php echo json_encode($payment_methods); ?>;
    const cashFlow = <?php echo json_encode($cash_flow); ?>;
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        initializeRevenueExpenseChart();
        initializePaymentMethodsChart();
        initializeProfitMarginChart();
        initializeCashFlowChart();
    });
    
    function initializeRevenueExpenseChart() {
        const ctx = document.getElementById('revenueExpenseChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyTrends.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('<?php echo $current_lang; ?>', { 
                        month: 'short', 
                        year: 'numeric' 
                    });
                }),
                datasets: [{
                    label: '<?php _e('revenue'); ?>',
                    data: monthlyTrends.map(item => item.revenue || 0),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: '<?php _e('expenses'); ?>',
                    data: monthlyTrends.map(item => item.expenses || 0),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
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
                        ticks: {
                            callback: function(value) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    function initializePaymentMethodsChart() {
        const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: paymentMethods.map(item => item.payment_method.replace('_', ' ').toUpperCase()),
                datasets: [{
                    data: paymentMethods.map(item => item.total_amount),
                    backgroundColor: [
                        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
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
                        position: 'bottom'
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
    
    function initializeProfitMarginChart() {
        const ctx = document.getElementById('profitMarginChart').getContext('2d');
        
        const profitMargins = monthlyTrends.map(item => {
            const revenue = item.revenue || 0;
            const expenses = item.expenses || 0;
            return revenue > 0 ? ((revenue - expenses) / revenue * 100) : 0;
        });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyTrends.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('<?php echo $current_lang; ?>', { 
                        month: 'short', 
                        year: 'numeric' 
                    });
                }),
                datasets: [{
                    label: '<?php _e('profit_margin'); ?> (%)',
                    data: profitMargins,
                    backgroundColor: profitMargins.map(margin => margin >= 0 ? '#10b981' : '#ef4444'),
                    borderColor: profitMargins.map(margin => margin >= 0 ? '#059669' : '#dc2626'),
                    borderWidth: 1
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
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    
    function initializeCashFlowChart() {
        const ctx = document.getElementById('cashFlowChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: cashFlow.map(item => formatDate(item.transaction_date)),
                datasets: [{
                    label: '<?php _e('cash_in'); ?>',
                    data: cashFlow.map(item => item.cash_in),
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1
                }, {
                    label: '<?php _e('cash_out'); ?>',
                    data: cashFlow.map(item => -item.cash_out), // Negative for visualization
                    backgroundColor: '#ef4444',
                    borderColor: '#dc2626',
                    borderWidth: 1
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
                        ticks: {
                            callback: function(value) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + Math.abs(value).toLocaleString();
                            }
                        }
                    }
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