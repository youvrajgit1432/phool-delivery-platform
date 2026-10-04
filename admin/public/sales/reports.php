<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales_summary';

// Validate date range (max 1 year)
if (!empty($date_from) && !empty($date_to)) {
    $start = new DateTime($date_from);
    $end = new DateTime($date_to);
    $interval = $start->diff($end);
    if ($interval->days > 365) {
        $date_to = $start->modify('+365 days')->format('Y-m-d');
    }
}

// Get sales summary report
if ($report_type === 'sales_summary') {
    $sales_summary = $pdo->prepare("
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as total_sales,
            SUM(payment_received) as total_payments,
            SUM(payment_pending) as total_pending,
            AVG(rate_per_kg) as avg_rate,
            MAX(total_amount) as max_sale,
            MIN(total_amount) as min_sale
        FROM flower_deliveries 
        WHERE delivery_date BETWEEN ? AND ?
    ");
    $sales_summary->execute([$date_from, $date_to]);
    $sales_summary = $sales_summary->fetch();

    // Daily sales trend
    $daily_trend = $pdo->prepare("
        SELECT 
            delivery_date,
            COUNT(*) as delivery_count,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as daily_sales,
            SUM(payment_received) as daily_payments
        FROM flower_deliveries 
        WHERE delivery_date BETWEEN ? AND ?
        GROUP BY delivery_date 
        ORDER BY delivery_date
    ");
    $daily_trend->execute([$date_from, $date_to]);
    $daily_trend = $daily_trend->fetchAll();

    // Top buyers
    $top_buyers = $pdo->prepare("
        SELECT 
            b.buyer_name,
            b.buyer_type,
            COUNT(fd.delivery_id) as delivery_count,
            SUM(fd.quantity_kg) as total_kg,
            SUM(fd.total_amount) as total_purchases,
            SUM(fd.payment_received) as total_payments,
            SUM(fd.payment_pending) as total_pending
        FROM flower_deliveries fd
        JOIN buyers b ON fd.buyer_id = b.buyer_id
        WHERE fd.delivery_date BETWEEN ? AND ?
        GROUP BY fd.buyer_id 
        ORDER BY total_purchases DESC 
        LIMIT 10
    ");
    $top_buyers->execute([$date_from, $date_to]);
    $top_buyers = $top_buyers->fetchAll();

    // Flower type analysis
    $flower_analysis = $pdo->prepare("
        SELECT 
            flower_type,
            COUNT(*) as delivery_count,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as total_sales,
            AVG(rate_per_kg) as avg_rate
        FROM flower_deliveries 
        WHERE delivery_date BETWEEN ? AND ?
        GROUP BY flower_type 
        ORDER BY total_sales DESC
    ");
    $flower_analysis->execute([$date_from, $date_to]);
    $flower_analysis = $flower_analysis->fetchAll();
}

// Get payment reports
if ($report_type === 'payment_analysis') {
    $payment_summary = $pdo->prepare("
        SELECT 
            payment_mode,
            COUNT(*) as payment_count,
            SUM(amount_paid) as total_amount,
            AVG(amount_paid) as avg_amount
        FROM buyer_payments 
        WHERE payment_date BETWEEN ? AND ?
        GROUP BY payment_mode 
        ORDER BY total_amount DESC
    ");
    $payment_summary->execute([$date_from, $date_to]);
    $payment_summary = $payment_summary->fetchAll();

    // Payment trends
    $payment_trends = $pdo->prepare("
        SELECT 
            payment_date,
            COUNT(*) as payment_count,
            SUM(amount_paid) as daily_payments
        FROM buyer_payments 
        WHERE payment_date BETWEEN ? AND ?
        GROUP BY payment_date 
        ORDER BY payment_date
    ");
    $payment_trends->execute([$date_from, $date_to]);
    $payment_trends = $payment_trends->fetchAll();
}

// Get buyer performance report
if ($report_type === 'buyer_performance') {
    $buyer_performance = $pdo->prepare("
        SELECT 
            b.buyer_id,
            b.buyer_name,
            b.buyer_type,
            b.contact_number1,
            COUNT(fd.delivery_id) as total_deliveries,
            SUM(fd.quantity_kg) as total_kg,
            SUM(fd.total_amount) as total_purchases,
            SUM(fd.payment_received) as total_payments,
            SUM(fd.payment_pending) as total_pending,
            AVG(fd.rate_per_kg) as avg_rate,
            MAX(fd.delivery_date) as last_delivery,
            (SUM(fd.payment_received) / SUM(fd.total_amount)) * 100 as payment_percentage
        FROM buyers b
        LEFT JOIN flower_deliveries fd ON b.buyer_id = fd.buyer_id 
            AND fd.delivery_date BETWEEN ? AND ?
        WHERE b.status = 'active'
        GROUP BY b.buyer_id 
        ORDER BY total_purchases DESC
    ");
    $buyer_performance->execute([$date_from, $date_to]);
    $buyer_performance = $buyer_performance->fetchAll();
}

// Set page title
$page_title = "Sales Reports - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Sales Reports & Analytics</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
        </a>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Report Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type" required>
                        <option value="sales_summary" <?php echo $report_type == 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                        <option value="payment_analysis" <?php echo $report_type == 'payment_analysis' ? 'selected' : ''; ?>>Payment Analysis</option>
                        <option value="buyer_performance" <?php echo $report_type == 'buyer_performance' ? 'selected' : ''; ?>>Buyer Performance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-1"></i> Generate Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($report_type === 'sales_summary'): ?>
<!-- Sales Summary Report -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>Sales Summary Report
                    <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total Sales</h6>
                                <h4>Rs. <?php echo number_format($sales_summary['total_sales'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total KG</h6>
                                <h4><?php echo number_format($sales_summary['total_kg'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Avg Rate/KG</h6>
                                <h4>Rs. <?php echo number_format($sales_summary['avg_rate'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total Payments</h6>
                                <h4>Rs. <?php echo number_format($sales_summary['total_payments'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Pending Amount</h6>
                                <h4>Rs. <?php echo number_format($sales_summary['total_pending'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Payment %</h6>
                                <h4>
                                    <?php 
                                    $percentage = ($sales_summary['total_sales'] ?? 0) > 0 ? 
                                        (($sales_summary['total_payments'] ?? 0) / ($sales_summary['total_sales'] ?? 1)) * 100 : 0;
                                    echo number_format($percentage, 1); ?>%
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Daily Sales Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="salesTrendChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Flower Type Analysis</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="flowerAnalysisChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Buyers -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Top 10 Buyers</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Buyer</th>
                                        <th>Type</th>
                                        <th>Deliveries</th>
                                        <th>Total KG</th>
                                        <th>Total Purchases</th>
                                        <th>Payments</th>
                                        <th>Pending</th>
                                        <th>Payment %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_buyers as $buyer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($buyer['buyer_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($buyer['buyer_type']) {
                                                    case 'small': echo 'secondary'; break;
                                                    case 'medium': echo 'primary'; break;
                                                    case 'large': echo 'success'; break;
                                                }
                                            ?>">
                                                <?php echo ucfirst($buyer['buyer_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $buyer['delivery_count']; ?></td>
                                        <td><?php echo number_format($buyer['total_kg'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($buyer['total_purchases'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($buyer['total_payments'], 2); ?></td>
                                        <td>
                                            <?php if ($buyer['total_pending'] > 0): ?>
                                            <span class="text-warning">Rs. <?php echo number_format($buyer['total_pending'], 2); ?></span>
                                            <?php else: ?>
                                            <span class="text-success">Rs. <?php echo number_format($buyer['total_pending'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $percentage = $buyer['total_purchases'] > 0 ? 
                                                ($buyer['total_payments'] / $buyer['total_purchases']) * 100 : 0;
                                            ?>
                                            <span class="badge bg-<?php echo $percentage >= 90 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'payment_analysis'): ?>
<!-- Payment Analysis Report -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Payment Analysis Report
                    <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <!-- Payment Mode Breakdown -->
                <div class="row mb-4">
                    <?php foreach ($payment_summary as $payment): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title text-<?php 
                                    switch($payment['payment_mode']) {
                                        case 'cash': echo 'primary'; break;
                                        case 'bank': echo 'info'; break;
                                        case 'esewa': echo 'success'; break;
                                        case 'khalti': echo 'warning'; break;
                                        case 'connectips': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($payment['payment_mode']); ?>
                                </h6>
                                <h4>Rs. <?php echo number_format($payment['total_amount'], 2); ?></h4>
                                <p class="text-muted mb-1">
                                    <?php echo $payment['payment_count']; ?> payments
                                </p>
                                <p class="text-muted mb-0">
                                    Avg: Rs. <?php echo number_format($payment['avg_amount'], 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Payment Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Payment Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentTrendsChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'buyer_performance'): ?>
<!-- Buyer Performance Report -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>Buyer Performance Report
                    <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="buyerPerformanceTable">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Deliveries</th>
                                <th>Total KG</th>
                                <th>Total Purchases</th>
                                <th>Payments</th>
                                <th>Pending</th>
                                <th>Avg Rate</th>
                                <th>Payment %</th>
                                <th>Last Delivery</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buyer_performance as $buyer): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($buyer['buyer_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($buyer['buyer_type']) {
                                            case 'small': echo 'secondary'; break;
                                            case 'medium': echo 'primary'; break;
                                            case 'large': echo 'success'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($buyer['buyer_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($buyer['contact_number1']); ?></td>
                                <td><?php echo $buyer['total_deliveries']; ?></td>
                                <td><?php echo number_format($buyer['total_kg'], 2); ?></td>
                                <td>Rs. <?php echo number_format($buyer['total_purchases'], 2); ?></td>
                                <td>Rs. <?php echo number_format($buyer['total_payments'], 2); ?></td>
                                <td>
                                    <?php if ($buyer['total_pending'] > 0): ?>
                                    <span class="text-warning">Rs. <?php echo number_format($buyer['total_pending'], 2); ?></span>
                                    <?php else: ?>
                                    <span class="text-success">Rs. <?php echo number_format($buyer['total_pending'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>Rs. <?php echo number_format($buyer['avg_rate'], 2); ?></td>
                                <td>
                                    <?php 
                                    $percentage = $buyer['payment_percentage'] ?? 0;
                                    ?>
                                    <span class="badge bg-<?php echo $percentage >= 90 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($buyer['last_delivery'])): ?>
                                    <?php echo date('M d, Y', strtotime($buyer['last_delivery'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">No deliveries</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Export function
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_report.php?' + params.toString(), '_blank');
}

<?php if ($report_type === 'sales_summary'): ?>
// Sales Trend Chart
const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
const salesTrendChart = new Chart(salesTrendCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M d', strtotime($item['delivery_date'])) . "'"; }, $daily_trend)); ?>],
        datasets: [
            {
                label: 'Daily Sales',
                data: [<?php echo implode(',', array_column($daily_trend, 'daily_sales')); ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Daily Payments',
                data: [<?php echo implode(',', array_column($daily_trend, 'daily_payments')); ?>],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Sales & Payments Trend'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rs. ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Flower Analysis Chart
const flowerAnalysisCtx = document.getElementById('flowerAnalysisChart').getContext('2d');
const flowerAnalysisChart = new Chart(flowerAnalysisCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['flower_type'] . "'"; }, $flower_analysis)); ?>],
        datasets: [{
            label: 'Total Sales (Rs.)',
            data: [<?php echo implode(',', array_column($flower_analysis, 'total_sales')); ?>],
            backgroundColor: [
                '#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Sales by Flower Type'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rs. ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

<?php elseif ($report_type === 'payment_analysis'): ?>
// Payment Trends Chart
const paymentTrendsCtx = document.getElementById('paymentTrendsChart').getContext('2d');
const paymentTrendsChart = new Chart(paymentTrendsCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M d', strtotime($item['payment_date'])) . "'"; }, $payment_trends)); ?>],
        datasets: [{
            label: 'Daily Payments',
            data: [<?php echo implode(',', array_column($payment_trends, 'daily_payments')); ?>],
            backgroundColor: '#28a745'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Daily Payment Trends'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rs. ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if ($report_type === 'buyer_performance'): ?>
// Initialize DataTables for buyer performance
$(document).ready(function() {
    $('#buyerPerformanceTable').DataTable({
        "pageLength": 25,
        "order": [[5, 'desc']] // Sort by total purchases descending
    });
});
<?php endif; ?>
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>