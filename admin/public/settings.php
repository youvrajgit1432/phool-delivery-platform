<?php
// public/analytics.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get analytics data
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'month';

// Determine date range based on timeframe
switch ($timeframe) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-1 month'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-1 month'));
}

$end_date = date('Y-m-d');

// Sales data for chart
$sales_data = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as order_count,
        SUM(total_amount) as total_sales
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$sales_data->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$sales_data = $sales_data->fetchAll();

// Prepare data for chart
$chart_labels = [];
$chart_orders = [];
$chart_sales = [];

foreach ($sales_data as $data) {
    $chart_labels[] = date('M d', strtotime($data['date']));
    $chart_orders[] = $data['order_count'];
    $chart_sales[] = $data['total_sales'];
}

// Top products
$top_products = $pdo->prepare("
    SELECT 
        p.name,
        COUNT(oi.id) as units_sold,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY oi.product_id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$top_products->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_products = $top_products->fetchAll();

// Customer statistics
$customer_stats = $pdo->prepare("
    SELECT 
        customer_type,
        COUNT(*) as count,
        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_this_period
    FROM customers
    GROUP BY customer_type
");
$customer_stats->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$customer_stats = $customer_stats->fetchAll();

// Overall statistics
$overall_stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT SUM(total_amount) FROM orders) as total_sales,
        (SELECT COUNT(*) FROM customers) as total_customers,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE() - INTERVAL 30 DAY) as orders_30d,
        (SELECT SUM(total_amount) FROM orders WHERE created_at >= CURDATE() - INTERVAL 30 DAY) as sales_30d,
        (SELECT COUNT(*) FROM customers WHERE created_at >= CURDATE() - INTERVAL 30 DAY) as customers_30d
")->fetch();

// Set page title
$page_title = "Analytics - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Analytics Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <select class="form-select form-select-sm" id="timeframeFilter" onchange="window.location.href='analytics.php?timeframe='+this.value">
                <option value="week" <?php echo $timeframe == 'week' ? 'selected' : ''; ?>>Last Week</option>
                <option value="month" <?php echo $timeframe == 'month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="quarter" <?php echo $timeframe == 'quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                <option value="year" <?php echo $timeframe == 'year' ? 'selected' : ''; ?>>Last Year</option>
            </select>
        </div>
    </div>
</div>

<!-- Overall Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['total_orders']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($overall_stats['total_sales'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['total_customers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Products</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['total_products']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sales Chart -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sales Performance (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Customer Distribution -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="customerChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Products -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Top Selling Products</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Units Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['units_sold']; ?></td>
                                <td>Rs. <?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php
                    $recent_activity = $pdo->query("
                        SELECT 'order' as type, order_number as reference, created_at 
                        FROM orders 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ")->fetchAll();
                    
                    foreach ($recent_activity as $activity):
                    ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">New <?php echo ucfirst($activity['type']); ?></h6>
                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo ucfirst($activity['type']); ?> #<?php echo $activity['reference']; ?> was created.</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Orders',
                data: <?php echo json_encode($chart_orders); ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                yAxisID: 'y',
                fill: true
            },
            {
                label: 'Sales (Rs.)',
                data: <?php echo json_encode($chart_sales); ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.05)',
                yAxisID: 'y1',
                fill: true
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        scales: {
            x: {
                ticks: {
                    maxRotation: 0
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Orders'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales (Rs.)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Customer Chart
const customerCtx = document.getElementById('customerChart').getContext('2d');
const customerChart = new Chart(customerCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($customer_stats, 'customer_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($customer_stats, 'count')); ?>,
            backgroundColor: [
                '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
            ]
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>