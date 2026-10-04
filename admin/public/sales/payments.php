<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$buyer_filter = isset($_GET['buyer']) ? intval($_GET['buyer']) : '';
$mode_filter = isset($_GET['mode']) ? $_GET['mode'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get all buyers for filter
$buyers = $pdo->query("SELECT buyer_id, buyer_name FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll();

// Build WHERE clause for payments
$where_conditions = [];
$params = [];

if (!empty($buyer_filter)) {
    $where_conditions[] = "bp.buyer_id = ?";
    $params[] = $buyer_filter;
}

if (!empty($mode_filter)) {
    $where_conditions[] = "bp.payment_mode = ?";
    $params[] = $mode_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "bp.payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "bp.payment_date <= ?";
    $params[] = $date_to;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get payments with filters
$payments = $pdo->prepare("
    SELECT bp.*, b.buyer_name, b.contact_number1, fd.delivery_date, fd.flower_type, fd.total_amount 
    FROM buyer_payments bp 
    JOIN buyers b ON bp.buyer_id = b.buyer_id 
    JOIN flower_deliveries fd ON bp.delivery_id = fd.delivery_id 
    $where_clause 
    ORDER BY bp.payment_date DESC, bp.created_at DESC
");
$payments->execute($params);
$payments = $payments->fetchAll();

// Calculate summary statistics
$summary = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(bp.amount_paid) as total_amount,
        SUM(CASE WHEN bp.payment_mode = 'cash' THEN bp.amount_paid ELSE 0 END) as cash_amount,
        SUM(CASE WHEN bp.payment_mode = 'bank' THEN bp.amount_paid ELSE 0 END) as bank_amount,
        SUM(CASE WHEN bp.payment_mode = 'esewa' THEN bp.amount_paid ELSE 0 END) as esewa_amount,
        SUM(CASE WHEN bp.payment_mode = 'khalti' THEN bp.amount_paid ELSE 0 END) as khalti_amount,
        SUM(CASE WHEN bp.payment_mode = 'connectips' THEN bp.amount_paid ELSE 0 END) as connectips_amount,
        SUM(CASE WHEN bp.payment_mode = 'other' THEN bp.amount_paid ELSE 0 END) as other_amount
    FROM buyer_payments bp 
    JOIN buyers b ON bp.buyer_id = b.buyer_id 
    $where_clause
");
$summary->execute($params);
$summary = $summary->fetch();

// Set page title
$page_title = "Payment History - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Payment History</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
        </a>
        <a href="add_payment.php" class="btn btn-sm btn-success ms-2">
            <i class="fas fa-plus me-1"></i> Add Payment
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Payments</h6>
                <h4><?php echo $summary['total_payments'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Amount</h6>
                <h4>Rs. <?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Cash Payments</h6>
                <h4>Rs. <?php echo number_format($summary['cash_amount'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Digital Payments</h6>
                <h4>Rs. <?php echo number_format(($summary['bank_amount'] + $summary['esewa_amount'] + $summary['khalti_amount'] + $summary['connectips_amount'] + $summary['other_amount']) ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Payment Mode Breakdown -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Payment Mode Breakdown
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-primary">Cash</h6>
                            <h4>Rs. <?php echo number_format($summary['cash_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['cash_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-info">Bank</h6>
                            <h4>Rs. <?php echo number_format($summary['bank_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['bank_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-success">eSewa</h6>
                            <h4>Rs. <?php echo number_format($summary['esewa_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['esewa_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-warning">Khalti</h6>
                            <h4>Rs. <?php echo number_format($summary['khalti_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['khalti_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-danger">ConnectIPS</h6>
                            <h4>Rs. <?php echo number_format($summary['connectips_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['connectips_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="border rounded p-3">
                            <h6 class="text-secondary">Other</h6>
                            <h4>Rs. <?php echo number_format($summary['other_amount'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                <?php echo $summary['total_amount'] > 0 ? number_format(($summary['other_amount'] / $summary['total_amount']) * 100, 1) : 0; ?>%
                            </small>
                        </div>
                    </div>
                </div>
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
                    <label for="mode_filter" class="form-label">Payment Mode</label>
                    <select class="form-select" id="mode_filter" name="mode">
                        <option value="">All Modes</option>
                        <option value="cash" <?php echo $mode_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="bank" <?php echo $mode_filter == 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="esewa" <?php echo $mode_filter == 'esewa' ? 'selected' : ''; ?>>eSewa</option>
                        <option value="khalti" <?php echo $mode_filter == 'khalti' ? 'selected' : ''; ?>>Khalti</option>
                        <option value="connectips" <?php echo $mode_filter == 'connectips' ? 'selected' : ''; ?>>ConnectIPS</option>
                        <option value="other" <?php echo $mode_filter == 'other' ? 'selected' : ''; ?>>Other</option>
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
                    <a href="payments.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="paymentsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Delivery Date</th>
                        <th>Flower Type</th>
                        <th>Delivery Total</th>
                        <th>Amount Paid</th>
                        <th>Payment Mode</th>
                        <th>Reference</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['buyer_name']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($payment['contact_number1']); ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($payment['delivery_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['flower_type']); ?></td>
                        <td>Rs. <?php echo number_format($payment['total_amount'], 2); ?></td>
                        <td><strong class="text-success">Rs. <?php echo number_format($payment['amount_paid'], 2); ?></strong></td>
                        <td>
                            <span class="badge bg-<?php 
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
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($payment['transaction_reference'])): ?>
                            <code><?php echo htmlspecialchars($payment['transaction_reference']); ?></code>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($payment['remarks'])): ?>
                            <span title="<?php echo htmlspecialchars($payment['remarks']); ?>">
                                <?php echo htmlspecialchars(substr($payment['remarks'], 0, 30)); ?>
                                <?php echo strlen($payment['remarks']) > 30 ? '...' : ''; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_buyer.php?id=<?php echo $payment['buyer_id']; ?>" 
                                   class="btn btn-info" title="View Buyer">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_delivery.php?id=<?php echo $payment['delivery_id']; ?>" 
                                   class="btn btn-primary" title="View Delivery">
                                    <i class="fas fa-truck"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($payments) === 0): ?>
        <div class="text-center py-4">
            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No payment records found</h5>
            <p class="text-muted">Try adjusting your filters or record a new payment.</p>
            <a href="add_payment.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i> Record Payment
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize DataTables
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        "pageLength": 25,
        "order": [[0, 'desc']]
    });
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>