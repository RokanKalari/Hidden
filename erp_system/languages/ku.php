<?php
/**
 * KURDISH LANGUAGE FILE
 * File: languages/ku.php
 * Purpose: Kurdish (Sorani) translations for the ERP system
 */

$lang = [
    // Common Terms
    'welcome' => 'بەخێربێیت',
    'dashboard' => 'داشبۆرد',
    'login' => 'چوونەژوور',
    'logout' => 'دەرچوون',
    'username' => 'ناوی بەکارهێنەر',
    'password' => 'تێپەڕەوشە',
    'email' => 'ئیمەیڵ',
    'name' => 'ناو',
    'first_name' => 'ناوی یەکەم',
    'last_name' => 'ناوی کۆتایی',
    'phone' => 'تەلەفۆن',
    'address' => 'ناونیشان',
    'city' => 'شار',
    'country' => 'وڵات',
    'status' => 'دۆخ',
    'active' => 'چالاک',
    'inactive' => 'ناچالاک',
    'save' => 'پاشەکەوتکردن',
    'cancel' => 'پاشگەزبوونەوە',
    'edit' => 'دەستکاریکردن',
    'delete' => 'سڕینەوە',
    'view' => 'بینین',
    'add' => 'زیادکردن',
    'create' => 'دروستکردن',
    'update' => 'نوێکردنەوە',
    'search' => 'گەڕان',
    'filter' => 'پاڵاوتن',
    'export' => 'هەناردەکردن',
    'import' => 'هاوردەکردن',
    'print' => 'چاپکردن',
    'total' => 'کۆی گشتی',
    'subtotal' => 'کۆی بەشی',
    'tax' => 'باج',
    'discount' => 'داشکاندن',
    'quantity' => 'بڕ',
    'price' => 'نرخ',
    'amount' => 'بڕی پارە',
    'date' => 'بەروار',
    'time' => 'کات',
    'description' => 'وەسف',
    'notes' => 'تێبینی',
    'actions' => 'کردارەکان',
    'yes' => 'بەڵێ',
    'no' => 'نەخێر',
    'loading' => 'باربوون...',
    'processing' => 'پرۆسێسکردن...',
    'please_wait' => 'تکایە چاوەڕێ بکە...',
    
    // Authentication
    'sign_in' => 'چوونەژوور',
    'sign_in_to_account' => 'بچۆژوور حیسابەکەت',
    'remember_me' => 'لەبیرم بهێڵەوە',
    'forgot_password' => 'تێپەڕەوشەت لەبیرچووە؟',
    'invalid_credentials' => 'ناوی بەکارهێنەر یان تێپەڕەوشە هەڵەیە',
    'login_successful' => 'چوونەژوور سەرکەوتوو بوو',
    'logout_successful' => 'دەرچوون سەرکەوتوو بوو',
    'session_expired' => 'کاتی دانیشتنەکەت بەسەرچووە',
    'access_denied' => 'دەستپێگەیشتن قەدەغەیە',
    'account_locked' => 'حیساب داخراوە بەهۆی زۆری هەوڵدانی سەرنەکەوتوو',
    
    // Dashboard
    'dashboard_title' => 'پوختەی داشبۆرد',
    'welcome_back' => 'بەخێربێیتەوە',
    'total_products' => 'کۆی بەرهەمەکان',
    'total_customers' => 'کۆی کڕیارەکان',
    'total_suppliers' => 'کۆی دابینکەرەکان',
    'total_orders' => 'کۆی داواکارییەکان',
    'sales_today' => 'فرۆشتنی ئەمڕۆ',
    'sales_this_month' => 'فرۆشتنی ئەم مانگە',
    'low_stock_items' => 'کاڵای کەم',
    'recent_orders' => 'داواکارییە نوێیەکان',
    'top_products' => 'بەرهەمە باشەکان',
    'sales_overview' => 'پوختەی فرۆشتن',
    'inventory_status' => 'دۆخی کۆگا',
    'financial_summary' => 'پوختەی دارایی',
    
    // Navigation Menu
    'inventory' => 'کۆگا',
    'products' => 'بەرهەمەکان',
    'categories' => 'جۆرەکان',
    'stock' => 'کۆگا',
    'sales' => 'فرۆشتن',
    'orders' => 'داواکارییەکان',
    'invoices' => 'پسوڵەکان',
    'customers' => 'کڕیارەکان',
    'purchase' => 'کڕین',
    'suppliers' => 'دابینکەرەکان',
    'reports' => 'ڕاپۆرتەکان',
    'users' => 'بەکارهێنەرەکان',
    'settings' => 'ڕێکخستنەکان',
    'profile' => 'پرۆفایل',
    
    // Products
    'product' => 'بەرهەم',
    'add_product' => 'زیادکردنی بەرهەم',
    'edit_product' => 'دەستکاریکردنی بەرهەم',
    'product_name' => 'ناوی بەرهەم',
    'product_code' => 'کۆدی بەرهەم',
    'sku' => 'کۆدی تایبەت',
    'category' => 'جۆر',
    'unit_price' => 'نرخی یەکە',
    'cost_price' => 'نرخی تێچوون',
    'stock_quantity' => 'بڕی کۆگا',
    'min_stock' => 'کەمترین بڕ',
    'max_stock' => 'زۆرترین بڕ',
    'unit_measure' => 'یەکەی پێوان',
    'barcode' => 'بارکۆد',
    'product_image' => 'وێنەی بەرهەم',
    'low_stock_alert' => 'ئاگاداریی کاڵای کەم',
    'out_of_stock' => 'کاڵا نەماوە',
    'in_stock' => 'کاڵا هەیە',
    
    // Categories
    'add_category' => 'زیادکردنی جۆر',
    'edit_category' => 'دەستکاریکردنی جۆر',
    'category_name' => 'ناوی جۆر',
    'parent_category' => 'جۆری سەرەکی',
    
    // Customers
    'customer' => 'کڕیار',
    'add_customer' => 'زیادکردنی کڕیار',
    'edit_customer' => 'دەستکاریکردنی کڕیار',
    'customer_name' => 'ناوی کڕیار',
    'company' => 'کۆمپانیا',
    'credit_limit' => 'سنووری قەرز',
    'balance' => 'بەرماوە',
    'customer_info' => 'زانیاری کڕیار',
    'contact_info' => 'زانیاری پەیوەندی',
    
    // Suppliers
    'supplier' => 'دابینکەر',
    'add_supplier' => 'زیادکردنی دابینکەر',
    'edit_supplier' => 'دەستکاریکردنی دابینکەر',
    'supplier_name' => 'ناوی دابینکەر',
    'tax_number' => 'ژمارەی باج',
    'supplier_info' => 'زانیاری دابینکەر',
    
    // Sales Orders
    'sales_order' => 'داواکاریی فرۆشتن',
    'new_sale' => 'فرۆشتنی نوێ',
    'order_number' => 'ژمارەی داواکاری',
    'order_date' => 'بەرواری داواکاری',
    'delivery_date' => 'بەرواری گەیاندن',
    'customer_selection' => 'هەڵبژاردنی کڕیار',
    'add_item' => 'زیادکردنی کاڵا',
    'order_items' => 'کاڵاکانی داواکاری',
    'order_total' => 'کۆی داواکاری',
    'order_status' => 'دۆخی داواکاری',
    'pending' => 'چاوەڕوان',
    'confirmed' => 'پەسەندکراو',
    'shipped' => 'نێردراو',
    'delivered' => 'گەیاندراو',
    'cancelled' => 'پاشگەزبوونەوە',
    
    // Purchase Orders
    'purchase_order' => 'داواکاریی کڕین',
    'new_purchase' => 'کڕینی نوێ',
    'supplier_selection' => 'هەڵبژاردنی دابینکەر',
    'expected_date' => 'بەرواری چاوەڕوان',
    'received' => 'وەرگیراو',
    
    // Reports
    'sales_report' => 'ڕاپۆرتی فرۆشتن',
    'inventory_report' => 'ڕاپۆرتی کۆگا',
    'financial_report' => 'ڕاپۆرتی دارایی',
    'customer_report' => 'ڕاپۆرتی کڕیار',
    'supplier_report' => 'ڕاپۆرتی دابینکەر',
    'profit_loss' => 'قازانج و زیان',
    'date_range' => 'مەودای بەروار',
    'from_date' => 'لە بەرواری',
    'to_date' => 'تا بەرواری',
    'generate_report' => 'دروستکردنی ڕاپۆرت',
    'report_generated' => 'ڕاپۆرت بە سەرکەوتووی دروستکرا',
    
    // Users
    'user' => 'بەکارهێنەر',
    'add_user' => 'زیادکردنی بەکارهێنەر',
    'edit_user' => 'دەستکاریکردنی بەکارهێنەر',
    'role' => 'ڕۆڵ',
    'admin' => 'بەڕێوەبەر',
    'manager' => 'مانەجەر',
    'employee' => 'کارمەند',
    'last_login' => 'کۆتایی چوونەژوور',
    'user_status' => 'دۆخی بەکارهێنەر',
    'change_password' => 'گۆڕینی تێپەڕەوشە',
    'new_password' => 'تێپەڕەوشەی نوێ',
    'confirm_password' => 'پشتڕاستکردنەوەی تێپەڕەوشە',
    
    // Messages
    'success' => 'سەرکەوتوو',
    'error' => 'هەڵە',
    'warning' => 'ئاگاداری',
    'info' => 'زانیاری',
    'record_saved' => 'تۆمار بە سەرکەوتووی پاشەکەوت کرا',
    'record_updated' => 'تۆمار بە سەرکەوتووی نوێکرایەوە',
    'record_deleted' => 'تۆمار بە سەرکەوتووی سڕایەوە',
    'operation_failed' => 'پرۆسەکە سەرکەوتوو نەبوو',
    'no_records_found' => 'هیچ تۆمارێک نەدۆزرایەوە',
    'confirm_delete' => 'دڵنیایت کە دەتەوێت ئەم تۆمارە بسڕیتەوە؟',
    'required_field' => 'ئەم خانەیە پێویستە',
    'invalid_email' => 'ئیمەیڵی نادروست',
    'invalid_phone' => 'ژمارەی تەلەفۆنی نادروست',
    'password_mismatch' => 'تێپەڕەوشەکان یەکناگرنەوە',
    'file_upload_error' => 'هەڵەی بارکردنی فایل',
    'invalid_file_type' => 'جۆری فایل نادروستە',
    'file_too_large' => 'قەبارەی فایل زۆر گەورەیە',
    
    // Pagination
    'showing' => 'نیشاندانی',
    'to' => 'تا',
    'of' => 'لە',
    'entries' => 'تۆمار',
    'previous' => 'پێشوو',
    'next' => 'دواتر',
    'first' => 'یەکەم',
    'last' => 'کۆتایی',
    
    // Time periods
    'today' => 'ئەمڕۆ',
    'yesterday' => 'دوێنێ',
    'this_week' => 'ئەم هەفتەیە',
    'this_month' => 'ئەم مانگە',
    'this_year' => 'ئەمساڵ',
    'last_week' => 'هەفتەی پێشوو',
    'last_month' => 'مانگی پێشوو',
    'last_year' => 'ساڵی پێشوو',
    'custom' => 'تایبەت',
    
    // Company Info
    'company_info' => 'زانیاری کۆمپانیا',
    'company_name' => 'ناوی کۆمپانیا',
    'company_logo' => 'لۆگۆی کۆمپانیا',
    'company_address' => 'ناونیشانی کۆمپانیا',
    'company_phone' => 'تەلەفۆنی کۆمپانیا',
    'company_email' => 'ئیمەیڵی کۆمپانیا',
    'website' => 'ماڵپەڕ',
    
    // System Settings
    'general_settings' => 'ڕێکخستنە گشتییەکان',
    'system_settings' => 'ڕێکخستنی سیستەم',
    'currency_settings' => 'ڕێکخستنی دراو',
    'language_settings' => 'ڕێکخستنی زمان',
    'notification_settings' => 'ڕێکخستنی ئاگادارکردنەوە',
    'backup_settings' => 'ڕێکخستنی پاڵپشت',
    'default_language' => 'زمانی بنەڕەتی',
    'currency_symbol' => 'هێمای دراو',
    'date_format' => 'شێوازی بەروار',
    'timezone' => 'کاتی ناوچە',
    
    // Notifications
    'notifications' => 'ئاگادارکردنەوەکان',
    'mark_as_read' => 'وەک خوێندراوەوە نیشانکردن',
    'mark_all_read' => 'هەموو وەک خوێندراوەوە نیشانکردن',
    'no_notifications' => 'هیچ ئاگادارکردنەوەیەکی نوێ نییە',
    'stock_low_notification' => 'کۆگا کەمە بۆ هەندێک بەرهەم',
    'new_order_notification' => 'داواکاریێکی نوێ دانراوە',
    'payment_received_notification' => 'پارەدان وەرگیراوە',
    
    // Footer
    'all_rights_reserved' => 'هەموو مافەکان پارێزراون',
    'powered_by' => 'بە توانای',
    'version' => 'وەشان',
    'copyright' => 'مافی چاپ',
];

/**
 * Get translated text
 * @param string $key Translation key
 * @param string $default Default text if key not found
 * @return string
 */
function __($key, $default = null) {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : ($default ?? $key);
}

/**
 * Echo translated text
 * @param string $key Translation key
 * @param string $default Default text if key not found
 */
function _e($key, $default = null) {
    echo __($key, $default);
}
?>