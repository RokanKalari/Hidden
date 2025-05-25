<?php
/**
 * MAIN ENTRY POINT
 * File: index.php
 * Purpose: Main entry point for the ERP system - redirects users to appropriate pages
 * 
 * This file serves as the main entry point for the ERP system.
 * It checks if the user is logged in and redirects accordingly:
 * - If logged in: redirect to dashboard
 * - If not logged in: redirect to login page
 */

// Start session
session_start();

// Include configuration
require_once 'config/config.php';

// Check if database connection works
if (!testDatabaseConnection()) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 50px; text-align: center; }
            .error { color: #dc3545; background: #f8d7da; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px; }
            .steps { text-align: left; margin: 20px auto; max-width: 600px; }
            .steps li { margin: 10px 0; }
        </style>
    </head>
    <body>
        <h1>Database Connection Error</h1>
        <div class="error">
            <strong>Unable to connect to the database!</strong><br>
            Please check your database configuration.
        </div>
        
        <div class="steps">
            <h3>Setup Steps:</h3>
            <ol>
                <li>Make sure MySQL server is running</li>
                <li>Create a database named "erp_system"</li>
                <li>Import the database structure from "database/erp_database.sql"</li>
                <li>Update database credentials in "config/database.php"</li>
                <li>Refresh this page</li>
            </ol>
        </div>
        
        <p><a href="?" style="color: #007bff; text-decoration: none;">Try Again</a></p>
    </body>
    </html>
    ');
}

// Handle language change if requested
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ku', 'ar'])) {
    setCurrentLanguage($_GET['lang']);
    
    // Redirect to remove the lang parameter from URL
    $redirect_url = $_SERVER['PHP_SELF'];
    if (!empty($_SERVER['QUERY_STRING'])) {
        $params = $_GET;
        unset($params['lang']);
        if (!empty($params)) {
            $redirect_url .= '?' . http_build_query($params);
        }
    }
    header("Location: $redirect_url");
    exit;
}

// Check if user is logged in
if (isLoggedIn()) {
    // User is logged in, redirect to dashboard
    header('Location: modules/dashboard/index.php');
    exit;
} else {
    // User is not logged in, redirect to login page
    header('Location: modules/auth/login.php');
    exit;
}

// This code should never be reached, but just in case:
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo (getCurrentLanguage() == 'ar') ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .welcome-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
        }
        
        .logo {
            font-size: 4rem;
            color: #2563eb;
            margin-bottom: 20px;
        }
        
        .title {
            font-size: 2.5rem;
            color: #1f2937;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .subtitle {
            font-size: 1.2rem;
            color: #6b7280;
            margin-bottom: 40px;
        }
        
        .actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .lang-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .lang-btn {
            padding: 8px 12px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .lang-btn:hover,
        .lang-btn.active {
            background: white;
            color: #2563eb;
        }
    </style>
</head>
<body>
    <!-- Language Switcher -->
    <div class="lang-switcher">
        <a href="?lang=en" class="lang-btn <?php echo (getCurrentLanguage() == 'en') ? 'active' : ''; ?>">EN</a>
        <a href="?lang=ku" class="lang-btn <?php echo (getCurrentLanguage() == 'ku') ? 'active' : ''; ?>">کوردی</a>
        <a href="?lang=ar" class="lang-btn <?php echo (getCurrentLanguage() == 'ar') ? 'active' : ''; ?>">العربية</a>
    </div>

    <div class="welcome-container">
        <div class="logo">
            <i class="fas fa-chart-line"></i>
        </div>
        
        <h1 class="title"><?php _e('welcome'); ?></h1>
        <p class="subtitle"><?php echo APP_NAME; ?> - <?php _e('dashboard_title'); ?></p>
        
        <div class="actions">
            <a href="modules/auth/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                <?php _e('login'); ?>
            </a>
            
            <a href="modules/dashboard/index.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt"></i>
                <?php _e('dashboard'); ?>
            </a>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.welcome-container');
            
            // Add floating animation
            setInterval(() => {
                container.style.transform = 'translateY(' + (Math.sin(Date.now() / 1000) * 5) + 'px)';
            }, 50);
        });
    </script>
</body>
</html>