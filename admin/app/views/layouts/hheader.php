<?php
// app/views/layouts/hheader.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define root path and include helpers with absolute path
$root_path = dirname(__DIR__, 3); // Goes up 3 levels from app/views/layouts to reach admin root
require_once $root_path . '/bootstrap/helpers.php';

// Check if admin is logged in
$is_admin_logged_in = isset($_SESSION['admin_id']);
$admin_name = $is_admin_logged_in ? $_SESSION['admin_name'] : '';
$admin_role = $is_admin_logged_in ? $_SESSION['admin_role'] : '';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page == 'index') $current_page = 'dashboard';

// Set page title if not already set
if (!isset($page_title)) {
    $page_title = "Admin Panel - Phool Delivery";
}

// Use dynamic asset paths
$base_url = getBaseUrl();
$admin_assets_path = getAdminAssetsPath();
$main_assets_path = getMainAssetsPath();

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection and user data - FIXED PATH
require_once $root_path . '/bootstrap/app.php';
$pdo = getDBConnection();

// Fetch current user's profile picture if logged in
$profile_picture = '';
$profile_picture_url = getDefaultProfilePicture(); // Default first

if ($is_admin_logged_in) {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();
    $profile_picture = $user['profile_picture'] ?? '';
    
    // Use getProfilePictureUrl which handles both local and production
    if (!empty($profile_picture)) {
        $profile_picture_url = getProfilePictureUrl($profile_picture);
    }
}

// Fetch notifications from database
$notifications = [];
$unread_count = 0;
if ($is_admin_logged_in) {
    // Get unread notifications count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $stmt->execute();
    $result = $stmt->fetch();
    $unread_count = $result['count'] ?? 0;
    
    // Get recent notifications (last 5)
    $stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $notifications = $stmt->fetchAll();
}

