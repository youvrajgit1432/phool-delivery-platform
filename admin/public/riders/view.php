<?php
/**
 * View Rider Details
 * Admin panel page to view detailed rider information
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'View Rider';
$current_page = 'riders';

$db = getDBConnection();

// Get rider ID
$rider_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($rider_id <= 0) {
    header('Location: ../riders.php');
    exit;
}

// Fetch rider with stats
$stmt = $db->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->execute([$rider_id]);
$rider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rider) {
    $_SESSION['error_message'] = 'Rider not found';
    header('Location: ../riders.php');
    exit;
}

// Get recent orders
$orders_stmt = $db->prepare("
    SELECT ro.*, o.order_number 
    FROM rider_orders ro
    LEFT JOIN orders o ON ro.order_id = o.id
    WHERE ro.rider_id = ?
    ORDER BY ro.assigned_at DESC
    LIMIT 10
");
$orders_stmt->execute([$rider_id]);
$recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending penalties
$penalties_stmt = $db->prepare("
    SELECT * FROM rider_penalties
    WHERE rider_id = ? AND status = 'pending'
    ORDER BY created_at DESC
");
$penalties_stmt->execute([$rider_id]);
$pending_penalties = $penalties_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent earnings
$earnings_stmt = $db->prepare("
    SELECT * FROM rider_earnings
    WHERE rider_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$earnings_stmt->execute([$rider_id]);
$recent_earnings = $earnings_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../app/views/layouts/hheader.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="../riders.php" class="btn btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Riders
            </a>
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="h3 mb-1"><?php echo htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']); ?></h1>
                    <small class="text-muted"><?php echo htmlspecialchars($rider['email']); ?> • <?php echo htmlspecialchars($rider['phone']); ?></small>
                </div>
                <div>
                    <a href="edit.php?id=<?php echo $rider['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Status</h6>
                    <h4><span class="badge bg-<?php 
                        echo $rider['status'] === 'active' ? 'success' : 
                             ($rider['status'] === 'pending' ? 'warning' : 
                              ($rider['status'] === 'suspended' ? 'danger' : 'secondary'));
                    ?>">
                        <?php echo ucfirst($rider['status']); ?>
                    </span></h4>
                    <?php if ($rider['is_available']): ?>
                        <small class="text-success"><i class="fas fa-circle"></i> Online Now</small>
                    <?php else: ?>
                        <small class="text-muted">Offline</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Rating</h6>
                    <h4><?php echo number_format($rider['average_rating'], 1); ?> ⭐</h4>
                    <small class="text-muted"><?php echo $rider['total_reviews']; ?> reviews</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Deliveries</h6>
                    <h4><?php echo $rider['total_deliveries']; ?></h4>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Earnings</h6>
                    <h4>Rs. <?php echo number_format($rider['total_earnings'], 0); ?></h4>
                    <small class="text-danger">Penalties: Rs. <?php echo number_format($rider['total_penalties'], 0); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Information -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">First Name</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['first_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Last Name</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['last_name']); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Email</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Phone</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['phone']); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Date of Birth</label>
                            <p><?php echo $rider['date_of_birth'] ? date('M d, Y', strtotime($rider['date_of_birth'])) : 'N/A'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Rider Type</label>
                            <p>
                                <span class="badge bg-<?php 
                                    echo $rider['rider_type'] === 'in_house' ? 'primary' : 
                                         ($rider['rider_type'] === 'gig' ? 'info' : 'success');
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $rider['rider_type'])); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Vehicle Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Vehicle Type</label>
                            <p class="mb-3"><?php echo ucfirst($rider['vehicle_type']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Vehicle Number</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['vehicle_number'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">City</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['city']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Postal Code</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['postal_code'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <label class="form-label text-muted">Home Address</label>
                    <p><?php echo htmlspecialchars($rider['home_address'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <!-- Documents -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>Documents</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Identification Type</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['identification_type'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">ID Number</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['identification_number'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">License Number</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['license_number'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">License Expiry</label>
                            <p class="mb-3"><?php echo $rider['license_expiry'] ? date('M d, Y', strtotime($rider['license_expiry'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-university me-2"></i>Bank Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Bank Name</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['bank_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Account Holder</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['account_holder_name'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Account Number</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['account_number'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">IFSC Code</label>
                            <p class="mb-3"><?php echo htmlspecialchars($rider['ifsc_code'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Recent Orders (Last 10)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($order['delivery_status']); ?></span></td>
                                    <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['assigned_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No orders yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Verification Status -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-check-double me-2"></i>Verification Status</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $rider['email_verified'] ? 'success' : 'danger'; ?> w-100 py-2">
                            <i class="fas fa-envelope me-1"></i> Email <?php echo $rider['email_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $rider['documents_verified'] ? 'success' : 'danger'; ?> w-100 py-2">
                            <i class="fas fa-file me-1"></i> Documents <?php echo $rider['documents_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-<?php echo $rider['bank_verified'] ? 'success' : 'danger'; ?> w-100 py-2">
                            <i class="fas fa-bank me-1"></i> Bank <?php echo $rider['bank_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted">On-Time Delivery Rate</label>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" style="width: <?php echo $rider['on_time_delivery_rate']; ?>%">
                                <?php echo number_format($rider['on_time_delivery_rate'], 1); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Cancellation Rate</label>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-warning" style="width: <?php echo $rider['cancellation_rate']; ?>%">
                                <?php echo number_format($rider['cancellation_rate'], 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Penalties -->
            <?php if (!empty($pending_penalties)): ?>
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pending Penalties</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($pending_penalties as $penalty): ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <p class="mb-1"><strong><?php echo htmlspecialchars($penalty['penalty_type']); ?></strong></p>
                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($penalty['reason'] ?? ''); ?></p>
                        <p class="mb-0"><span class="badge bg-danger">Rs. <?php echo number_format($penalty['amount'], 2); ?></span></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Account Information -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Account Information</h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>Registered:</strong> <?php echo date('M d, Y H:i', strtotime($rider['created_at'])); ?></p>
                    <p class="mb-2"><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($rider['updated_at'])); ?></p>
                    <p class="mb-0"><strong>Last Login:</strong> <?php echo $rider['last_login'] ? date('M d, Y H:i', strtotime($rider['last_login'])) : 'Never'; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../app/views/layouts/footer.php';
?>
