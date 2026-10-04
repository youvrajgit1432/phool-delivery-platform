<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get buyer ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid buyer ID.";
    header("Location: ../sales.php");
    exit;
}

$buyer_id = intval($_GET['id']);

// Get buyer details
$buyer = $pdo->prepare("SELECT * FROM buyers WHERE buyer_id = ?");
$buyer->execute([$buyer_id]);
$buyer = $buyer->fetch();

if (!$buyer) {
    $_SESSION['error_message'] = "Buyer not found.";
    header("Location: ../sales.php");
    exit;
}

// Get buyer's delivery history
$deliveries = $pdo->prepare("
    SELECT * FROM flower_deliveries 
    WHERE buyer_id = ? 
    ORDER BY delivery_date DESC, created_at DESC
");
$deliveries->execute([$buyer_id]);
$deliveries = $deliveries->fetchAll();

// Calculate buyer statistics
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(quantity_kg) as total_kg,
        SUM(total_amount) as total_purchases,
        SUM(payment_received) as total_payments,
        SUM(payment_pending) as total_pending
    FROM flower_deliveries 
    WHERE buyer_id = ?
");
$stats->execute([$buyer_id]);
$stats = $stats->fetch();

// Set page title
$page_title = "View Buyer - " . htmlspecialchars($buyer['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Buyer Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../sales.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Sales
            </a>
            <a href="edit_buyer.php?id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit me-1"></i> Edit Buyer
            </a>
            <a href="add_delivery.php?buyer_id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-truck me-1"></i> Add Delivery
            </a>
            <a href="add_payment.php?buyer_id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-warning">
                <i class="fas fa-money-bill me-1"></i> Add Payment
            </a>
            <a href="buyer_reports.php?buyer_id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-info">
                <i class="fas fa-chart-bar me-1"></i> Buyer Report
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Buyer Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Buyer Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($buyer['profile_picture'])): ?>
                <div class="text-center mb-3">
                    <img src="../../storage/uploads/buyers/<?php echo htmlspecialchars($buyer['profile_picture']); ?>" 
                         alt="Profile Picture" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                </div>
                <?php endif; ?>
                
                <table class="table table-sm">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($buyer['buyer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Type:</th>
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
                    </tr>
                    <tr>
                        <th>Primary Contact:</th>
                        <td><?php echo htmlspecialchars($buyer['contact_number1']); ?></td>
                    </tr>
                    <?php if (!empty($buyer['contact_number2'])): ?>
                    <tr>
                        <th>Secondary Contact:</th>
                        <td><?php echo htmlspecialchars($buyer['contact_number2']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($buyer['email'])): ?>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($buyer['email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Special Rate:</th>
                        <td>
                            <?php if (!empty($buyer['special_rate'])): ?>
                                Rs. <?php echo number_format($buyer['special_rate'], 2); ?>/kg
                            <?php else: ?>
                                <span class="text-muted">Standard Rate</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php echo $buyer['status'] == 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($buyer['status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>Address Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($buyer['original_address'])): ?>
                <h6>Original Address:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($buyer['original_address'])); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($buyer['current_address'])): ?>
                <h6>Current Address:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($buyer['current_address'])); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($buyer['business_address'])): ?>
                <h6>Business/Delivery Address:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($buyer['business_address'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Delivery History and Statistics -->
    <div class="col-md-8">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h6>Total Deliveries</h6>
                        <h3><?php echo $stats['total_deliveries'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6>Total KG</h6>
                        <h3><?php echo number_format($stats['total_kg'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6>Total Purchases</h6>
                        <h3>Rs. <?php echo number_format($stats['total_purchases'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-<?php echo ($stats['total_pending'] ?? 0) > 0 ? 'warning' : 'secondary'; ?> text-white">
                    <div class="card-body text-center">
                        <h6>Pending Amount</h6>
                        <h3>Rs. <?php echo number_format($stats['total_pending'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delivery History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Delivery History
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($deliveries) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Flower Type</th>
                                <th>Quantity</th>
                                <th>Rate</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Pending</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $delivery): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                                <td><?php echo htmlspecialchars($delivery['flower_type']); ?></td>
                                <td><?php echo number_format($delivery['quantity_kg'], 2); ?> kg</td>
                                <td>Rs. <?php echo number_format($delivery['rate_per_kg'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['total_amount'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['payment_received'], 2); ?></td>
                                <td>Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></td>
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
                                        <!-- NEW: Invoice Button -->
                                        <a href="generate_invoice.php?id=<?php echo $delivery['delivery_id']; ?>" 
                                           class="btn btn-info" title="Generate Invoice">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <!-- NEW: Delete Button -->
                                        <a href="delete_delivery.php?id=<?php echo $delivery['delivery_id']; ?>" 
                                           class="btn btn-danger" title="Delete Delivery">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No delivery records found for this buyer.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Payment Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="paymentChart" width="400" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th>Total Purchases:</th>
                                <td class="text-end">Rs. <?php echo number_format($stats['total_purchases'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Total Payments:</th>
                                <td class="text-end">Rs. <?php echo number_format($stats['total_payments'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Pending Amount:</th>
                                <td class="text-end">
                                    <strong class="text-<?php echo ($stats['total_pending'] ?? 0) > 0 ? 'warning' : 'success'; ?>">
                                        Rs. <?php echo number_format($stats['total_pending'] ?? 0, 2); ?>
                                    </strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Percentage:</th>
                                <td class="text-end">
                                    <?php 
                                    $percentage = ($stats['total_purchases'] ?? 0) > 0 ? 
                                        (($stats['total_payments'] ?? 0) / ($stats['total_purchases'] ?? 1)) * 100 : 0;
                                    echo number_format($percentage, 1); ?>%
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for Payment Summary -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Payment Chart
const paymentCtx = document.getElementById('paymentChart').getContext('2d');
const paymentChart = new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: ['Paid Amount', 'Pending Amount'],
        datasets: [{
            data: [
                <?php echo $stats['total_payments'] ?? 0; ?>,
                <?php echo $stats['total_pending'] ?? 0; ?>
            ],
            backgroundColor: [
                '#28a745',
                '#ffc107'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
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
include '../../app/views/layouts/footer.php';
?>