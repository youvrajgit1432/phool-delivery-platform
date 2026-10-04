<?php
/**
 * Dashboard - Vendor Panel Main Page
 * Fetch real data from database for the logged-in vendor.
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);

$vendorId = $_SESSION['vendor_id'] ?? null;

$totalProducts = 0;
$totalOrders = 0;
$totalRevenue = 0.00;
$pendingPayouts = 0.00;
$recentOrders = [];
$topProducts = [];

if ($vendorId) {
    // Total products - count active linked products from vendor_product_map
    $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_product_map WHERE vendor_id = ? AND unlinked_at IS NULL', [$vendorId]);
    $row = $stmt->fetch();
    $totalProducts = $row['cnt'] ?? 0;

    // Total orders (this month)
    $stmt = $db->query(
        'SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND MONTH(assigned_at) = MONTH(CURRENT_DATE()) AND YEAR(assigned_at) = YEAR(CURRENT_DATE())',
        [$vendorId]
    );
    $row = $stmt->fetch();
    $totalOrders = $row['cnt'] ?? 0;

    // Total revenue (this month, delivered) - Calculate as subtotal - discount
    $stmt = $db->query(
        "SELECT COALESCE(SUM(subtotal - discount),0) as total FROM vendor_orders WHERE vendor_id = ? AND MONTH(assigned_at) = MONTH(CURRENT_DATE()) AND YEAR(assigned_at) = YEAR(CURRENT_DATE()) AND status = 'delivered'",
        [$vendorId]
    );
    $row = $stmt->fetch();
    $totalRevenue = $row['total'] ?? 0.00;

    // Pending payouts
    $stmt = $db->query('SELECT COALESCE(SUM(payout_amount),0) as total FROM vendor_payouts WHERE vendor_id = ? AND status = ?', [$vendorId, 'pending']);
    $row = $stmt->fetch();
    $pendingPayouts = $row['total'] ?? 0.00;

    // Recent orders (customer details are not exposed to vendors)
    $stmt = $db->query('SELECT order_number, subtotal, discount, status FROM vendor_orders WHERE vendor_id = ? ORDER BY assigned_at DESC LIMIT 5', [$vendorId]);
    $recentOrders = $stmt->fetchAll();

    // Top performing products (by quantity sold)
    $sql = "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.item_total) as revenue
            FROM vendor_order_items oi
            JOIN vendor_orders vo ON oi.vendor_order_id = vo.id
            WHERE vo.vendor_id = ?
            GROUP BY oi.product_id, oi.product_name
            ORDER BY qty_sold DESC
            LIMIT 3";
    $stmt = $db->query($sql, [$vendorId]);
    $topProducts = $stmt->fetchAll();
}

?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div>
        <h1 class="mb-2">Overview</h1>
        <p class="text-muted mb-0">Welcome back! Here's your business overview</p>
    </div>
    <div>
        <a href="<?php echo htmlspecialchars(vendor_url('/products/add')); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Product
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="dashboard-stats stats-compact">
    <!-- Total Products Card -->
    <div class="stat-card primary">
        <div class="stat-card-content">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div>
                <p class="stat-label">Total Products</p>
                <h4 class="stat-number"><?php echo (int)$totalProducts; ?></h4>
                <small class="stat-description">Active</small>
            </div>
        </div>
    </div>
    
    <!-- Total Orders Card -->
    <div class="stat-card success">
        <div class="stat-card-content">
            <div class="stat-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <p class="stat-label">Total Orders</p>
                <h4 class="stat-number"><?php echo (int)$totalOrders; ?></h4>
                <small class="stat-description">This month</small>
            </div>
        </div>
    </div>
    
    <!-- Total Revenue Card -->
    <div class="stat-card warning">
        <div class="stat-card-content">
            <div class="stat-icon">
                <i class="fas fa-rupee-sign"></i>
            </div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <h4 class="stat-number">₹<?php echo number_format((float)$totalRevenue, 0); ?></h4>
                <small class="stat-description">This month</small>
            </div>
        </div>
    </div>
    
    <!-- Pending Payouts Card -->
    <div class="stat-card danger">
        <div class="stat-card-content">
            <div class="stat-icon">
                <i class="fas fa-money-bill"></i>
            </div>
            <div>
                <p class="stat-label">Pending</p>
                <h4 class="stat-number">₹<?php echo number_format((float)$pendingPayouts, 0); ?></h4>
                <small class="stat-description">Payouts</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="dashboard-content">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Recent Orders
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $ord): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ord['order_number']); ?></td>
                                    <td>₹<?php 
                                        $orderAmount = ((float)$ord['subtotal'] - (float)$ord['discount']);
                                        echo number_format($orderAmount, 2); 
                                    ?></td>
                                    <td><span class="order-status <?php echo strtolower(htmlspecialchars($ord['status'])); ?>"><?php echo htmlspecialchars(ucfirst($ord['status'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No recent orders</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center">
                <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-sm btn-outline-primary">
                    View All Orders
                </a>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-star me-2"></i>Top Performing Products
            </h5>
        </div>
        <div class="card-body">
            <div class="product-list">
                <?php if (!empty($topProducts)): ?>
                    <?php foreach ($topProducts as $p): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom: 1px solid #e5e7eb;">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($p['product_name']); ?></h6>
                                <small class="text-muted"><?php echo (int)$p['qty_sold']; ?> sold</small>
                            </div>
                            <div class="text-end">
                                <p class="mb-0 fw-bold">₹<?php echo number_format((float)$p['revenue'], 2); ?></p>
                                <small class="text-success"><i class="fas fa-arrow-up me-1"></i></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted">No top products data</div>
                <?php endif; ?>
            </div>
            <div class="mt-3 text-center">
                <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-sm btn-outline-primary">
                    Manage Products
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Additional Info Row -->
<div class="row mt-4">
    <!-- Quick Actions -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightning-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="<?php echo htmlspecialchars(vendor_url('/products/add')); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-receipt me-2"></i>View Orders
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo htmlspecialchars(vendor_url('/availability')); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-clock me-2"></i>Set Availability
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo htmlspecialchars(vendor_url('/payouts')); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-download me-2"></i>Request Payout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Chart -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Revenue Trend
                </h5>
            </div>
            <div class="card-body">
                <div id="chartContainer" style="height: 250px; background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <div class="text-center">
                        <p class="text-muted mb-0">Chart visualization coming soon</p>
                        <small class="text-muted">Integration with Chart.js recommended</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
