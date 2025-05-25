<?php
/**
 * MAIN CONFIGURATION FILE
 * File: config/config.php
 * Purpose: General application configuration and settings
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application Configuration
define('APP_NAME', 'Enterprise ERP System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/erp_system');  // Update this to your domain
define('ADMIN_EMAIL', 'admin@company.com');

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Pagination
define('RECORDS_PER_PAGE', 20);

// Date and Time Configuration
define('DEFAULT_TIMEZONE', 'UTC');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');

// Currency Configuration
define('DEFAULT_CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_POSITION', 'left'); // left or right

// Multi-language Configuration
define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'ku', 'ar']);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@yourcompany.com');
define('SMTP_FROM_NAME', 'ERP System');

// Error Reporting (set to false in production)
define('DEBUG_MODE', true);

// Set error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set default timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Get application setting from database
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $query = "SELECT setting_key, setting_value FROM settings";
            $result = fetchAll($query);
            $settings = [];
            foreach ($result as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update application setting
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool
 */
function updateSetting($key, $value) {
    try {
        $query = "INSERT INTO settings (setting_key, setting_value) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        executeQuery($query, [$key, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get current user language
 * @return string
 */
function getCurrentLanguage() {
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    
    if (isset($_SESSION['user_id'])) {
        $user = fetchRow("SELECT language FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if ($user && in_array($user['language'], SUPPORTED_LANGUAGES)) {
            $_SESSION['language'] = $user['language'];
            return $user['language'];
        }
    }
    
    return DEFAULT_LANGUAGE;
}

/**
 * Set current user language
 * @param string $lang Language code
 */
function setCurrentLanguage($lang) {
    if (in_array($lang, SUPPORTED_LANGUAGES)) {
        $_SESSION['language'] = $lang;
        
        // Update user's language preference if logged in
        if (isset($_SESSION['user_id'])) {
            executeQuery("UPDATE users SET language = ? WHERE id = ?", 
                        [$lang, $_SESSION['user_id']]);
        }
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Check if user has permission (role-based access control)
 * @param string $permission Permission to check
 * @return bool
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? 'guest';
    
    $permissions = [
        'admin' => ['*'], // Admin has all permissions
        'manager' => [
            'dashboard.view', 'products.view', 'products.create', 'products.edit',
            'customers.view', 'customers.create', 'customers.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'sales.view', 'sales.create', 'purchase.view', 'purchase.create',
            'reports.view', 'users.view'
        ],
        'employee' => [
            'dashboard.view', 'products.view', 'customers.view', 'suppliers.view',
            'sales.view', 'sales.create', 'purchase.view'
        ]
    ];
    
    $userPermissions = $permissions[$userRole] ?? [];
    
    return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Redirect to access denied if no permission
 * @param string $permission Required permission
 */
function requirePermission($permission) {
    requireLogin();
    
    if (!hasPermission($permission)) {
        header('HTTP/1.0 403 Forbidden');
        include '../includes/403.php';
        exit;
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log user activity
 * @param string $action Action performed
 * @param string $table_name Table affected
 * @param int $record_id Record ID affected
 * @param array $old_values Old values (for updates)
 * @param array $new_values New values
 */
function logActivity($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!isLoggedIn()) return;
    
    try {
        $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($query, [
            $_SESSION['user_id'],
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Auto-load language file
$current_lang = getCurrentLanguage();
$langFile = __DIR__ . "/../languages/{$current_lang}.php";
require_once file_exists($langFile) ? $langFile : (__DIR__ . '/../languages/en.php');

/** */

?>