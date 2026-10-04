<?php
// public/index.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Debug session
error_log("Index page - Session: " . print_r($_SESSION, true));

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();
initDatabase($pdo);

// Get filter parameters for orders
$orders_payment_filter = isset($_GET['orders_payment_status']) ? $_GET['orders_payment_status'] : 'pending';
$orders_date_filter = isset($_GET['orders_date_filter']) ? $_GET['orders_date_filter'] : '';
$orders_sort_order = isset($_GET['orders_sort_order']) ? $_GET['orders_sort_order'] : 'new_first';

// Build the recent orders query with filters
$orders_query = "
    SELECT o.*, c.name as customer_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE 1=1
";

// Apply payment status filter
if (!empty($orders_payment_filter) && $orders_payment_filter != 'all') {
    $orders_query .= " AND o.payment_status = :orders_payment_status";
}

// Apply date filter
if (!empty($orders_date_filter)) {
    if ($orders_date_filter == 'today') {
        $orders_query .= " AND DATE(o.created_at) = CURDATE()";
    } elseif ($orders_date_filter == 'yesterday') {
        $orders_query .= " AND DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($orders_date_filter == 'this_week') {
        $orders_query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($orders_date_filter == 'last_week') {
        $orders_query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
    } elseif ($orders_date_filter == 'this_month') {
        $orders_query .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    } elseif ($orders_date_filter == 'last_month') {
        $orders_query .= " AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                   AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    }
}

// Apply sorting
if ($orders_sort_order == 'old_first') {
    $orders_query .= " ORDER BY o.created_at ASC";
} else {
    $orders_query .= " ORDER BY o.created_at DESC";
}

// Limit to 10 orders for dashboard
$orders_query .= " LIMIT 10";

// Get buyer statistics
$buyer_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_buyers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_buyers,
        SUM(CASE WHEN buyer_type = 'small' THEN 1 ELSE 0 END) as small_buyers,
        SUM(CASE WHEN buyer_type = 'medium' THEN 1 ELSE 0 END) as medium_buyers,
        SUM(CASE WHEN buyer_type = 'large' THEN 1 ELSE 0 END) as large_buyers
    FROM buyers
")->fetch();

// Get call order statistics
$call_order_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_call_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_call_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_call_orders,
        SUM(total_amount) as call_order_revenue
    FROM call_customer_orders
")->fetch();

// Get today's sales stats
$today_stats = $pdo->query("
    SELECT 
        COUNT(*) as today_orders,
        SUM(total_amount) as today_revenue,
        (SELECT COUNT(*) FROM flower_deliveries WHERE DATE(delivery_date) = CURDATE()) as today_deliveries,
        (SELECT SUM(total_amount) FROM flower_deliveries WHERE DATE(delivery_date) = CURDATE()) as today_sales
    FROM orders 
    WHERE DATE(created_at) = CURDATE()
")->fetch();

// Get pending payments
$pending_payments = $pdo->query("
    SELECT SUM(payment_pending) as total_pending 
    FROM flower_deliveries 
    WHERE payment_status IN ('pending', 'partial')
")->fetch();

// Prepare and execute the orders query
try {
    $orders_stmt = $pdo->prepare($orders_query);
    
    if (!empty($orders_payment_filter) && $orders_payment_filter != 'all') {
        $orders_stmt->bindParam(':orders_payment_status', $orders_payment_filter);
    }
    
    $orders_stmt->execute();
    $recent_orders = $orders_stmt->fetchAll();

    // Get dashboard statistics
    $users_count = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    $customers_count = $pdo->query("SELECT COUNT(*) as count FROM customers")->fetch()['count'];
    $products_count = $pdo->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
    $orders_count = $pdo->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
    
    // Get invoice statistics
    $customer_invoices_count = $pdo->query("SELECT COUNT(*) as count FROM customer_invoices")->fetch()['count'];
    $paid_invoices_count = $pdo->query("SELECT COUNT(*) as count FROM customer_invoices WHERE status = 'paid'")->fetch()['count'];

    // Get popular products - modified to handle multilingual product names
    $popular_products = $pdo->query("
        SELECT p.name_en as name, p.price, COUNT(oi.id) as order_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id
        ORDER BY order_count DESC
        LIMIT 5
    ")->fetchAll();

    // Get recent buyers
    $recent_buyers = $pdo->query("
        SELECT * FROM buyers 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Get recent call orders
    $recent_call_orders = $pdo->query("
        SELECT co.*, cc.name as customer_name, p.name_en as product_name
        FROM call_customer_orders co
        LEFT JOIN call_customers cc ON co.call_customer_id = cc.id
        LEFT JOIN products p ON co.product_id = p.id
        ORDER BY co.order_date DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    // Set default values
    $users_count = $customers_count = $products_count = $orders_count = 0;
    $customer_invoices_count = $paid_invoices_count = 0;
    $recent_orders = $popular_products = $recent_buyers = $recent_call_orders = [];
    $buyer_stats = $call_order_stats = $today_stats = $pending_payments = [];
}

// Set page title
$page_title = "Dashboard - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<style>
/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .btn-toolbar {
        margin-top: 15px;
        width: 100%;
        justify-content: flex-start;
    }
    
    .card-body .row.no-gutters {
        flex-direction: column;
        text-align: center;
    }
    
    .card-body .col-auto {
        margin-top: 10px;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem;
        white-space: nowrap;
    }
    
    .btn-group-sm > .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.775rem;
    }
    
    .card-header.d-flex {
        flex-direction: column;
        gap: 10px;
    }
    
    .card-header .btn {
        align-self: flex-start;
    }
    
    .d-grid.gap-2 .btn {
        font-size: 0.9rem;
        padding: 0.75rem;
    }
    
    .list-group-item {
        padding: 0.75rem 0.5rem;
    }
    
    .h2 {
        font-size: 1.5rem;
    }
    
    .h5 {
        font-size: 1.1rem;
    }
    
    /* Stats cards mobile optimization */
    .col-xl-2.col-md-4.col-sm-6.mb-4 {
        padding-left: 8px;
        padding-right: 8px;
    }
    
    .card .h5 {
        font-size: 1rem;
    }
    
    .text-xs {
        font-size: 0.7rem !important;
    }
    
    /* Filter form mobile optimization */
    #ordersFilterCollapse .card-body {
        padding: 1rem;
    }
    
    #ordersFilterCollapse .row.g-3 {
        margin: 0;
    }
    
    #ordersFilterCollapse .col-md-4 {
        margin-bottom: 1rem;
    }
    
    /* Table actions mobile optimization */
    .table td:last-child {
        position: sticky;
        right: 0;
        background: white;
        border-left: 1px solid #dee2e6;
    }
}

@media (max-width: 576px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .card {
        margin-bottom: 1rem;
    }
    
    .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 2px;
    }
    
    .btn-group .btn {
        flex: 1;
        min-width: auto;
    }
    
    /* Hide less important columns on very small screens */
    .table th:nth-child(4),
    .table td:nth-child(4) {
        display: none;
    }
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
    .btn {
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-group-sm > .btn {
        min-height: 36px;
    }
    
    .table td {
        padding: 0.75rem 0.5rem;
    }
    
    /* Increase tap targets */
    .list-group-item {
        min-height: 44px;
        display: flex;
        align-items: center;
    }
}

/* Ensure proper scrolling on mobile */
.table-responsive {
    -webkit-overflow-scrolling: touch;
}

/* Mobile-first responsive grid for stats */
.row.mb-4 {
    margin-left: -5px;
    margin-right: -5px;
}

.row.mb-4 > [class*="col-"] {
    padding-left: 5px;
    padding-right: 5px;
}

/* Custom card styles for different sections */
.card.buyer-card {
    border-left: 4px solid #007bff;
}

.card.call-order-card {
    border-left: 4px solid #28a745;
}

.card.sales-card {
    border-left: 4px solid #ffc107;
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard Overview</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
            <i class="fas fa-calendar me-1"></i>
            This week
        </button>
    </div>
</div>

<!-- Dashboard Stats -->
<div class="row mb-4">
    <!-- Total Users -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $users_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Customers -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $customers_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Products -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Products</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $products_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Orders -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $orders_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Buyers -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Total Buyers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $buyer_stats['total_buyers'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call Orders -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-left-dark shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                            Call Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $call_order_stats['total_call_orders'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-phone fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales and Revenue Stats -->
<div class="row mb-4">
    <!-- Today's Revenue -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Today's Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format(($today_stats['today_revenue'] ?? 0) + ($today_stats['today_sales'] ?? 0), 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call Order Revenue -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Call Order Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($call_order_stats['call_order_revenue'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-phone-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Payments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($pending_payments['total_pending'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Deliveries -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Today's Deliveries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $today_stats['today_deliveries'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column - Orders and Buyers -->
    <div class="col-lg-8">
        <!-- Recent Orders -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Orders</h5>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ordersFilterCollapse">
                    <i class="fas fa-filter"></i> Filters
                </button>
            </div>
            <div class="card-body">
                <!-- Orders Filter Section -->
                <div class="collapse mb-3" id="ordersFilterCollapse">
                    <div class="card card-body">
                        <form method="GET" class="row g-3" id="ordersFilterForm">
                            <div class="col-md-4">
                                <label for="orders_payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="orders_payment_status" name="orders_payment_status">
                                    <option value="all" <?php echo $orders_payment_filter == 'all' ? 'selected' : ''; ?>>All Payments</option>
                                    <option value="pending" <?php echo $orders_payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $orders_payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="failed" <?php echo $orders_payment_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="refunded" <?php echo $orders_payment_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="orders_date_filter" class="form-label">Date Filter</label>
                                <select class="form-select" id="orders_date_filter" name="orders_date_filter">
                                    <option value="">All Dates</option>
                                    <option value="today" <?php echo $orders_date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="yesterday" <?php echo $orders_date_filter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                    <option value="this_week" <?php echo $orders_date_filter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="last_week" <?php echo $orders_date_filter == 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                                    <option value="this_month" <?php echo $orders_date_filter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="last_month" <?php echo $orders_date_filter == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="orders_sort_order" class="form-label">Sort By Date</label>
                                <select class="form-select" id="orders_sort_order" name="orders_sort_order">
                                    <option value="new_first" <?php echo $orders_sort_order == 'new_first' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="old_first" <?php echo $orders_sort_order == 'old_first' ? 'selected' : ''; ?>>Oldest First</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="index.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th class="d-none d-md-table-cell">Status</th>
                                <th>Payment Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></td>
                                <td>Rs. <?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-<?php 
                                    switch($order['status'] ?? 'pending') {
                                        case 'pending': echo 'warning'; break;
                                        case 'confirmed': echo 'info'; break;
                                        case 'preparing': echo 'primary'; break;
                                        case 'out_for_delivery': echo 'secondary'; break;
                                        case 'delivered': echo 'success'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($order['payment_status'] ?? 'pending') {
                                        case 'pending': echo 'warning'; break;
                                        case 'paid': echo 'success'; break;
                                        case 'failed': echo 'danger'; break;
                                        case 'refunded': echo 'info'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Invoice Button for Customers -->
                                        <a href="orders/generate_customer_invoice.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-success" data-bs-toggle="tooltip" title="Generate Invoice" >
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($recent_orders) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center">No orders found with the selected filters.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                </div>
            </div>
        </div>

        <!-- Recent Buyers and Call Orders Row -->
        <div class="row">
            <!-- Recent Buyers -->
            <div class="col-md-6">
                <div class="card buyer-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Buyers</h5>
                        <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_buyers as $buyer): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($buyer['buyer_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($buyer['contact_number1']); ?></small>
                                </div>
                                <span class="badge bg-<?php 
                                    switch($buyer['buyer_type']) {
                                        case 'small': echo 'secondary'; break;
                                        case 'medium': echo 'primary'; break;
                                        case 'large': echo 'success'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($buyer['buyer_type']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($recent_buyers) === 0): ?>
                            <div class="list-group-item text-center text-muted">
                                No buyers found
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Call Orders -->
            <div class="col-md-6">
                <div class="card call-order-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Call Orders</h5>
                        <a href="call_orders.php" class="btn btn-sm btn-success">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_call_orders as $order): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($order['customer_name']); ?></h6>
                                    <small class="text-muted">Rs. <?php echo number_format($order['total_amount'], 2); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($order['product_name']); ?> (Qty: <?php echo $order['quantity']; ?>)</p>
                                <small class="text-muted">
                                    <span class="badge bg-<?php 
                                        switch($order['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'confirmed': echo 'primary'; break;
                                            case 'delivered': echo 'success'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    • <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($recent_call_orders) === 0): ?>
                            <div class="list-group-item text-center text-muted">
                                No call orders found
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">
        <!-- Popular Products -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Popular Products</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($popular_products as $product): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-truncate"><?php echo htmlspecialchars($product['name'] ?? 'Unknown Product'); ?></span>
                        <span class="badge bg-primary rounded-pill">
                            <?php echo $product['order_count'] ?? 0; ?> orders
                        </span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (count($popular_products) === 0): ?>
                    <li class="list-group-item text-center text-muted">
                        No product data available
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="orders.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Create New Order
                    </a>
                    <a href="call/add_call_order.php" class="btn btn-success">
                        <i class="fas fa-phone me-1"></i> Add Call Order
                    </a>
                    <a href="sales/add_buyer.php" class="btn btn-info">
                        <i class="fas fa-user-plus me-1"></i> Add New Buyer
                    </a>
                    <a href="products.php?action=add" class="btn btn-warning">
                        <i class="fas fa-box me-1"></i> Add New Product
                    </a>
                </div>
            </div>
        </div>

        <!-- Sales Reports Summary -->
        <div class="card sales-card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sales Reports Summary</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Total Buyers</span>
                        <strong><?php echo $buyer_stats['total_buyers'] ?? 0; ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Active Buyers</span>
                        <strong class="text-success"><?php echo $buyer_stats['active_buyers'] ?? 0; ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Call Orders</span>
                        <strong><?php echo $call_order_stats['total_call_orders'] ?? 0; ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Pending Call Orders</span>
                        <strong class="text-warning"><?php echo $call_order_stats['pending_call_orders'] ?? 0; ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Today's Revenue</span>
                        <strong class="text-primary">Rs. <?php echo number_format(($today_stats['today_revenue'] ?? 0) + ($today_stats['today_sales'] ?? 0), 2); ?></strong>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="sales.php" class="btn btn-sm btn-outline-primary me-2">Sales Management</a>
                    <a href="call_orders.php" class="btn btn-sm btn-outline-success">Call Orders</a>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">System Status</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Database Connection
                        <span class="badge bg-success rounded-pill">Connected</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Authentication
                        <span class="badge bg-success rounded-pill">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Sales System
                        <span class="badge bg-success rounded-pill">Ready</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Call Orders
                        <span class="badge bg-success rounded-pill">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Last Update
                        <span class="text-muted small"><?php echo date('M d, Y H:i:s'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filter values change
    const ordersFilterForm = document.getElementById('ordersFilterForm');
    if (ordersFilterForm) {
        const filterSelects = ordersFilterForm.querySelectorAll('select');
        
        // Store current filter values
        let currentFilters = {
            orders_payment_status: document.getElementById('orders_payment_status').value,
            orders_date_filter: document.getElementById('orders_date_filter').value,
            orders_sort_order: document.getElementById('orders_sort_order').value
        };
        
        // Add change event listeners to all filter selects
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Check if any filter has actually changed
                const newFilters = {
                    orders_payment_status: document.getElementById('orders_payment_status').value,
                    orders_date_filter: document.getElementById('orders_date_filter').value,
                    orders_sort_order: document.getElementById('orders_sort_order').value
                };
                
                // Compare with current filters
                const filtersChanged = 
                    newFilters.orders_payment_status !== currentFilters.orders_payment_status ||
                    newFilters.orders_date_filter !== currentFilters.orders_date_filter ||
                    newFilters.orders_sort_order !== currentFilters.orders_sort_order;
                
                if (filtersChanged) {
                    // Update current filters
                    currentFilters = newFilters;
                    
                    // Show a notification that filters will be applied
                    const applyButton = ordersFilterForm.querySelector('button[type="submit"]');
                    applyButton.textContent = 'Apply Filters ✓';
                    applyButton.classList.add('btn-success');
                    
                    // Auto-submit the form after a brief delay
                    setTimeout(() => {
                        ordersFilterForm.submit();
                    }, 800);
                }
            });
        });
        
        // Reset button style when form is submitted
        ordersFilterForm.addEventListener('submit', function() {
            const applyButton = ordersFilterForm.querySelector('button[type="submit"]');
            applyButton.textContent = 'Applying...';
            applyButton.disabled = true;
        });
    }

    // Add some interactive features
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Mobile-specific enhancements
    if (window.innerWidth <= 768) {
        // Add touch-friendly enhancements
        document.querySelectorAll('.btn').forEach(btn => {
            btn.style.minHeight = '44px';
            btn.style.display = 'flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
        });
    }
});
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>