<?php
/**
 * ARABIC LANGUAGE FILE
 * File: languages/ar.php
 * Purpose: Arabic translations for the ERP system
 */

$lang = [
    // Common Terms
    'welcome' => 'أهلاً وسهلاً',
    'dashboard' => 'لوحة التحكم',
    'login' => 'تسجيل الدخول',
    'logout' => 'تسجيل الخروج',
    'username' => 'اسم المستخدم',
    'password' => 'كلمة المرور',
    'email' => 'البريد الإلكتروني',
    'name' => 'الاسم',
    'first_name' => 'الاسم الأول',
    'last_name' => 'اسم العائلة',
    'phone' => 'رقم الهاتف',
    'address' => 'العنوان',
    'city' => 'المدينة',
    'country' => 'البلد',
    'status' => 'الحالة',
    'active' => 'نشط',
    'inactive' => 'غير نشط',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'edit' => 'تعديل',
    'delete' => 'حذف',
    'view' => 'عرض',
    'add' => 'إضافة',
    'create' => 'إنشاء',
    'update' => 'تحديث',
    'search' => 'بحث',
    'filter' => 'تصفية',
    'export' => 'تصدير',
    'import' => 'استيراد',
    'print' => 'طباعة',
    'total' => 'المجموع الكلي',
    'subtotal' => 'المجموع الفرعي',
    'tax' => 'ضريبة',
    'discount' => 'خصم',
    'quantity' => 'الكمية',
    'price' => 'السعر',
    'amount' => 'المبلغ',
    'date' => 'التاريخ',
    'time' => 'الوقت',
    'description' => 'الوصف',
    'notes' => 'ملاحظات',
    'actions' => 'الإجراءات',
    'yes' => 'نعم',
    'no' => 'لا',
    'loading' => 'جار التحميل...',
    'processing' => 'جار المعالجة...',
    'please_wait' => 'يرجى الانتظار...',
    
    // Authentication
    'sign_in' => 'تسجيل الدخول',
    'sign_in_to_account' => 'سجل دخولك إلى حسابك',
    'remember_me' => 'تذكرني',
    'forgot_password' => 'نسيت كلمة المرور؟',
    'invalid_credentials' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
    'login_successful' => 'تم تسجيل الدخول بنجاح',
    'logout_successful' => 'تم تسجيل الخروج بنجاح',
    'session_expired' => 'انتهت صلاحية جلستك',
    'access_denied' => 'تم رفض الوصول',
    'account_locked' => 'تم إغلاق الحساب بسبب كثرة المحاولات الفاشلة',
    
    // Dashboard
    'dashboard_title' => 'نظرة عامة على لوحة التحكم',
    'welcome_back' => 'مرحباً بعودتك',
    'total_products' => 'إجمالي المنتجات',
    'total_customers' => 'إجمالي العملاء',
    'total_suppliers' => 'إجمالي الموردين',
    'total_orders' => 'إجمالي الطلبات',
    'sales_today' => 'مبيعات اليوم',
    'sales_this_month' => 'مبيعات هذا الشهر',
    'low_stock_items' => 'المنتجات قليلة المخزون',
    'recent_orders' => 'الطلبات الحديثة',
    'top_products' => 'أفضل المنتجات مبيعاً',
    'sales_overview' => 'نظرة عامة على المبيعات',
    'inventory_status' => 'حالة المخزون',
    'financial_summary' => 'الملخص المالي',
    
    // Navigation Menu
    'inventory' => 'المخزون',
    'products' => 'المنتجات',
    'categories' => 'الفئات',
    'stock' => 'المخزون',
    'sales' => 'المبيعات',
    'orders' => 'الطلبات',
    'invoices' => 'الفواتير',
    'customers' => 'العملاء',
    'purchase' => 'المشتريات',
    'suppliers' => 'الموردون',
    'reports' => 'التقارير',
    'users' => 'المستخدمون',
    'settings' => 'الإعدادات',
    'profile' => 'الملف الشخصي',
    
    // Products
    'product' => 'منتج',
    'add_product' => 'إضافة منتج',
    'edit_product' => 'تعديل منتج',
    'product_name' => 'اسم المنتج',
    'product_code' => 'كود المنتج',
    'sku' => 'رمز المنتج',
    'category' => 'الفئة',
    'unit_price' => 'سعر الوحدة',
    'cost_price' => 'سعر التكلفة',
    'stock_quantity' => 'كمية المخزون',
    'min_stock' => 'الحد الأدنى للمخزون',
    'max_stock' => 'الحد الأقصى للمخزون',
    'unit_measure' => 'وحدة القياس',
    'barcode' => 'الباركود',
    'product_image' => 'صورة المنتج',
    'low_stock_alert' => 'تنبيه نفاد المخزون',
    'out_of_stock' => 'نفد من المخزون',
    'in_stock' => 'متوفر في المخزون',
    
    // Categories
    'add_category' => 'إضافة فئة',
    'edit_category' => 'تعديل فئة',
    'category_name' => 'اسم الفئة',
    'parent_category' => 'الفئة الرئيسية',
    
    // Customers
    'customer' => 'عميل',
    'add_customer' => 'إضافة عميل',
    'edit_customer' => 'تعديل عميل',
    'customer_name' => 'اسم العميل',
    'company' => 'الشركة',
    'credit_limit' => 'حد الائتمان',
    'balance' => 'الرصيد',
    'customer_info' => 'معلومات العميل',
    'contact_info' => 'معلومات الاتصال',
    
    // Suppliers
    'supplier' => 'مورد',
    'add_supplier' => 'إضافة مورد',
    'edit_supplier' => 'تعديل مورد',
    'supplier_name' => 'اسم المورد',
    'tax_number' => 'الرقم الضريبي',
    'supplier_info' => 'معلومات المورد',
    
    // Sales Orders
    'sales_order' => 'طلب مبيعات',
    'new_sale' => 'مبيعة جديدة',
    'order_number' => 'رقم الطلب',
    'order_date' => 'تاريخ الطلب',
    'delivery_date' => 'تاريخ التسليم',
    'customer_selection' => 'اختيار العميل',
    'add_item' => 'إضافة عنصر',
    'order_items' => 'عناصر الطلب',
    'order_total' => 'إجمالي الطلب',
    'order_status' => 'حالة الطلب',
    'pending' => 'في الانتظار',
    'confirmed' => 'مؤكد',
    'shipped' => 'تم الشحن',
    'delivered' => 'تم التسليم',
    'cancelled' => 'ملغي',
    
    // Purchase Orders
    'purchase_order' => 'طلب شراء',
    'new_purchase' => 'شراء جديد',
    'supplier_selection' => 'اختيار المورد',
    'expected_date' => 'التاريخ المتوقع',
    'received' => 'تم الاستلام',
    
    // Reports
    'sales_report' => 'تقرير المبيعات',
    'inventory_report' => 'تقرير المخزون',
    'financial_report' => 'التقرير المالي',
    'customer_report' => 'تقرير العملاء',
    'supplier_report' => 'تقرير الموردين',
    'profit_loss' => 'الأرباح والخسائر',
    'date_range' => 'نطاق التاريخ',
    'from_date' => 'من تاريخ',
    'to_date' => 'إلى تاريخ',
    'generate_report' => 'إنشاء التقرير',
    'report_generated' => 'تم إنشاء التقرير بنجاح',
    
    // Users
    'user' => 'مستخدم',
    'add_user' => 'إضافة مستخدم',
    'edit_user' => 'تعديل مستخدم',
    'role' => 'الدور',
    'admin' => 'مدير',
    'manager' => 'مدير',
    'employee' => 'موظف',
    'last_login' => 'آخر تسجيل دخول',
    'user_status' => 'حالة المستخدم',
    'change_password' => 'تغيير كلمة المرور',
    'new_password' => 'كلمة المرور الجديدة',
    'confirm_password' => 'تأكيد كلمة المرور',
    
    // Messages
    'success' => 'نجح',
    'error' => 'خطأ',
    'warning' => 'تحذير',
    'info' => 'معلومات',
    'record_saved' => 'تم حفظ السجل بنجاح',
    'record_updated' => 'تم تحديث السجل بنجاح',
    'record_deleted' => 'تم حذف السجل بنجاح',
    'operation_failed' => 'فشلت العملية',
    'no_records_found' => 'لم يتم العثور على سجلات',
    'confirm_delete' => 'هل أنت متأكد من أنك تريد حذف هذا السجل؟',
    'required_field' => 'هذا الحقل مطلوب',
    'invalid_email' => 'عنوان بريد إلكتروني غير صحيح',
    'invalid_phone' => 'رقم هاتف غير صحيح',
    'password_mismatch' => 'كلمات المرور غير متطابقة',
    'file_upload_error' => 'خطأ في رفع الملف',
    'invalid_file_type' => 'نوع ملف غير صحيح',
    'file_too_large' => 'حجم الملف كبير جداً',
    
    // Pagination
    'showing' => 'عرض',
    'to' => 'إلى',
    'of' => 'من',
    'entries' => 'إدخال',
    'previous' => 'السابق',
    'next' => 'التالي',
    'first' => 'الأول',
    'last' => 'الأخير',
    
    // Time periods
    'today' => 'اليوم',
    'yesterday' => 'أمس',
    'this_week' => 'هذا الأسبوع',
    'this_month' => 'هذا الشهر',
    'this_year' => 'هذا العام',
    'last_week' => 'الأسبوع الماضي',
    'last_month' => 'الشهر الماضي',
    'last_year' => 'العام الماضي',
    'custom' => 'مخصص',
    
    // Company Info
    'company_info' => 'معلومات الشركة',
    'company_name' => 'اسم الشركة',
    'company_logo' => 'شعار الشركة',
    'company_address' => 'عنوان الشركة',
    'company_phone' => 'هاتف الشركة',
    'company_email' => 'بريد الشركة الإلكتروني',
    'website' => 'الموقع الإلكتروني',
    
    // System Settings
    'general_settings' => 'الإعدادات العامة',
    'system_settings' => 'إعدادات النظام',
    'currency_settings' => 'إعدادات العملة',
    'language_settings' => 'إعدادات اللغة',
    'notification_settings' => 'إعدادات الإشعارات',
    'backup_settings' => 'إعدادات النسخ الاحتياطي',
    'default_language' => 'اللغة الافتراضية',
    'currency_symbol' => 'رمز العملة',
    'date_format' => 'تنسيق التاريخ',
    'timezone' => 'المنطقة الزمنية',
    
    // Notifications
    'notifications' => 'الإشعارات',
    'mark_as_read' => 'تحديد كمقروء',
    'mark_all_read' => 'تحديد الكل كمقروء',
    'no_notifications' => 'لا توجد إشعارات جديدة',
    'stock_low_notification' => 'المخزون منخفض لبعض المنتجات',
    'new_order_notification' => 'تم وضع طلب جديد',
    'payment_received_notification' => 'تم استلام الدفعة',
    
    // Footer
    'all_rights_reserved' => 'جميع الحقوق محفوظة',
    'powered_by' => 'مدعوم بواسطة',
    'version' => 'إصدار',
    'copyright' => 'حقوق الطبع والنشر',
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