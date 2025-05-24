<?php
/**
 * NAVIGATION SIDEBAR
 * File: includes/sidebar.php
 * Purpose: Navigation sidebar with menu items based on user permissions
 * 
 * This file contains the sidebar navigation with:
 * - Role-based menu items
 * - Current page highlighting
 * - Collapsible menu
 * - Multi-language support
 */

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_lang = getCurrentLanguage();
$is_rtl = ($current_lang === 'ar');

// Define menu items with permissions
$menu_items = [
    [
        'title' => 'dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => APP_URL . '/modules/dashboard/index.php',
        'permission' => 'dashboard.view',
        'active' => ($current_dir === 'dashboard')
    ],
    [
        'title' => 'inventory',
        'icon' => 'fas fa-boxes',
        'permission' => 'inventory.view',
        'submenu' => [
            [
                'title' => 'products',
                'icon' => 'fas fa-box',
                'url' => APP_URL . '/modules/inventory/products.php',
                'permission' => 'products.view',
                'active' => ($current_page === 'products.php')
            ],
            [
                'title' => 'categories',
                'icon' => 'fas fa-tags',
                'url' => APP_URL . '/modules/inventory/categories.php',
                'permission' => 'products.view',
                'active' => ($current_page === 'categories.php')
            ],
            [
                'title' => 'stock',
                'icon' => 'fas fa-warehouse',
                'url' => APP_URL . '/modules/inventory/stock.php',
                'permission' => 'inventory.view',
                'active' => ($current_page === 'stock.php')
            ]
        ]
    ],
    [
        'title' => 'sales',
        'icon' => 'fas fa-shopping-cart',
        'permission' => 'sales.view',
        'submenu' => [
            [
                'title' => 'orders',
                'icon' => 'fas fa-receipt',
                'url' => APP_URL . '/modules/sales/orders.php',
                'permission' => 'sales.view',
                'active' => ($current_page === 'orders.php' && $current_dir === 'sales')
            ],
            [
                'title' => 'invoices',
                'icon' => 'fas fa-file-invoice',
                'url' => APP_URL . '/modules/sales/invoices.php',
                'permission' => 'sales.view',
                'active' => ($current_page === 'invoices.php')
            ],
            [
                'title' => 'customers',
                'icon' => 'fas fa-users',
                'url' => APP_URL . '/modules/sales/customers.php',
                'permission' => 'customers.view',
                'active' => ($current_page === 'customers.php')
            ]
        ]
    ],
    [
        'title' => 'purchase',
        'icon' => 'fas fa-truck',
        'permission' => 'purchase.view',
        'submenu' => [
            [
                'title' => 'orders',
                'icon' => 'fas fa-clipboard-list',
                'url' => APP_URL . '/modules/purchase/orders.php',
                'permission' => 'purchase.view',
                'active' => ($current_page === 'orders.php' && $current_dir === 'purchase')
            ],
            [
                'title' => 'suppliers',
                'icon' => 'fas fa-industry',
                'url' => APP_URL . '/modules/purchase/suppliers.php',
                'permission' => 'suppliers.view',
                'active' => ($current_page === 'suppliers.php')
            ]
        ]
    ],
    [
        'title' => 'reports',
        'icon' => 'fas fa-chart-bar',
        'permission' => 'reports.view',
        'submenu' => [
            [
                'title' => 'sales_report',
                'icon' => 'fas fa-chart-line',
                'url' => APP_URL . '/modules/reports/sales_report.php',
                'permission' => 'reports.view',
                'active' => ($current_page === 'sales_report.php')
            ],
            [
                'title' => 'inventory_report',
                'icon' => 'fas fa-chart-pie',
                'url' => APP_URL . '/modules/reports/inventory_report.php',
                'permission' => 'reports.view',
                'active' => ($current_page === 'inventory_report.php')
            ],
            [
                'title' => 'financial_report',
                'icon' => 'fas fa-chart-area',
                'url' => APP_URL . '/modules/reports/financial_report.php',
                'permission' => 'reports.view',
                'active' => ($current_page === 'financial_report.php')
            ]
        ]
    ]
];

