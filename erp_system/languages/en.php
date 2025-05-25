<?php
/**
 * ENGLISH LANGUAGE FILE
 * File: languages/en.php
 * Purpose: English translations for the ERP system
 */

$lang = [
    // Common Terms
    'welcome' => 'Welcome',
    'dashboard' => 'Dashboard',
    'login' => 'Login',
    'logout' => 'Logout',
    'username' => 'Username',
    'password' => 'Password',
    'email' => 'Email',
    'name' => 'Name',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'phone' => 'Phone',
    'address' => 'Address',
    'city' => 'City',
    'country' => 'Country',
    'status' => 'Status',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'view' => 'View',
    'add' => 'Add',
    'create' => 'Create',
    'update' => 'Update',
    'search' => 'Search',
    'filter' => 'Filter',
    'export' => 'Export',
    'import' => 'Import',
    'print' => 'Print',
    'total' => 'Total',
    'subtotal' => 'Subtotal',
    'tax' => 'Tax',
    'discount' => 'Discount',
    'quantity' => 'Quantity',
    'price' => 'Price',
    'amount' => 'Amount',
    'date' => 'Date',
    'time' => 'Time',
    'description' => 'Description',
    'notes' => 'Notes',
    'actions' => 'Actions',
    'yes' => 'Yes',
    'no' => 'No',
    'loading' => 'Loading...',
    'processing' => 'Processing...',
    'please_wait' => 'Please wait...',
    
    // Authentication
    'sign_in' => 'Sign In',
    'sign_in_to_account' => 'Sign in to your account',
    'remember_me' => 'Remember me',
    'forgot_password' => 'Forgot your password?',
    'invalid_credentials' => 'Invalid username or password',
    'login_successful' => 'Login successful',
    'logout_successful' => 'Logout successful',
    'session_expired' => 'Your session has expired',
    'access_denied' => 'Access denied',
    'account_locked' => 'Account has been locked due to too many failed attempts',
    
    // Dashboard
    'dashboard_title' => 'Dashboard Overview',
    'welcome_back' => 'Welcome back',
    'total_products' => 'Total Products',
    'total_customers' => 'Total Customers',
    'total_suppliers' => 'Total Suppliers',
    'total_orders' => 'Total Orders',
    'sales_today' => 'Sales Today',
    'sales_this_month' => 'Sales This Month',
    'low_stock_items' => 'Low Stock Items',
    'recent_orders' => 'Recent Orders',
    'top_products' => 'Top Selling Products',
    'sales_overview' => 'Sales Overview',
    'inventory_status' => 'Inventory Status',
    'financial_summary' => 'Financial Summary',
    
    // Navigation Menu
    'inventory' => 'Inventory',
    'products' => 'Products',
    'categories' => 'Categories',
    'stock' => 'Stock',
    'sales' => 'Sales',
    'orders' => 'Orders',
    'invoices' => 'Invoices',
    'customers' => 'Customers',
    'purchase' => 'Purchase',
    'suppliers' => 'Suppliers',
    'reports' => 'Reports',
    'users' => 'Users',
    'settings' => 'Settings',
    'profile' => 'Profile',
    
    // Products
    'product' => 'Product',
    'add_product' => 'Add Product',
    'edit_product' => 'Edit Product',
    'product_name' => 'Product Name',
    'product_code' => 'Product Code',
    'sku' => 'SKU',
    'category' => 'Category',
    'unit_price' => 'Unit Price',
    'cost_price' => 'Cost Price',
    'stock_quantity' => 'Stock Quantity',
    'min_stock' => 'Minimum Stock',
    'max_stock' => 'Maximum Stock',
    'unit_measure' => 'Unit of Measure',
    'barcode' => 'Barcode',
    'product_image' => 'Product Image',
    'low_stock_alert' => 'Low Stock Alert',
    'out_of_stock' => 'Out of Stock',
    'in_stock' => 'In Stock',
    
    // Categories
    'add_category' => 'Add Category',
    'edit_category' => 'Edit Category',
    'category_name' => 'Category Name',
    'parent_category' => 'Parent Category',
    
    // Customers
    'customer' => 'Customer',
    'add_customer' => 'Add Customer',
    'edit_customer' => 'Edit Customer',
    'customer_name' => 'Customer Name',
    'company' => 'Company',
    'credit_limit' => 'Credit Limit',
    'balance' => 'Balance',
    'customer_info' => 'Customer Information',
    'contact_info' => 'Contact Information',
    
    // Suppliers
    'supplier' => 'Supplier',
    'add_supplier' => 'Add Supplier',
    'edit_supplier' => 'Edit Supplier',
    'supplier_name' => 'Supplier Name',
    'tax_number' => 'Tax Number',
    'supplier_info' => 'Supplier Information',
    
    // Sales Orders
    'sales_order' => 'Sales Order',
    'new_sale' => 'New Sale',
    'order_number' => 'Order Number',
    'order_date' => 'Order Date',
    'delivery_date' => 'Delivery Date',
    'customer_selection' => 'Customer Selection',
    'add_item' => 'Add Item',
    'order_items' => 'Order Items',
    'order_total' => 'Order Total',
    'order_status' => 'Order Status',
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    
    // Purchase Orders
    'purchase_order' => 'Purchase Order',
    'new_purchase' => 'New Purchase',
    'supplier_selection' => 'Supplier Selection',
    'expected_date' => 'Expected Date',
    'received' => 'Received',
    
    // Reports
    'sales_report' => 'Sales Report',
    'inventory_report' => 'Inventory Report',
    'financial_report' => 'Financial Report',
    'customer_report' => 'Customer Report',
    'supplier_report' => 'Supplier Report',
    'profit_loss' => 'Profit & Loss',
    'date_range' => 'Date Range',
    'from_date' => 'From Date',
    'to_date' => 'To Date',
    'generate_report' => 'Generate Report',
    'report_generated' => 'Report generated successfully',
    
    // Users
    'user' => 'User',
    'add_user' => 'Add User',
    'edit_user' => 'Edit User',
    'role' => 'Role',
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'employee' => 'Employee',
    'last_login' => 'Last Login',
    'user_status' => 'User Status',
    'change_password' => 'Change Password',
    'new_password' => 'New Password',
    'confirm_password' => 'Confirm Password',
    
    // Messages
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',
    'info' => 'Information',
    'record_saved' => 'Record saved successfully',
    'record_updated' => 'Record updated successfully',
    'record_deleted' => 'Record deleted successfully',
    'operation_failed' => 'Operation failed',
    'no_records_found' => 'No records found',
    'confirm_delete' => 'Are you sure you want to delete this record?',
    'required_field' => 'This field is required',
    'invalid_email' => 'Invalid email address',
    'invalid_phone' => 'Invalid phone number',
    'password_mismatch' => 'Passwords do not match',
    'file_upload_error' => 'File upload error',
    'invalid_file_type' => 'Invalid file type',
    'file_too_large' => 'File size too large',
    
    // Pagination
    'showing' => 'Showing',
    'to' => 'to',
    'of' => 'of',
    'entries' => 'entries',
    'previous' => 'Previous',
    'next' => 'Next',
    'first' => 'First',
    'last' => 'Last',
    
    // Time periods
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This Week',
    'this_month' => 'This Month',
    'this_year' => 'This Year',
    'last_week' => 'Last Week',
    'last_month' => 'Last Month',
    'last_year' => 'Last Year',
    'custom' => 'Custom',
    
    // Company Info
    'company_info' => 'Company Information',
    'company_name' => 'Company Name',
    'company_logo' => 'Company Logo',
    'company_address' => 'Company Address',
    'company_phone' => 'Company Phone',
    'company_email' => 'Company Email',
    'website' => 'Website',
    
    // System Settings
    'general_settings' => 'General Settings',
    'system_settings' => 'System Settings',
    'currency_settings' => 'Currency Settings',
    'language_settings' => 'Language Settings',
    'notification_settings' => 'Notification Settings',
    'backup_settings' => 'Backup Settings',
    'default_language' => 'Default Language',
    'currency_symbol' => 'Currency Symbol',
    'date_format' => 'Date Format',
    'timezone' => 'Timezone',
    
    // Notifications
    'notifications' => 'Notifications',
    'mark_as_read' => 'Mark as read',
    'mark_all_read' => 'Mark all as read',
    'no_notifications' => 'No new notifications',
    'stock_low_notification' => 'Stock is running low for some products',
    'new_order_notification' => 'New order has been placed',
    'payment_received_notification' => 'Payment has been received',
    
    // Footer
    'all_rights_reserved' => 'All rights reserved',
    'powered_by' => 'Powered by',
    'version' => 'Version',
    'copyright' => 'Copyright',
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