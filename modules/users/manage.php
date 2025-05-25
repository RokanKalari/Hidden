<?php
/**
 * USER MANAGEMENT
 * File: modules/users/manage.php
 * Purpose: Complete user management system for administrators
 * 
 * Features:
 * - Add, edit, delete users
 * - Role-based access control
 * - User activity tracking
 * - Password management
 * - Multi-language support
 * - User statistics
 */

// Start session and include required files
session_start();
require_once '../../config/config.php';

// Check authentication and permissions
requireLogin();
requirePermission('users.manage');

// Set page title
$page_title = __('user_management');

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            
            // Prevent self-deletion
            if ($user_id === $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => __('cannot_delete_yourself')]);
                exit;
            }
            
            try {
                // Check if user has created orders or other records
                $order_check = fetchRow("SELECT COUNT(*) as count FROM sales_orders WHERE user_id = ?", [$user_id]);
                
                // Get user info for logging
                $user = fetchRow("SELECT * FROM users WHERE id = ?", [$user_id]);
                
                if ($order_check['count'] > 0) {
                    // Deactivate instead of delete if user has records
                    executeQuery("UPDATE users SET status = 'inactive' WHERE id = ?", [$user_id]);
                    logActivity('User Deactivated', 'users', $user_id, $user);
                    echo json_encode(['success' => true, 'message' => __('user_deactivated_successfully')]);
                } else {
                    // Delete user if no records
                    executeQuery("DELETE FROM users WHERE id = ?", [$user_id]);
                    logActivity('User Deleted', 'users', $user_id, $user);
                    echo json_encode(['success' => true, 'message' => __('user_deleted_successfully')]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'toggle_status':
            $user_id = intval($_POST['user_id']);
            
            // Prevent self-deactivation
            if ($user_id === $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => __('cannot_deactivate_yourself')]);
                exit;
            }
            
            $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            try {
                executeQuery("UPDATE users SET status = ? WHERE id = ?", [$new_status, $user_id]);
                logActivity('User Status Changed', 'users', $user_id);
                
                echo json_encode(['success' => true, 'status' => $new_status]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
            
        case 'get_user':
            $user_id = intval($_POST['user_id']);
            $user = fetchRow("SELECT * FROM users WHERE id = ?", [$user_id]);
            
            if ($user) {
                // Remove password from response
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => __('user_not_found')]);
            }
            exit;
            
        case 'reset_password':
            $user_id = intval($_POST['user_id']);
            $new_password = generateRandomString(12); // Generate random password
            
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id]);
                
                logActivity('User Password Reset', 'users', $user_id);
                
                echo json_encode(['success' => true, 'password' => $new_password, 'message' => __('password_reset_successfully')]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => __('operation_failed')]);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    if (isset($_POST['add_user']) && hasPermission('users.create')) {
        // Add new user
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $role = sanitize($_POST['role']);
        $language = sanitize($_POST['language']);
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            $_SESSION['error_message'] = __('required_fields_missing');
        } elseif (!isValidEmail($email)) {
            $_SESSION['error_message'] = __('invalid_email');
        } elseif (strlen($password) < 6) {
            $_SESSION['error_message'] = __('password_too_short');
        } else {
            try {
                // Check if username or email already exists
                $existing_user = fetchRow("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                if ($existing_user) {
                    $_SESSION['error_message'] = __('username_or_email_exists');
                } else {
                    // Handle avatar upload
                    $avatar_filename = null;
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadFile($_FILES['avatar'], UPLOAD_PATH . 'avatars/', ['jpg', 'jpeg', 'png']);
                        if ($upload_result['success']) {
                            $avatar_filename = $upload_result['filename'];
                        }
                    }
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $query = "INSERT INTO users (username, email, password, first_name, last_name, role, language, avatar) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($query, [
                        $username, $email, $hashed_password, $first_name, $last_name, $role, $language, $avatar_filename
                    ]);
                    
                    $user_id = getLastInsertId();
                    
                    logActivity('User Created', 'users', $user_id);
                    
                    $_SESSION['success_message'] = __('user_added_successfully');
                    header('Location: manage.php');
                    exit;
                }
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['edit_user']) && hasPermission('users.edit')) {
        // Edit existing user
        $user_id = intval($_POST['user_id']);
        $old_user = fetchRow("SELECT * FROM users WHERE id = ?", [$user_id]);
        
        if ($old_user) {
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            $role = sanitize($_POST['role']);
            $language = sanitize($_POST['language']);
            
            // Validate required fields
            if (empty($username) || empty($email) || empty($first_name) || empty($last_name)) {
                $_SESSION['error_message'] = __('required_fields_missing');
            } elseif (!isValidEmail($email)) {
                $_SESSION['error_message'] = __('invalid_email');
            } else {
                try {
                    // Check if username or email already exists (excluding current user)
                    $existing_user = fetchRow("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?", 
                                            [$username, $email, $user_id]);
                    if ($existing_user) {
                        $_SESSION['error_message'] = __('username_or_email_exists');
                    } else {
                        // Handle avatar upload
                        $avatar_filename = $old_user['avatar'];
                        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                            $upload_result = uploadFile($_FILES['avatar'], UPLOAD_PATH . 'avatars/', ['jpg', 'jpeg', 'png']);
                            if ($upload_result['success']) {
                                // Delete old avatar
                                if ($old_user['avatar']) {
                                    deleteFile(UPLOAD_PATH . 'avatars/' . $old_user['avatar']);
                                }
                                $avatar_filename = $upload_result['filename'];
                            }
                        }
                        
                        // Update user (without password)
                        $query = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, 
                                 role = ?, language = ?, avatar = ?, updated_at = NOW() WHERE id = ?";
                        
                        executeQuery($query, [
                            $username, $email, $first_name, $last_name, $role, $language, $avatar_filename, $user_id
                        ]);
                        
                        // Handle password change if provided
                        if (!empty($_POST['password'])) {
                            $new_password = $_POST['password'];
                            if (strlen($new_password) >= 6) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id]);
                            }
                        }
                        
                        logActivity('User Updated', 'users', $user_id, $old_user);
                        
                        $_SESSION['success_message'] = __('user_updated_successfully');
                        header('Location: manage.php');
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
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = RECORDS_PER_PAGE;

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users WHERE {$where_clause}";
$total_result = fetchRow($count_query, $params);
$total_records = $total_result['total'];

// Get pagination data
$pagination = getPaginationData($total_records, $per_page, $page);

// Get users
$users_query = "SELECT *, 
                (SELECT COUNT(*) FROM sales_orders WHERE user_id = users.id) as order_count,
                (SELECT COUNT(*) FROM activity_logs WHERE user_id = users.id) as activity_count
                FROM users 
                WHERE {$where_clause} 
                ORDER BY created_at DESC 
                LIMIT {$per_page} OFFSET {$pagination['offset']}";

$users = fetchAll($users_query, $params);

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
                    <i class="fas fa-users-cog"></i>
                    <?php _e('user_management'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_system_users'); ?></p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission('users.create')): ?>
                <button class="btn btn-primary" onclick="showAddUserModal()">
                    <i class="fas fa-plus"></i>
                    <?php _e('add_user'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_records); ?></div>
                    <div class="stat-label"><?php _e('total_users'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $active_users = fetchRow("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
                        echo number_format($active_users['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('active_users'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $admin_count = fetchRow("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                        echo number_format($admin_count['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('administrators'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $recent_logins = fetchRow("SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                        echo number_format($recent_logins['count']);
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('active_this_week'); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
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
                           placeholder="<?php _e('search_users'); ?>..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="role" class="form-select">
                        <option value=""><?php _e('all_roles'); ?></option>
                        <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>><?php _e('admin'); ?></option>
                        <option value="manager" <?php echo ($role_filter === 'manager') ? 'selected' : ''; ?>><?php _e('manager'); ?></option>
                        <option value="employee" <?php echo ($role_filter === 'employee') ? 'selected' : ''; ?>><?php _e('employee'); ?></option>
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
                    <a href="manage.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php _e('clear'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title"><?php _e('users_list'); ?></h3>
                <div class="table-info">
                    <?php echo sprintf(__('showing_results'), $pagination['offset'] + 1, 
                                     min($pagination['offset'] + $per_page, $total_records), $total_records); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php _e('user'); ?></th>
                            <th><?php _e('role'); ?></th>
                            <th><?php _e('activity'); ?></th>
                            <th><?php _e('last_login'); ?></th>
                            <th><?php _e('status'); ?></th>
                            <th><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if ($user['avatar']): ?>
                                                    <img src="<?php echo getAvatarUrl($user['avatar']); ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                        <span class="current-user-badge">(<?php _e('you'); ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="user-email">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="role-badge role-<?php echo $user['role']; ?>">
                                            <i class="fas fa-<?php echo ($user['role'] === 'admin') ? 'crown' : (($user['role'] === 'manager') ? 'user-tie' : 'user'); ?>"></i>
                                            <?php _e($user['role']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="activity-stats">
                                            <div class="stat-item">
                                                <i class="fas fa-shopping-cart"></i>
                                                <span><?php echo $user['order_count']; ?> <?php _e('orders'); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <i class="fas fa-history"></i>
                                                <span><?php echo $user['activity_count']; ?> <?php _e('activities'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="last-login">
                                            <?php if ($user['last_login']): ?>
                                                <div class="login-date"><?php echo formatDateTime($user['last_login']); ?></div>
                                                <div class="login-ago"><?php echo timeAgo($user['last_login']); ?></div>
                                            <?php else: ?>
                                                <span class="never-logged"><?php _e('never_logged_in'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="status-toggle <?php echo $user['status']; ?>" 
                                                onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')"
                                                <?php echo ($user['id'] === $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                            <?php _e($user['status']); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewUserActivity(<?php echo $user['id']; ?>)"
                                                    title="<?php _e('view_activity'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editUser(<?php echo $user['id']; ?>)"
                                                    title="<?php _e('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-password" 
                                                    onclick="resetPassword(<?php echo $user['id']; ?>)"
                                                    title="<?php _e('reset_password'); ?>">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>)"
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
                                <td colspan="6" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p><?php _e('no_users_found'); ?></p>
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
                    <?php echo generatePaginationHTML($pagination, 'manage.php?search=' . urlencode($search) . '&role=' . $role_filter . '&status=' . $status_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php _e('add_user'); ?></h3>
            <button class="modal-close" onclick="closeUserModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="userForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="user_id" id="userId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="userName" class="form-label"><?php _e('first_name'); ?> *</label>
                        <input type="text" id="userName" name="first_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userLastName" class="form-label"><?php _e('last_name'); ?> *</label>
                        <input type="text" id="userLastName" name="last_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userUsername" class="form-label"><?php _e('username'); ?> *</label>
                        <input type="text" id="userUsername" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userEmail" class="form-label"><?php _e('email'); ?> *</label>
                        <input type="email" id="userEmail" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userRole" class="form-label"><?php _e('role'); ?> *</label>
                        <select id="userRole" name="role" class="form-select" required>
                            <option value="employee"><?php _e('employee'); ?></option>
                            <option value="manager"><?php _e('manager'); ?></option>
                            <option value="admin"><?php _e('admin'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="userLanguage" class="form-label"><?php _e('language'); ?></label>
                        <select id="userLanguage" name="language" class="form-select">
                            <option value="en">English</option>
                            <option value="ku">کوردی</option>
                            <option value="ar">العربية</option>
                        </select>
                    </div>
                    
                    <div class="form-group password-group">
                        <label for="userPassword" class="form-label">
                            <?php _e('password'); ?>
                            <span id="passwordRequired">*</span>
                        </label>
                        <input type="password" id="userPassword" name="password" class="form-input">
                        <small class="form-help"><?php _e('min_6_characters'); ?></small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="userAvatar" class="form-label"><?php _e('avatar'); ?></label>
                        <input type="file" id="userAvatar" name="avatar" class="form-input" accept="image/*">
                        <div id="avatarPreview" class="avatar-preview"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUserModal()">
                    <?php _e('cancel'); ?>
                </button>
                <button type="submit" name="add_user" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php _e('save_user'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* User Management Styles */
    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .current-user-badge {
        background: var(--primary-color);
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 500;
    }

    .user-username {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .user-email {
        color: var(--text-secondary);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .role-badge.role-admin {
        background: #fef3c7;
        color: #d97706;
    }

    .role-badge.role-manager {
        background: #dbeafe;
        color: #2563eb;
    }

    .role-badge.role-employee {
        background: #d1fae5;
        color: #059669;
    }

    .activity-stats {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .stat-item i {
        width: 14px;
        font-size: 12px;
    }

    .last-login {
        text-align: center;
    }

    .login-date {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .login-ago {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 2px;
    }

    .never-logged {
        color: var(--warning-color);
        font-style: italic;
        font-size: 0.85rem;
    }

    .btn-password {
        background: var(--warning-color);
        color: white;
    }

    .password-group {
        position: relative;
    }

    #passwordRequired {
        color: var(--danger-color);
    }

    .avatar-preview {
        margin-top: 10px;
        text-align: center;
    }

    .avatar-preview img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid var(--border-color);
        object-fit: cover;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .user-info {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }
        
        .activity-stats {
            align-items: center;
        }
    }
</style>

<script>
    // User management JavaScript
    let isEditMode = false;
    
    function showAddUserModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = '<?php _e('add_user'); ?>';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('save_user'); ?>';
        document.getElementById('submitBtn').name = 'add_user';
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('userPassword').required = true;
        document.getElementById('avatarPreview').innerHTML = '';
        document.getElementById('userModal').classList.add('show');
    }
    
    function editUser(userId) {
        isEditMode = true;
        document.getElementById('modalTitle').textContent = '<?php _e('edit_user'); ?>';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> <?php _e('update_user'); ?>';
        document.getElementById('submitBtn').name = 'edit_user';
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('userPassword').required = false;
        
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax_action', 'get_user');
        formData.append('user_id', userId);
        
        fetch('manage.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const user = data.user;
                document.getElementById('userId').value = user.id;
                document.getElementById('userName').value = user.first_name || '';
                document.getElementById('userLastName').value = user.last_name || '';
                document.getElementById('userUsername').value = user.username || '';
                document.getElementById('userEmail').value = user.email || '';
                document.getElementById('userRole').value = user.role || 'employee';
                document.getElementById('userLanguage').value = user.language || 'en';
                
                // Show avatar preview if exists
                if (user.avatar) {
                    document.getElementById('avatarPreview').innerHTML = 
                        `<img src="${getAvatarUrl(user.avatar)}" alt="Avatar">`;
                }
                
                document.getElementById('userModal').classList.add('show');
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('<?php _e('operation_failed'); ?>');
        });
    }
    
    function deleteUser(userId) {
        if (confirm('<?php _e('confirm_delete_user'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete_user');
            formData.append('user_id', userId);
            
            fetch('manage.php', {
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
    
    function toggleUserStatus(userId, currentStatus) {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_status');
        formData.append('user_id', userId);
        formData.append('status', currentStatus);
        
        fetch('manage.php', {
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
    
    function resetPassword(userId) {
        if (confirm('<?php _e('confirm_reset_password'); ?>')) {
            showLoading();
            
            const formData = new FormData();
            formData.append('ajax_action', 'reset_password');
            formData.append('user_id', userId);
            
            fetch('manage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess(data.message);
                    alert('<?php _e('new_password'); ?>: ' + data.password);
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
    
    function viewUserActivity(userId) {
        // Implement user activity view
        alert('View user activity - User ID: ' + userId);
    }
    
    function closeUserModal() {
        document.getElementById('userModal').classList.remove('show');
    }
    
    function getAvatarUrl(avatar) {
        return avatar ? '<?php echo APP_URL; ?>/<?php echo UPLOAD_PATH; ?>avatars/' + avatar : '<?php echo APP_URL; ?>/assets/images/default-avatar.png';
    }
    
    // Handle avatar preview
    document.getElementById('userAvatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('avatarPreview');
        
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
    document.getElementById('userForm').addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
        
        showLoading();
    });
    
    // Close modal when clicking outside
    document.getElementById('userModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUserModal();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>