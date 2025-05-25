<?php
/**
 * PRODUCT MANAGEMENT
 * File: modules/inventory/products.php
 * Purpose: Complete product management system with CRUD operations
 * 
 * Features:
 * - Add, edit, delete products
 * - Product listing with search and pagination
 * - Stock management
 * - Image upload
 * - Category assignment
 * - Multi-language support
 * - Export functionality
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('products.view');

// Set page title
$page_title = __('products');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_product':
            if (!hasPermission('products.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $product_id = intval($_POST['product_id']);
            
            try {
                // Check if product is used in orders
                $order_check = fetchRow("SELECT COUNT(*) as count FROM sales_order_items WHERE product_id = ?", [$product_id]);
                if ($order_check['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => __('cannot_delete_product_with_orders')]);
                    exit;
                }
                
                // Get product info for logging
                $product = fetchRow("SELECT * FROM products WHERE id = ?", [$product_id]);
                
                // Delete product image if exists
                if ($product && $product['image']) {
                    deleteFile(UPLOAD_PATH . 'products/' . $product['image']);
                }
                
                // Delete product
                executeQuery("DELETE FROM products WHERE id = ?", [$product_id]);
                
                logActivity('Product Deleted', 'products', $product_id, $product);
                
                echo json_encode(['success' => true, 'message' => __('product_deleted_successfully')]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'toggle_status':
            if (!hasPermission('products.edit')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $product_id = intval($_POST['product_id']);
            $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            try {
                executeQuery("UPDATE products SET status = ? WHERE id = ?", [$new_status, $product_id]);
                logActivity('Product Status Changed', 'products', $product_id);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['add_product']) && hasPermission('products.create')) {
        // Add new product
        $name = sanitize($_POST['name']);
        $sku = sanitize($_POST['sku']);
        $category_id = intval($_POST['category_id']) ?: null;
        $description = sanitize($_POST['description']);
        $unit_price = floatval($_POST['unit_price']);
        $cost_price = floatval($_POST['cost_price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $min_stock_level = intval($_POST['min_stock_level']);
        $max_stock_level = intval($_POST['max_stock_level']);
        $unit_of_measure = sanitize($_POST['unit_of_measure']);
        $barcode = sanitize($_POST['barcode']);
        
        // Validate required fields
        if (empty($name) || empty($sku) || $unit_price <= 0) {
            $_SESSION['error_message'] = __('required_fields_missing');
        } else {
            try {
                // Check if SKU already exists
                $existing = fetchRow("SELECT id FROM products WHERE sku = ?", [$sku]);
                if ($existing) {
                    $_SESSION['error_message'] = __('sku_already_exists');
                } else {
                    // Handle image upload
                    $image_filename = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadFile($_FILES['image'], UPLOAD_PATH . 'products/');
                        if ($upload_result['success']) {
                            $image_filename = $upload_result['filename'];
                        }
                    }
                    
                    // Insert product
                    $query = "INSERT INTO products (name, sku, category_id, description, unit_price, cost_price, 
                             stock_quantity, min_stock_level, max_stock_level, unit_of_measure, barcode, image) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($query, [
                        $name, $sku, $category_id, $description, $unit_price, $cost_price,
                        $stock_quantity, $min_stock_level, $max_stock_level, $unit_of_measure, $barcode, $image_filename
                    ]);
                    
                    $product_id = getLastInsertId();
                    
                    // Record initial stock if any
                    if ($stock_quantity > 0) {
                        recordStockMovement($product_id, 'in', $stock_quantity, 'adjustment', null, 'Initial stock');
                    }
                    
                    logActivity('Product Created', 'products', $product_id);
                    
                    $_SESSION['success_message'] = __('product_added_successfully');
                    header('Location: products.php');
                    exit;
                }
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['edit_product']) && hasPermission('products.edit')) {
        // Edit existing product
        $product_id = intval($_POST['product_id']);
        $old_product = fetchRow("SELECT * FROM products WHERE id = ?", [$product_id]);
        
        if ($old_product) {
            $name = sanitize($_POST['name']);
            $sku = sanitize($_POST['sku']);
            $category_id = intval($_POST['category_id']) ?: null;
            $description = sanitize($_POST['description']);
            $unit_price = floatval($_POST['unit_price']);
            $cost_price = floatval($_POST['cost_price']);
            $min_stock_level = intval($_POST['min_stock_level']);
            $max_stock_level = intval($_POST['max_stock_level']);
            $unit_of_measure = sanitize($_POST['unit_of_measure']);
            $barcode = sanitize($_POST['barcode']);
            
            // Validate required fields
            if (empty($name) || empty($sku) || $unit_price <= 0) {
                $_SESSION['error_message'] = __('required_fields_missing');
            } else {
                try {
                    // Check if SKU already exists (excluding current product)
                    $existing = fetchRow("SELECT id FROM products WHERE sku = ? AND id != ?", [$sku, $product_id]);
                    if ($existing) {
                        $_SESSION['error_message'] = __('sku_already_exists');
                    } else {
                        // Handle image upload
                        $image_filename = $old_product['image'];
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            $upload_result = uploadFile($_FILES['image'], UPLOAD_PATH . 'products/');
                            if ($upload_result['success']) {
                                // Delete old image
                                if ($old_product['image']) {
                                    deleteFile(UPLOAD_PATH . 'products/' . $old_product['image']);
                                }
                                $image_filename = $upload_result['filename'];
                            }
                        }
                        
                        // Update product
                        $query = "UPDATE products SET name = ?, sku = ?, category_id = ?, description = ?, 
                                 unit_price = ?, cost_price = ?, min_stock_level = ?, max_stock_level = ?, 
                                 unit_of_measure = ?, barcode = ?, image = ?, updated_at = NOW() WHERE id = ?";
                        
                        executeQuery($query, [
                            $name, $sku, $category_id, $description, $unit_price, $cost_price,
                            $min_stock_level, $max_stock_level, $unit_of_measure, $barcode, $image_filename, $product_id
                        ]);
                        
                        logActivity('Product Updated', 'products', $product_id, $old_product);
                        
                        $_SESSION['success_message'] = __('product_updated_successfully');
                        header('Location: products.php');
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
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM products p WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get products
$products_query = "SELECT p.*, c.name as category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE {$where_clause} 
                   ORDER BY p.created_at DESC 
                   LIMIT {$per_page} OFFSET {$pagination['offset']}";

$products = fetchAll($products_query, $params);

// Get categories for filter dropdown
$categories = fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get current editing product if edit mode
$editing_product = null;
if (isset($_GET['edit']) && hasPermission('products.edit')) {
    $edit_id = intval($_GET['edit']);
    $editing_product = fetchRow("SELECT * FROM products WHERE id = ?", [$edit_id]);
}

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
                    <i class="fas fa-box"></i>
                    <?php _e('products'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_products_inventory'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('products.create')): ?>
                <button class="btn btn-primary" onclick="showAddProductModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('add_product'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="exportProducts()">
                    <i class="fas fa-download"></i>
                    <?php _e('export'); ?>
                </button>
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
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('products_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('image'); ?></th>
                            <th><?php _e('product_name'); ?></th>
                            <th><?php _e('sku'); ?></th>
                            <th><?php _e('category'); ?></th>
                            <th><?php _e('unit_price'); ?></th>
                            <th><?php _e('stock_quantity'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-image">
                                            <?php if ($product['image']): ?>
                                                <img src="<?php echo APP_URL . '/' . UPLOAD_PATH . 'products/' . $product['image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <?php if ($product['description']): ?>
                                                <div class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="sku-badge"><?php echo htmlspecialchars($product['sku']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="price"><?php echo formatCurrency($product['unit_price']); ?></span>
                                    </td>
                                    <td>
                                        <div class="stock-info">
                                            <span class="stock-quantity 
                                                <?php echo ($product['stock_quantity'] <= $product['min_stock_level']) ? 'low-stock' : ''; ?>">
                                                <?php echo $product['stock_quantity']; ?>
                                            </span>
                                            <span class="stock-unit"><?php echo htmlspecialchars($product['unit_of_measure']); ?></span>
                                            <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                                <i class="fas fa-exclamation-triangle stock-warning" title="<?php _e('low_stock'); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="status-toggle <?php echo $product['status']; ?>" 
                                                onclick="toggleProductStatus(<?php echo $product['id']; ?>, '<?php echo $product['status']; ?>')">
                                            <?php _e($product['status']); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewProduct(<?php echo $product['id']; ?>)"
                                                    title="<?php _e('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('products.edit')): ?>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editProduct(<?php echo $product['id']; ?>)"
                                                    title="<?php _e('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('products.delete')): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteProduct(<?php echo $product['id']; ?>)"
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
                                        <i class="fas fa-box"></i>
                                        <p><?php _e('no_products_found'); ?></p>
                                        <?php if (hasPermission('products.create')): ?>
                                        <button class="btn btn-primary" onclick="showAddProductModal()">
                                            <i class="fas fa-plus"></i>
                                            <?php _e('add_first_product'); ?>
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
                    <?php echo generatePaginationHTML($pagination, 'products.php?search=' . urlencode($search) . '&category=' . $category_filter . '&status=' . $status_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php _e('add_product'); ?></h3>
            <button class="modal-close" onclick="closeProductModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="productForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="productId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="productName" class="form-label"><?php _e('product_name'); ?> *</label>
                        <input type="text" id="productName" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productSku" class="form-label"><?php _e('sku'); ?> *</label>
                        <input type="text" id="productSku" name="sku" class="form-input" required>
                        <small class="form-help"><?php _e('unique_product_identifier'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="productCategory" class="form-label"><?php _e('category'); ?></label>
                        <select id="productCategory" name="category_id" class="form-select">
                            <option value=""><?php _e('select_category'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="productUnitPrice" class="form-label"><?php _e('unit_price'); ?> *</label>
                        <input type="number" id="productUnitPrice" name="unit_price" class="form-input" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productCostPrice" class="form-label"><?php _e('cost_price'); ?></label>
                        <input type="number" id="productCostPrice" name="cost_price" class="form-input" 
                               step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="productStock" class="form-label"><?php _e('initial_stock'); ?></label>
                        <input type="number" id="productStock" name="stock_quantity" class="form-input" 
                               min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="productMinStock" class="form-label"><?php _e('min_stock'); ?></label>
                        <input type="number" id="productMinStock" name="min_stock_level" class="form-input" 
                               min="0" value="5">
                    </div>
                    
                    <div class="form-group">
                        <label for="productMaxStock" class="form-label"><?php _e('max_stock'); ?></label>
                        <input type="number" id="productMaxStock" name="max_stock_level" class="form-input" 
                               min="0" value="1000">
                    </div>
                    
                    <div class="form-group">
                        <label for="productUnit" class="form-label"><?php _e('unit_measure'); ?></label>
                        <input type="text" id="productUnit" name="unit_of_measure" class="form-input" 
                               value="pcs" placeholder="pcs, kg, m, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="productBarcode" class="form-label"><?php _e('barcode'); ?></label>
                        <input type="text" id="productBarcode" name="barcode" class="form-input">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="productDescription" class="form-label"><?php _e('description'); ?></label>
                        <textarea id="productDescription" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="productImage" class="form-label"><?php _e('product_image'); ?></label>
                        <input type="file" id="productImage" name="image" class="form-input" accept="image/*">
                        <small class="form-help"><?php _e('max_file_size_5mb'); ?></small>
                        <div id="imagePreview" class="image-preview"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProductModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="add_product" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('save_product'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Product Management Styles */
    .filters-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 20px;
        margin-bottom: 20px;
    }

    .filters-form {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .table-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .table-info {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: var(--light-color);
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
        white-space: nowrap;
    }

    .data-table td {
        padding: 15px 12px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: var(--light-color);
    }

    .product-image {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--light-color);
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .no-image {
        color: var(--text-secondary);
        font-size: 1.5rem;
    }

    .product-info {
        max-width: 200px;
    }

    .product-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .product-description {
        font-size: 0.8rem;
        color: var(--text-secondary);
        line-height: 1.3;
    }

    .sku-badge {
        background: var(--light-color);
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .price {
        font-weight: 600;
        color: var(--success-color);
        font-size: 1.1rem;
    }

    .stock-info {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stock-quantity {
        font-weight: 600;
        font-size: 1.1rem;
    }

    .stock-quantity.low-stock {
        color: var(--danger-color);
    }

    .stock-unit {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .stock-warning {
        color: var(--warning-color);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .status-toggle {
        padding: 4px 12px;
        border: none;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: capitalize;
    }

    .status-toggle.active {
        background: var(--success-color);
        color: white;
    }

    .status-toggle.inactive {
        background: var(--text-secondary);
        color: white;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-view {
        background: var(--info-color);
        color: white;
    }

    .btn-edit {
        background: var(--warning-color);
        color: white;
    }

    .btn-delete {
        background: var(--danger-color);
        color: white;
    }

    .btn-action:hover {
        transform: scale(1.1);
        box-shadow: var(--shadow);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: var(--text-primary);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: var(--light-color);
        color: var(--text-primary);
    }

    .modal-body {
        padding: 20px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-help {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: block;
    }

    .image-preview {
        margin-top: 10px;
        max-width: 200px;
    }

    .image-preview img {
        width: 100%;
        border-radius: 8px;
        box-shadow: var(--shadow);
    }

    .modal-footer {
        padding: 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .table-footer {
        padding: 20px;
        border-top: 1px solid var(--border-color);
    }

    .no-data {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        opacity: 0.5;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 15px;
        }
        
        .filters-form {
            flex-direction: column;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            margin: 10px;
        }
    }
</style>

<script>
    // Product management JavaScript
    let isEditMode = false;
    
    function showAddProductModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = '<?php _e('add_product'); ?>';
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('save_product'); ?>';
        document.getElementById('submitBtn').name = 'add_product';
        document.getElementById('imagePreview').innerHTML = '';
        document.getElementById('productModal').classList.add('show');
    }
    
    function editProduct(productId) {
        // In a real application, you would fetch product data via AJAX
        // For now, we'll redirect to edit mode
        window.location.href = `products.php?edit=${productId}`;
    }
    
    function viewProduct(productId) {
        // Implement product view modal
        alert('View product functionality - Product ID: ' + productId);
    }
    
    function deleteProduct(productId) {
        if (confirm('<?php _e('confirm_delete_product'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_product');
            formData.append('product_id', productId);
            
            fetch('products.php', {
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
    
    function toggleProductStatus(productId, currentStatus) {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_status');
        formData.append('product_id', productId);
        formData.append('status', currentStatus);
        
        fetch('products.php', {
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
    
    function closeProductModal() {
        document.getElementById('productModal').classList.remove('show');
    }
    
    function exportProducts() {
        window.open('export_products.php', '_blank');
    }
    
    // Handle image preview
    document.getElementById('productImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
    
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
        
        showLoading();
    });
    
    // Close modal when clicking outside
    document.getElementById('productModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProductModal();
        }
    });
    
    // Auto-generate SKU
    document.getElementById('productName').addEventListener('blur', function() {
        const skuField = document.getElementById('productSku');
        if (!skuField.value && this.value) {
            const name = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 6);
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            skuField.value = `${name}${random}`;
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>