// Fetch messages for admin (only order messages in header)
$messages = [];
$unread_message_count = 0;
if ($is_admin_logged_in) {
    // Get unread order messages count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE type = 'order' AND is_read = 0");
    $stmt->execute();
    $result = $stmt->fetch();
    $unread_message_count = $result['count'] ?? 0;
    
    // Get recent order messages (last 5)
    $stmt = $pdo->prepare("
        SELECT m.*, c.name as customer_name 
        FROM messages m 
        LEFT JOIN customers c ON m.customer_id = c.id 
        WHERE m.type = 'order' 
        ORDER BY m.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll();
}

// Helper function to display time ago
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return floor($diff / 86400) . ' days ago';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo $admin_assets_path; ?>/css/admin.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $main_assets_path; ?>/img/favicon.png" type="image/png">
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Topbar mobile adjustments */
            .topbar .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* Mobile menu toggle button */
            #mobileMenuToggle {
                padding: 8px;
                margin-right: 5px;
            }
            
            /* Brand logo mobile adjustment */
            .navbar-brand {
                margin-right: 0 !important;
            }
            
            /* Search toggle button */
            #searchToggle {
                padding: 8px;
                margin-left: 5px;
            }
            
            /* Navbar items mobile alignment */
            .navbar-nav {
                flex-direction: row;
                align-items: center;
            }
            
            .nav-item.dropdown {
                margin: 0 5px;
            }
            
            /* Dropdown menu positioning for mobile */
            .dropdown-menu {
                position: absolute;
                right: 0;
                left: auto;
                min-width: 280px;
            }
            
            /* Mobile search form */
            .search-form-mobile {
                padding: 10px;
                background: #f8f9fa;
                border-top: 1px solid #dee2e6;
                display: none;
            }
            
            .search-form-mobile.show {
                display: block;
            }
            
            /* Badge counter mobile adjustment */
            .badge-counter {
                position: absolute;
                top: -5px;
                right: -5px;
                font-size: 0.7rem;
                padding: 2px 5px;
            }
            
            /* Profile image mobile adjustment */
            .nav-link img {
                width: 25px !important;
                height: 25px !important;
            }
            
            /* Sidebar mobile behavior */
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1040;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            /* Overlay for mobile sidebar */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1039;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            /* Main content adjustment for mobile */
            main {
                margin-left: 0 !important;
                padding: 70px 10px 20px 10px !important;
            }
            
            /* Dropdown list images mobile adjustment */
            .dropdown-list-image {
                min-width: 35px;
            }
            
            /* Notification and message dropdown items */
            .dropdown-item {
                white-space: normal;
                padding: 10px 15px;
            }
            
            /* Mobile specific spacing */
            .mobile-spacing {
                margin: 0 3px;
            }
        }
        
        @media (max-width: 576px) {
            /* Extra small devices */
            .navbar-nav .nav-link {
                padding: 8px 5px;
            }
            
            .dropdown-menu {
                min-width: 250px;
                font-size: 0.9rem;
            }
            
            /* Hide user name on very small screens */
            .nav-link span.d-none.d-lg-inline {
                display: none !important;
            }
        }
        
        /* Common mobile styles */
        .mobile-only {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-only {
                display: block;
            }
            
            .desktop-only {
                display: none;
            }
        }

        /* Additional mobile fixes */
        @media (max-width: 768px) {
            .topbar {
                padding: 0.5rem 0;
            }
            
            .sidebar {
                position: fixed;
                top: 56px;
                left: -280px;
                width: 280px;
                height: calc(100vh - 56px);
                background: #fff;
                transition: left 0.3s ease;
                z-index: 1000;
                overflow-y: auto;
                box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            }
            
            .sidebar.mobile-open {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="topbar navbar navbar-expand-lg navbar-light fixed-top bg-light shadow-sm">
        <div class="container-fluid">
            <!-- Menu Toggle for Mobile -->
            <button class="btn btn-link d-lg-none me-2 order-1" id="mobileMenuToggle" type="button" aria-label="Toggle navigation menu">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Brand Logo -->
            <a class="navbar-brand me-0 me-lg-3 order-2 order-lg-1" href="../index.php">
                <img src="<?php echo $admin_assets_path; ?>/img/logo.jpg" alt="Phool Delivery" height="30" class="d-inline-block align-text-top">
            </a>
            
            <!-- Search Form for Desktop -->
            <form class="d-none d-lg-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0 order-lg-2 desktop-only">
                <div class="input-group">
                    <input class="form-control" type="text" placeholder="Search for..." aria-label="Search">
                    <button class="btn btn-primary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- Search Toggle for Mobile -->
            <button class="btn btn-link d-lg-none ms-auto order-3 mobile-only" id="searchToggle" type="button">
                <i class="fas fa-search"></i>
            </button>
            
            <!-- Navbar Items -->
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4 order-4 order-lg-3">
                <!-- Messages -->
                <li class="nav-item dropdown mobile-spacing">
                    <a class="nav-link dropdown-toggle" id="messagesDropdown" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false" style="position: relative;">
                        <i class="fas fa-envelope fa-fw"></i>
                        <?php if ($unread_message_count > 0): ?>
                        <span class="badge bg-danger badge-counter"><?php echo $unread_message_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="messagesDropdown">
                        <li><h6 class="dropdown-header">Order Messages</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $message): 
                                $type_class = 'text-primary';
                                switch ($message['type']) {
                                    case 'order': $type_class = 'text-info'; break;
                                    case 'system': $type_class = 'text-primary'; break;
                                    case 'promotion': $type_class = 'text-warning'; break;
                                }
                            ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="../messages.php">
                                    <div class="dropdown-list-image me-3">
                                        <div class="status-indicator bg-<?php echo $message['is_read'] ? 'secondary' : 'success'; ?>"></div>
                                    </div>
                                    <div class="font-weight-bold flex-grow-1">
                                        <div class="text-truncate <?php echo $type_class; ?>">
                                            <strong><?php echo htmlspecialchars($message['title']); ?></strong>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars(substr($message['message'], 0, 50)); ?>...
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo time_ago($message['created_at']); ?>
                                            <?php if ($message['customer_name']): ?>
                                            · From: <?php echo htmlspecialchars($message['customer_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <a class="dropdown-item text-center text-muted" href="#">
                                    No order messages available
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        
                        <li>
                            <a class="dropdown-item text-center small text-muted" href="../messages.php">
                                <i class="fas fa-eye me-1"></i> View all messages
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Notifications -->
                <li class="nav-item dropdown mobile-spacing">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false" style="position: relative;">
                        <i class="fas fa-bell fa-fw"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger badge-counter"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><h6 class="dropdown-header">Notifications Center</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): 
                                $type_class = '';
                                switch ($notification['type']) {
                                    case 'success': $type_class = 'text-success'; break;
                                    case 'warning': $type_class = 'text-warning'; break;
                                    case 'danger': $type_class = 'text-danger'; break;
                                    default: $type_class = 'text-primary';
                                }
                            ?>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <div class="<?php echo $type_class; ?>">
                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                        <span class="small text-muted">- <?php echo time_ago($notification['created_at']); ?></span>
                                    </div>
                                    <div class="text-muted"><?php echo htmlspecialchars($notification['message']); ?></div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <a class="dropdown-item text-center text-muted" href="#">
                                    No notifications available
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        
                        <li>
                            <a class="dropdown-item text-center small text-muted" href="#">
                                Show all notifications
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- User Profile -->
                <?php if ($is_admin_logged_in): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo $profile_picture_url; ?>" 
                             alt="Profile" class="rounded-circle me-1" style="width: 25px; height: 25px; object-fit: cover;">
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($admin_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                                Profile
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item mobile-spacing">
                    <a class="nav-link" href="../login.php">
                        <i class="fas fa-sign-in-alt fa-fw"></i>
                        Login
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Search Form for Mobile -->
        <div class="search-form-mobile container-fluid" id="mobileSearchForm">
            <div class="input-group">
                <input class="form-control" type="text" placeholder="Search for..." aria-label="Search">
                <button class="btn btn-primary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <?php if ($is_admin_logged_in): ?>
    <!-- Sidebar Navigation -->
    <nav id="sidebar" class="sidebar bg-light">
        <div class="position-sticky pt-3">
            <ul class="nav flex-column">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>" href="../index.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <!-- Call Orders Button -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'call_orders') ? 'active' : ''; ?>" href="../call_orders.php">
                        <i class="fas fa-phone-alt me-2"></i>
                        <span>Call Orders</span>
                    </a>
                </li>
                   
                <!-- Orders Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#ordersCollapse" role="button" aria-expanded="false" aria-controls="ordersCollapse">
                        <i class="fas fa-shopping-cart me-2"></i>
                        <span>Orders</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="ordersCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'orders') ? 'active' : ''; ?>" href="../orders.php"><i class="fas fa-list me-1"></i> All Orders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../orders.php?status=pending"><i class="fas fa-clock me-1"></i> Pending Orders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../orders.php?status=completed"><i class="fas fa-check-circle me-1"></i> Completed Orders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../orders.php?status=cancelled"><i class="fas fa-times-circle me-1"></i> Cancelled Orders</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Vendor Management Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#vendorCollapse" role="button" aria-expanded="false" aria-controls="vendorCollapse">
                        <i class="fas fa-store me-2"></i>
                        <span>Vendor Management</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="vendorCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'vendors') ? 'active' : ''; ?>" href="../vendors.php"><i class="fas fa-list me-1"></i> All Vendors</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../vendors/add.php"><i class="fas fa-plus-circle me-1"></i> Add Vendor</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo (strpos($current_page, 'vendor-product') !== false) ? 'active' : ''; ?>" href="../vendor-products/manage.php"><i class="fas fa-box me-1"></i> Vendor Products</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo (strpos($current_page, 'vendor-order') !== false) ? 'active' : ''; ?>" href="../vendor-orders/view.php"><i class="fas fa-shopping-bag me-1"></i> Vendor Orders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo (strpos($current_page, 'vendor-payout') !== false) ? 'active' : ''; ?>" href="../vendor-payouts/view.php"><i class="fas fa-money-bill me-1"></i> Vendor Payouts</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo (strpos($current_page, 'vendor-review') !== false) ? 'active' : ''; ?>" href="../vendor-reviews/view.php"><i class="fas fa-star me-1"></i> Vendor Reviews</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo (strpos($current_page, 'vendor-analytic') !== false) ? 'active' : ''; ?>" href="../vendor-analytics/dashboard.php"><i class="fas fa-chart-line me-1"></i> Vendor Analytics</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Delivery Riders Management Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#ridersCollapse" role="button" aria-expanded="false" aria-controls="ridersCollapse">
                        <i class="fas fa-motorcycle me-2"></i>
                        <span>Riders</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="ridersCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'riders') ? 'active' : ''; ?>" href="../riders.php"><i class="fas fa-list me-1"></i> All Riders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'riders-penalties') ? 'active' : ''; ?>" href="../riders-penalties.php"><i class="fas fa-exclamation-triangle me-1"></i> Penalties & Approvals</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../riders.php?status=pending"><i class="fas fa-clock me-1"></i> Pending Riders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../riders.php?status=active"><i class="fas fa-check-circle me-1"></i> Active Riders</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../riders/add.php"><i class="fas fa-user-plus me-1"></i> Add Rider</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../riders.php?show=deleted"><i class="fas fa-trash-alt me-1"></i> Deleted Riders</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Sales Button -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'sales') ? 'active' : ''; ?>" href="../sales.php">
                        <i class="fas fa-shopping-cart me-2"></i>
                        <span>Sales</span>
                    </a>
                </li>
                
                <!-- Messages -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'messages') ? 'active' : ''; ?>" href="../messages.php">
                        <i class="fas fa-envelope me-2"></i>
                        <span>Messages</span>
                        <?php if ($unread_message_count > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $unread_message_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
              
                
                <!-- Expenses Dropdown -->
<li class="nav-item">
    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#expensesCollapse" role="button" aria-expanded="false" aria-controls="expensesCollapse">
        <i class="fas fa-money-bill-wave me-2"></i>
        <span>Expenses</span>
        <i class="fas fa-chevron-down float-end mt-1"></i>
    </a>
    <div class="collapse" id="expensesCollapse">
        <ul class="nav flex-column ms-4">
            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'expenses/index') ? 'active' : ''; ?>" href="../expenses/index.php"><i class="fas fa-list me-1"></i> All Expenses</a></li>
            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'expenses/add') ? 'active' : ''; ?>" href="../expenses/add.php"><i class="fas fa-plus-circle me-1"></i> Add Expense</a></li>
            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'expenses/reports') ? 'active' : ''; ?>" href="../expenses/reports.php"><i class="fas fa-chart-bar me-1"></i> Reports</a></li>
        </ul>
    </div>
