<?php
/**
 * COMMON HEADER
 * File: includes/header.php
 * Purpose: Common header component used across the ERP system
 * 
 * This file contains the header section with:
 * - Navigation bar
 * - Search functionality
 * - User menu
 * - Notifications
 * - Language switcher
 */

// Ensure user is logged in
requireLogin();

$current_lang = getCurrentLanguage();
$is_rtl = ($current_lang === 'ar');
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'employee';
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-color);
            line-height: 1.6;
            color: var(--text-primary);
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }

        .logo i {
            font-size: 1.8rem;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }

        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            width: 350px;
            padding: 10px 40px 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--light-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-btn {
            position: absolute;
            <?php echo $is_rtl ? 'left' : 'right'; ?>: 12px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .search-btn:hover {
            color: var(--primary-color);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Language Switcher */
        .language-switcher {
            display: flex;
            gap: 5px;
        }

        .lang-btn {
            padding: 6px 10px;
            background: transparent;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-secondary);
        }

        .lang-btn:hover,
        .lang-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Notification Button */
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* User Menu */
        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: var(--light-color);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        .dropdown-arrow {
            font-size: 10px;
            color: var(--text-secondary);
            margin-left: 5px;
        }

        /* User Dropdown */
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            padding: 10px 0;
            display: none;
            z-index: 1001;
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }

        .dropdown-item i {
            width: 16px;
            font-size: 14px;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 5px 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 0 15px;
            }

            .menu-toggle {
                display: block;
            }

            .search-container {
                display: none;
            }

            .user-info {
                display: none;
            }

            .language-switcher {
                display: none;
            }
        }

        /* RTL Support */
        <?php if ($is_rtl): ?>
        .header-right {
            order: -1;
        }

        .header-left {
            order: 1;
        }

        .user-dropdown {
            right: auto;
            left: 0;
        }
        <?php endif; ?>

        /* Main Layout */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
            padding-top: var(--header-height);
        }

        .content-wrapper {
            flex: 1;
            padding: 30px;
            margin-<?php echo $is_rtl ? 'right' : 'left'; ?>: var(--sidebar-width);
            transition: margin 0.3s ease;
        }

        .content-wrapper.sidebar-collapsed {
            margin-<?php echo $is_rtl ? 'right' : 'left'; ?>: 60px;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border-color: #fed7aa;
        }

        .alert-info {
            background: #f0f9ff;
            color: #0284c7;
            border-color: #bae6fd;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            margin-left: auto;
        }

        .alert-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <a href="<?php echo APP_URL; ?>/modules/dashboard/index.php" class="logo">
                <i class="fas fa-chart-line"></i>
                <span><?php echo getSetting('company_name', 'ERP System'); ?></span>
            </a>
            
            <div class="search-container">
                <input type="text" class="search-input" placeholder="<?php _e('search'); ?>..." id="globalSearch">
                <button class="search-btn" onclick="performSearch()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <div class="header-right">
            <!-- Language Switcher -->
            <div class="language-switcher">
                <a href="?lang=en" class="lang-btn <?php echo ($current_lang == 'en') ? 'active' : ''; ?>">EN</a>
                <a href="?lang=ku" class="lang-btn <?php echo ($current_lang == 'ku') ? 'active' : ''; ?>">کوردی</a>
                <a href="?lang=ar" class="lang-btn <?php echo ($current_lang == 'ar') ? 'active' : ''; ?>">العربية</a>
            </div>
            
            <!-- Notifications -->
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">3</span>
            </button>
            
            <!-- User Menu -->
            <div class="user-menu" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-role"><?php _e($user_role); ?></div>
                </div>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
                
                <!-- User Dropdown -->
                <div class="user-dropdown" id="userDropdown">
                    <a href="<?php echo APP_URL; ?>/modules/users/profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        <?php _e('profile'); ?>
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/users/settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        <?php _e('settings'); ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo APP_URL; ?>/modules/auth/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <?php _e('logout'); ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Show alerts if any -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <script>
        // Global JavaScript functions
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content-wrapper');
            
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
            if (content) {
                content.classList.toggle('sidebar-collapsed');
            }
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        function toggleNotifications() {
            alert('<?php _e('notifications'); ?> - Feature coming soon!');
        }

        function performSearch() {
            const query = document.getElementById('globalSearch').value;
            if (query.trim()) {
                // Implement global search functionality
                window.location.href = '<?php echo APP_URL; ?>/modules/search/index.php?q=' + encodeURIComponent(query);
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userDropdown');
            
            if (!userMenu.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Search on Enter key
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    </script>