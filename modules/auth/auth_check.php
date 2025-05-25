<?php
/**
 * AUTHENTICATION CHECK
 * File: modules/auth/auth_check.php
 * Purpose: Verify user authentication and session validity
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once '../../config/config.php';

/**
 * Check if user is authenticated
 */
function checkAuthentication() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time'])) {
        $session_lifetime = time() - $_SESSION['login_time'];
        if ($session_lifetime > SESSION_TIMEOUT) {
            // Session expired
            session_destroy();
            return false;
        }
    }
    
    // Verify user still exists and is active
    try {
        $user = fetchRow("SELECT id, status FROM users WHERE id = ? AND status = 'active'", 
                        [$_SESSION['user_id']]);
        
        if (!$user) {
            // User no longer exists or is inactive
            session_destroy();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require authentication - redirect if not logged in
 */
function requireAuth() {
    if (!checkAuthentication()) {
        // Store current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login
        header('Location: ../../modules/auth/login.php');
        exit;
    }
}

/**
 * Check specific permission
 */
function checkPermission($permission) {
    if (!checkAuthentication()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? 'guest';
    
    // Admin has all permissions
    if ($user_role === 'admin') {
        return true;
    }
    
    // Define role permissions
    $role_permissions = [
        'manager' => [
            'dashboard.view', 'products.view', 'products.create', 'products.edit',
            'customers.view', 'customers.create', 'customers.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'sales.view', 'sales.create', 'purchase.view', 'purchase.create',
            'reports.view', 'users.view', 'inventory.view'
        ],
        'employee' => [
            'dashboard.view', 'products.view', 'customers.view', 'suppliers.view',
            'sales.view', 'sales.create', 'purchase.view', 'inventory.view'
        ]
    ];
    
    $user_permissions = $role_permissions[$user_role] ?? [];
    return in_array($permission, $user_permissions);
}

/**
 * Require specific permission - show 403 if not authorized
 */
function requirePermission($permission) {
    requireAuth(); // First check authentication
    
    if (!checkPermission($permission)) {
        http_response_code(403);
        include '../../includes/403.php';
        exit;
    }
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!checkAuthentication()) {
        return null;
    }
    
    try {
        return fetchRow("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Log user activity
 */
function logUserActivity($activity, $details = null) {
    if (!checkAuthentication()) {
        return;
    }
    
    try {
        $query = "INSERT INTO activity_logs (user_id, action, table_name, ip_address, user_agent, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        executeQuery($query, [
            $_SESSION['user_id'],
            $activity,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log error but don't break application
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>