</li>
                <!-- Products Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#productsCollapse" role="button" aria-expanded="false" aria-controls="productsCollapse">
                        <i class="fas fa-box me-2"></i>
                        <span>Products</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="productsCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'products') ? 'active' : ''; ?>" href="../products.php"><i class="fas fa-list me-1"></i> All Products</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../products.php?action=add"><i class="fas fa-plus-circle me-1"></i> Add Product</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../products.php?action=categories"><i class="fas fa-tags me-1"></i> Categories</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'carousel') ? 'active' : ''; ?>" href="../products/carousel.php"><i class="fas fa-images me-1"></i> Carousel Images</a></li>
                        </ul>
                    </div>
                </li>
                
                <!-- Product Availability -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_product_availability') ? 'active' : ''; ?>" 
                       href="../address/manage_product_availability.php">
                        <i class="fas fa-toggle-on me-2"></i>
                        <span>Product Availability</span>
                    </a>
                </li>
                
                <!-- Customers Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#customersCollapse" role="button" aria-expanded="false" aria-controls="customersCollapse">
                        <i class="fas fa-users me-2"></i>
                        <span>Customers</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="customersCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'customers') ? 'active' : ''; ?>" href="../customers.php"><i class="fas fa-list me-1"></i> All Customers</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'customer-verification') ? 'active' : ''; ?>" href="../customer-verification.php"><i class="fas fa-user-check me-1"></i> Verification Center</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../customers.php?action=add"><i class="fas fa-user-plus me-1"></i> Add Customer</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../customers.php?type=verified"><i class="fas fa-user-shield me-1"></i> Verified Customers</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../customers.php?type=pending"><i class="fas fa-clock me-1"></i> Pending Verification</a></li>
                            <li class="nav-item"><a class="nav-link text-muted" href="../customers.php?type=rejected"><i class="fas fa-user-times me-1"></i> Rejected Verification</a></li>
                        </ul>
                    </div>
                </li>
                
                <!-- Transactions -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'transactions') ? 'active' : ''; ?>" href="../transactions.php">
                        <i class="fas fa-exchange-alt me-2"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'pay') ? 'active' : ''; ?>" href="../pay.php">
                        <i class="fas fa-credit-card me-2"></i>
                        <span>Pay</span>
                    </a>
                </li>
                
                <!-- Events & Offers -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'events_offers') ? 'active' : ''; ?>" href="../events_offers.php">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span>Events & Offers</span>
                    </a>
                </li>
                
                <!-- Address Fee -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'address_fee') ? 'active' : ''; ?>" href="../address_fee.php">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <span>Address Fee</span>
                    </a>
                </li>

                <!-- Media -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'media') ? 'active' : ''; ?>" href="../media.php">
                        <i class="fas fa-photo-video me-2"></i>
                        <span>Media</span>
                    </a>
                </li>
                
                <!-- Notices -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'notices') ? 'active' : ''; ?>" href="../notices.php">
                        <i class="fas fa-bell me-2"></i>
                        <span>Notices</span>
                    </a>
                </li>

                <!-- Ads -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'ads') ? 'active' : ''; ?>" href="../ads.php">
                        <i class="fas fa-bullhorn me-2"></i>
                        <span>Ads</span>
                    </a>
                </li>
                
                <!-- Nepali Calendar -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'nepali_calendar') ? 'active' : ''; ?>" href="../nepali_calendar.php">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span>Nepali Calendar</span>
                    </a>
                </li>
                
                <!-- Reports -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reports') ? 'active' : ''; ?>" href="../reports.php">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span>Reports</span>
                    </a>
                </li>
                
                <!-- Settings Dropdown -->
                <li class="nav-item">
                    <a class="nav-link collapsed" data-bs-toggle="collapse" href="#settingsCollapse" role="button" aria-expanded="false" aria-controls="settingsCollapse">
                        <i class="fas fa-cog me-2"></i>
                        <span>Settings</span>
                        <i class="fas fa-chevron-down float-end mt-1"></i>
                    </a>
                    <div class="collapse" id="settingsCollapse">
                        <ul class="nav flex-column ms-4">
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'users') ? 'active' : ''; ?>" href="../users.php"><i class="fas fa-user-shield me-1"></i> Admin Users</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'settings') ? 'active' : ''; ?>" href="../settings.php"><i class="fas fa-cogs me-1"></i> General Settings</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'email-status') ? 'active' : ''; ?>" href="../email-status.php"><i class="fas fa-envelope me-1"></i> Email Status</a></li>
                            <li class="nav-item"><a class="nav-link text-muted <?php echo ($current_page == 'check-config') ? 'active' : ''; ?>" href="../check-config.php"><i class="fas fa-wrench me-1"></i> System Configuration</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" style="margin-top: 70px;">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile responsiveness JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            });
            
            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
        
        // Mobile search toggle
        const searchToggle = document.getElementById('searchToggle');
        const mobileSearchForm = document.getElementById('mobileSearchForm');
        
        if (searchToggle && mobileSearchForm) {
            searchToggle.addEventListener('click', function() {
                mobileSearchForm.classList.toggle('show');
            });
        }
        
        // Close dropdowns when clicking outside (for mobile)
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(function(dropdown) {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Handle window resize
        function handleResize() {
            if (window.innerWidth > 768) {
                // Reset mobile states on desktop
                if (sidebar) {
                    sidebar.classList.remove('mobile-open');
                }
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                if (mobileSearchForm) {
                    mobileSearchForm.classList.remove('show');
                }
                document.body.style.overflow = '';
            }
        }
        
        window.addEventListener('resize', handleResize);
        
        // Close sidebar when clicking on a link (mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        });
    });
    </script>