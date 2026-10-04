<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get buyer ID from URL
if (!isset($_GET['buyer_id']) || !is_numeric($_GET['buyer_id'])) {
    $_SESSION['error_message'] = "Invalid buyer ID.";
    header("Location: ../sales.php");
    exit;
}

$buyer_id = intval($_GET['buyer_id']);

// Get buyer details
$buyer = $pdo->prepare("SELECT * FROM buyers WHERE buyer_id = ?");
$buyer->execute([$buyer_id]);
$buyer = $buyer->fetch();

if (!$buyer) {
    $_SESSION['error_message'] = "Buyer not found.";
    header("Location: ../sales.php");
    exit;
}

// Handle date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'delivery_summary';

// Validate date range (max 1 year)
if (!empty($date_from) && !empty($date_to)) {
    $start = new DateTime($date_from);
    $end = new DateTime($date_to);
    $interval = $start->diff($end);
    if ($interval->days > 365) {
        $date_to = $start->modify('+365 days')->format('Y-m-d');
    }
}

// Get buyer's delivery summary
if ($report_type === 'delivery_summary') {
    $delivery_summary = $pdo->prepare("
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as total_purchases,
            SUM(payment_received) as total_payments,
            SUM(payment_pending) as total_pending,
            AVG(rate_per_kg) as avg_rate,
            MAX(total_amount) as max_purchase,
            MIN(total_amount) as min_purchase
        FROM flower_deliveries 
        WHERE buyer_id = ? AND delivery_date BETWEEN ? AND ?
    ");
    $delivery_summary->execute([$buyer_id, $date_from, $date_to]);
    $delivery_summary = $delivery_summary->fetch();

    // Daily delivery trend
    $daily_trend = $pdo->prepare("
        SELECT 
            delivery_date,
            COUNT(*) as delivery_count,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as daily_purchases,
            SUM(payment_received) as daily_payments
        FROM flower_deliveries 
        WHERE buyer_id = ? AND delivery_date BETWEEN ? AND ?
        GROUP BY delivery_date 
        ORDER BY delivery_date
    ");
    $daily_trend->execute([$buyer_id, $date_from, $date_to]);
    $daily_trend = $daily_trend->fetchAll();

    // Flower type analysis for this buyer
    $flower_analysis = $pdo->prepare("
        SELECT 
            flower_type,
            COUNT(*) as delivery_count,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as total_purchases,
            AVG(rate_per_kg) as avg_rate
        FROM flower_deliveries 
        WHERE buyer_id = ? AND delivery_date BETWEEN ? AND ?
        GROUP BY flower_type 
        ORDER BY total_purchases DESC
    ");
    $flower_analysis->execute([$buyer_id, $date_from, $date_to]);
    $flower_analysis = $flower_analysis->fetchAll();
}

// Get buyer's payment analysis
if ($report_type === 'payment_analysis') {
    $payment_summary = $pdo->prepare("
        SELECT 
            payment_mode,
            COUNT(*) as payment_count,
            SUM(amount_paid) as total_amount,
            AVG(amount_paid) as avg_amount
        FROM buyer_payments 
        WHERE buyer_id = ? AND payment_date BETWEEN ? AND ?
        GROUP BY payment_mode 
        ORDER BY total_amount DESC
    ");
    $payment_summary->execute([$buyer_id, $date_from, $date_to]);
    $payment_summary = $payment_summary->fetchAll();

    // Payment trends for this buyer
    $payment_trends = $pdo->prepare("
        SELECT 
            payment_date,
            COUNT(*) as payment_count,
            SUM(amount_paid) as daily_payments
        FROM buyer_payments 
        WHERE buyer_id = ? AND payment_date BETWEEN ? AND ?
        GROUP BY payment_date 
        ORDER BY payment_date
    ");
    $payment_trends->execute([$buyer_id, $date_from, $date_to]);
    $payment_trends = $payment_trends->fetchAll();
}

// Get detailed delivery history
if ($report_type === 'delivery_history') {
    $delivery_history = $pdo->prepare("
        SELECT 
            delivery_id,
            delivery_date,
            flower_type,
            quantity_kg,
            rate_per_kg,
            total_amount,
            payment_received,
            payment_pending,
            payment_status,
            created_at
        FROM flower_deliveries 
        WHERE buyer_id = ? AND delivery_date BETWEEN ? AND ?
        ORDER BY delivery_date DESC, created_at DESC
    ");
    $delivery_history->execute([$buyer_id, $date_from, $date_to]);
    $delivery_history = $delivery_history->fetchAll();
}

// Set page title
$page_title = "Buyer Reports - " . htmlspecialchars($buyer['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>Buyer Reports
        <small class="text-muted">- <?php echo htmlspecialchars($buyer['buyer_name']); ?></small>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view_buyer.php?id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Buyer
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
            <input type="hidden" name="buyer_id" value="<?php echo $buyer_id; ?>">
            <div class="row">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type" required>
                        <option value="delivery_summary" <?php echo $report_type == 'delivery_summary' ? 'selected' : ''; ?>>Delivery Summary</option>
                        <option value="payment_analysis" <?php echo $report_type == 'payment_analysis' ? 'selected' : ''; ?>>Payment Analysis</option>
                        <option value="delivery_history" <?php echo $report_type == 'delivery_history' ? 'selected' : ''; ?>>Detailed History</option>
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
                        <button type="button" class="btn btn-success" onclick="exportBuyerReport()">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($report_type === 'delivery_summary'): ?>
<!-- Delivery Summary Report -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-truck me-2"></i>Delivery Summary Report
                    <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total Deliveries</h6>
                                <h4><?php echo $delivery_summary['total_deliveries'] ?? 0; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total KG</h6>
                                <h4><?php echo number_format($delivery_summary['total_kg'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total Purchases</h6>
                                <h4>Rs. <?php echo number_format($delivery_summary['total_purchases'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Total Payments</h6>
                                <h4>Rs. <?php echo number_format($delivery_summary['total_payments'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Pending Amount</h6>
                                <h4>Rs. <?php echo number_format($delivery_summary['total_pending'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title mb-1">Avg Rate/KG</h6>
                                <h4>Rs. <?php echo number_format($delivery_summary['avg_rate'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Delivery Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="deliveryTrendChart" height="250"></canvas>
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

                <!-- Payment Status -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Payment Status Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h5 class="text-success">Rs. <?php echo number_format($delivery_summary['total_payments'] ?? 0, 2); ?></h5>
                                        <p class="mb-0">Total Paid</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h5 class="text-warning">Rs. <?php echo number_format($delivery_summary['total_pending'] ?? 0, 2); ?></h5>
                                        <p class="mb-0">Total Pending</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h5 class="text-info">
                                            <?php 
                                            $percentage = ($delivery_summary['total_purchases'] ?? 0) > 0 ? 
                                                (($delivery_summary['total_payments'] ?? 0) / ($delivery_summary['total_purchases'] ?? 1)) * 100 : 0;
                                            echo number_format($percentage, 1); ?>%
                                        </h5>
                                        <p class="mb-0">Payment Percentage</p>
                                    </div>
                                </div>
                            </div>
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
                    <?php if (count($payment_summary) > 0): ?>
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
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-muted text-center">No payment records found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Trends Chart -->
                <?php if (count($payment_trends) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Payment Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentTrendsChart" height="300"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'delivery_history'): ?>
<!-- Detailed Delivery History -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Detailed Delivery History
                    <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($delivery_history) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="deliveryHistoryTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Flower Type</th>
                                <th>Quantity (KG)</th>
                                <th>Rate/KG</th>
                                <th>Total Amount</th>
                                <th>Paid Amount</th>
                                <th>Pending Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($delivery_history as $delivery): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                                <td><?php echo htmlspecialchars($delivery['flower_type']); ?></td>
                                <td><?php echo number_format($delivery['quantity_kg'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['rate_per_kg'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['total_amount'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['payment_received'], 2); ?></td>
                                <td>
                                    <?php if ($delivery['payment_pending'] > 0): ?>
                                    <span class="text-warning">Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></span>
                                    <?php else: ?>
                                    <span class="text-success">Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($delivery['payment_status']) {
                                            case 'paid': echo 'success'; break;
                                            case 'partial': echo 'warning'; break;
                                            case 'pending': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($delivery['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_delivery.php?id=<?php echo $delivery['delivery_id']; ?>" 
                                           class="btn btn-primary" title="Edit Delivery">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="add_payment.php?delivery_id=<?php echo $delivery['delivery_id']; ?>" 
                                           class="btn btn-success" title="Add Payment">
                                            <i class="fas fa-money-bill"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No delivery records found for this period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<script>
// Export function for buyer reports
function exportBuyerReport() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_buyer_report.php?' + params.toString(), '_blank');
}

<?php if ($report_type === 'delivery_summary'): ?>
// Delivery Trend Chart
const deliveryTrendCtx = document.getElementById('deliveryTrendChart').getContext('2d');
const deliveryTrendChart = new Chart(deliveryTrendCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M d', strtotime($item['delivery_date'])) . "'"; }, $daily_trend)); ?>],
        datasets: [
            {
                label: 'Daily Purchases',
                data: [<?php echo implode(',', array_column($daily_trend, 'daily_purchases')); ?>],
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
                text: 'Purchase & Payment Trend'
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
            label: 'Total Purchases (Rs.)',
            data: [<?php echo implode(',', array_column($flower_analysis, 'total_purchases')); ?>],
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
                text: 'Purchases by Flower Type'
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

<?php elseif ($report_type === 'payment_analysis' && count($payment_trends) > 0): ?>
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

<?php if ($report_type === 'delivery_history'): ?>
// Initialize DataTables for delivery history
$(document).ready(function() {
    $('#deliveryHistoryTable').DataTable({
        "pageLength": 25,
        "order": [[0, 'desc']] // Sort by date descending
    });
});
<?php endif; ?>
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>