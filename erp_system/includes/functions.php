<?php
/**
 * COMMON FUNCTIONS FILE
 * File: includes/functions.php
 * Purpose: Common utility functions used throughout the ERP system
 */

/**
 * Sanitize input data
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool
 */
function isValidPhone($phone) {
    return preg_match('/^[\+]?[0-9\s\-\(\)]{7,15}$/', $phone);
}

/**
 * Generate a random string
 * @param int $length Length of string
 * @param string $characters Characters to use
 * @return string
 */
function generateRandomString($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Format currency
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @return string
 */
function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = DEFAULT_CURRENCY;
    }
    
    $symbol = CURRENCY_SYMBOL;
    $formatted = number_format($amount, 2);
    
    if (CURRENCY_POSITION === 'left') {
        return $symbol . $formatted;
    } else {
        return $formatted . ' ' . $symbol;
    }
}

/**
 * Format date
 * @param string $date Date string
 * @param string $format Date format
 * @return string
 */
function formatDate($date, $format = null) {
    if ($format === null) {
        $format = DISPLAY_DATE_FORMAT;
    }
    
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime Datetime string
 * @return string
 */
function formatDateTime($datetime) {
    return formatDate($datetime, DISPLAY_DATE_FORMAT . ' H:i');
}

/**
 * Generate unique order number
 * @param string $prefix Prefix for order number
 * @return string
 */
function generateOrderNumber($prefix = 'ORD') {
    return $prefix . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique SKU
 * @param string $prefix Prefix for SKU
 * @return string
 */
function generateSKU($prefix = 'SKU') {
    return $prefix . date('Ym') . generateRandomString(6, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/**
 * Get file extension
 * @param string $filename Filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 * @param string $filename Filename
 * @return bool
 */
function isAllowedFileType($filename) {
    $extension = getFileExtension($filename);
    return in_array($extension, ALLOWED_EXTENSIONS);
}

/**
 * Upload file
 * @param array $file $_FILES array element
 * @param string $destination Destination directory
 * @param array $allowed_types Allowed file types
 * @return array Result array with success status and message
 */
function uploadFile($file, $destination = UPLOAD_PATH, $allowed_types = null) {
    if ($allowed_types === null) {
        $allowed_types = ALLOWED_EXTENSIONS;
    }
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => __('file_upload_error')];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => __('file_too_large')];
    }
    
    // Check file type
    $extension = getFileExtension($file['name']);
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => __('invalid_file_type')];
    }
    
    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generate unique filename
    $filename = generateRandomString(20) . '.' . $extension;
    $filepath = $destination . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true, 
            'message' => __('file_uploaded_successfully'),
            'filename' => $filename,
            'filepath' => $filepath
        ];
    } else {
        return ['success' => false, 'message' => __('file_upload_error')];
    }
}

/**
 * Delete file
 * @param string $filepath File path
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return true;
}

/**
 * Get user avatar URL
 * @param string $avatar Avatar filename
 * @return string
 */
function getAvatarUrl($avatar = null) {
    if ($avatar && file_exists(UPLOAD_PATH . $avatar)) {
        return APP_URL . '/' . UPLOAD_PATH . $avatar;
    }
    return APP_URL . '/assets/images/default-avatar.png';
}

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body
 * @param array $headers Additional headers
 * @return bool
 */
function sendEmail($to, $subject, $body, $headers = []) {
    // Basic email sending - in production, use PHPMailer or similar
    $default_headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    $all_headers = array_merge($default_headers, $headers);
    
    return mail($to, $subject, $body, implode("\r\n", $all_headers));
}

/**
 * Get dashboard statistics
 * @return array
 */
