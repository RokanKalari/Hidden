<?php
/**
 * USER PROFILE
 * File: modules/users/profile.php
 * Purpose: User profile management and settings
 */

session_start();
require_once '../../config/config.php';

// Check authentication
requireLogin();

$page_title = __('profile');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_profile'])) {
        $user_id = $_SESSION['user_id'];
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $language = sanitize($_POST['language']);
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $_SESSION['error_message'] = __('required_fields_missing');
        } elseif (!isValidEmail($email)) {
            $_SESSION['error_message'] = __('invalid_email');
        } else {
            try {
                // Check if email already exists (excluding current user)
                $existing = fetchRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
                if ($existing) {
                    $_SESSION['error_message'] = __('email_already_exists');
                } else {
                    // Get old user data for logging
                    $old_user = fetchRow("SELECT * FROM users WHERE id = ?", [$user_id]);
                    
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
                    
                    // Update profile
                    $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, language = ?, avatar = ?, updated_at = NOW() WHERE id = ?";
                    executeQuery($query, [$first_name, $last_name, $email, $language, $avatar_filename, $user_id]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['language'] = $language;
                    
                    logActivity('Profile Updated', 'users', $user_id, $old_user);
                    
                    $_SESSION['success_message'] = __('profile_updated_successfully');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        $user_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error_message'] = __('required_fields_missing');
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error_message'] = __('password_too_short');
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = __('password_mismatch');
        } else {
            try {
                // Verify current password
                $user = fetchRow("SELECT password FROM users WHERE id = ?", [$user_id]);
                if (!password_verify($current_password, $user['password'])) {
                    $_SESSION['error_message'] = __('current_password_incorrect');
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    executeQuery("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hashed_password, $user_id]);
                    
                    logActivity('Password Changed', 'users', $user_id);
                    
                    $_SESSION['success_message'] = __('password_changed_successfully');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = __('operation_failed');
            }
        }
    }
}

// Get current user data
$user = fetchRow("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

// Get user activity stats
$activity_stats = fetchRow("SELECT 
    COUNT(*) as total_activities,
    MAX(created_at) as last_activity
    FROM activity_logs WHERE user_id = ?", [$_SESSION['user_id']]);

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
                    <i class="fas fa-user"></i>
                    <?php _e('my_profile'); ?>
                </h1>
                <p class="page-description"><?php _e('manage_profile_settings'); ?></p>
            </div>
        </div>

        <div class="profile-container">
            <!-- Profile Overview -->
            <div class="profile-overview">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar-large">
                            <?php if ($user['avatar']): ?>
                                <img src="<?php echo getAvatarUrl($user['avatar']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-info">
                            <h2 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                            <p class="profile-role">
                                <i class="fas fa-crown"></i>
                                <?php _e($user['role']); ?>
                            </p>
                            <p class="profile-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="profile-member-since">
                                <i class="fas fa-calendar"></i>
                                <?php _e('member_since'); ?> <?php echo formatDate($user['created_at']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $activity_stats['total_activities']; ?></div>
                            <div class="stat-label"><?php _e('total_activities'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php if ($user['last_login']): ?>
                                    <?php echo timeAgo($user['last_login']); ?>
                                <?php else: ?>
                                    <?php _e('never'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="stat-label"><?php _e('last_login'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php if ($activity_stats['last_activity']): ?>
                                    <?php echo timeAgo($activity_stats['last_activity']); ?>
                                <?php else: ?>
                                    <?php _e('never'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="stat-label"><?php _e('last_activity'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Forms -->
            <div class="profile-forms">
                <!-- Edit Profile -->
                <div class="form-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-edit"></i>
                            <?php _e('edit_profile'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="firstName" class="form-label"><?php _e('first_name'); ?> *</label>
                                    <input type="text" id="firstName" name="first_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lastName" class="form-label"><?php _e('last_name'); ?> *</label>
                                    <input type="text" id="lastName" name="last_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label"><?php _e('email'); ?> *</label>
                                    <input type="email" id="email" name="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="language" class="form-label"><?php _e('language'); ?></label>
                                    <select id="language" name="language" class="form-select">
                                        <option value="en" <?php echo ($user['language'] === 'en') ? 'selected' : ''; ?>>English</option>
                                        <option value="ku" <?php echo ($user['language'] === 'ku') ? 'selected' : ''; ?>>کوردی</option>
                                        <option value="ar" <?php echo ($user['language'] === 'ar') ? 'selected' : ''; ?>>العربية</option>
                                    </select>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="avatar" class="form-label"><?php _e('profile_picture'); ?></label>
                                    <input type="file" id="avatar" name="avatar" class="form-input" accept="image/*">
                                    <small class="form-help"><?php _e('max_file_size_2mb'); ?></small>
                                    <div id="avatarPreview" class="avatar-preview">
                                        <?php if ($user['avatar']): ?>
                                            <img src="<?php echo getAvatarUrl($user['avatar']); ?>" alt="Current Avatar">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php _e('update_profile'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="form-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-key"></i>
                            <?php _e('change_password'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="currentPassword" class="form-label"><?php _e('current_password'); ?> *</label>
                                <input type="password" id="currentPassword" name="current_password" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="newPassword" class="form-label"><?php _e('new_password'); ?> *</label>
                                <input type="password" id="newPassword" name="new_password" class="form-input" required>
                                <small class="form-help"><?php _e('min_6_characters'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword" class="form-label"><?php _e('confirm_password'); ?> *</label>
                                <input type="password" id="confirmPassword" name="confirm_password" class="form-input" required>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key"></i>
                                    <?php _e('change_password'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .profile-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 30px;
        max-width: 1200px;
    }

    .profile-card,
    .form-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .profile-header {
        padding: 30px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        text-align: center;
    }

    .profile-avatar-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 2.5rem;
        font-weight: bold;
        overflow: hidden;
        border: 4px solid rgba(255, 255, 255, 0.3);
    }

    .profile-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-name {
        font-size: 1.8rem;
        font-weight: bold;
        margin-bottom: 8px;
    }

    .profile-role,
    .profile-email,
    .profile-member-since {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 6px;
        opacity: 0.9;
    }

    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        padding: 25px;
        background: white;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .form-card {
        margin-bottom: 20px;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border-color);
        background: var(--light-color);
    }

    .card-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .card-body {
        padding: 25px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-actions {
        text-align: right;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
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

    @media (max-width: 768px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .profile-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // Handle avatar preview
    document.getElementById('avatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('avatarPreview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Password confirmation validation
    document.getElementById('confirmPassword').addEventListener('input', function() {
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = this.value;
        
        if (confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('<?php _e('password_mismatch'); ?>');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>