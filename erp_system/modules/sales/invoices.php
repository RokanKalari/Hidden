<?php
/**
 * INVOICE MANAGEMENT
 * File: modules/sales/invoices.php
 * Purpose: Complete invoice management system with PDF generation
 * 
 * Features:
 * - Generate invoices from orders
 * - Invoice listing and search
 * - Invoice status management
 * - PDF invoice generation
 * - Payment tracking
 * - Multi-language support
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('sales.view');

// Set page title
$page_title = __('invoices');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'generate_invoice':
            if (!hasPermission('sales.create')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $order_id = intval($_POST['order_id']);
            
            try {
                // Check if order exists and is confirmed
                $order = fetchRow("SELECT * FROM sales_orders WHERE id = ? AND status IN ('confirmed', 'shipped', 'delivered')", [$order_id]);
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => __('invalid_order_for_invoice')]);
                    exit;
                }
                
                // Check if invoice already exists
                $existing = fetchRow("SELECT id FROM invoices WHERE order_id = ?", [$order_id]);
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => __('invoice_already_exists')]);
                    exit;
                }
                
                beginTransaction();
                
                // Generate invoice number
                $invoice_number = generateInvoiceNumber();
                
                // Create invoice
                $invoice_query = "INSERT INTO invoices (invoice_number, order_id, customer_id, issue_date, due_date, 
                                 subtotal, tax_amount, discount_amount, total_amount, status, user_id) 
                                 VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, ?, 'draft', ?)";
                
                executeQuery($invoice_query, [
                    $invoice_number, $order_id, $order['customer_id'],
                    $order['subtotal'], $order['tax_amount'], $order['discount_amount'], $order['total_amount'],
                    $_SESSION['user_id']
                ]);
                
                $invoice_id = getLastInsertId();
                
                // Copy order items to invoice items
                $order_items = fetchAll("SELECT * FROM sales_order_items WHERE order_id = ?", [$order_id]);
                foreach ($order_items as $item) {
                    executeQuery("INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, total_price) 
                                 VALUES (?, ?, ?, ?, ?)", [
                        $invoice_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total_price']
                    ]);
                }
                
                commitTransaction();
                
                logActivity('Invoice Generated', 'invoices', $invoice_id);
                
                echo json_encode([
                    'success' => true, 
                    'message' => __('invoice_generated_successfully'),
                    'invoice_id' => $invoice_id
                ]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'update_status':
            if (!hasPermission('sales.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $invoice_id = intval($_POST['invoice_id']);
            $new_status = sanitize($_POST['status']);
            
            $valid_statuses = ['draft', 'sent', 'paid', 'cancelled', 'overdue'];
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => __('invalid_status')]);
                exit;
            }
            
            try {
                $old_invoice = fetchRow("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
                
                executeQuery("UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $invoice_id]);
                
                // If marked as paid, record payment
                if ($new_status === 'paid' && $old_invoice['status'] !== 'paid') {
                    executeQuery("INSERT INTO payments (invoice_id, amount, payment_date, payment_method, status) 
                                 VALUES (?, ?, CURDATE(), 'manual', 'completed')", 
                                [$invoice_id, $old_invoice['total_amount']]);
                }
                
                logActivity('Invoice Status Updated', 'invoices', $invoice_id, $old_invoice);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'delete_invoice':
            if (!hasPermission('sales.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $invoice_id = intval($_POST['invoice_id']);
            
            try {
                $invoice = fetchRow("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
                if (!$invoice) {
                    echo json_encode(['success' => false, 'message' => __('invoice_not_found')]);
                    exit;
                }
                
                // Don't allow deletion of paid invoices
                if ($invoice['status'] === 'paid') {
                    echo json_encode(['success' => false, 'message' => __('cannot_delete_paid_invoice')]);
                    exit;
                }
                
                beginTransaction();
                
                // Delete invoice items
                executeQuery("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoice_id]);
                
                // Delete payments
                executeQuery("DELETE FROM payments WHERE invoice_id = ?", [$invoice_id]);
                
                // Delete invoice
                executeQuery("DELETE FROM invoices WHERE id = ?", [$invoice_id]);
                
                commitTransaction();
                
                logActivity('Invoice Deleted', 'invoices', $invoice_id, $invoice);
                
                echo json_encode(['success' => true, 'message' => __('invoice_deleted_successfully')]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_invoice':
            $invoice_id = intval($_POST['invoice_id']);
            
            $invoice = fetchRow("SELECT i.*, c.name as customer_name, c.company, c.address, c.email, c.phone,
                               so.order_number 
                               FROM invoices i 
                               LEFT JOIN customers c ON i.customer_id = c.id 
                               LEFT JOIN sales_orders so ON i.order_id = so.id
                               WHERE i.id = ?", [$invoice_id]);
            
            if ($invoice) {
                $invoice_items = fetchAll("SELECT ii.*, p.name as product_name, p.sku 
                                         FROM invoice_items ii 
                                         LEFT JOIN products p ON ii.product_id = p.id 
                                         WHERE ii.invoice_id = ?", [$invoice_id]);
                
                $payments = fetchAll("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC", [$invoice_id]);
                
                $invoice['items'] = $invoice_items;
                $invoice['payments'] = $payments;
                
                echo json_encode(['success' => true, 'invoice' => $invoice]);
            } else {
                echo json_encode(['success' => false, 'message' => __('invoice_not_found')]);
            }
            exit;
    }
}

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    if (!hasPermission('sales.edit')) {
        $_SESSION['error_message'] = __('access_denied');
    } else {
        $invoice_id = intval($_POST['invoice_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = sanitize($_POST['payment_date']);
        $payment_method = sanitize($_POST['payment_method']);
        $notes = sanitize($_POST['notes']);
        
        if ($amount <= 0) {
            $_SESSION['error_message'] = __('invalid_payment_amount');
        } else {
            try {
                beginTransaction();
                
                // Get invoice info
                $invoice = fetchRow("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
                if (!$invoice) {
                    $_SESSION['error_message'] = __('invoice_not_found');
                } else {
                    // Record payment
                    executeQuery("INSERT INTO payments (invoice_id, amount, payment_date, payment_method, notes, status) 
                                 VALUES (?, ?, ?, ?, ?, 'completed')", 
                                [$invoice_id, $amount, $payment_date, $payment_method, $notes]);
                    
                    // Get total payments
                    $total_payments = fetchRow("SELECT SUM(amount) as total FROM payments WHERE invoice_id = ? AND status = 'completed'", [$invoice_id]);
                    $paid_amount = $total_payments['total'] ?? 0;
                    
                    // Update invoice status
                    if ($paid_amount >= $invoice['total_amount']) {
                        executeQuery("UPDATE invoices SET status = 'paid' WHERE id = ?", [$invoice_id]);
                    } else if ($paid_amount > 0) {
                        executeQuery("UPDATE invoices SET status = 'partial' WHERE id = ?", [$invoice_id]);
                    }
                    
                    commitTransaction();
                    
                    logActivity('Payment Recorded', 'payments', getLastInsertId());
                    
                    $_SESSION['success_message'] = __('payment_recorded_successfully');
                }
            } catch (Exception $e) {
                rollbackTransaction();
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
        
        header('Location: invoices.php');
        exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$customer_filter = $_GET['customer'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR c.name LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if (!empty($customer_filter)) {
    $where_conditions[] = "i.customer_id = ?";
    $params[] = $customer_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "i.issue_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "i.issue_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM invoices i 
                LEFT JOIN customers c ON i.customer_id = c.id 
                WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get invoices
$invoices_query = "SELECT i.*, c.name as customer_name, c.company,
                   so.order_number,
                   COALESCE(SUM(p.amount), 0) as paid_amount
                   FROM invoices i 
                   LEFT JOIN customers c ON i.customer_id = c.id 
                   LEFT JOIN sales_orders so ON i.order_id = so.id
                   LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
                   WHERE {$where_clause} 
                   GROUP BY i.id
                   ORDER BY i.created_at DESC 
                   LIMIT {$per_page} OFFSET {$pagination['offset']}";

$invoices = fetchAll($invoices_query, $params);

// Get customers for dropdown
$customers = fetchAll("SELECT * FROM customers WHERE status = 'active' ORDER BY name");

// Get orders without invoices for generation
$available_orders = fetchAll("SELECT so.*, c.name as customer_name 
                             FROM sales_orders so 
                             LEFT JOIN customers c ON so.customer_id = c.id
                             LEFT JOIN invoices i ON so.id = i.order_id
                             WHERE so.status IN ('confirmed', 'shipped', 'delivered') 
                             AND i.id IS NULL 
                             ORDER BY so.created_at DESC 
                             LIMIT 20");

// Get invoice statistics
$invoice_stats = fetchRow("SELECT 
    COUNT(*) as total_invoices,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
    SUM(total_amount) as total_value,
    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_value
    FROM invoices");

$current_lang = getCurrentLanguage();

/**
 * Generate unique invoice number
 */