function getDashboardStats() {
    try {
        $stats = [];
        
        // Total products
        $result = fetchRow("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
        $stats['total_products'] = $result['count'] ?? 0;
        
        // Total customers
        $result = fetchRow("SELECT COUNT(*) as count FROM customers WHERE status = 'active'");
        $stats['total_customers'] = $result['count'] ?? 0;
        
        // Total suppliers
        $result = fetchRow("SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'");
        $stats['total_suppliers'] = $result['count'] ?? 0;
        
        // Total orders
        $result = fetchRow("SELECT COUNT(*) as count FROM sales_orders");
        $stats['total_orders'] = $result['count'] ?? 0;
        
        // Sales today
        $result = fetchRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_orders WHERE DATE(order_date) = CURDATE()");
        $stats['sales_today'] = $result['total'] ?? 0;
        
        // Sales this month
        $result = fetchRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())");
        $stats['sales_this_month'] = $result['total'] ?? 0;
        
        // Low stock items
        $result = fetchRow("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND status = 'active'");
        $stats['low_stock_items'] = $result['count'] ?? 0;
        
        // Pending orders
        $result = fetchRow("SELECT COUNT(*) as count FROM sales_orders WHERE status = 'pending'");
        $stats['pending_orders'] = $result['count'] ?? 0;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_products' => 0,
            'total_customers' => 0,
            'total_suppliers' => 0,
            'total_orders' => 0,
            'sales_today' => 0,
            'sales_this_month' => 0,
            'low_stock_items' => 0,
            'pending_orders' => 0
        ];
    }
}

/**
 * Get recent orders
 * @param int $limit Number of orders to return
 * @return array
 */
function getRecentOrders($limit = 5) {
    try {
        $query = "SELECT so.*, c.name as customer_name 
                  FROM sales_orders so 
                  LEFT JOIN customers c ON so.customer_id = c.id 
                  ORDER BY so.created_at DESC 
                  LIMIT ?";
        return fetchAll($query, [$limit]);
    } catch (Exception $e) {
        error_log("Error getting recent orders: " . $e->getMessage());
        return [];
    }
}

/**
 * Get low stock products
 * @param int $limit Number of products to return
 * @return array
 */
