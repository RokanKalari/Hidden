<?php
/**
 * COMMON FOOTER
 * File: includes/footer.php
 * Purpose: Common footer component used across the ERP system
 * 
 * This file contains the footer section with:
 * - Copyright information
 * - System information
 * - JavaScript includes
 * - Common scripts
 */

$current_year = date('Y');
$current_lang = getCurrentLanguage();
?>

<!-- Footer -->
<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-left">
            <p>&copy; <?php echo $current_year; ?> <?php echo getSetting('company_name', APP_NAME); ?>. <?php _e('all_rights_reserved'); ?></p>
        </div>
        <div class="footer-right">
            <span><?php _e('powered_by'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></span>
            <span class="separator">|</span>
            <span><?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
    </div>
</footer>

<style>
    /* Footer Styles */
    .main-footer {
        background: white;
        border-top: 1px solid var(--border-color);
        padding: 15px 30px;
        margin-top: auto;
        margin-<?php echo (getCurrentLanguage() === 'ar') ? 'right' : 'left'; ?>: var(--sidebar-width);
        transition: margin 0.3s ease;
    }

    .main-footer.sidebar-collapsed {
        margin-<?php echo (getCurrentLanguage() === 'ar') ? 'right' : 'left'; ?>: 60px;
    }

    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .footer-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .separator {
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .main-footer {
            margin-left: 0;
            margin-right: 0;
        }
        
        .footer-content {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
    }
</style>

<!-- Common JavaScript -->
<script>
    // Global JavaScript variables
    const APP_URL = '<?php echo APP_URL; ?>';
    const CURRENT_LANG = '<?php echo getCurrentLanguage(); ?>';
    const CURRENCY_SYMBOL = '<?php echo CURRENCY_SYMBOL; ?>';
    const IS_RTL = <?php echo (getCurrentLanguage() === 'ar') ? 'true' : 'false'; ?>;

    // Common utility functions
    function formatCurrency(amount) {
        const formatted = new Intl.NumberFormat('<?php echo $current_lang; ?>', {
            style: 'currency',
            currency: '<?php echo DEFAULT_CURRENCY; ?>'
        }).format(amount);
        return formatted;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('<?php echo $current_lang; ?>');
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('<?php echo $current_lang; ?>');
    }

    // Show loading overlay
    function showLoading() {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span><?php _e('loading'); ?></span>
            </div>
        `;
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: white;
            font-size: 18px;
        `;
        overlay.querySelector('.loading-spinner').style.cssText = `
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        `;
        document.body.appendChild(overlay);
    }

    // Hide loading overlay
    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    }

    // Show success message
    function showSuccess(message) {
        showAlert(message, 'success');
    }

    // Show error message
    function showError(message) {
        showAlert(message, 'error');
    }

    // Show warning message
    function showWarning(message) {
        showAlert(message, 'warning');
    }

    // Show info message
    function showInfo(message) {
        showAlert(message, 'info');
    }

    // Generic alert function
    function showAlert(message, type = 'info') {
        const alertId = 'alert_' + Date.now();
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-triangle',
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        const alert = document.createElement('div');
        alert.id = alertId;
        alert.className = `alert alert-${type} alert-floating`;
        alert.innerHTML = `
            <i class="fas ${icons[type]}"></i>
            <span>${message}</span>
            <button class="alert-close" onclick="removeAlert('${alertId}')">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Styles for floating alert
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            max-width: 500px;
            z-index: 9998;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        `;

        document.body.appendChild(alert);

        // Auto remove after 5 seconds
        setTimeout(() => {
            removeAlert(alertId);
        }, 5000);
    }

    // Remove alert
    function removeAlert(alertId) {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }

    // Confirm dialog
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // AJAX helper function
    function makeAjaxRequest(url, data = {}, method = 'POST') {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            resolve(xhr.responseText);
                        }
                    } else {
                        reject(new Error('Request failed with status: ' + xhr.status));
                    }
                }
            };

            if (method === 'POST') {
                xhr.send(JSON.stringify(data));
            } else {
                xhr.send();
            }
        });
    }

    // Form validation helper
    function validateForm(formElement) {
        const requiredFields = formElement.querySelectorAll('[required]');
        let isValid = true;
        let firstErrorField = null;

        requiredFields.forEach(field => {
            const value = field.value.trim();
            const fieldGroup = field.closest('.form-group');
            
            // Remove existing error styling
            field.classList.remove('error');
            if (fieldGroup) {
                const errorMessage = fieldGroup.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
            }

            // Check if field is empty
            if (!value) {
                isValid = false;
                field.classList.add('error');
                
                if (fieldGroup && !firstErrorField) {
                    firstErrorField = field;
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = '<?php _e('required_field'); ?>';
                    errorDiv.style.cssText = 'color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;';
                    fieldGroup.appendChild(errorDiv);
                }
            }
            // Email validation
            else if (field.type === 'email' && !isValidEmail(value)) {
                isValid = false;
                field.classList.add('error');
                
                if (fieldGroup && !firstErrorField) {
                    firstErrorField = field;
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = '<?php _e('invalid_email'); ?>';
                    errorDiv.style.cssText = 'color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;';
                    fieldGroup.appendChild(errorDiv);
                }
            }
        });

        // Focus on first error field
        if (firstErrorField) {
            firstErrorField.focus();
        }

        return isValid;
    }

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Initialize common functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Add error styling for form validation
        const style = document.createElement('style');
        style.textContent = `
            .form-input.error,
            .form-select.error,
            .form-textarea.error {
                border-color: var(--danger-color) !important;
                background-color: #fef2f2 !important;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .alert-floating {
                cursor: pointer;
            }
            
            .alert-floating:hover {
                transform: translateX(-5px);
                transition: transform 0.2s ease;
            }
        `;
        document.head.appendChild(style);

        // Auto-hide alerts after page load
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
            }, 5000);
        });

        // Handle sidebar collapse state
        const sidebar = document.querySelector('.sidebar');
        const footer = document.querySelector('.main-footer');
        
        if (sidebar && footer) {
            // Check if sidebar is collapsed
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.attributeName === 'class') {
                        if (sidebar.classList.contains('collapsed')) {
                            footer.classList.add('sidebar-collapsed');
                        } else {
                            footer.classList.remove('sidebar-collapsed');
                        }
                    }
                });
            });
            
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    });

    // Handle online/offline status
    window.addEventListener('online', function() {
        showSuccess('<?php _e('connection_restored'); ?>');
    });

    window.addEventListener('offline', function() {
        showWarning('<?php _e('connection_lost'); ?>');
    });

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>

</body>
</html>