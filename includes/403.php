<?php
/**
 * 403 FORBIDDEN ERROR PAGE
 * File: includes/403.php
 * Purpose: Display access denied page
 */

// Ensure current language is set
$current_lang = getCurrentLanguage() ?? 'en';
$is_rtl = ($current_lang === 'ar');
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('access_denied'); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --danger-color: #ef4444;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .error-icon {
            font-size: 5rem;
            color: var(--danger-color);
            margin-bottom: 30px;
        }

        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .error-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .error-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .contact-info {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .error-container {
                padding: 40px 20px;
            }
            
            .error-code {
                font-size: 3rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-ban"></i>
        </div>
        
        <div class="error-code">403</div>
        
        <h1 class="error-title"><?php _e('access_denied'); ?></h1>
        
        <p class="error-message">
            <?php _e('you_dont_have_permission'); ?>
            <br>
            <?php _e('contact_administrator_if_needed'); ?>
        </p>
        
        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <?php _e('go_back'); ?>
            </a>
            
            <a href="<?php echo APP_URL; ?>/modules/dashboard/index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                <?php _e('dashboard'); ?>
            </a>
        </div>
        
        <div class="contact-info">
            <p>
                <i class="fas fa-envelope"></i>
                <?php _e('need_help'); ?>? <?php _e('contact'); ?>: 
                <a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a>
            </p>
        </div>
    </div>
</body>
</html>