function getLowStockProducts($limit = 5) {
    try {
        $query = "SELECT * FROM products 
                  WHERE stock_quantity <= min_stock_level 
                  AND status = 'active' 
                  ORDER BY stock_quantity ASC 
                  LIMIT ?";
        return fetchAll($query, [$limit]);
    } catch (Exception $e) {
        error_log("Error getting low stock products: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top selling products
 * @param int $limit Number of products to return
 * @return array
 */
function getTopSellingProducts($limit = 5) {
    try {
        $query = "SELECT p.*, COALESCE(SUM(soi.quantity), 0) as total_sold
                  FROM products p 
                  LEFT JOIN sales_order_items soi ON p.id = soi.product_id
                  LEFT JOIN sales_orders so ON soi.order_id = so.id
                  WHERE p.status = 'active'
                  AND (so.status IS NULL OR so.status != 'cancelled')
                  GROUP BY p.id
                  ORDER BY total_sold DESC
                  LIMIT ?";
        return fetchAll($query, [$limit]);
    } catch (Exception $e) {
        error_log("Error getting top selling products: " . $e->getMessage());
        return [];
    }
}

/**
 * Get monthly sales data for chart
 * @param int $months Number of months to include
 * @return array
 */
function getMonthlySalesData($months = 12) {
    try {
        $query = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as month,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COUNT(*) as order_count
                  FROM sales_orders 
                  WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  AND status != 'cancelled'
                  GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                  ORDER BY month ASC";
        return fetchAll($query, [$months]);
    } catch (Exception $e) {
        error_log("Error getting monthly sales data: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if product has sufficient stock
 * @param int $product_id Product ID
 * @param int $quantity Required quantity
 * @return bool
 */
function hasStock($product_id, $quantity) {
    try {
        $result = fetchRow("SELECT stock_quantity FROM products WHERE id = ?", [$product_id]);
        return ($result && $result['stock_quantity'] >= $quantity);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update product stock
 * @param int $product_id Product ID
 * @param int $quantity Quantity to add/subtract
 * @param string $operation 'add' or 'subtract'
 * @return bool
 */
function updateStock($product_id, $quantity, $operation = 'subtract') {
    try {
        if ($operation === 'add') {
            $query = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
        } else {
            $query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
        }
        
        executeQuery($query, [$quantity, $product_id]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating stock: " . $e->getMessage());
        return false;
    }
}

/**
 * Record stock movement
 * @param int $product_id Product ID
 * @param string $movement_type 'in', 'out', or 'adjustment'
 * @param int $quantity Quantity moved
 * @param string $reference_type Reference type
 * @param int $reference_id Reference ID
 * @param string $notes Notes
 * @return bool
 */
function recordStockMovement($product_id, $movement_type, $quantity, $reference_type, $reference_id = null, $notes = null) {
    try {
        $query = "INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, reference_id, notes, user_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($query, [
            $product_id,
            $movement_type,
            $quantity,
            $reference_type,
            $reference_id,
            $notes,
            $_SESSION['user_id'] ?? 1
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording stock movement: " . $e->getMessage());
        return false;
    }
}

/**
 * Pagination helper
 * @param int $total_records Total number of records
 * @param int $records_per_page Records per page
 * @param int $current_page Current page number
 * @return array Pagination data
 */
function getPaginationData($total_records, $records_per_page = RECORDS_PER_PAGE, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'records_per_page' => $records_per_page,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => max(1, $current_page - 1),
        'next_page' => min($total_pages, $current_page + 1)
    ];
}

/**
 * Generate pagination HTML
 * @param array $pagination Pagination data from getPaginationData()
 * @param string $base_url Base URL for pagination links
 * @return string HTML pagination
 */
function generatePaginationHTML($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination-nav">';
    $html .= '<div class="pagination-info">';
    $html .= sprintf(__('showing') . ' %d ' . __('to') . ' %d ' . __('of') . ' %d ' . __('entries'),
        ($pagination['current_page'] - 1) * $pagination['records_per_page'] + 1,
        min($pagination['current_page'] * $pagination['records_per_page'], $pagination['total_records']),
        $pagination['total_records']
    );
    $html .= '</div>';
    
    $html .= '<div class="pagination-links">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $html .= '<a href="' . $base_url . '&page=' . $pagination['previous_page'] . '" class="pagination-btn">' . __('previous') . '</a>';
    }
    
    // Page numbers
    $start_page = max(1, $pagination['current_page'] - 2);
    $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active_class = ($i == $pagination['current_page']) ? ' active' : '';
        $html .= '<a href="' . $base_url . '&page=' . $i . '" class="pagination-btn' . $active_class . '">' . $i . '</a>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<a href="' . $base_url . '&page=' . $pagination['next_page'] . '" class="pagination-btn">' . __('next') . '</a>';
    }
    
    $html .= '</div>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Create breadcrumb navigation
 * @param array $breadcrumbs Array of breadcrumb items
 * @return string HTML breadcrumb
 */
function createBreadcrumb($breadcrumbs) {
    if (empty($breadcrumbs)) {
        return '';
    }
    
    $html = '<nav class="breadcrumb">';
    $html .= '<ol class="breadcrumb-list">';
    
    foreach ($breadcrumbs as $index => $breadcrumb) {
        $is_last = ($index === count($breadcrumbs) - 1);
        $html .= '<li class="breadcrumb-item' . ($is_last ? ' active' : '') . '">';
        
        if ($is_last || empty($breadcrumb['url'])) {
            $html .= htmlspecialchars($breadcrumb['title']);
        } else {
            $html .= '<a href="' . htmlspecialchars($breadcrumb['url']) . '">' . htmlspecialchars($breadcrumb['title']) . '</a>';
        }
        
        $html .= '</li>';
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Generate alert/notification HTML
 * @param string $message Message text
 * @param string $type Alert type (success, error, warning, info)
 * @param bool $dismissible Whether alert can be dismissed
 * @return string HTML alert
 */
function createAlert($message, $type = 'info', $dismissible = true) {
    $icons = [
        'success' => 'fa-check-circle',
        'error' => 'fa-exclamation-triangle',
        'warning' => 'fa-exclamation-circle',
        'info' => 'fa-info-circle'
    ];
    
    $icon = $icons[$type] ?? $icons['info'];
    $dismissible_class = $dismissible ? ' dismissible' : '';
    
    $html = '<div class="alert alert-' . $type . $dismissible_class . '">';
    $html .= '<i class="fas ' . $icon . '"></i>';
    $html .= '<span>' . htmlspecialchars($message) . '</span>';
    
    if ($dismissible) {
        $html .= '<button type="button" class="alert-close" onclick="this.parentElement.remove()">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</button>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Time ago helper
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return __('just_now');
    if ($time < 3600) return floor($time/60) . ' ' . __('minutes_ago');
    if ($time < 86400) return floor($time/3600) . ' ' . __('hours_ago');
    if ($time < 2592000) return floor($time/86400) . ' ' . __('days_ago');
    if ($time < 31536000) return floor($time/2592000) . ' ' . __('months_ago');
    
    return floor($time/31536000) . ' ' . __('years_ago');
}
?>