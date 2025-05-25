<?php
/**
 * CATEGORY MANAGEMENT
 * File: modules/inventory/categories.php
 * Purpose: Product category management with CRUD operations
 */

session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('products.view');

$page_title = __('categories');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_category':
            if (!hasPermission('products.delete')) {
                echo json_encode(['success' => false, 'message' => __('access_denied')]);
                exit;
            }
            
            $category_id = intval($_POST['category_id']);
            
            try {
                // Check if category has products
                $product_check = fetchRow("SELECT COUNT(*) as count FROM products WHERE category_id = ?", [$category_id]);
                if ($product_check['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => __('cannot_delete_category_with_products')]);
                    exit;
                }
                
                // Get category info for logging
                $category = fetchRow("SELECT * FROM categories WHERE id = ?", [$category_id]);
                
                // Delete category
                executeQuery("DELETE FROM categories WHERE id = ?", [$category_id]);
                
                logActivity('Category Deleted', 'categories', $category_id, $category);
                
                echo json_encode(['success' => true, 'message' => __('category_deleted_successfully')]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_category':
            $category_id = intval($_POST['category_id']);
            $category = fetchRow("SELECT * FROM categories WHERE id = ?", [$category_id]);
            
            if ($category) {
                echo json_encode(['success' => true, 'category' => $category]);
            } else {
                echo json_encode(['success' => false, 'message' => __('category_not_found')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['add_category']) && hasPermission('products.create')) {
        $name = sanitize($_POST['name']);
        $name_ku = sanitize($_POST['name_ku']);
        $name_ar = sanitize($_POST['name_ar']);
        $description = sanitize($_POST['description']);
        
        if (empty($name)) {
            $_SESSION['error_message'] = __('category_name_required');
        } else {
            try {
                $query = "INSERT INTO categories (name, name_ku, name_ar, description) VALUES (?, ?, ?, ?)";
                executeQuery($query, [$name, $name_ku, $name_ar, $description]);
                
                $category_id = getLastInsertId();
                logActivity('Category Created', 'categories', $category_id);
                
                $_SESSION['success_message'] = __('category_added_successfully');
                header('Location: categories.php');
                exit;
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['edit_category']) && hasPermission('products.edit')) {
        $category_id = intval($_POST['category_id']);
        $old_category = fetchRow("SELECT * FROM categories WHERE id = ?", [$category_id]);
        
        if ($old_category) {
            $name = sanitize($_POST['name']);
            $name_ku = sanitize($_POST['name_ku']);
            $name_ar = sanitize($_POST['name_ar']);
            $description = sanitize($_POST['description']);
            
            if (empty($name)) {
                $_SESSION['error_message'] = __('category_name_required');
            } else {
                try {
                    $query = "UPDATE categories SET name = ?, name_ku = ?, name_ar = ?, description = ? WHERE id = ?";
                    executeQuery($query, [$name, $name_ku, $name_ar, $description, $category_id]);
                    
                    logActivity('Category Updated', 'categories', $category_id, $old_category);
                    
                    $_SESSION['success_message'] = __('category_updated_successfully');
                    header('Location: categories.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error_message'] = __('operation_failed');
                }
            }
        }
    }
}

// Get categories with product counts
$categories_query = "SELECT c.*, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                     GROUP BY c.id 
                     ORDER BY c.created_at DESC";

$categories = fetchAll($categories_query);

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
                    <i class="fas fa-tags"></i>
                    <?php _e('categories'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_product_categories'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('products.create')): ?>
                <button class="btn btn-primary" onclick="showAddCategoryModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('add_category'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="categories-grid">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="category-actions">
                                <?php if (hasPermission('products.edit')): ?>
                                <button class="btn-action btn-edit" onclick="editCategory(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (hasPermission('products.delete') && $category['product_count'] == 0): ?>
                                <button class="btn-action btn-delete" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="category-body">
                            <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                            
                            <?php if ($current_lang === 'ku' && $category['name_ku']): ?>
                                <div class="category-name-alt"><?php echo htmlspecialchars($category['name_ku']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($current_lang === 'ar' && $category['name_ar']): ?>
                                <div class="category-name-alt"><?php echo htmlspecialchars($category['name_ar']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($category['description']): ?>
                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="category-stats">
                                <div class="stat-item">
                                    <i class="fas fa-box"></i>
                                    <span><?php echo $category['product_count']; ?> <?php _e('products'); ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo formatDate($category['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3><?php _e('no_categories_found'); ?></h3>
                    <p><?php _e('create_your_first_category'); ?></p>
                    <?php if (hasPermission('products.create')): ?>
                    <button class="btn btn-primary" onclick="showAddCategoryModal()">
                        <i class="fas fa-plus"></i>
                        <?php _e('add_category'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php _e('add_category'); ?></h3>
            <button class="modal-close" onclick="closeCategoryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="categoryForm" method="POST">
            <input type="hidden" name="category_id" id="categoryId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="categoryName" class="form-label"><?php _e('category_name'); ?> (English) *</label>
                    <input type="text" id="categoryName" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="categoryNameKu" class="form-label"><?php _e('category_name'); ?> (Kurdish)</label>
                    <input type="text" id="categoryNameKu" name="name_ku" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="categoryNameAr" class="form-label"><?php _e('category_name'); ?> (Arabic)</label>
                    <input type="text" id="categoryNameAr" name="name_ar" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="categoryDescription" class="form-label"><?php _e('description'); ?></label>
                    <textarea id="categoryDescription" name="description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="add_category" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('save_category'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        padding: 20px 0;
    }

    .category-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .category-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
    }

    .category-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .category-actions {
        display: flex;
        gap: 8px;
    }

    .category-body {
        padding: 20px;
    }

    .category-name {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .category-name-alt {
        font-size: 1.1rem;
        color: var(--text-secondary);
        margin-bottom: 8px;
        font-style: italic;
    }

    .category-description {
        color: var(--text-secondary);
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .category-stats {
        display: flex;
        gap: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        margin-bottom: 10px;
        color: var(--text-primary);
    }

    @media (max-width: 768px) {
        .categories-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    let isEditMode = false;
    
    function showAddCategoryModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = '<?php _e('add_category'); ?>';
        document.getElementById('categoryForm').reset();
        document.getElementById('categoryId').value = '';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('save_category'); ?>';
        document.getElementById('submitBtn').name = 'add_category';
        document.getElementById('categoryModal').classList.add('show');
    }
    
    function editCategory(categoryId) {
        isEditMode = true;
        document.getElementById('modalTitle').textContent = '<?php _e('edit_category'); ?>';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('update_category'); ?>';
        document.getElementById('submitBtn').name = 'edit_category';
        
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_category');
        formData.append('category_id', categoryId);
        
        fetch('categories.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const category = data.category;
                document.getElementById('categoryId').value = category.id;
                document.getElementById('categoryName').value = category.name || '';
                document.getElementById('categoryNameKu').value = category.name_ku || '';
                document.getElementById('categoryNameAr').value = category.name_ar || '';
                document.getElementById('categoryDescription').value = category.description || '';
                
                document.getElementById('categoryModal').classList.add('show');
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function deleteCategory(categoryId) {
        if (confirm('<?php _e('confirm_delete_category'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_category');
            formData.append('category_id', categoryId);
            
            fetch('categories.php', {
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
    
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.remove('show');
    }
    
    // Form validation
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
        
        showLoading();
    });
    
    // Close modal when clicking outside
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCategoryModal();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>