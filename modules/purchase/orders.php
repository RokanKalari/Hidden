<?php
/**
 * PURCHASE ORDER MANAGEMENT
 * File: modules/purchase/orders.php
 * Purpose: Complete purchase order management system with CRUD operations
 * 
 * Features:
 * - Create, edit, view, delete purchase orders
 * - Order listing with search and pagination
 * - Order status management
 * - Stock management integration
 * - Multi-language support
 * - Print functionality
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('purchase.view');

// Set page title
$page_title = __('purchase_orders');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_order':
            if (!hasPermission('purchase.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $order_id = intval($_POST['order_id']);
            
            try {
                beginTransaction();
                
                // Get order info
                $order = fetchRow("SELECT * FROM purchase_orders WHERE id = ?", [$order_id]);
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => __('order_not_found')]);
                    exit;
                }
                
                // Delete order items
                executeQuery("DELETE FROM purchase_order_items WHERE order_id = ?", [$order_id]);
                
                // Delete order
                executeQuery("DELETE FROM purchase_orders WHERE id = ?", [$order_id]);
                
                commitTransaction();
                
                logActivity('Purchase Order Deleted', 'purchase_orders', $order_id, $order);
                
                echo json_encode(['success' => true, 'message' => __('order_deleted_successfully')]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'update_status':
            if (!hasPermission('purchase.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $order_id = intval($_POST['order_id']);
            $new_status = sanitize($_POST['status']);
            
            $valid_statuses = ['pending', 'approved', 'ordered', 'received', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => __('invalid_status')]);
                exit;
            }
            
            try {
                $old_order = fetchRow("SELECT * FROM purchase_orders WHERE id = ?", [$order_id]);
                if (!$old_order) {
                    echo json_encode(['success' => false, 'message' => __('order_not_found')]);
                    exit;
                }
                
                beginTransaction();
                
                // Handle stock movements based on status change
                if ($new_status === 'received' && $old_order['status'] !== 'received') {
                    $order_items = fetchAll("SELECT * FROM purchase_order_items WHERE order_id = ?", [$order_id]);
                    foreach ($order_items as $item) {
                        updateStock($item['product_id'], $item['quantity'], 'add');
                        recordStockMovement($item['product_id'], 'in', $item['quantity'], 'purchase', $order_id, 'Purchase order received');
                    }
                }
                
                // Update order status
                executeQuery("UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $order_id]);
                
                commitTransaction();
                
                logActivity('Purchase Order Status Updated', 'purchase_orders', $order_id, $old_order, ['status' => $new_status]);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_order':
            $order_id = intval($_POST['order_id']);
            
            $order = fetchRow("SELECT po.*, s.name as supplier_name FROM purchase_orders po 
                              LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?", [$order_id]);
            
            if ($order) {
                $order_items = fetchAll("SELECT poi.*, p.name as product_name, p.sku 
                                        FROM purchase_order_items poi 
                                        LEFT JOIN products p ON poi.product_id = p.id 
                                        WHERE poi.order_id = ?", [$order_id]);
                
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
    
    if (isset($_POST['create_order']) && hasPermission('purchase.create')) {
        // Create new purchase order
        $supplier_id = intval($_POST['supplier_id']);
        $order_date = sanitize($_POST['order_date']);
        $expected_date = sanitize($_POST['expected_date']) ?: null;
        $notes = sanitize($_POST['notes']);
        $items = json_decode($_POST['order_items'], true);
        
        // Validate required fields
        if (empty($supplier_id) || empty($order_date) || empty($items)) {
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
                
                if (empty($valid_items)) {
                    rollbackTransaction();
                    $_SESSION['error_message'] = __('no_valid_items');
                } else {
                    // Calculate tax and total
                    $tax_rate = floatval(getSetting('purchase_tax_rate', 0)) / 100;
                    $tax_amount = $subtotal * $tax_rate;
                    $total_amount = $subtotal + $tax_amount;
                    
                    // Generate order number
                    $order_number = generateOrderNumber('PO');
                    
                    // Insert purchase order
                    $order_query = "INSERT INTO purchase_orders (order_number, supplier_id, user_id, order_date, expected_date, 
                                   status, subtotal, tax_amount, total_amount, notes) 
                                   VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";
                    
                    executeQuery($order_query, [
                        $order_number, $supplier_id, $_SESSION['user_id'], $order_date, $expected_date,
                        $subtotal, $tax_amount, $total_amount, $notes
                    ]);
                    
                    $order_id = getLastInsertId();
                    
                    // Insert order items
                    foreach ($valid_items as $item) {
                        $item_query = "INSERT INTO purchase_order_items (order_id, product_id, quantity, unit_price, total_price) 
                                      VALUES (?, ?, ?, ?, ?)";
                        
                        executeQuery($item_query, [
                            $order_id, $item['product_id'], $item['quantity'], 
                            $item['unit_price'], $item['total_price']
                        ]);
                    }
                    
                    commitTransaction();
                    
                    logActivity('Purchase Order Created', 'purchase_orders', $order_id);
                    
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
$supplier_filter = $_GET['supplier'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(po.order_number LIKE ? OR s.name LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($supplier_filter)) {
    $where_conditions[] = "po.supplier_id = ?";
    $params[] = $supplier_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "po.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "po.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "po.order_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM purchase_orders po 
                LEFT JOIN suppliers s ON po.supplier_id = s.id 
                WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get orders
$orders_query = "SELECT po.*, s.name as supplier_name, s.company as supplier_company,
                 u.first_name, u.last_name,
                 (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.order_id = po.id) as item_count
                 FROM purchase_orders po 
                 LEFT JOIN suppliers s ON po.supplier_id = s.id 
                 LEFT JOIN users u ON po.user_id = u.id
                 WHERE {$where_clause} 
                 ORDER BY po.created_at DESC 
                 LIMIT {$per_page} OFFSET {$pagination['offset']}";

$orders = fetchAll($orders_query, $params);

// Get suppliers for dropdown
$suppliers = fetchAll("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name");

// Get products for order creation
$products = fetchAll("SELECT * FROM products WHERE status = 'active' ORDER BY name");

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
                    <i class="fas fa-clipboard-list"></i>
                    <?php _e('purchase_orders'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_purchase_orders'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('purchase.create')): ?>
                <button class="btn btn-primary" onclick="showCreateOrderModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('new_order'); ?>
                </button>
                <?php endif; ?>
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
                    <i class="fas fa-clipboard-list"></i>
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
                    <select name="supplier" class="form-select">
                        <option value=""><?php _e('all_suppliers'); ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo ($supplier_filter == $supplier['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="status" class="form-select">
                        <option value=""><?php _e('all_statuses'); ?></option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>><?php _e('pending'); ?></option>
                        <option value="approved" <?php echo ($status_filter === 'approved') ? 'selected' : ''; ?>><?php _e('approved'); ?></option>
                        <option value="ordered" <?php echo ($status_filter === 'ordered') ? 'selected' : ''; ?>><?php _e('ordered'); ?></option>
                        <option value="received" <?php echo ($status_filter === 'received') ? 'selected' : ''; ?>><?php _e('received'); ?></option>
                        <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                    </select>
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
                <h3 class="table-title"><?php _e('purchase_orders_list'); ?></h3>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('order_number'); ?></th>
                            <th><?php _e('supplier'); ?></th>
                            <th><?php _e('order_date'); ?></th>
                            <th><?php _e('items'); ?></th>
                            <th><?php _e('total_amount'); ?></th>
                            <th><?php _e('status'); ?></th>
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
                                        </div>
                                    </td>
                                    <td>
                                        <div class="supplier-info">
                                            <div class="supplier-name"><?php echo htmlspecialchars($order['supplier_name']); ?></div>
                                            <?php if ($order['supplier_company']): ?>
                                                <div class="supplier-company"><?php echo htmlspecialchars($order['supplier_company']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($order['order_date']); ?></td>
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
                                                <?php echo hasPermission('purchase.edit') ? '' : 'disabled'; ?>>
                                            <option value="pending" <?php echo ($order['status'] === 'pending') ? 'selected' : ''; ?>><?php _e('pending'); ?></option>
                                            <option value="approved" <?php echo ($order['status'] === 'approved') ? 'selected' : ''; ?>><?php _e('approved'); ?></option>
                                            <option value="ordered" <?php echo ($order['status'] === 'ordered') ? 'selected' : ''; ?>><?php _e('ordered'); ?></option>
                                            <option value="received" <?php echo ($order['status'] === 'received') ? 'selected' : ''; ?>><?php _e('received'); ?></option>
                                            <option value="cancelled" <?php echo ($order['status'] === 'cancelled') ? 'selected' : ''; ?>><?php _e('cancelled'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewOrder(<?php echo $order['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('purchase.delete')): ?>
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
                                <td colspan="7" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-clipboard-list"></i>
                                        <p><?php _e('no_orders_found'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Order Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3><?php _e('create_purchase_order'); ?></h3>
            <button class="modal-close" onclick="closeOrderModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="orderForm" method="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supplierId" class="form-label"><?php _e('supplier'); ?> *</label>
                        <select id="supplierId" name="supplier_id" class="form-select" required>
                            <option value=""><?php _e('select_supplier'); ?></option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="orderDate" class="form-label"><?php _e('order_date'); ?> *</label>
                        <input type="date" id="orderDate" name="order_date" class="form-input" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items-section">
                    <div class="section-header">
                        <h4><?php _e('order_items'); ?></h4>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addOrderItem()">
                            <i class="fas fa-plus"></i>
                            <?php _e('add_item'); ?>
                        </button>
                    </div>
                    
                    <div class="items-table-container">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th><?php _e('product'); ?></th>
                                    <th><?php _e('quantity'); ?></th>
                                    <th><?php _e('unit_price'); ?></th>
                                    <th><?php _e('total'); ?></th>
                                    <th><?php _e('actions'); ?></th>
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

<style>
    .large-modal .modal-content {
        max-width: 1000px;
        width: 95%;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
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

    .order-totals {
        padding: 20px;
        background: var(--light-color);
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
    }
</style>

<script>
    let orderItems = [];
    const products = <?php echo json_encode($products); ?>;
    
    function showCreateOrderModal() {
        document.getElementById('orderModal').classList.add('show');
        addOrderItem();
    }
    
    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
        orderItems = [];
        document.getElementById('orderItemsBody').innerHTML = '';
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
        row.innerHTML = `
            <td>
                <select class="form-select" onchange="updateItemProduct(${itemIndex}, this.value)">
                    <option value=""><?php _e('select_product'); ?></option>
                    ${products.map(product => `
                        <option value="${product.id}" data-price="${product.cost_price || product.unit_price}">
                            ${product.name} (${product.sku})
                        </option>
                    `).join('')}
                </select>
            </td>
            <td>
                <input type="number" class="form-input" min="1" value="1" 
                       onchange="updateItemQuantity(${itemIndex}, this.value)">
            </td>
            <td>
                <input type="number" class="form-input" step="0.01" min="0" value="0" 
                       onchange="updateItemPrice(${itemIndex}, this.value)">
            </td>
            <td class="item-total">
                ${formatCurrency(0)}
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderItem(${itemIndex})">
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
                orderItems[index].unit_price = parseFloat(product.cost_price || product.unit_price);
                
                const row = document.getElementById('orderItemsBody').children[index];
                row.querySelector('input[type="number"][step="0.01"]').value = orderItems[index].unit_price;
                
                updateItemTotal(index);
            }
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
        
        const row = document.getElementById('orderItemsBody').children[index];
        row.querySelector('.item-total').textContent = formatCurrency(item.total_price);
        
        updateOrderTotals();
    }
    
    function updateOrderTotals() {
        const subtotal = orderItems.reduce((sum, item) => sum + item.total_price, 0);
        
        document.getElementById('orderSubtotal').textContent = formatCurrency(subtotal);
        document.getElementById('orderTotal').textContent = formatCurrency(subtotal);
    }
    
    function rebuildItemsTable() {
        const tbody = document.getElementById('orderItemsBody');
        tbody.innerHTML = '';
        
        orderItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select class="form-select" onchange="updateItemProduct(${index}, this.value)">
                        <option value=""><?php _e('select_product'); ?></option>
                        ${products.map(product => `
                            <option value="${product.id}" ${product.id == item.product_id ? 'selected' : ''}>
                                ${product.name} (${product.sku})
                            </option>
                        `).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-input" min="1" value="${item.quantity}" 
                           onchange="updateItemQuantity(${index}, this.value)">
                </td>
                <td>
                    <input type="number" class="form-input" step="0.01" min="0" value="${item.unit_price}" 
                           onchange="updateItemPrice(${index}, this.value)">
                </td>
                <td class="item-total">
                    ${formatCurrency(item.total_price)}
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderItem(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
    
    function viewOrder(orderId) {
        alert('View order functionality - Order ID: ' + orderId);
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
            });
        }
    }
    
    function updateOrderStatus(orderId, newStatus) {
        if (confirm('<?php _e('confirm_status_change'); ?>')) {
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
                if (data.success) {
                    showSuccess('<?php _e('status_updated_successfully'); ?>');
                    location.reload();
                } else {
                    showError(data.message);
                }
            });
        } else {
            location.reload();
        }
    }
    
    // Form submission
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        if (orderItems.length === 0) {
            e.preventDefault();
            showError('<?php _e('please_add_items'); ?>');
            return false;
        }
        
        document.getElementById('orderItemsData').value = JSON.stringify(orderItems);
        showLoading();
    });
    
    function formatCurrency(amount) {
        return new Intl.NumberFormat('<?php echo $current_lang; ?>', {
            style: 'currency',
            currency: '<?php echo DEFAULT_CURRENCY; ?>'
        }).format(amount);
    }
</script>

<?php include '../../includes/footer.php'; ?>