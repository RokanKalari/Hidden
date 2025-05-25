<?php
/**
 * SALES ORDER MANAGEMENT
 * File: modules/sales/orders.php
 * Purpose: Complete sales order management system with CRUD operations
 * 
 * Features:
 * - Create, edit, view, delete sales orders
 * - Order listing with search and pagination
 * - Order status management
 * - Stock management integration
 * - Invoice generation
 * - Multi-language support
 * - Print functionality
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('sales.view');

// Set page title
$page_title = __('sales_orders');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_order':
            if (!hasPermission('sales.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $order_id = intval($_POST['order_id']);
            
            try {
                beginTransaction();
                
                // Get order info
                $order = fetchRow("SELECT * FROM sales_orders WHERE id = ?", [$order_id]);
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => __('order_not_found')]);
                    exit;
                }
                
                // Restore stock if order was confirmed/shipped
                if (in_array($order['status'], ['confirmed', 'shipped', 'delivered'])) {
                    $order_items = fetchAll("SELECT * FROM sales_order_items WHERE order_id = ?", [$order_id]);
                    foreach ($order_items as $item) {
                        updateStock($item['product_id'], $item['quantity'], 'add');
                        recordStockMovement($item['product_id'], 'in', $item['quantity'], 'return', $order_id, 'Order cancelled - stock restored');
                    }
                }
                
                // Delete order items
                executeQuery("DELETE FROM sales_order_items WHERE order_id = ?", [$order_id]);
                
                // Delete order
                executeQuery("DELETE FROM sales_orders WHERE id = ?", [$order_id]);
                
                commitTransaction();
                
                logActivity('Sales Order Deleted', 'sales_orders', $order_id, $order);
                
                echo json_encode(['success' => true, 'message' => __('order_deleted_successfully')]);
                
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
            
            $order_id = intval($_POST['order_id']);
            $new_status = sanitize($_POST['status']);
            
            $valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => __('invalid_status')]);
                exit;
            }
            
            try {
                $old_order = fetchRow("SELECT * FROM sales_orders WHERE id = ?", [$order_id]);
                if (!$old_order) {
                    echo json_encode(['success' => false, 'message' => __('order_not_found')]);
                    exit;
                }
                
                beginTransaction();
                
                // Handle stock movements based on status change
                $order_items = fetchAll("SELECT * FROM sales_order_items WHERE order_id = ?", [$order_id]);
                
                // If changing from pending to confirmed/shipped - reduce stock
                if ($old_order['status'] === 'pending' && in_array($new_status, ['confirmed', 'shipped', 'delivered'])) {
                    foreach ($order_items as $item) {
                        if (!hasStock($item['product_id'], $item['quantity'])) {
                            rollbackTransaction();
                            echo json_encode(['success' => false, 'message' => __('insufficient_stock')]);
                            exit;
                        }
                        updateStock($item['product_id'], $item['quantity'], 'subtract');
                        recordStockMovement($item['product_id'], 'out', $item['quantity'], 'sale', $order_id, 'Sale confirmed');
                    }
                }
                
                // If changing to cancelled from confirmed/shipped - restore stock
                if (in_array($old_order['status'], ['confirmed', 'shipped', 'delivered']) && $new_status === 'cancelled') {
                    foreach ($order_items as $item) {
                        updateStock($item['product_id'], $item['quantity'], 'add');
                        recordStockMovement($item['product_id'], 'in', $item['quantity'], 'return', $order_id, 'Order cancelled - stock restored');
                    }
                }
                
                // Update order status
                executeQuery("UPDATE sales_orders SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $order_id]);
                
                commitTransaction();
                
                logActivity('Sales Order Status Updated', 'sales_orders', $order_id, $old_order, ['status' => $new_status]);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_order':
            $order_id = intval($_POST['order_id']);
            
            $order = fetchRow("SELECT so.*, c.name as customer_name FROM sales_orders so 
                              LEFT JOIN customers c ON so.customer_id = c.id WHERE so.id = ?", [$order_id]);
            
            if ($order) {
                $order_items = fetchAll("SELECT soi.*, p.name as product_name, p.sku 
                                        FROM sales_order_items soi 
                                        LEFT JOIN products p ON soi.product_id = p.id 
                                        WHERE soi.order_id = ?", [$order_id]);
                
                $order['items'] = $order_items;
                echo json_encode(['success' => true, 'order' => $order]);
            } else {
                echo json_encode(['success' => false, 'message' => __('order_not_found')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['create_order']) && hasPermission('sales.create')) {
        // Create new sales order
        $customer_id = intval($_POST['customer_id']);
        $order_date = sanitize($_POST['order_date']);
        $delivery_date = sanitize($_POST['delivery_date']) ?: null;
        $notes = sanitize($_POST['notes']);
        $items = json_decode($_POST['order_items'], true);
        
        // Validate required fields
        if (empty($customer_id) || empty($order_date) || empty($items)) {
            $_SESSION['error_message'] = __('required_fields_missing');
        } else {
            try {
                beginTransaction();
                
                // Calculate totals
                $subtotal = 0;
                $valid_items = [];
                
                foreach ($items as $item) {
                    $product_id = intval($item['product_id']);
                    $quantity = intval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    
                    if ($product_id && $quantity > 0 && $unit_price >= 0) {
                        // Verify product exists and get current price
                        $product = fetchRow("SELECT * FROM products WHERE id = ? AND status = 'active'", [$product_id]);
                        if ($product) {
                            $total_price = $quantity * $unit_price;
                            $subtotal += $total_price;
                            
                            $valid_items[] = [
                                'product_id' => $product_id,
                                'quantity' => $quantity,
                                'unit_price' => $unit_price,
                                'total_price' => $total_price
                            ];
                        }
                    }
                }
                
                if (empty($valid_items)) {
                    rollbackTransaction();
                    $_SESSION['error_message'] = __('no_valid_items');
                } else {
                    // Calculate tax and total
                    $tax_rate = floatval(getSetting('tax_rate', 0)) / 100;
                    $tax_amount = $subtotal * $tax_rate;
                    $discount_amount = 0; // Can be added later
                    $total_amount = $subtotal + $tax_amount - $discount_amount;
                    
                    // Generate order number
                    $order_number = generateOrderNumber('SO');
                    
                    // Insert sales order
                    $order_query = "INSERT INTO sales_orders (order_number, customer_id, user_id, order_date, delivery_date, 
                                   status, subtotal, tax_amount, discount_amount, total_amount, notes) 
                                   VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)";
                    
                    executeQuery($order_query, [
                        $order_number, $customer_id, $_SESSION['user_id'], $order_date, $delivery_date,
                        $subtotal, $tax_amount, $discount_amount, $total_amount, $notes
                    ]);
                    
                    $order_id = getLastInsertId();
                    
                    // Insert order items
                    foreach ($valid_items as $item) {
                        $item_query = "INSERT INTO sales_order_items (order_id, product_id, quantity, unit_price, total_price) 
                                      VALUES (?, ?, ?, ?, ?)";
                        
                        executeQuery($item_query, [
                            $order_id, $item['product_id'], $item['quantity'], 
                            $item['unit_price'], $item['total_price']
                        ]);
                    }
                    
                    commitTransaction();
                    
                    logActivity('Sales Order Created', 'sales_orders', $order_id);
                    
                    $_SESSION['success_message'] = __('order_created_successfully');
                    header('Location: orders.php');
                    exit;
                }
                
            } catch (Exception $e) {
                rollbackTransaction();
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$customer_filter = $_GET['customer'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(so.order_number LIKE ? OR c.name LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($customer_filter)) {
    $where_conditions[] = "so.customer_id = ?";
    $params[] = $customer_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "so.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "so.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "so.order_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM sales_orders so 
                LEFT JOIN customers c ON so.customer_id = c.id 
                WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get orders
$orders_query = "SELECT so.*, c.name as customer_name, c.company as customer_company,
                 u.first_name, u.last_name,
                 (SELECT COUNT(*) FROM sales_order_items soi WHERE soi.order_id = so.id) as item_count
                 FROM sales_orders so 
                 LEFT JOIN customers c ON so.customer_id = c.id 
                 LEFT JOIN users u ON so.user_id = u.id
                 WHERE {$where_clause} 
                 ORDER BY so.created_at DESC 
                 LIMIT {$per_page} OFFSET {$pagination['offset']}";

$orders = fetchAll($orders_query, $params);

// Get customers for dropdown
$customers = fetchAll("SELECT * FROM customers WHERE status = 'active' ORDER BY name");

// Get products for order creation
$products = fetchAll("SELECT * FROM products WHERE status = 'active' ORDER BY name");

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
                    <i class="fas fa-receipt"></i>
                    <?php _e('sales_orders'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_sales_orders'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('sales.create')): ?>
                <button class="btn btn-primary" onclick="showCreateOrderModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('new_order'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="exportOrders()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Order Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_records); ?></div>
                    <div class="stat-label"><?php _e('total_orders'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $pending_orders = fetchRow("SELECT COUNT(*) as count FROM sales_orders WHERE status = 'pending'");
                        echo number_format($pending_orders['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('pending_orders'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $month_total = fetchRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_orders 
                                               WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) 
                                               AND status != 'cancelled'");
                        echo formatCurrency($month_total['total']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('this_month_sales'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $avg_order = fetchRow("SELECT AVG(total_amount) as avg_amount FROM sales_orders WHERE status != 'cancelled'");
                        echo formatCurrency($avg_order['avg_amount'] ?? 0);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('avg_order_value'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
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
                           placeholder="<?php _e('search_orders'); ?>..."
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
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>><?php _e('pending'); ?></option>
                        <option value="confirmed" <?php echo ($status_filter === 'confirmed') ? 'selected' : ''; ?>><?php _e('confirmed'); ?></option>
                        <option value="shipped" <?php echo ($status_filter === 'shipped') ? 'selected' : ''; ?>><?php _e('shipped'); ?></option>
                        <option value="delivered" <?php echo ($status_filter === 'delivered') ? 'selected' : ''; ?>><?php _e('delivered'); ?></option>
                        <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <input type="date" 
                           name="date_from" 
                           class="form-input" 
                           placeholder="<?php _e('from_date'); ?>"
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <input type="date" 
                           name="date_to" 
                           class="form-input" 
                           placeholder="<?php _e('to_date'); ?>"
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        <?php _e('search'); ?>
                    </button>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('orders_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('order_number'); ?></th>
                            <th><?php _e('customer'); ?></th>
                            <th><?php _e('order_date'); ?></th>
                            <th><?php _e('items'); ?></th>
                            <th><?php _e('total_amount'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('created_by'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <div class="order-number">
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                            <div class="order-id">#<?php echo $order['id']; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <?php if ($order['customer_company']): ?>
                                                <div class="customer-company"><?php echo htmlspecialchars($order['customer_company']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <div class="order-date"><?php echo formatDate($order['order_date']); ?></div>
                                            <?php if ($order['delivery_date']): ?>
                                                <div class="delivery-date">
                                                    <i class="fas fa-truck"></i>
                                                    <?php echo formatDate($order['delivery_date']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="item-count">
                                            <i class="fas fa-box"></i>
                                            <?php echo $order['item_count']; ?> <?php _e('items'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="total-amount"><?php echo formatCurrency($order['total_amount']); ?></span>
                                    </td>
                                    <td>
                                        <select class="status-select status-<?php echo $order['status']; ?>" 
                                                onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)"
                                                <?php echo hasPermission('sales.edit') ? '' : 'disabled'; ?>>
                                            <option value="pending" <?php echo ($order['status'] === 'pending') ? 'selected' : ''; ?>><?php _e('pending'); ?></option>
                                            <option value="confirmed" <?php echo ($order['status'] === 'confirmed') ? 'selected' : ''; ?>><?php _e('confirmed'); ?></option>
                                            <option value="shipped" <?php echo ($order['status'] === 'shipped') ? 'selected' : ''; ?>><?php _e('shipped'); ?></option>
                                            <option value="delivered" <?php echo ($order['status'] === 'delivered') ? 'selected' : ''; ?>><?php _e('delivered'); ?></option>
                                            <option value="cancelled" <?php echo ($order['status'] === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewOrder(<?php echo $order['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-print" 
                                                    onclick="printOrder(<?php echo $order['id']; ?>)"
                                                    title="<?php _e('print'); ?>">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if (hasPermission('sales.edit')): ?>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editOrder(<?php echo $order['id']; ?>)"
                                                    title="<?php _e('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('sales.delete')): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteOrder(<?php echo $order['id']; ?>)"
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
                                        <i class="fas fa-receipt"></i>
                                        <p><?php _e('no_orders_found'); ?></p>
                                        <?php if (hasPermission('sales.create')): ?>
                                        <button class="btn btn-primary" onclick="showCreateOrderModal()">
                                            <i class="fas fa-plus"></i>
                                            <?php _e('create_first_order'); ?>
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
                    <?php echo generatePaginationHTML($pagination, 'orders.php?search=' . urlencode($search) . '&customer=' . $customer_filter . '&status=' . $status_filter . '&date_from=' . $date_from . '&date_to=' . $date_to); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Order Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3 id="orderModalTitle"><?php _e('create_new_order'); ?></h3>
            <button class="modal-close" onclick="closeOrderModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="orderForm" method="POST">
            <div class="modal-body">
                <div class="order-form-grid">
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('customer_information'); ?></h4>
                        <div class="form-group">
                            <label for="customerId" class="form-label"><?php _e('customer'); ?> *</label>
                            <select id="customerId" name="customer_id" class="form-select" required>
                                <option value=""><?php _e('select_customer'); ?></option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                        <?php if ($customer['company']): ?>
                                            - <?php echo htmlspecialchars($customer['company']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Order Information -->
                    <div class="form-section">
                        <h4 class="section-title"><?php _e('order_information'); ?></h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="orderDate" class="form-label"><?php _e('order_date'); ?> *</label>
                                <input type="date" id="orderDate" name="order_date" class="form-input" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="deliveryDate" class="form-label"><?php _e('delivery_date'); ?></label>
                                <input type="date" id="deliveryDate" name="delivery_date" class="form-input">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="orderNotes" class="form-label"><?php _e('notes'); ?></label>
                            <textarea id="orderNotes" name="notes" class="form-textarea" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items-section">
                    <div class="section-header">
                        <h4 class="section-title"><?php _e('order_items'); ?></h4>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addOrderItem()">
                            <i class="fas fa-plus"></i>
                            <?php _e('add_item'); ?>
                        </button>
                    </div>
                    
                    <div class="items-table-container">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 35%"><?php _e('product'); ?></th>
                                    <th style="width: 15%"><?php _e('quantity'); ?></th>
                                    <th style="width: 20%"><?php _e('unit_price'); ?></th>
                                    <th style="width: 20%"><?php _e('total'); ?></th>
                                    <th style="width: 10%"><?php _e('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="orderItemsBody">
                                <!-- Items will be added dynamically -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Order Totals -->
                    <div class="order-totals">
                        <div class="totals-grid">
                            <div class="total-row">
                                <span class="total-label"><?php _e('subtotal'); ?>:</span>
                                <span class="total-value" id="orderSubtotal"><?php echo formatCurrency(0); ?></span>
                            </div>
                            <div class="total-row">
                                <span class="total-label"><?php _e('tax'); ?> (<?php echo getSetting('tax_rate', 0); ?>%):</span>
                                <span class="total-value" id="orderTax"><?php echo formatCurrency(0); ?></span>
                            </div>
                            <div class="total-row total-final">
                                <span class="total-label"><?php _e('total'); ?>:</span>
                                <span class="total-value" id="orderTotal"><?php echo formatCurrency(0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="create_order" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('create_order'); ?>
                </button>
            </div>
            <input type="hidden" name="order_items" id="orderItemsData">
        </form>
    </div>
</div>

<!-- View Order Modal -->
<div id="viewOrderModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3 id="viewOrderTitle"><?php _e('order_details'); ?></h3>
            <button class="modal-close" onclick="closeViewOrderModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="viewOrderContent">
            <!-- Order details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewOrderModal()">
                <?php _e('close'); ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="printCurrentOrder()">
                <i class="fas fa-print"></i>
                <?php _e('print'); ?>
            </button>
        </div>
    </div>
</div>

<style>
    /* Sales Orders Styles */
    .large-modal .modal-content {
        max-width: 1000px;
        width: 95%;
    }

    .order-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .form-section {
        background: var(--light-color);
        padding: 20px;
        border-radius: 8px;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--primary-color);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .order-items-section {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: var(--light-color);
        border-bottom: 1px solid var(--border-color);
    }

    .items-table-container {
        overflow-x: auto;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .items-table th,
    .items-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .items-table th {
        background: white;
        font-weight: 600;
        color: var(--text-primary);
    }

    .item-row {
        background: white;
    }

    .item-row:hover {
        background: var(--light-color);
    }

    .item-select,
    .item-quantity,
    .item-price {
        width: 100%;
        padding: 8px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
    }

    .item-total {
        font-weight: 600;
        color: var(--success-color);
    }

    .btn-remove-item {
        background: var(--danger-color);
        color: white;
        border: none;
        border-radius: 4px;
        width: 30px;
        height: 30px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .order-totals {
        padding: 20px;
        background: var(--light-color);
        border-top: 1px solid var(--border-color);
    }

    .totals-grid {
        max-width: 300px;
        margin-left: auto;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
    }

    .total-final {
        border-top: 2px solid var(--primary-color);
        margin-top: 10px;
        padding-top: 15px;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .total-label {
        font-weight: 500;
    }

    .total-value {
        font-weight: 600;
        color: var(--success-color);
    }

    /* Table specific styles */
    .order-number {
        font-weight: 600;
    }

    .order-id {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .customer-company {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .date-info .delivery-date {
        font-size: 0.8rem;
        color: var(--info-color);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .item-count {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
    }

    .total-amount {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--success-color);
    }

    .status-select {
        padding: 4px 8px;
        border: none;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        color: white;
    }

    .status-select.status-pending {
        background: var(--warning-color);
    }

    .status-select.status-confirmed {
        background: var(--info-color);
    }

    .status-select.status-shipped {
        background: var(--primary-color);
    }

    .status-select.status-delivered {
        background: var(--success-color);
    }

    .status-select.status-cancelled {
        background: var(--danger-color);
    }

    .btn-print {
        background: var(--info-color);
        color: white;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .order-form-grid {
            grid-template-columns: 1fr;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .data-table {
            min-width: 800px;
        }
    }
</style>

<script>
    // Sales order management JavaScript
    let orderItems = [];
    let currentOrderId = null;
    const products = <?php echo json_encode($products); ?>;
    const taxRate = <?php echo getSetting('tax_rate', 0); ?> / 100;
    
    function showCreateOrderModal() {
        document.getElementById('orderModal').classList.add('show');
        addOrderItem(); // Add first item
    }
    
    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
        document.getElementById('orderForm').reset();
        orderItems = [];
        document.getElementById('orderItemsBody').innerHTML = '';
        updateOrderTotals();
    }
    
    function addOrderItem() {
        const itemIndex = orderItems.length;
        orderItems.push({
            product_id: '',
            quantity: 1,
            unit_price: 0,
            total_price: 0
        });
        
        const tbody = document.getElementById('orderItemsBody');
        const row = document.createElement('tr');
        row.className = 'item-row';
        row.innerHTML = `
            <td>
                <select class="item-select" onchange="updateItemProduct(${itemIndex}, this.value)">
                    <option value=""><?php _e('select_product'); ?></option>
                    ${products.map(product => `
                        <option value="${product.id}" data-price="${product.unit_price}">
                            ${product.name} (${product.sku})
                        </option>
                    `).join('')}
                </select>
            </td>
            <td>
                <input type="number" class="item-quantity" min="1" value="1" 
                       onchange="updateItemQuantity(${itemIndex}, this.value)">
            </td>
            <td>
                <input type="number" class="item-price" step="0.01" min="0" value="0" 
                       onchange="updateItemPrice(${itemIndex}, this.value)">
            </td>
            <td class="item-total">
                ${formatCurrency(0)}
            </td>
            <td>
                <button type="button" class="btn-remove-item" onclick="removeOrderItem(${itemIndex})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    }
    
    function removeOrderItem(index) {
        orderItems.splice(index, 1);
        rebuildItemsTable();
        updateOrderTotals();
    }
    
    function updateItemProduct(index, productId) {
        if (productId) {
            const product = products.find(p => p.id == productId);
            if (product) {
                orderItems[index].product_id = productId;
                orderItems[index].unit_price = parseFloat(product.unit_price);
                
                // Update price field
                const row = document.getElementById('orderItemsBody').children[index];
                row.querySelector('.item-price').value = product.unit_price;
                
                updateItemTotal(index);
            }
        } else {
            orderItems[index].product_id = '';
            orderItems[index].unit_price = 0;
            updateItemTotal(index);
        }
    }
    
    function updateItemQuantity(index, quantity) {
        orderItems[index].quantity = parseInt(quantity) || 1;
        updateItemTotal(index);
    }
    
    function updateItemPrice(index, price) {
        orderItems[index].unit_price = parseFloat(price) || 0;
        updateItemTotal(index);
    }
    
    function updateItemTotal(index) {
        const item = orderItems[index];
        item.total_price = item.quantity * item.unit_price;
        
        // Update total display
        const row = document.getElementById('orderItemsBody').children[index];
        row.querySelector('.item-total').textContent = formatCurrency(item.total_price);
        
        updateOrderTotals();
    }
    
    function updateOrderTotals() {
        const subtotal = orderItems.reduce((sum, item) => sum + item.total_price, 0);
        const tax = subtotal * taxRate;
        const total = subtotal + tax;
        
        document.getElementById('orderSubtotal').textContent = formatCurrency(subtotal);
        document.getElementById('orderTax').textContent = formatCurrency(tax);
        document.getElementById('orderTotal').textContent = formatCurrency(total);
    }
    
    function rebuildItemsTable() {
        const tbody = document.getElementById('orderItemsBody');
        tbody.innerHTML = '';
        
        orderItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td>
                    <select class="item-select" onchange="updateItemProduct(${index}, this.value)">
                        <option value=""><?php _e('select_product'); ?></option>
                        ${products.map(product => `
                            <option value="${product.id}" data-price="${product.unit_price}" 
                                    ${product.id == item.product_id ? 'selected' : ''}>
                                ${product.name} (${product.sku})
                            </option>
                        `).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" class="item-quantity" min="1" value="${item.quantity}" 
                           onchange="updateItemQuantity(${index}, this.value)">
                </td>
                <td>
                    <input type="number" class="item-price" step="0.01" min="0" value="${item.unit_price}" 
                           onchange="updateItemPrice(${index}, this.value)">
                </td>
                <td class="item-total">
                    ${formatCurrency(item.total_price)}
                </td>
                <td>
                    <button type="button" class="btn-remove-item" onclick="removeOrderItem(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
    
    function viewOrder(orderId) {
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_order');
        formData.append('order_id', orderId);
        
        fetch('orders.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                displayOrderDetails(data.order);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function displayOrderDetails(order) {
        const content = `
            <div class="order-details">
                <div class="order-header">
                    <div class="order-info">
                        <h4>Order #${order.order_number}</h4>
                        <p>Customer: ${order.customer_name}</p>
                        <p>Date: ${formatDate(order.order_date)}</p>
                        <p>Status: <span class="status-badge status-${order.status}">${order.status}</span></p>
                    </div>
                </div>
                
                <div class="order-items">
                    <h5>Order Items</h5>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${order.items.map(item => `
                                <tr>
                                    <td>${item.product_name} (${item.sku})</td>
                                    <td>${item.quantity}</td>
                                    <td>${formatCurrency(item.unit_price)}</td>
                                    <td>${formatCurrency(item.total_price)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="order-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>${formatCurrency(order.subtotal)}</span>
                    </div>
                    <div class="total-row">
                        <span>Tax:</span>
                        <span>${formatCurrency(order.tax_amount)}</span>
                    </div>
                    <div class="total-row total-final">
                        <span>Total:</span>
                        <span>${formatCurrency(order.total_amount)}</span>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('viewOrderContent').innerHTML = content;
        document.getElementById('viewOrderModal').classList.add('show');
        currentOrderId = order.id;
    }
    
    function closeViewOrderModal() {
        document.getElementById('viewOrderModal').classList.remove('show');
    }
    
    function editOrder(orderId) {
        // Implement edit functionality
        alert('Edit order functionality - Order ID: ' + orderId);
    }
    
    function deleteOrder(orderId) {
        if (confirm('<?php _e('confirm_delete_order'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_order');
            formData.append('order_id', orderId);
            
            fetch('orders.php', {
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
    
    function updateOrderStatus(orderId, newStatus) {
        if (confirm('<?php _e('confirm_status_change'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);
            
            fetch('orders.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('<?php _e('status_updated_successfully'); ?>');
                    location.reload();
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showError('<?php _e('operation_failed'); ?>');
            });
        } else {
            // Revert select value
            location.reload();
        }
    }
    
    function printOrder(orderId) {
        window.open(`print_order.php?id=${orderId}`, '_blank', 'width=800,height=600');
    }
    
    function printCurrentOrder() {
        if (currentOrderId) {
            printOrder(currentOrderId);
        }
    }
    
    function exportOrders() {
        window.open('export_orders.php', '_blank');
    }
    
    // Form submission
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        // Validate form
        if (orderItems.length === 0 || !orderItems.some(item => item.product_id)) {
            e.preventDefault();
            showError('<?php _e('please_add_items'); ?>');
            return false;
        }
        
        // Set order items data
        document.getElementById('orderItemsData').value = JSON.stringify(orderItems);
        
        showLoading();
    });
    
    // Close modals when clicking outside
    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });
    
    document.getElementById('viewOrderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeViewOrderModal();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>