<?php
/**
 * LOGOUT FUNCTIONALITY
 * File: modules/auth/logout.php
 * Purpose: Handle user logout and session cleanup
 * 
 * This file handles the logout process:
 * - Logs the logout activity
 * - Cleans up session data
 * - Removes remember me cookies
 * - Redirects to login page
 */

// Start session
session_start();

// Include configuration
require_once '../../config/config.php';

// Log logout activity if user is logged in
if (isLoggedIn()) {
    logActivity('User Logout', 'users', $_SESSION['user_id']);
    
    // Get user info for cleanup
    $user_id = $_SESSION['user_id'];
    
    // Clear remember me token from database
    try {
        executeQuery("UPDATE users SET remember_token = NULL WHERE id = ?", [$user_id]);
    } catch (Exception $e) {
        error_log("Error clearing remember token: " . $e->getMessage());
    }
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    unset($_COOKIE['remember_token']);
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['logout_message'] = __('logout_successful');

// Redirect to login page
header('Location: login.php');
exit;
?>