<?php
/**
 * STOCK MANAGEMENT
 * File: modules/inventory/stock.php
 * Purpose: Complete stock management system with inventory tracking
 * 
 * Features:
 * - Stock level monitoring
 * - Stock movements tracking
 * - Low stock alerts
 * - Stock adjustments
 * - Bulk stock updates
 * - Export functionality
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('inventory.view');

// Set page title
$page_title = __('stock_management');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'adjust_stock':
            if (!hasPermission('inventory.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $product_id = intval($_POST['product_id']);
            $adjustment_type = sanitize($_POST['adjustment_type']); // 'add' or 'subtract'
            $quantity = intval($_POST['quantity']);
            $reason = sanitize($_POST['reason']);
            
            if ($quantity <= 0) {
                echo json_encode(['success' => false, 'message' => __('invalid_quantity')]);
                exit;
            }
            
            try {
                beginTransaction();
                
                // Get current stock
                $product = fetchRow("SELECT * FROM products WHERE id = ?", [$product_id]);
                if (!$product) {
                    echo json_encode(['success' => false, 'message' => __('product_not_found')]);
                    exit;
                }
                
                $old_stock = $product['stock_quantity'];
                
                // Calculate new stock
                if ($adjustment_type === 'add') {
                    $new_stock = $old_stock + $quantity;
                } else {
                    $new_stock = max(0, $old_stock - $quantity);
                }
                
                // Update product stock
                executeQuery("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?", 
                           [$new_stock, $product_id]);
                
                // Record stock movement
                recordStockMovement($product_id, $adjustment_type === 'add' ? 'in' : 'out', 
                                  $quantity, 'adjustment', null, $reason);
                
                commitTransaction();
                
                logActivity('Stock Adjusted', 'products', $product_id, 
                          ['old_stock' => $old_stock, 'new_stock' => $new_stock, 'reason' => $reason]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => __('stock_adjusted_successfully'),
                    'new_stock' => $new_stock
                ]);
                
            } catch (Exception $e) {
                rollbackTransaction();
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_stock_movements':
            $product_id = intval($_POST['product_id']);
            
            $movements = fetchAll("SELECT sm.*, p.name as product_name, u.first_name, u.last_name 
                                  FROM stock_movements sm 
                                  LEFT JOIN products p ON sm.product_id = p.id 
                                  LEFT JOIN users u ON sm.user_id = u.id  
                                  WHERE sm.product_id = ? 
                                  ORDER BY sm.created_at DESC 
                                  LIMIT 50", [$product_id]);
            
            echo json_encode(['success' => true, 'movements' => $movements]);
            exit;
    }
}

// Handle bulk stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    if (!hasPermission('inventory.edit')) {
        $_SESSION['error_message'] = __('access_denied');
    } else {
        $updates = json_decode($_POST['stock_updates'], true);
        
        if (!empty($updates)) {
            try {
                beginTransaction();
                
                foreach ($updates as $update) {
                    $product_id = intval($update['product_id']);
                    $new_stock = intval($update['new_stock']);
                    
                    if ($new_stock >= 0) {
                        // Get old stock for logging
                        $old_product = fetchRow("SELECT stock_quantity FROM products WHERE id = ?", [$product_id]);
                        
                        // Update stock
                        executeQuery("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?", 
                                   [$new_stock, $product_id]);
                        
                        // Record movement
                        $difference = $new_stock - $old_product['stock_quantity'];
                        if ($difference != 0) {
                            recordStockMovement($product_id, $difference > 0 ? 'in' : 'out', 
                                              abs($difference), 'adjustment', null, 'Bulk stock update');
                        }
                    }
                }
                
                commitTransaction();
                logActivity('Bulk Stock Update', 'products', null);
                
                $_SESSION['success_message'] = __('bulk_stock_updated_successfully');
                
            } catch (Exception $e) {
                rollbackTransaction();
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
        
        header('Location: stock.php');
        exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? ''; // 'low', 'out', 'normal'
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['p.status = "active"'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

// Stock level filters
if ($stock_filter === 'low') {
    $where_conditions[] = "p.stock_quantity <= p.min_stock_level AND p.stock_quantity > 0";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "p.stock_quantity = 0";
} elseif ($stock_filter === 'normal') {
    $where_conditions[] = "p.stock_quantity > p.min_stock_level";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get products with stock information
$products_query = "SELECT p.*, c.name as category_name,
                   (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id = p.id) as movement_count,
                   (SELECT sm.created_at FROM stock_movements sm WHERE sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT 1) as last_movement
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE {$where_clause} 
                   ORDER BY 
                   CASE 
                       WHEN p.stock_quantity = 0 THEN 1
                       WHEN p.stock_quantity <= p.min_stock_level THEN 2
                       ELSE 3
                   END,
                   p.name ASC
                   LIMIT {$per_page} OFFSET {$pagination['offset']}";

$products = fetchAll($products_query, $params);

// Get categories for filter
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Get stock statistics
$stock_stats = fetchRow("SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock_quantity <= min_stock_level AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN stock_quantity > min_stock_level THEN 1 ELSE 0 END) as normal_stock,
    SUM(stock_quantity * cost_price) as total_stock_value
    FROM products WHERE status = 'active'");

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
                    <i class="fas fa-warehouse"></i>
                    <?php _e('stock_management'); ?>
                </h1>
                <p class="page-description"><?php _e('monitor_and_manage_inventory_levels'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('inventory.edit')): ?>
                <button class="btn btn-secondary" onclick="showBulkUpdateModal()">
                    <i class="fas fa-edit"></i>
                    <?php _e('bulk_update'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="exportStock()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
            </div>
        </div>

        <!-- Stock Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stock_stats['total_products']); ?></div>
                    <div class="stat-label"><?php _e('total_products'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stock_stats['out_of_stock']); ?></div>
                    <div class="stat-label"><?php _e('out_of_stock'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stock_stats['low_stock']); ?></div>
                    <div class="stat-label"><?php _e('low_stock'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number"><?php echo formatCurrency($stock_stats['total_stock_value']); ?></div>
                    <div class="stat-label"><?php _e('total_stock_value'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
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
                           placeholder="<?php _e('search_products'); ?>..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="category" class="form-select">
                        <option value=""><?php _e('all_categories'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="stock_filter" class="form-select">
                        <option value=""><?php _e('all_stock_levels'); ?></option>
                        <option value="out" <?php echo ($stock_filter === 'out') ? 'selected' : ''; ?>><?php _e('out_of_stock'); ?></option>
                        <option value="low" <?php echo ($stock_filter === 'low') ? 'selected' : ''; ?>><?php _e('low_stock'); ?></option>
                        <option value="normal" <?php echo ($stock_filter === 'normal') ? 'selected' : ''; ?>><?php _e('normal_stock'); ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        <?php _e('search'); ?>
                    </button>
                    <a href="stock.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Stock Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('inventory_levels'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('product'); ?></th>
                            <th><?php _e('category'); ?></th>
                            <th><?php _e('current_stock'); ?></th>
                            <th><?php _e('min_stock'); ?></th>
                            <th><?php _e('max_stock'); ?></th>
                            <th><?php _e('stock_value'); ?></th>
                            <th><?php _e('last_movement'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $stock_status = 'normal';
                                if ($product['stock_quantity'] == 0) {
                                    $stock_status = 'out';
                                } elseif ($product['stock_quantity'] <= $product['min_stock_level']) {
                                    $stock_status = 'low';
                                }
                                ?>
                                <tr class="stock-row stock-<?php echo $stock_status; ?>">
                                    <td>
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                    <td>
                                        <div class="stock-level">
                                            <span class="stock-quantity stock-<?php echo $stock_status; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                            <span class="stock-unit"><?php echo htmlspecialchars($product['unit_of_measure']); ?></span>
                                            <?php if ($stock_status !== 'normal'): ?>
                                                <i class="fas fa-exclamation-triangle stock-warning" 
                                                   title="<?php echo $stock_status === 'out' ? __('out_of_stock') : __('low_stock'); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="min-stock"><?php echo number_format($product['min_stock_level']); ?></span>
                                    </td>
                                    <td>
                                        <span class="max-stock"><?php echo number_format($product['max_stock_level']); ?></span>
                                    </td>
                                    <td>
                                        <span class="stock-value">
                                            <?php echo formatCurrency($product['stock_quantity'] * $product['cost_price']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['last_movement']): ?>
                                            <span class="last-movement"><?php echo timeAgo($product['last_movement']); ?></span>
                                        <?php else: ?>
                                            <span class="no-movement"><?php _e('no_movements'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-history" 
                                                    onclick="viewStockHistory(<?php echo $product['id']; ?>)"
                                                    title="<?php _e('stock_history'); ?>">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <?php if (hasPermission('inventory.edit')): ?>
                                            <button class="btn-action btn-adjust" 
                                                    onclick="showAdjustModal(<?php echo $product['id']; ?>)"
                                                    title="<?php _e('adjust_stock'); ?>">
                                                <i class="fas fa-edit"></i>
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
                                        <i class="fas fa-warehouse"></i>
                                        <p><?php _e('no_products_found'); ?></p>
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
                    <?php echo generatePaginationHTML($pagination, 'stock.php?search=' . urlencode($search) . '&category=' . $category_filter . '&stock_filter=' . $stock_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="adjustModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('adjust_stock'); ?></h3>
            <button class="modal-close" onclick="closeAdjustModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="adjustForm">
                <input type="hidden" id="adjustProductId">
                <div id="productInfo" class="product-info-box"></div>
                
                <div class="form-group">
                    <label class="form-label"><?php _e('adjustment_type'); ?> *</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="adjustment_type" value="add" checked>
                            <span><?php _e('add_stock'); ?></span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="adjustment_type" value="subtract">
                            <span><?php _e('reduce_stock'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="adjustQuantity" class="form-label"><?php _e('quantity'); ?> *</label>
                    <input type="number" id="adjustQuantity" class="form-input" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="adjustReason" class="form-label"><?php _e('reason'); ?> *</label>
                    <select id="adjustReason" class="form-select" required>
                        <option value=""><?php _e('select_reason'); ?></option>
                        <option value="stock_count"><?php _e('stock_count'); ?></option>
                        <option value="damaged_goods"><?php _e('damaged_goods'); ?></option>
                        <option value="expired_items"><?php _e('expired_items'); ?></option>
                        <option value="theft_loss"><?php _e('theft_loss'); ?></option>
                        <option value="supplier_return"><?php _e('supplier_return'); ?></option>
                        <option value="customer_return"><?php _e('customer_return'); ?></option>
                        <option value="other"><?php _e('other'); ?></option>
                    </select>
                </div>
                
                <div class="form-group" id="customReasonGroup" style="display: none;">
                    <label for="customReason" class="form-label"><?php _e('custom_reason'); ?></label>
                    <input type="text" id="customReason" class="form-input">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAdjustModal()">
                <?php _e('cancel'); ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="submitAdjustment()">
                <i class="fas fa-save"></i>
                <?php _e('adjust_stock'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3><?php _e('stock_movement_history'); ?></h3>
            <button class="modal-close" onclick="closeHistoryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="historyContent">
                <!-- Stock movements will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeHistoryModal()">
                <?php _e('close'); ?>
            </button>
        </div>
    </div>
</div>

<style>
    /* Stock Management Styles */
    .stock-row.stock-out {
        background: #fef2f2 !important;
    }
    
    .stock-row.stock-low {
        background: #fffbeb !important;
    }
    
    .stock-quantity.stock-out {
        color: var(--danger-color);
        font-weight: bold;
    }
    
    .stock-quantity.stock-low {
        color: var(--warning-color);
        font-weight: bold;
    }
    
    .stock-level {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .stock-warning {
        animation: pulse 2s infinite;
    }
    
    .product-info-box {
        background: var(--light-color);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .radio-group {
        display: flex;
        gap: 20px;
    }
    
    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .btn-history {
        background: var(--info-color);
        color: white;
    }
    
    .btn-adjust {
        background: var(--warning-color);
        color: white;
    }
    
    .large-modal .modal-content {
        max-width: 800px;
    }
</style>

<script>
    let currentProductId = null;
    
    function showAdjustModal(productId) {
        currentProductId = productId;
        
        // Get product info
        const row = event.target.closest('tr');
        const productName = row.querySelector('.product-name').textContent;
        const productSku = row.querySelector('.product-sku').textContent;
        const currentStock = row.querySelector('.stock-quantity').textContent;
        
        document.getElementById('adjustProductId').value = productId;
        document.getElementById('productInfo').innerHTML = `
            <h4>${productName}</h4>
            <p><strong><?php _e('sku'); ?>:</strong> ${productSku}</p>
            <p><strong><?php _e('current_stock'); ?>:</strong> ${currentStock}</p>
        `;
        
        document.getElementById('adjustModal').classList.add('show');
    }
    
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.remove('show');
        document.getElementById('adjustForm').reset();
    }
    
    function submitAdjustment() {
        const form = document.getElementById('adjustForm');
        const productId = document.getElementById('adjustProductId').value;
        const adjustmentType = form.querySelector('input[name="adjustment_type"]:checked').value;
        const quantity = document.getElementById('adjustQuantity').value;
        const reason = document.getElementById('adjustReason').value;
        const customReason = document.getElementById('customReason').value;
        
        if (!quantity || !reason) {
            showError('<?php _e('required_fields_missing'); ?>');
            return;
        }
        
        const finalReason = reason === 'other' ? customReason : reason;
        
        if (reason === 'other' && !customReason) {
            showError('<?php _e('custom_reason_required'); ?>');
            return;
        }
        
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'adjust_stock');
        formData.append('product_id', productId);
        formData.append('adjustment_type', adjustmentType);
        formData.append('quantity', quantity);
        formData.append('reason', finalReason);
        
        fetch('stock.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showSuccess(data.message);
                closeAdjustModal();
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
    
    function viewStockHistory(productId) {
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_stock_movements');
        formData.append('product_id', productId);
        
        fetch('stock.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                displayStockHistory(data.movements);
            } else {
                showError('<?php _e('operation_failed'); ?>');
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function displayStockHistory(movements) {
        let html = '<div class="movements-table">';
        
        if (movements.length > 0) {
            html += `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('date'); ?></th>
                            <th><?php _e('type'); ?></th>
                            <th><?php _e('quantity'); ?></th>
                            <th><?php _e('reference'); ?></th>
                            <th><?php _e('notes'); ?></th>
                            <th><?php _e('user'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            movements.forEach(movement => {
                const typeClass = movement.movement_type === 'in' ? 'success' : 'danger';
                const typeSymbol = movement.movement_type === 'in' ? '+' : '-';
                
                html += `
                    <tr>
                        <td>${formatDateTime(movement.created_at)}</td>
                        <td><span class="badge badge-${typeClass}">${movement.movement_type.toUpperCase()}</span></td>
                        <td class="text-${typeClass}">${typeSymbol}${movement.quantity}</td>
                        <td>${movement.reference_type || '-'}</td>
                        <td>${movement.notes || '-'}</td>
                        <td>${movement.first_name} ${movement.last_name}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
        } else {
            html += '<div class="empty-state"><p><?php _e('no_stock_movements'); ?></p></div>';
        }
        
        html += '</div>';
        
        document.getElementById('historyContent').innerHTML = html;
        document.getElementById('historyModal').classList.add('show');
    }
    
    function closeHistoryModal() {
        document.getElementById('historyModal').classList.remove('show');
    }
    
    function showBulkUpdateModal() {
        alert('<?php _e('bulk_update_feature_coming_soon'); ?>');
    }
    
    function exportStock() {
        window.open('export_stock.php', '_blank');
    }
    
    // Show/hide custom reason field
    document.getElementById('adjustReason').addEventListener('change', function() {
        const customGroup = document.getElementById('customReasonGroup');
        if (this.value === 'other') {
            customGroup.style.display = 'block';
        } else {
            customGroup.style.display = 'none';
        }
    });
    
    // Close modals when clicking outside
    document.getElementById('adjustModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAdjustModal();
        }
    });
    
    document.getElementById('historyModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeHistoryModal();
        }
    });
    
    // Utility functions
    function formatDateTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString('<?php echo $current_lang; ?>');
    }
</script>

<?php include '../../includes/footer.php'; ?>