function generateInvoiceNumber() {
    $prefix = 'INV';
    $date_part = date('Ym');
    
    // Get next sequence number for this month
    $last_invoice = fetchRow("SELECT invoice_number FROM invoices 
                             WHERE invoice_number LIKE ? 
                             ORDER BY id DESC LIMIT 1", ["{$prefix}{$date_part}%"]);
    
    if ($last_invoice) {
        $last_number = intval(substr($last_invoice['invoice_number'], -4));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
    
    return $prefix . $date_part . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-file-invoice"></i>
                    <?php _e('invoices'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_customer_invoices'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('sales.create')): ?>
                <button class="btn btn-primary" onclick="showGenerateModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('generate_invoice'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="exportInvoices()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Invoice Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($invoice_stats['total_invoices']); ?></div>
                    <div class="stat-label"><?php _e('total_invoices'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($invoice_stats['draft'] + $invoice_stats['sent']); ?></div>
                    <div class="stat-label"><?php _e('pending_payment'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number"><?php echo formatCurrency($invoice_stats['paid_value']); ?></div>
                    <div class="stat-label"><?php _e('paid_amount'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($invoice_stats['overdue']); ?></div>
                    <div class="stat-label"><?php _e('overdue_invoices'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <input type="text" 
                           name="search" 
                           class="form-input" 
                           placeholder="<?php _e('search_invoices'); ?>..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="customer" class="form-select">
                        <option value=""><?php _e('all_customers'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($customer_filter == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="status" class="form-select">
                        <option value=""><?php _e('all_statuses'); ?></option>
                        <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>><?php _e('draft'); ?></option>
                        <option value="sent" <?php echo ($status_filter === 'sent') ? 'selected' : ''; ?>><?php _e('sent'); ?></option>
                        <option value="paid" <?php echo ($status_filter === 'paid') ? 'selected' : ''; ?>><?php _e('paid'); ?></option>
                        <option value="overdue" <?php echo ($status_filter === 'overdue') ? 'selected' : ''; ?>><?php _e('overdue'); ?></option>
                        <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <input type="date" 
                           name="date_from" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <input type="date" 
                           name="date_to" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        <?php _e('search'); ?>
                    </button>
                    <a href="invoices.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Invoices Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('invoices_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('invoice_number'); ?></th>
                            <th><?php _e('customer'); ?></th>
                            <th><?php _e('issue_date'); ?></th>
                            <th><?php _e('due_date'); ?></th>
                            <th><?php _e('amount'); ?></th>
                            <th><?php _e('paid'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($invoices)): ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php
                                $balance = $invoice['total_amount'] - $invoice['paid_amount'];
                                $is_overdue = (strtotime($invoice['due_date']) < time() && $balance > 0 && $invoice['status'] !== 'paid');
                                ?>
                                <tr class="<?php echo $is_overdue ? 'overdue-row' : ''; ?>">
                                    <td>
                                        <div class="invoice-number">
                                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                            <?php if ($invoice['order_number']): ?>
                                                <div class="order-ref"><?php _e('order'); ?>: <?php echo htmlspecialchars($invoice['order_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                            <?php if ($invoice['company']): ?>
                                                <div class="customer-company"><?php echo htmlspecialchars($invoice['company']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($invoice['issue_date']); ?></td>
                                    <td>
                                        <span class="<?php echo $is_overdue ? 'overdue-date' : ''; ?>">
                                            <?php echo formatDate($invoice['due_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="total-amount"><?php echo formatCurrency($invoice['total_amount']); ?></span>
                                    </td>
                                    <td>
                                        <div class="payment-info">
                                            <span class="paid-amount"><?php echo formatCurrency($invoice['paid_amount']); ?></span>
                                            <?php if ($balance > 0): ?>
                                                <div class="balance-due"><?php _e('balance'); ?>: <?php echo formatCurrency($balance); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="status-select status-<?php echo $invoice['status']; ?>" 
                                                onchange="updateInvoiceStatus(<?php echo $invoice['id']; ?>, this.value)"
                                                <?php echo hasPermission('sales.edit') ? '' : 'disabled'; ?>>
                                            <option value="draft" <?php echo ($invoice['status'] === 'draft') ? 'selected' : ''; ?>><?php _e('draft'); ?></option>
                                            <option value="sent" <?php echo ($invoice['status'] === 'sent') ? 'selected' : ''; ?>><?php _e('sent'); ?></option>
                                            <option value="paid" <?php echo ($invoice['status'] === 'paid') ? 'selected' : ''; ?>><?php _e('paid'); ?></option>
                                            <option value="overdue" <?php echo ($invoice['status'] === 'overdue') ? 'selected' : ''; ?>><?php _e('overdue'); ?></option>
                                            <option value="cancelled" <?php echo ($invoice['status'] === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewInvoice(<?php echo $invoice['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-print" 
                                                    onclick="printInvoice(<?php echo $invoice['id']; ?>)"
                                                    title="<?php _e('print_pdf'); ?>">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <?php if ($balance > 0 && hasPermission('sales.edit')): ?>
                                            <button class="btn-action btn-payment" 
                                                    onclick="recordPayment(<?php echo $invoice['id']; ?>)"
                                                    title="<?php _e('record_payment'); ?>">
                                                <i class="fas fa-money-bill"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('sales.delete') && $invoice['status'] !== 'paid'): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteInvoice(<?php echo $invoice['id']; ?>)"
                                                    title="<?php _e('delete'); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-file-invoice"></i>
                                        <p><?php _e('no_invoices_found'); ?></p>
                                        <?php if (hasPermission('sales.create') && !empty($available_orders)): ?>
                                        <button class="btn btn-primary" onclick="showGenerateModal()">
                                            <i class="fas fa-plus"></i>
                                            <?php _e('generate_first_invoice'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_records > $per_page): ?>
                <div class="table-footer">
                    <?php echo generatePaginationHTML($pagination, 'invoices.php?search=' . urlencode($search) . '&customer=' . $customer_filter . '&status=' . $status_filter . '&date_from=' . $date_from . '&date_to=' . $date_to); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Generate Invoice Modal -->
<div id="generateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('generate_invoice'); ?></h3>
            <button class="modal-close" onclick="closeGenerateModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <?php if (!empty($available_orders)): ?>
                <p><?php _e('select_order_to_generate_invoice'); ?>:</p>
                <div class="orders-list">
                    <?php foreach ($available_orders as $order): ?>
                        <div class="order-item" onclick="generateInvoiceFromOrder(<?php echo $order['id']; ?>)">
                            <div class="order-info">
                                <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-customer"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div class="order-date"><?php echo formatDate($order['order_date']); ?></div>
                            </div>
                            <div class="order-amount"><?php echo formatCurrency($order['total_amount']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p><?php _e('no_orders_available_for_invoicing'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeGenerateModal()">
                <?php _e('close'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('record_payment'); ?></h3>
            <button class="modal-close" onclick="closePaymentModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="invoice_id" id="paymentInvoiceId">
                <div id="paymentInvoiceInfo" class="invoice-info-box"></div>
                
                <div class="form-group">
                    <label for="paymentAmount" class="form-label"><?php _e('payment_amount'); ?> *</label>
                    <input type="number" id="paymentAmount" name="amount" class="form-input" 
                           step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="paymentDate" class="form-label"><?php _e('payment_date'); ?> *</label>
                    <input type="date" id="paymentDate" name="payment_date" class="form-input" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="paymentMethod" class="form-label"><?php _e('payment_method'); ?> *</label>
                    <select id="paymentMethod" name="payment_method" class="form-select" required>
                        <option value=""><?php _e('select_payment_method'); ?></option>
                        <option value="cash"><?php _e('cash'); ?></option>
                        <option value="bank_transfer"><?php _e('bank_transfer'); ?></option>
                        <option value="credit_card"><?php _e('credit_card'); ?></option>
                        <option value="check"><?php _e('check'); ?></option>
                        <option value="other"><?php _e('other'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="paymentNotes" class="form-label"><?php _e('notes'); ?></label>
                    <textarea id="paymentNotes" name="notes" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="record_payment" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('record_payment'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Invoice Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3 id="viewModalTitle"><?php _e('invoice_details'); ?></h3>
            <button class="modal-close" onclick="closeViewModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="invoiceDetails">
            <!-- Invoice details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                <?php _e('close'); ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="printCurrentInvoice()">
                <i class="fas fa-print"></i>
                <?php _e('print'); ?>
            </button>
        </div>
    </div>
</div>

<style>
    /* Invoice Management Styles */
    .overdue-row {
        background: #fef2f2 !important;
    }
    
    .overdue-date {
        color: var(--danger-color);
        font-weight: bold;
    }
    
    .order-ref {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .customer-company {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .payment-info .balance-due {
        font-size: 0.8rem;
        color: var(--warning-color);
    }
    
    .status-select.status-draft {
        background: var(--text-secondary);
        color: white;
    }
    
    .status-select.status-sent {
        background: var(--info-color);
        color: white;
    }
    
    .status-select.status-paid {
        background: var(--success-color);
        color: white;
    }
    
    .status-select.status-overdue {
        background: var(--danger-color);
        color: white;
    }
    
    .status-select.status-cancelled {
        background: var(--text-secondary);
        color: white;
    }
    
    .btn-payment {
        background: var(--success-color);
        color: white;
    }
    
    .orders-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .order-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .order-item:hover {
        background: var(--light-color);
        border-color: var(--primary-color);
    }
    
    .order-info .order-number {
        font-weight: 600;
    }
    
    .order-info .order-customer {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .order-info .order-date {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }
    
    .order-amount {
        font-weight: 600;
        color: var(--success-color);
    }
    
    .invoice-info-box {
        background: var(--light-color);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .large-modal .modal-content {
        max-width: 900px;
    }
</style>

<script>
    let currentInvoiceId = null;
    
    function showGenerateModal() {
        document.getElementById('generateModal').classList.add('show');
    }
    
    function closeGenerateModal() {
        document.getElementById('generateModal').classList.remove('show');
    }
    
    function generateInvoiceFromOrder(orderId) {
        if (confirm('<?php _e('confirm_generate_invoice'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'generate_invoice');
            formData.append('order_id', orderId);
            
            fetch('invoices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess(data.message);
                    closeGenerateModal();
                    location.reload();
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showError('<?php _e('operation_failed'); ?>');
            });
        }
    }
    
    function updateInvoiceStatus(invoiceId, newStatus) {
        if (confirm('<?php _e('confirm_status_change'); ?>')) {
            const formData = new FormData();
            formData.append('ajax_action', 'update_status');
            formData.append('invoice_id', invoiceId);
            formData.append('status', newStatus);
            
            fetch('invoices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('<?php _e('status_updated_successfully'); ?>');
                    location.reload();
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('<?php _e('operation_failed'); ?>');
            });
        } else {
            location.reload();
        }
    }
    
    function deleteInvoice(invoiceId) {
        if (confirm('<?php _e('confirm_delete_invoice'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_invoice');
            formData.append('invoice_id', invoiceId);
            
            fetch('invoices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess(data.message);
                    location.reload();
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showError('<?php _e('operation_failed'); ?>');
            });
        }
    }
    
    function recordPayment(invoiceId) {
        // Get invoice info from table row
        const row = event.target.closest('tr');
        const invoiceNumber = row.querySelector('.invoice-number strong').textContent;
        const totalAmount = row.querySelector('.total-amount').textContent;
        const balanceElement = row.querySelector('.balance-due');
        const balance = balanceElement ? balanceElement.textContent.split(': ')[1] : totalAmount;
        
        document.getElementById('paymentInvoiceId').value = invoiceId;
        document.getElementById('paymentInvoiceInfo').innerHTML = `
            <h4>${invoiceNumber}</h4>
            <p><strong><?php _e('total_amount'); ?>:</strong> ${totalAmount}</p>
            <p><strong><?php _e('balance_due'); ?>:</strong> ${balance}</p>
        `;
        
        // Set default payment amount to balance due
        const balanceValue = parseFloat(balance.replace(/[^\d.-]/g, ''));
        document.getElementById('paymentAmount').value = balanceValue;
        
        document.getElementById('paymentModal').classList.add('show');
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('show');
    }
    
    function viewInvoice(invoiceId) {
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_invoice');
        formData.append('invoice_id', invoiceId);
        
        fetch('invoices.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                displayInvoiceDetails(data.invoice);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function displayInvoiceDetails(invoice) {
        let html = `
            <div class="invoice-preview">
                <div class="invoice-header">
                    <h2>Invoice ${invoice.invoice_number}</h2>
                    <div class="invoice-dates">
                        <p><strong><?php _e('issue_date'); ?>:</strong> ${formatDate(invoice.issue_date)}</p>
                        <p><strong><?php _e('due_date'); ?>:</strong> ${formatDate(invoice.due_date)}</p>
                    </div>
                </div>
                
                <div class="invoice-customer">
                    <h3><?php _e('bill_to'); ?></h3>
                    <p><strong>${invoice.customer_name}</strong></p>
                    ${invoice.company ? `<p>${invoice.company}</p>` : ''}
                    ${invoice.address ? `<p>${invoice.address}</p>` : ''}
                    ${invoice.email ? `<p>${invoice.email}</p>` : ''}
                    ${invoice.phone ? `<p>${invoice.phone}</p>` : ''}
                </div>
                
                <div class="invoice-items">
                    <h3><?php _e('items'); ?></h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th><?php _e('product'); ?></th>
                                <th><?php _e('quantity'); ?></th>
                                <th><?php _e('unit_price'); ?></th>
                                <th><?php _e('total'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        invoice.items.forEach(item => {
            html += `
                <tr>
                    <td>${item.product_name} (${item.sku})</td>
                    <td>${item.quantity}</td>
                    <td>${formatCurrency(item.unit_price)}</td>
                    <td>${formatCurrency(item.total_price)}</td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
                
                <div class="invoice-totals">
                    <div class="total-row">
                        <span><?php _e('subtotal'); ?>:</span>
                        <span>${formatCurrency(invoice.subtotal)}</span>
                    </div>
                    <div class="total-row">
                        <span><?php _e('tax'); ?>:</span>
                        <span>${formatCurrency(invoice.tax_amount)}</span>
                    </div>
                    <div class="total-row final-total">
                        <span><?php _e('total'); ?>:</span>
                        <span>${formatCurrency(invoice.total_amount)}</span>
                    </div>
                </div>
        `;
        
        if (invoice.payments && invoice.payments.length > 0) {
            html += `
                <div class="invoice-payments">
                    <h3><?php _e('payments'); ?></h3>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th><?php _e('date'); ?></th>
                                <th><?php _e('amount'); ?></th>
                                <th><?php _e('method'); ?></th>
                                <th><?php _e('notes'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            invoice.payments.forEach(payment => {
                html += `
                    <tr>
                        <td>${formatDate(payment.payment_date)}</td>
                        <td>${formatCurrency(payment.amount)}</td>
                        <td>${payment.payment_method}</td>
                        <td>${payment.notes || '-'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
        }
        
        html += '</div>';
        
        document.getElementById('invoiceDetails').innerHTML = html;
        document.getElementById('viewModal').classList.add('show');
        currentInvoiceId = invoice.id;
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('show');
    }
    
    function printInvoice(invoiceId) {
        window.open(`print_invoice.php?id=${invoiceId}`, '_blank', 'width=800,height=600');
    }
    
    function printCurrentInvoice() {
        if (currentInvoiceId) {
            printInvoice(currentInvoiceId);
        }
    }
    
    function exportInvoices() {
        window.open('export_invoices.php', '_blank');
    }
    
    // Utility functions
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('<?php echo $current_lang; ?>');
    }
    
    function formatCurrency(amount) {
        return new Intl.NumberFormat('<?php echo $current_lang; ?>', {
            style: 'currency',
            currency: '<?php echo DEFAULT_CURRENCY; ?>'
        }).format(amount);
    }
    
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>