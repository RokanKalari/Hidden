<?php
/**
 * CUSTOMER MANAGEMENT
 * File: modules/sales/customers.php
 * Purpose: Complete customer management system with CRUD operations
 * 
 * Features:
 * - Add, edit, delete customers
 * - Customer listing with search and pagination
 * - Customer balance tracking
 * - Order history
 * - Multi-language support
 * - Export functionality
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('customers.view');

// Set page title
$page_title = __('customers');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_customer':
            if (!hasPermission('customers.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $customer_id = intval($_POST['customer_id']);
            
            try {
                // Check if customer has orders
                $order_check = fetchRow("SELECT COUNT(*) as count FROM sales_orders WHERE customer_id = ?", [$customer_id]);
                if ($order_check['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => __('cannot_delete_customer_with_orders')]);
                    exit;
                }
                
                // Get customer info for logging
                $customer = fetchRow("SELECT * FROM customers WHERE id = ?", [$customer_id]);
                
                // Delete customer
                executeQuery("DELETE FROM customers WHERE id = ?", [$customer_id]);
                
                logActivity('Customer Deleted', 'customers', $customer_id, $customer);
                
                echo json_encode(['success' => true, 'message' => __('customer_deleted_successfully')]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'toggle_status':
            if (!hasPermission('customers.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $customer_id = intval($_POST['customer_id']);
            $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            try {
                executeQuery("UPDATE customers SET status = ? WHERE id = ?", [$new_status, $customer_id]);
                logActivity('Customer Status Changed', 'customers', $customer_id);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_customer':
            $customer_id = intval($_POST['customer_id']);
            $customer = fetchRow("SELECT * FROM customers WHERE id = ?", [$customer_id]);
            
            if ($customer) {
                echo json_encode(['success' => true, 'customer' => $customer]);
            } else {
                echo json_encode(['success' => false, 'message' => __('customer_not_found')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['add_customer']) && hasPermission('customers.create')) {
        // Add new customer
        $name = sanitize($_POST['name']);
        $company = sanitize($_POST['company']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $city = sanitize($_POST['city']);
        $country = sanitize($_POST['country']);
        $postal_code = sanitize($_POST['postal_code']);
        $credit_limit = floatval($_POST['credit_limit'] ?? 0);
        
        // Validate required fields
        if (empty($name)) {
            $_SESSION['error_message'] = __('customer_name_required');
        } elseif (!empty($email) && !isValidEmail($email)) {
            $_SESSION['error_message'] = __('invalid_email');
        } else {
            try {
                // Check if email already exists
                if (!empty($email)) {
                    $existing = fetchRow("SELECT id FROM customers WHERE email = ?", [$email]);
                    if ($existing) {
                        $_SESSION['error_message'] = __('email_already_exists');
                    }
                }
                
                if (!isset($_SESSION['error_message'])) {
                    // Insert customer
                    $query = "INSERT INTO customers (name, company, email, phone, address, city, country, postal_code, credit_limit) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($query, [
                        $name, $company, $email, $phone, $address, $city, $country, $postal_code, $credit_limit
                    ]);
                    
                    $customer_id = getLastInsertId();
                    
                    logActivity('Customer Created', 'customers', $customer_id);
                    
                    $_SESSION['success_message'] = __('customer_added_successfully');
                    header('Location: customers.php');
                    exit;
                }
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['edit_customer']) && hasPermission('customers.edit')) {
        // Edit existing customer
        $customer_id = intval($_POST['customer_id']);
        $old_customer = fetchRow("SELECT * FROM customers WHERE id = ?", [$customer_id]);
        
        if ($old_customer) {
            $name = sanitize($_POST['name']);
            $company = sanitize($_POST['company']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $city = sanitize($_POST['city']);
            $country = sanitize($_POST['country']);
            $postal_code = sanitize($_POST['postal_code']);
            $credit_limit = floatval($_POST['credit_limit'] ?? 0);
            
            // Validate required fields
            if (empty($name)) {
                $_SESSION['error_message'] = __('customer_name_required');
            } elseif (!empty($email) && !isValidEmail($email)) {
                $_SESSION['error_message'] = __('invalid_email');
            } else {
                try {
                    // Check if email already exists (excluding current customer)
                    if (!empty($email)) {
                        $existing = fetchRow("SELECT id FROM customers WHERE email = ? AND id != ?", [$email, $customer_id]);
                        if ($existing) {
                            $_SESSION['error_message'] = __('email_already_exists');
                        }
                    }
                    
                    if (!isset($_SESSION['error_message'])) {
                        // Update customer
                        $query = "UPDATE customers SET name = ?, company = ?, email = ?, phone = ?, address = ?, 
                                 city = ?, country = ?, postal_code = ?, credit_limit = ?, updated_at = NOW() WHERE id = ?";
                        
                        executeQuery($query, [
                            $name, $company, $email, $phone, $address, $city, $country, $postal_code, $credit_limit, $customer_id
                        ]);
                        
                        logActivity('Customer Updated', 'customers', $customer_id, $old_customer);
                        
                        $_SESSION['success_message'] = __('customer_updated_successfully');
                        header('Location: customers.php');
                        exit;
                    }
                    
                } catch (Exception $e) {
                    $_SESSION['error_message'] = __('operation_failed');
                }
            }
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR company LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM customers WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get customers
$customers_query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id) as order_count,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM sales_orders so WHERE so.customer_id = c.id AND so.status != 'cancelled') as total_orders_value
                    FROM customers c 
                    WHERE {$where_clause} 
                    ORDER BY c.created_at DESC 
                    LIMIT {$per_page} OFFSET {$pagination['offset']}";

$customers = fetchAll($customers_query, $params);

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
                    <i class="fas fa-users"></i>
                    <?php _e('customers'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_customer_database'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('customers.create')): ?>
                <button class="btn btn-primary" onclick="showAddCustomerModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('add_customer'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="exportCustomers()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Customer Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_records); ?></div>
                    <div class="stat-label"><?php _e('total_customers'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $active_customers = fetchRow("SELECT COUNT(*) as count FROM customers WHERE status = 'active'");
                        echo number_format($active_customers['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('active_customers'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $new_customers = fetchRow("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                        echo number_format($new_customers['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('new_this_month'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $avg_orders = fetchRow("SELECT AVG(order_count) as avg_count FROM (SELECT COUNT(*) as order_count FROM sales_orders GROUP BY customer_id) as subquery");
                        echo number_format($avg_orders['avg_count'] ?? 0, 1);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('avg_orders_per_customer'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
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
                           placeholder="<?php _e('search_customers'); ?>..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="status" class="form-select">
                        <option value=""><?php _e('all_statuses'); ?></option>
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>><?php _e('active'); ?></option>
                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>><?php _e('inactive'); ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        <?php _e('search'); ?>
                    </button>
                    <a href="customers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Customers Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('customers_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('customer_info'); ?></th>
                            <th><?php _e('contact_info'); ?></th>
                            <th><?php _e('location'); ?></th>
                            <th><?php _e('orders'); ?></th>
                            <th><?php _e('total_value'); ?></th>
                            <th><?php _e('balance'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr($customer['name'], 0, 2)); ?>
                                            </div>
                                            <div class="customer-details">
                                                <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                <?php if ($customer['company']): ?>
                                                    <div class="customer-company"><?php echo htmlspecialchars($customer['company']); ?></div>
                                                <?php endif; ?>
                                                <div class="customer-since">
                                                    <?php _e('since'); ?> <?php echo formatDate($customer['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <?php if ($customer['email']): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                                        <?php echo htmlspecialchars($customer['email']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($customer['phone']): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>">
                                                        <?php echo htmlspecialchars($customer['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <?php if ($customer['city'] || $customer['country']): ?>
                                                <div class="location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo trim(htmlspecialchars($customer['city']) . ', ' . htmlspecialchars($customer['country']), ', '); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="order-stats">
                                            <span class="order-count"><?php echo $customer['order_count']; ?></span>
                                            <span class="order-label"><?php _e('orders'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="total-value"><?php echo formatCurrency($customer['total_orders_value']); ?></span>
                                    </td>
                                    <td>
                                        <span class="balance <?php echo ($customer['balance'] < 0) ? 'negative' : 'positive'; ?>">
                                            <?php echo formatCurrency($customer['balance']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="status-toggle <?php echo $customer['status']; ?>" 
                                                onclick="toggleCustomerStatus(<?php echo $customer['id']; ?>, '<?php echo $customer['status']; ?>')">
                                            <?php _e($customer['status']); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewCustomer(<?php echo $customer['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('customers.edit')): ?>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editCustomer(<?php echo $customer['id']; ?>)"
                                                    title="<?php _e('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-orders" 
                                                    onclick="viewCustomerOrders(<?php echo $customer['id']; ?>)"
                                                    title="<?php _e('view_orders'); ?>">
                                                <i class="fas fa-receipt"></i>
                                            </button>
                                            <?php if (hasPermission('customers.delete')): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteCustomer(<?php echo $customer['id']; ?>)"
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
                                        <i class="fas fa-users"></i>
                                        <p><?php _e('no_customers_found'); ?></p>
                                        <?php if (hasPermission('customers.create')): ?>
                                        <button class="btn btn-primary" onclick="showAddCustomerModal()">
                                            <i class="fas fa-plus"></i>
                                            <?php _e('add_first_customer'); ?>
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
                    <?php echo generatePaginationHTML($pagination, 'customers.php?search=' . urlencode($search) . '&status=' . $status_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Customer Modal -->
<div id="customerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php _e('add_customer'); ?></h3>
            <button class="modal-close" onclick="closeCustomerModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="customerForm" method="POST">
            <input type="hidden" name="customer_id" id="customerId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customerName" class="form-label"><?php _e('customer_name'); ?> *</label>
                        <input type="text" id="customerName" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customerCompany" class="form-label"><?php _e('company'); ?></label>
                        <input type="text" id="customerCompany" name="company" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerEmail" class="form-label"><?php _e('email'); ?></label>
                        <input type="email" id="customerEmail" name="email" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerPhone" class="form-label"><?php _e('phone'); ?></label>
                        <input type="tel" id="customerPhone" name="phone" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerCity" class="form-label"><?php _e('city'); ?></label>
                        <input type="text" id="customerCity" name="city" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerCountry" class="form-label"><?php _e('country'); ?></label>
                        <input type="text" id="customerCountry" name="country" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerPostalCode" class="form-label"><?php _e('postal_code'); ?></label>
                        <input type="text" id="customerPostalCode" name="postal_code" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="customerCreditLimit" class="form-label"><?php _e('credit_limit'); ?></label>
                        <input type="number" id="customerCreditLimit" name="credit_limit" class="form-input" 
                               step="0.01" min="0" value="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="customerAddress" class="form-label"><?php _e('address'); ?></label>
                        <textarea id="customerAddress" name="address" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="add_customer" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('save_customer'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Customer Management Styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .customer-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .customer-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
        flex-shrink: 0;
    }

    .customer-details {
        flex: 1;
        min-width: 0;
    }

    .customer-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .customer-company {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .customer-since {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }

    .contact-item i {
        width: 14px;
        color: var(--text-secondary);
        font-size: 12px;
    }

    .contact-item a {
        color: var(--text-primary);
        text-decoration: none;
    }

    .contact-item a:hover {
        color: var(--primary-color);
        text-decoration: underline;
    }

    .location-info .location {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .location i {
        font-size: 12px;
    }

    .order-stats {
        text-align: center;
    }

    .order-count {
        display: block;
        font-size: 1.4rem;
        font-weight: bold;
        color: var(--primary-color);
        line-height: 1;
    }

    .order-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .total-value {
        font-weight: 600;
        color: var(--success-color);
        font-size: 1.05rem;
    }

    .balance {
        font-weight: 600;
        font-size: 1.05rem;
    }

    .balance.positive {
        color: var(--success-color);
    }

    .balance.negative {
        color: var(--danger-color);
    }

    .btn-orders {
        background: var(--primary-color);
        color: white;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .data-table {
            min-width: 1000px;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .customer-info {
            flex-direction: column;
            text-align: center;
            gap: 8px;
        }
        
        .contact-info {
            align-items: center;
        }
    }
</style>

<script>
    // Customer management JavaScript
    let isEditMode = false;
    
    function showAddCustomerModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = '<?php _e('add_customer'); ?>';
        document.getElementById('customerForm').reset();
        document.getElementById('customerId').value = '';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('save_customer'); ?>';
        document.getElementById('submitBtn').name = 'add_customer';
        document.getElementById('customerModal').classList.add('show');
    }
    
    function editCustomer(customerId) {
        isEditMode = true;
        document.getElementById('modalTitle').textContent = '<?php _e('edit_customer'); ?>';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('update_customer'); ?>';
        document.getElementById('submitBtn').name = 'edit_customer';
        
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_customer');
        formData.append('customer_id', customerId);
        
        fetch('customers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const customer = data.customer;
                document.getElementById('customerId').value = customer.id;
                document.getElementById('customerName').value = customer.name || '';
                document.getElementById('customerCompany').value = customer.company || '';
                document.getElementById('customerEmail').value = customer.email || '';
                document.getElementById('customerPhone').value = customer.phone || '';
                document.getElementById('customerAddress').value = customer.address || '';
                document.getElementById('customerCity').value = customer.city || '';
                document.getElementById('customerCountry').value = customer.country || '';
                document.getElementById('customerPostalCode').value = customer.postal_code || '';
                document.getElementById('customerCreditLimit').value = customer.credit_limit || 0;
                
                document.getElementById('customerModal').classList.add('show');
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function viewCustomer(customerId) {
        // Implement customer view modal with detailed information
        alert('View customer functionality - Customer ID: ' + customerId);
    }
    
    function viewCustomerOrders(customerId) {
        // Redirect to orders page filtered by customer
        window.location.href = `../sales/orders.php?customer=${customerId}`;
    }
    
    function deleteCustomer(customerId) {
        if (confirm('<?php _e('confirm_delete_customer'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_customer');
            formData.append('customer_id', customerId);
            
            fetch('customers.php', {
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
    
    function toggleCustomerStatus(customerId, currentStatus) {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_status');
        formData.append('customer_id', customerId);
        formData.append('status', currentStatus);
        
        fetch('customers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function closeCustomerModal() {
        document.getElementById('customerModal').classList.remove('show');
    }
    
    function exportCustomers() {
        window.open('export_customers.php', '_blank');
    }
    
    // Form validation
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
        
        showLoading();
    });
    
    // Close modal when clicking outside
    document.getElementById('customerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCustomerModal();
        }
    });
    
    // Phone number formatting
    document.getElementById('customerPhone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 0) {
            if (value.length <= 3) {
                value = value;
            } else if (value.length <= 6) {
                value = value.slice(0, 3) + '-' + value.slice(3);
            } else {
                value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
            }
        }
        e.target.value = value;
    });
</script>

<?php include '../../includes/footer.php'; ?>