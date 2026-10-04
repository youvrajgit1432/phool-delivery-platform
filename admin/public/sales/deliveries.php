<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$buyer_filter = isset($_GET['buyer']) ? intval($_GET['buyer']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get all buyers for filter
$buyers = $pdo->query("SELECT buyer_id, buyer_name FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll();

// Build WHERE clause for deliveries
$where_conditions = [];
$params = [];

if (!empty($buyer_filter)) {
    $where_conditions[] = "fd.buyer_id = ?";
    $params[] = $buyer_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "fd.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "fd.delivery_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "fd.delivery_date <= ?";
    $params[] = $date_to;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get deliveries with filters
$deliveries = $pdo->prepare("
    SELECT fd.*, b.buyer_name, b.contact_number1 
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    $where_clause 
    ORDER BY fd.delivery_date DESC, fd.created_at DESC
");
$deliveries->execute($params);
$deliveries = $deliveries->fetchAll();

// Calculate summary statistics
$summary = $pdo->prepare("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(fd.quantity_kg) as total_kg,
        SUM(fd.total_amount) as total_sales,
        SUM(fd.payment_received) as total_payments,
        SUM(fd.payment_pending) as total_pending
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    $where_clause
");
$summary->execute($params);
$summary = $summary->fetch();

// Set page title
$page_title = "All Deliveries - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">All Deliveries</h1>
  <div class="btn-toolbar mb-2 mb-md-0">
    <a href="../sales.php" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Sales
    </a>
    <a href="add_delivery.php" class="btn btn-sm btn-primary ms-2">
        <i class="fas fa-plus me-1"></i> Add Delivery
    </a>
    <!-- ADD THESE NEW LINKS -->
    <a href="pending_payments.php" class="btn btn-sm btn-warning ms-2">
        <i class="fas fa-clock me-1"></i> Pending Payments
    </a>
    <a href="reports.php" class="btn btn-sm btn-info ms-2">
        <i class="fas fa-chart-bar me-1"></i> Reports
    </a>
</div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Deliveries</h6>
                <h4><?php echo $summary['total_deliveries'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total KG</h6>
                <h4><?php echo number_format($summary['total_kg'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Sales</h6>
                <h4>Rs. <?php echo number_format($summary['total_sales'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Payments</h6>
                <h4>Rs. <?php echo number_format($summary['total_payments'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Pending Amount</h6>
                <h4>Rs. <?php echo number_format($summary['total_pending'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Payment %</h6>
                <h4>
                    <?php 
                    $percentage = ($summary['total_sales'] ?? 0) > 0 ? 
                        (($summary['total_payments'] ?? 0) / ($summary['total_sales'] ?? 1)) * 100 : 0;
                    echo number_format($percentage, 1); ?>%
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label for="buyer_filter" class="form-label">Buyer</label>
                    <select class="form-select" id="buyer_filter" name="buyer">
                        <option value="">All Buyers</option>
                        <?php foreach ($buyers as $buyer): ?>
                        <option value="<?php echo $buyer['buyer_id']; ?>" 
                                <?php echo $buyer_filter == $buyer['buyer_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($buyer['buyer_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Payment Status</label>
                    <select class="form-select" id="status_filter" name="status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo $status_filter == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Apply Filters
                    </button>
                    <a href="deliveries.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Deliveries Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="deliveriesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Contact</th>
                        <th>Flower Type</th>
                        <th>Quantity (KG)</th>
                        <th>Rate (Rs.)</th>
                        <th>Total (Rs.)</th>
                        <th>Paid (Rs.)</th>
                        <th>Pending (Rs.)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($delivery['buyer_name']); ?></strong>
                            <br>
                            <small class="text-muted">ID: <?php echo $delivery['buyer_id']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($delivery['contact_number1']); ?></td>
                        <td><?php echo htmlspecialchars($delivery['flower_type']); ?></td>
                        <td><?php echo number_format($delivery['quantity_kg'], 2); ?></td>
                        <td>Rs. <?php echo number_format($delivery['rate_per_kg'], 2); ?></td>
                        <td><strong>Rs. <?php echo number_format($delivery['total_amount'], 2); ?></strong></td>
                        <td>Rs. <?php echo number_format($delivery['payment_received'], 2); ?></td>
                        <td>
                            <?php if ($delivery['payment_pending'] > 0): ?>
                            <span class="text-warning"><strong>Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></strong></span>
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
                                <a href="delete_delivery.php?id=<?php echo $delivery['delivery_id']; ?>" 
                                   class="btn btn-danger" title="Delete Delivery"
                                   onclick="return confirm('Are you sure you want to delete this delivery record? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($deliveries) === 0): ?>
        <div class="text-center py-4">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No delivery records found</h5>
            <p class="text-muted">Try adjusting your filters or add a new delivery record.</p>
            <a href="add_delivery.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Delivery
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Export Options -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-download me-2"></i>Export Data
        </h5>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="export_deliveries.php?<?php echo http_build_query($_GET); ?>&format=csv" 
               class="btn btn-outline-primary">
                <i class="fas fa-file-csv me-1"></i> Export as CSV
            </a>
            <a href="export_deliveries.php?<?php echo http_build_query($_GET); ?>&format=excel" 
               class="btn btn-outline-success">
                <i class="fas fa-file-excel me-1"></i> Export as Excel
            </a>
            <a href="export_deliveries.php?<?php echo http_build_query($_GET); ?>&format=pdf" 
               class="btn btn-outline-danger">
                <i class="fas fa-file-pdf me-1"></i> Export as PDF
            </a>
        </div>
    </div>
</div>

<script>
// Initialize DataTables if needed
$(document).ready(function() {
    $('#deliveriesTable').DataTable({
        "pageLength": 25,
        "order": [[0, 'desc']]
    });
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>