// Add admin-only menu items
if (hasRole('admin')) {
    $menu_items[] = [
        'title' => 'users',
        'icon' => 'fas fa-user-cog',
        'permission' => 'users.view',
        'submenu' => [
            [
                'title' => 'manage',
                'icon' => 'fas fa-users-cog',
                'url' => APP_URL . '/modules/users/manage.php',
                'permission' => 'users.manage',
                'active' => ($current_page === 'manage.php' && $current_dir === 'users')
            ],
            [
                'title' => 'settings',
                'icon' => 'fas fa-cogs',
                'url' => APP_URL . '/modules/settings/index.php',
                'permission' => 'settings.manage',
                'active' => ($current_dir === 'settings')
            ]
        ]
    ];
}
?>

<style>
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: var(--header-height);
        <?php echo $is_rtl ? 'right' : 'left'; ?>: 0;
        width: var(--sidebar-width);
        height: calc(100vh - var(--header-height));
        background: white;
        border-<?php echo $is_rtl ? 'left' : 'right'; ?>: 1px solid var(--border-color);
        box-shadow: var(--shadow);
        overflow-y: auto;
        overflow-x: hidden;
        transition: all 0.3s ease;
        z-index: 999;
    }

    .sidebar.collapsed {
        width: 60px;
    }

    .sidebar-content {
        padding: 20px 0;
    }

    /* Menu Styles */
    .menu-section {
        margin-bottom: 30px;
    }

    .menu-section:last-child {
        margin-bottom: 0;
    }

    .menu-title {
        padding: 0 20px 10px;
        font-size: 11px;
        font-weight: bold;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sidebar.collapsed .menu-title {
        display: none;
    }

    .menu-list {
        list-style: none;
    }

    .menu-item {
        margin-bottom: 2px;
    }

    .menu-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        border-<?php echo $is_rtl ? 'right' : 'left'; ?>: 3px solid transparent;
    }

    .menu-link:hover {
        background: rgba(37, 99, 235, 0.1);
        color: var(--primary-color);
        border-<?php echo $is_rtl ? 'right' : 'left'; ?>-color: var(--primary-color);
    }

    .menu-link.active {
        background: rgba(37, 99, 235, 0.15);
        color: var(--primary-color);
        border-<?php echo $is_rtl ? 'right' : 'left'; ?>-color: var(--primary-color);
        font-weight: 600;
    }

    .menu-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-<?php echo $is_rtl ? 'left' : 'right'; ?>: 12px;
        font-size: 16px;
        flex-shrink: 0;
    }

    .menu-text {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
    }

    .sidebar.collapsed .menu-text {
        display: none;
    }

    .menu-arrow {
        font-size: 10px;
        transition: transform 0.3s ease;
        margin-<?php echo $is_rtl ? 'right' : 'left'; ?>: auto;
    }

    .sidebar.collapsed .menu-arrow {
        display: none;
    }

    .menu-link.expanded .menu-arrow {
        transform: rotate(180deg);
    }

    /* Submenu Styles */
    .submenu {
        list-style: none;
        background: rgba(0, 0, 0, 0.02);
        border-top: 1px solid var(--border-color);
        display: none;
        animation: slideDown 0.3s ease;
    }

    .submenu.show {
        display: block;
    }

    .sidebar.collapsed .submenu {
        display: none !important;
    }

    .submenu-item {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .submenu-item:last-child {
        border-bottom: none;
    }

    .submenu-link {
        display: flex;
        align-items: center;
        padding: 12px 20px 12px 50px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 13px;
        position: relative;
    }

    .submenu-link:before {
        content: '';
        position: absolute;
        <?php echo $is_rtl ? 'right' : 'left'; ?>: 30px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        background: var(--text-secondary);
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .submenu-link:hover,
    .submenu-link.active {
        background: rgba(37, 99, 235, 0.1);
        color: var(--primary-color);
        padding-<?php echo $is_rtl ? 'right' : 'left'; ?>: 55px;
    }

    .submenu-link:hover:before,
    .submenu-link.active:before {
        background: var(--primary-color);
        transform: translateY(-50%) scale(1.5);
    }

    .submenu-icon {
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-<?php echo $is_rtl ? 'left' : 'right'; ?>: 10px;
        font-size: 12px;
        flex-shrink: 0;
    }

    .submenu-text {
        flex: 1;
        font-weight: 500;
    }

    /* Animations */
    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 500px;
        }
    }

    /* Tooltip for collapsed sidebar */
    .sidebar.collapsed .menu-link {
        position: relative;
    }

    .sidebar.collapsed .menu-link:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        <?php echo $is_rtl ? 'right' : 'left'; ?>: 65px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--dark-color);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
        opacity: 0.9;
        pointer-events: none;
    }

    .sidebar.collapsed .menu-link:hover::before {
        content: '';
        position: absolute;
        <?php echo $is_rtl ? 'right' : 'left'; ?>: 60px;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-<?php echo $is_rtl ? 'left' : 'right'; ?>-color: var(--dark-color);
        z-index: 1000;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(<?php echo $is_rtl ? '' : '-'; ?>100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }
    }

    /* Scrollbar Styling */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <?php foreach ($menu_items as $item): ?>
                <?php if (hasPermission($item['permission'])): ?>
                    <div class="menu-section">
                        <?php if (isset($item['submenu'])): ?>
                            <!-- Parent menu with submenu -->
                            <a href="#" 
                               class="menu-link <?php echo (isset($item['active']) && $item['active']) ? 'active' : ''; ?>" 
                               onclick="toggleSubmenu(this)"
                               data-tooltip="<?php _e($item['title']); ?>">
                                <div class="menu-icon">
                                    <i class="<?php echo $item['icon']; ?>"></i>
                                </div>
                                <div class="menu-text"><?php _e($item['title']); ?></div>
                                <i class="fas fa-chevron-down menu-arrow"></i>
                            </a>
                            
                            <!-- Submenu -->
                            <ul class="submenu <?php echo (isset($item['active']) && $item['active']) ? 'show' : ''; ?>">
                                <?php foreach ($item['submenu'] as $subitem): ?>
                                    <?php if (hasPermission($subitem['permission'])): ?>
                                        <li class="submenu-item">
                                            <a href="<?php echo $subitem['url']; ?>" 
                                               class="submenu-link <?php echo (isset($subitem['active']) && $subitem['active']) ? 'active' : ''; ?>">
                                                <div class="submenu-icon">
                                                    <i class="<?php echo $subitem['icon']; ?>"></i>
                                                </div>
                                                <div class="submenu-text"><?php _e($subitem['title']); ?></div>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <!-- Simple menu link -->
                            <a href="<?php echo $item['url']; ?>" 
                               class="menu-link <?php echo (isset($item['active']) && $item['active']) ? 'active' : ''; ?>"
                               data-tooltip="<?php _e($item['title']); ?>">
                                <div class="menu-icon">
                                    <i class="<?php echo $item['icon']; ?>"></i>
                                </div>
                                <div class="menu-text"><?php _e($item['title']); ?></div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>

<script>
    // Sidebar JavaScript functionality
    function toggleSubmenu(element) {
        const submenu = element.nextElementSibling;
        const arrow = element.querySelector('.menu-arrow');
        
        if (submenu && submenu.classList.contains('submenu')) {
            // Close other submenus
            document.querySelectorAll('.submenu.show').forEach(menu => {
                if (menu !== submenu) {
                    menu.classList.remove('show');
                    menu.previousElementSibling.classList.remove('expanded');
                }
            });
            
            // Toggle current submenu
            submenu.classList.toggle('show');
            element.classList.toggle('expanded');
        }
    }

    // Initialize sidebar state
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-expand parent menu if child is active
        document.querySelectorAll('.submenu-link.active').forEach(activeLink => {
            const submenu = activeLink.closest('.submenu');
            const parentLink = submenu.previousElementSibling;
            
            if (submenu && parentLink) {
                submenu.classList.add('show');
                parentLink.classList.add('expanded');
            }
        });
        
        // Handle mobile sidebar toggle
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
        }
    });
</script>