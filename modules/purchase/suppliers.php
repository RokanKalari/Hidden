<?php
/**
 * SUPPLIER MANAGEMENT
 * File: modules/purchase/suppliers.php
 * Purpose: Complete supplier management system with CRUD operations
 */

session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('suppliers.view');

$page_title = __('suppliers');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_supplier':
            if (!hasPermission('suppliers.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $supplier_id = intval($_POST['supplier_id']);
            
            try {
                // Check if supplier has purchase orders
                $order_check = fetchRow("SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?", [$supplier_id]);
                if ($order_check['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => __('cannot_delete_supplier_with_orders')]);
                    exit;
                }
                
                $supplier = fetchRow("SELECT * FROM suppliers WHERE id = ?", [$supplier_id]);
                executeQuery("DELETE FROM suppliers WHERE id = ?", [$supplier_id]);
                
                logActivity('Supplier Deleted', 'suppliers', $supplier_id, $supplier);
                
                echo json_encode(['success' => true, 'message' => __('supplier_deleted_successfully')]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'toggle_status':
            if (!hasPermission('suppliers.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $supplier_id = intval($_POST['supplier_id']);
            $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            try {
                executeQuery("UPDATE suppliers SET status = ? WHERE id = ?", [$new_status, $supplier_id]);
                logActivity('Supplier Status Changed', 'suppliers', $supplier_id);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_supplier':
            $supplier_id = intval($_POST['supplier_id']);
            $supplier = fetchRow("SELECT * FROM suppliers WHERE id = ?", [$supplier_id]);
            
            if ($supplier) {
                echo json_encode(['success' => true, 'supplier' => $supplier]);
            } else {
                echo json_encode(['success' => false, 'message' => __('supplier_not_found')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['add_supplier']) && hasPermission('suppliers.create')) {
        $name = sanitize($_POST['name']);
        $company = sanitize($_POST['company']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $city = sanitize($_POST['city']);
        $country = sanitize($_POST['country']);
        $postal_code = sanitize($_POST['postal_code']);
        $tax_number = sanitize($_POST['tax_number']);
        
        if (empty($name)) {
            $_SESSION['error_message'] = __('supplier_name_required');
        } elseif (!empty($email) && !isValidEmail($email)) {
            $_SESSION['error_message'] = __('invalid_email');
        } else {
            try {
                if (!empty($email)) {
                    $existing = fetchRow("SELECT id FROM suppliers WHERE email = ?", [$email]);
                    if ($existing) {
                        $_SESSION['error_message'] = __('email_already_exists');
                    }
                }
                
                if (!isset($_SESSION['error_message'])) {
                    $query = "INSERT INTO suppliers (name, company, email, phone, address, city, country, postal_code, tax_number) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($query, [$name, $company, $email, $phone, $address, $city, $country, $postal_code, $tax_number]);
                    
                    $supplier_id = getLastInsertId();
                    logActivity('Supplier Created', 'suppliers', $supplier_id);
                    
                    $_SESSION['success_message'] = __('supplier_added_successfully');
                    header('Location: suppliers.php');
                    exit;
                }
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['edit_supplier']) && hasPermission('suppliers.edit')) {
        $supplier_id = intval($_POST['supplier_id']);
        $old_supplier = fetchRow("SELECT * FROM suppliers WHERE id = ?", [$supplier_id]);
        
        if ($old_supplier) {
            $name = sanitize($_POST['name']);
            $company = sanitize($_POST['company']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $city = sanitize($_POST['city']);
            $country = sanitize($_POST['country']);
            $postal_code = sanitize($_POST['postal_code']);
            $tax_number = sanitize($_POST['tax_number']);
            
            if (empty($name)) {
                $_SESSION['error_message'] = __('supplier_name_required');
            } elseif (!empty($email) && !isValidEmail($email)) {
                $_SESSION['error_message'] = __('invalid_email');
            } else {
                try {
                    if (!empty($email)) {
                        $existing = fetchRow("SELECT id FROM suppliers WHERE email = ? AND id != ?", [$email, $supplier_id]);
                        if ($existing) {
                            $_SESSION['error_message'] = __('email_already_exists');
                        }
                    }
                    
                    if (!isset($_SESSION['error_message'])) {
                        $query = "UPDATE suppliers SET name = ?, company = ?, email = ?, phone = ?, address = ?, 
                                 city = ?, country = ?, postal_code = ?, tax_number = ?, updated_at = NOW() WHERE id = ?";
                        
                        executeQuery($query, [$name, $company, $email, $phone, $address, $city, $country, $postal_code, $tax_number, $supplier_id]);
                        
                        logActivity('Supplier Updated', 'suppliers', $supplier_id, $old_supplier);
                        
                        $_SESSION['success_message'] = __('supplier_updated_successfully');
                        header('Location: suppliers.php');
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
$count_query = "SELECT COUNT(*) as total FROM suppliers WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get suppliers
$suppliers_query = "SELECT s.*, 
                    (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) as order_count,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.status != 'cancelled') as total_orders_value
                    FROM suppliers s 
                    WHERE {$where_clause} 
                    ORDER BY s.created_at DESC 
                    LIMIT {$per_page} OFFSET {$pagination['offset']}";

$suppliers = fetchAll($suppliers_query, $params);

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
                    <i class="fas fa-industry"></i>
                    <?php _e('suppliers'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_supplier_database'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('suppliers.create')): ?>
                <button class="btn btn-primary" onclick="showAddSupplierModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('add_supplier'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="exportSuppliers()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Supplier Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_records); ?></div>
                    <div class="stat-label"><?php _e('total_suppliers'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-industry"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $active_suppliers = fetchRow("SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'");
                        echo number_format($active_suppliers['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('active_suppliers'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
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
                           placeholder="<?php _e('search_suppliers'); ?>..."
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
                    <a href="suppliers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Suppliers Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('suppliers_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('supplier_info'); ?></th>
                            <th><?php _e('contact_info'); ?></th>
                            <th><?php _e('location'); ?></th>
                            <th><?php _e('orders'); ?></th>
                            <th><?php _e('total_value'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <div class="supplier-info">
                                            <div class="supplier-avatar">
                                                <?php echo strtoupper(substr($supplier['name'], 0, 2)); ?>
                                            </div>
                                            <div class="supplier-details">
                                                <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                                <?php if ($supplier['company']): ?>
                                                    <div class="supplier-company"><?php echo htmlspecialchars($supplier['company']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($supplier['tax_number']): ?>
                                                    <div class="supplier-tax"><?php _e('tax'); ?>: <?php echo htmlspecialchars($supplier['tax_number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <?php if ($supplier['email']): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($supplier['phone']): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <a href="tel:<?php echo htmlspecialchars($supplier['phone']); ?>">
                                                        <?php echo htmlspecialchars($supplier['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <?php if ($supplier['city'] || $supplier['country']): ?>
                                                <div class="location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo trim(htmlspecialchars($supplier['city']) . ', ' . htmlspecialchars($supplier['country']), ', '); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="order-stats">
                                            <span class="order-count"><?php echo $supplier['order_count']; ?></span>
                                            <span class="order-label"><?php _e('orders'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="total-value"><?php echo formatCurrency($supplier['total_orders_value']); ?></span>
                                    </td>
                                    <td>
                                        <button class="status-toggle <?php echo $supplier['status']; ?>" 
                                                onclick="toggleSupplierStatus(<?php echo $supplier['id']; ?>, '<?php echo $supplier['status']; ?>')">
                                            <?php _e($supplier['status']); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewSupplier(<?php echo $supplier['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('suppliers.edit')): ?>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editSupplier(<?php echo $supplier['id']; ?>)"
                                                    title="<?php _e('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('suppliers.delete')): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteSupplier(<?php echo $supplier['id']; ?>)"
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
                                        <i class="fas fa-industry"></i>
                                        <p><?php _e('no_suppliers_found'); ?></p>
                                        <?php if (hasPermission('suppliers.create')): ?>
                                        <button class="btn btn-primary" onclick="showAddSupplierModal()">
                                            <i class="fas fa-plus"></i>
                                            <?php _e('add_first_supplier'); ?>
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
                    <?php echo generatePaginationHTML($pagination, 'suppliers.php?search=' . urlencode($search) . '&status=' . $status_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Supplier Modal -->
<div id="supplierModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php _e('add_supplier'); ?></h3>
            <button class="modal-close" onclick="closeSupplierModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="supplierForm" method="POST">
            <input type="hidden" name="supplier_id" id="supplierId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supplierName" class="form-label"><?php _e('supplier_name'); ?> *</label>
                        <input type="text" id="supplierName" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierCompany" class="form-label"><?php _e('company'); ?></label>
                        <input type="text" id="supplierCompany" name="company" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierEmail" class="form-label"><?php _e('email'); ?></label>
                        <input type="email" id="supplierEmail" name="email" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierPhone" class="form-label"><?php _e('phone'); ?></label>
                        <input type="tel" id="supplierPhone" name="phone" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierCity" class="form-label"><?php _e('city'); ?></label>
                        <input type="text" id="supplierCity" name="city" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierCountry" class="form-label"><?php _e('country'); ?></label>
                        <input type="text" id="supplierCountry" name="country" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierPostalCode" class="form-label"><?php _e('postal_code'); ?></label>
                        <input type="text" id="supplierPostalCode" name="postal_code" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="supplierTaxNumber" class="form-label"><?php _e('tax_number'); ?></label>
                        <input type="text" id="supplierTaxNumber" name="tax_number" class="form-input">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="supplierAddress" class="form-label"><?php _e('address'); ?></label>
                        <textarea id="supplierAddress" name="address" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSupplierModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="add_supplier" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('save_supplier'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .supplier-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .supplier-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--info-color), var(--primary-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
        flex-shrink: 0;
    }

    .supplier-details {
        flex: 1;
        min-width: 0;
    }

    .supplier-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .supplier-company {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 2px;
    }

    .supplier-tax {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
</style>

<script>
    let isEditMode = false;
    
    function showAddSupplierModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = '<?php _e('add_supplier'); ?>';
        document.getElementById('supplierForm').reset();
        document.getElementById('supplierId').value = '';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('save_supplier'); ?>';
        document.getElementById('submitBtn').name = 'add_supplier';
        document.getElementById('supplierModal').classList.add('show');
    }
    
    function editSupplier(supplierId) {
        isEditMode = true;
        document.getElementById('modalTitle').textContent = '<?php _e('edit_supplier'); ?>';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('update_supplier'); ?>';
        document.getElementById('submitBtn').name = 'edit_supplier';
        
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_supplier');
        formData.append('supplier_id', supplierId);
        
        fetch('suppliers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const supplier = data.supplier;
                document.getElementById('supplierId').value = supplier.id;
                document.getElementById('supplierName').value = supplier.name || '';
                document.getElementById('supplierCompany').value = supplier.company || '';
                document.getElementById('supplierEmail').value = supplier.email || '';
                document.getElementById('supplierPhone').value = supplier.phone || '';
                document.getElementById('supplierAddress').value = supplier.address || '';
                document.getElementById('supplierCity').value = supplier.city || '';
                document.getElementById('supplierCountry').value = supplier.country || '';
                document.getElementById('supplierPostalCode').value = supplier.postal_code || '';
                document.getElementById('supplierTaxNumber').value = supplier.tax_number || '';
                
                document.getElementById('supplierModal').classList.add('show');
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function deleteSupplier(supplierId) {
        if (confirm('<?php _e('confirm_delete_supplier'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_supplier');
            formData.append('supplier_id', supplierId);
            
            fetch('suppliers.php', {
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
    
    function toggleSupplierStatus(supplierId, currentStatus) {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_status');
        formData.append('supplier_id', supplierId);
        formData.append('status', currentStatus);
        
        fetch('suppliers.php', {
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
    
    function viewSupplier(supplierId) {
        alert('View supplier functionality - Supplier ID: ' + supplierId);
    }
    
    function closeSupplierModal() {
        document.getElementById('supplierModal').classList.remove('show');
    }
    
    function exportSuppliers() {
        window.open('export_suppliers.php', '_blank');
    }
    
    // Form validation
    document.getElementById('supplierForm').addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
        
        showLoading();
    });
    
    // Close modal when clicking outside
    document.getElementById('supplierModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSupplierModal();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>