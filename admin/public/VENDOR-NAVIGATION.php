<?php
/**
 * Vendor Management Navigation Links
 * Add these to your main admin navigation/sidebar
 */

// Vendor Management Menu Items
$vendor_menu = [
    [
        'title' => 'Vendor Management',
        'icon' => 'bi bi-shop',
        'link' => 'vendors.php',
        'submenu' => [
            [
                'title' => 'All Vendors',
                'link' => 'vendors.php',
                'icon' => 'bi bi-list-ul'
            ],
            [
                'title' => 'Add Vendor',
                'link' => 'vendors/add.php',
                'icon' => 'bi bi-plus-circle'
            ],
            [
                'title' => 'Vendor Products',
                'link' => 'vendor-products/manage.php',
                'icon' => 'bi bi-box'
            ],
            [
                'title' => 'Vendor Orders',
                'link' => 'vendor-orders/view.php',
                'icon' => 'bi bi-bag'
            ],
            [
                'title' => 'Vendor Payouts',
                'link' => 'vendor-payouts/view.php',
                'icon' => 'bi bi-cash-coin'
            ],
            [
                'title' => 'Vendor Reviews',
                'link' => 'vendor-reviews/view.php',
                'icon' => 'bi bi-star'
            ],
            [
                'title' => 'Vendor Analytics',
                'link' => 'vendor-analytics/dashboard.php',
                'icon' => 'bi bi-graph-up'
            ]
        ]
    ]
];

// HTML Navigation Snippet for Sidebar
?>

<!-- Add this to your admin sidebar navigation -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle <?php echo (strpos($current_page, 'vendor') !== false) ? 'active' : ''; ?>" 
       href="#" id="vendorMenu" role="button" data-bs-toggle="dropdown">
        <i class="bi bi-shop"></i> Vendor Management
    </a>
    <ul class="dropdown-menu" aria-labelledby="vendorMenu">
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendors') ? 'active' : ''; ?>" 
               href="vendors.php">
                <i class="bi bi-list-ul"></i> All Vendors
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="vendors/add.php">
                <i class="bi bi-plus-circle"></i> Add New Vendor
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendor-products') ? 'active' : ''; ?>" 
               href="vendor-products/manage.php">
                <i class="bi bi-box"></i> Products
            </a>
        </li>
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendor-orders') ? 'active' : ''; ?>" 
               href="vendor-orders/view.php">
                <i class="bi bi-bag"></i> Orders
            </a>
        </li>
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendor-payouts') ? 'active' : ''; ?>" 
               href="vendor-payouts/view.php">
                <i class="bi bi-cash-coin"></i> Payouts
            </a>
        </li>
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendor-reviews') ? 'active' : ''; ?>" 
               href="vendor-reviews/view.php">
                <i class="bi bi-star"></i> Reviews
            </a>
        </li>
        <li>
            <a class="dropdown-item <?php echo ($current_page === 'vendor-analytics') ? 'active' : ''; ?>" 
               href="vendor-analytics/dashboard.php">
                <i class="bi bi-graph-up"></i> Analytics
            </a>
        </li>
    </ul>
</li>
