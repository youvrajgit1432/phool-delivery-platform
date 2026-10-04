<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$buyer_filter = isset($_GET['buyer']) ? intval($_GET['buyer']) : '';
$days_filter = isset($_GET['days']) ? intval($_GET['days']) : '';

// Get all buyers for filter
$buyers = $pdo->query("SELECT buyer_id, buyer_name FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll();

// Build WHERE clause for pending payments
$where_conditions = ["fd.payment_status IN ('pending', 'partial')"];
$params = [];

if (!empty($buyer_filter)) {
    $where_conditions[] = "fd.buyer_id = ?";
    $params[] = $buyer_filter;
}

if (!empty($days_filter)) {
    $where_conditions[] = "fd.delivery_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $params[] = $days_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get pending payments
$pending_payments = $pdo->prepare("
    SELECT 
        fd.*, 
        b.buyer_name, 
        b.contact_number1, 
        b.contact_number2,
        DATEDIFF(CURDATE(), fd.delivery_date) as days_pending
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    $where_clause 
    ORDER BY fd.payment_pending DESC, days_pending DESC
");
$pending_payments->execute($params);
$pending_payments = $pending_payments->fetchAll();

// Calculate summary
$summary = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pending,
        SUM(fd.payment_pending) as total_amount,
        AVG(DATEDIFF(CURDATE(), fd.delivery_date)) as avg_days_pending,
        MAX(DATEDIFF(CURDATE(), fd.delivery_date)) as max_days_pending
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    $where_clause
");
$summary->execute($params);
$summary = $summary->fetch();

// Set page title
$page_title = "Pending Payments - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Pending Payments</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
        </a>
        <a href="payments.php" class="btn btn-sm btn-info ms-2">
            <i class="fas fa-history me-1"></i> Payment History
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Pending Records</h6>
                <h4><?php echo $summary['total_pending'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Total Pending Amount</h6>
                <h4>Rs. <?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Avg Days Pending</h6>
                <h4><?php echo number_format($summary['avg_days_pending'] ?? 0, 1); ?> days</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center p-3">
                <h6 class="card-title mb-1">Max Days Pending</h6>
                <h4><?php echo $summary['max_days_pending'] ?? 0; ?> days</h4>
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
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <label for="days_filter" class="form-label">Older Than (Days)</label>
                    <select class="form-select" id="days_filter" name="days">
                        <option value="">All Pending</option>
                        <option value="7" <?php echo $days_filter == 7 ? 'selected' : ''; ?>>7+ Days</option>
                        <option value="15" <?php echo $days_filter == 15 ? 'selected' : ''; ?>>15+ Days</option>
                        <option value="30" <?php echo $days_filter == 30 ? 'selected' : ''; ?>>30+ Days</option>
                        <option value="60" <?php echo $days_filter == 60 ? 'selected' : ''; ?>>60+ Days</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Apply Filters
                        </button>
                        <a href="pending_payments.php" class="btn btn-secondary">Clear Filters</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Pending Payments Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="pendingPaymentsTable">
                <thead>
                    <tr>
                        <th>Delivery Date</th>
                        <th>Buyer</th>
                        <th>Contact</th>
                        <th>Flower Type</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Pending Amount</th>
                        <th>Days Pending</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($payment['delivery_date'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['buyer_name']); ?></strong>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($payment['contact_number1']); ?></div>
                            <?php if (!empty($payment['contact_number2'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($payment['contact_number2']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($payment['flower_type']); ?></td>
                        <td><?php echo number_format($payment['quantity_kg'], 2); ?> kg</td>
                        <td>Rs. <?php echo number_format($payment['total_amount'], 2); ?></td>
                        <td>Rs. <?php echo number_format($payment['payment_received'], 2); ?></td>
                        <td>
                            <strong class="text-danger">Rs. <?php echo number_format($payment['payment_pending'], 2); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                if ($payment['days_pending'] >= 60) echo 'danger';
                                elseif ($payment['days_pending'] >= 30) echo 'warning';
                                else echo 'info';
                            ?>">
                                <?php echo $payment['days_pending']; ?> days
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $payment['payment_status'] == 'partial' ? 'warning' : 'danger'; ?>">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="add_payment.php?delivery_id=<?php echo $payment['delivery_id']; ?>" 
                                   class="btn btn-success" title="Add Payment">
                                    <i class="fas fa-money-bill"></i>
                                </a>
                                <a href="view_buyer.php?id=<?php echo $payment['buyer_id']; ?>" 
                                   class="btn btn-info" title="View Buyer">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_delivery.php?id=<?php echo $payment['delivery_id']; ?>" 
                                   class="btn btn-primary" title="Edit Delivery">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($pending_payments) === 0): ?>
        <div class="text-center py-4">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h5 class="text-success">No pending payments found!</h5>
            <p class="text-muted">All payments are up to date.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Actions -->
<?php if (count($pending_payments) > 0): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-bolt me-2"></i>Quick Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="reports.php?report_type=sales_summary&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" 
               class="btn btn-outline-primary">
                <i class="fas fa-chart-bar me-1"></i> View Sales Report
            </a>
            <a href="export_pending.php?<?php echo http_build_query($_GET); ?>" 
               class="btn btn-outline-success">
                <i class="fas fa-file-excel me-1"></i> Export to Excel
            </a>
            <button type="button" class="btn btn-outline-warning" onclick="sendReminders()">
                <i class="fas fa-bell me-1"></i> Send Payment Reminders
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Initialize DataTables
$(document).ready(function() {
    $('#pendingPaymentsTable').DataTable({
        "pageLength": 25,
        "order": [[7, 'desc']] // Sort by pending amount descending
    });
});

// Send payment reminders (placeholder function)
function sendReminders() {
    if (confirm('Are you sure you want to send payment reminders to all buyers with pending payments?')) {
        // This would typically make an AJAX call to a backend script
        alert('Payment reminder functionality would be implemented here. This could send SMS or email reminders to buyers.');
        // Example implementation:
        // fetch('send_reminders.php', { method: 'POST' })
        // .then(response => response.json())
        // .then(data => {
        //     alert('Reminders sent successfully to ' + data.sent_count + ' buyers.');
        // });
    }
}
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>