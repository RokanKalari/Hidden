<?php
/**
 * LOGIN PAGE
 * File: modules/auth/login.php
 * Purpose: User authentication interface with multi-language support
 * 
 * Features:
 * - Multi-language support (English, Kurdish, Arabic)
 * - Secure password authentication
 * - Session management
 * - Login attempt limiting
 * - Remember me functionality
 * - Responsive design
 */

// Start session
session_start();

// Include configuration files
require_once '../../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit;
}

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
    setCurrentLanguage($_GET['lang']);
    header('Location: login.php');
    exit;
}

// Initialize variables
$error_message = '';
$success_message = '';
$username = '';

// Check for lockout
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$lockout_key = 'login_attempts_' . md5($client_ip);

if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key]['count'] >= MAX_LOGIN_ATTEMPTS) {
    $time_remaining = LOCKOUT_TIME - (time() - $_SESSION[$lockout_key]['time']);
    if ($time_remaining > 0) {
        $error_message = __('account_locked') . ' ' . ceil($time_remaining / 60) . ' minutes.';
    } else {
        // Reset lockout
        unset($_SESSION[$lockout_key]);
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Check CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = __('error') . ': Invalid request';
    }
    // Check if locked out
    elseif (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key]['count'] >= MAX_LOGIN_ATTEMPTS) {
        $time_remaining = LOCKOUT_TIME - (time() - $_SESSION[$lockout_key]['time']);
        if ($time_remaining > 0) {
            $error_message = __('account_locked');
        }
    }
    // Validate input
    elseif (empty($username) || empty($password)) {
        $error_message = __('required_field');
    }
    else {
        try {
            // Query user from database
            $query = "SELECT id, username, email, password, first_name, last_name, role, status, language 
                      FROM users 
                      WHERE (username = ? OR email = ?) AND status = 'active'";
            $user = fetchRow($query, [$username, $username]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                
                // Reset login attempts
                unset($_SESSION[$lockout_key]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['language'] = $user['language'];
                $_SESSION['login_time'] = time();
                
                // Handle remember me
                if ($remember_me) {
                    $remember_token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $remember_token, time() + (86400 * 30), '/'); // 30 days
                    
                    // Store remember token in database (you might want to create a separate table for this)
                    executeQuery("UPDATE users SET remember_token = ? WHERE id = ?", [$remember_token, $user['id']]);
                }
                
                // Update last login
                executeQuery("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                
                // Log the login activity
                logActivity('User Login', 'users', $user['id']);
                
                // Redirect to dashboard
                header('Location: ../dashboard/index.php');
                exit;
                
            } else {
                // Failed login - increment attempts
                if (!isset($_SESSION[$lockout_key])) {
                    $_SESSION[$lockout_key] = ['count' => 0, 'time' => time()];
                }
                $_SESSION[$lockout_key]['count']++;
                $_SESSION[$lockout_key]['time'] = time();
                
                $error_message = __('invalid_credentials');
                
                // Log failed login attempt
                if ($user) {
                    logActivity('Failed Login Attempt', 'users', $user['id']);
                }
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = __('error') . ': ' . __('operation_failed');
        }
    }
}

// Check for remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $remember_token = $_COOKIE['remember_token'];
    $user = fetchRow("SELECT * FROM users WHERE remember_token = ? AND status = 'active'", [$remember_token]);
    
    if ($user) {
        // Auto-login user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['language'] = $user['language'];
        $_SESSION['login_time'] = time();
        
        header('Location: ../dashboard/index.php');
        exit;
    }
}

$current_lang = getCurrentLanguage();
$is_rtl = ($current_lang === 'ar');
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('login'); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        /* Language Switcher */
        .language-switcher {
            position: fixed;
            top: 20px;
            <?php echo $is_rtl ? 'left' : 'right'; ?>: 20px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }

        .lang-btn {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .lang-btn:hover,
        .lang-btn.active {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
        }

        .login-title {
            font-size: 1.8rem;
            color: var(--text-primary);
            font-weight: bold;
            margin-bottom: 5px;
        }

        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-input-group {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            padding-<?php echo $is_rtl ? 'right' : 'left'; ?>: 45px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-icon {
            position: absolute;
            <?php echo $is_rtl ? 'right' : 'left'; ?>: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
        }

        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .form-checkbox label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        /* Loading Animation */
        .btn-loading {
            position: relative;
            color: transparent;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .login-logo {
                font-size: 2.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* RTL Specific Styles */
        <?php if ($is_rtl): ?>
        body {
            direction: rtl;
        }
        
        .form-checkbox {
            flex-direction: row-reverse;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Language Switcher -->
    <div class="language-switcher">
        <a href="?lang=en" class="lang-btn <?php echo ($current_lang == 'en') ? 'active' : ''; ?>">EN</a>
        <a href="?lang=ku" class="lang-btn <?php echo ($current_lang == 'ku') ? 'active' : ''; ?>">کوردی</a>
        <a href="?lang=ar" class="lang-btn <?php echo ($current_lang == 'ar') ? 'active' : ''; ?>">العربية</a>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-chart-line login-logo"></i>
                <h1 class="login-title"><?php _e('sign_in'); ?></h1>
                <p class="login-subtitle"><?php _e('sign_in_to_account'); ?></p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label"><?php _e('username'); ?> / <?php _e('email'); ?></label>
                    <div class="form-input-group">
                        <i class="fas fa-user form-icon"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($username); ?>"
                               placeholder="<?php _e('username'); ?> <?php _e('or'); ?> <?php _e('email'); ?>"
                               required 
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label"><?php _e('password'); ?></label>
                    <div class="form-input-group">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="<?php _e('password'); ?>"
                               required 
                               autocomplete="current-password">
                    </div>
                </div>

                <div class="form-checkbox">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me"><?php _e('remember_me'); ?></label>
                </div>

                <button type="submit" name="login" class="login-btn" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i>
                    <?php _e('sign_in'); ?>
                </button>
            </form>

            <div class="forgot-password">
                <a href="#" onclick="alert('<?php _e('contact_admin'); ?>')">
                    <?php _e('forgot_password'); ?>
                </a>
            </div>

            <div class="login-footer">
                <p><?php _e('powered_by'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
                <p><?php _e('copyright'); ?> © <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Form submission handling
            loginForm.addEventListener('submit', function(e) {
                // Add loading state
                loginButton.classList.add('btn-loading');
                loginButton.disabled = true;

                // Validate form
                if (!usernameInput.value.trim() || !passwordInput.value) {
                    e.preventDefault();
                    loginButton.classList.remove('btn-loading');
                    loginButton.disabled = false;
                    alert('<?php _e('required_field'); ?>');
                    return;
                }
            });

            // Auto-focus on username field
            usernameInput.focus();

            // Enter key handling
            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !loginButton.disabled) {
                    loginForm.submit();
                }
            });

            // Demo credentials info (remove in production)
            <?php if (DEBUG_MODE): ?>
            console.log('Demo Credentials:');
            console.log('Username: admin');
            console.log('Password: admin123');
            <?php endif; ?>
        });
    </script>
</body>
</html>