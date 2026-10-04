<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get buyers with filters
$where_clause = "";
$params = [];
$conditions = [];

if (!empty($type_filter)) {
    $conditions[] = "buyer_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Get buyers
$buyers = $pdo->prepare("
    SELECT * FROM buyers 
    $where_clause 
    ORDER BY created_at DESC
");
$buyers->execute($params);
$buyers = $buyers->fetchAll();

// Get sales statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_buyers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_buyers,
        SUM(CASE WHEN buyer_type = 'small' THEN 1 ELSE 0 END) as small_buyers,
        SUM(CASE WHEN buyer_type = 'medium' THEN 1 ELSE 0 END) as medium_buyers,
        SUM(CASE WHEN buyer_type = 'large' THEN 1 ELSE 0 END) as large_buyers
    FROM buyers
")->fetch();

// Get today's delivery stats
$today_stats = $pdo->query("
    SELECT 
        COUNT(*) as today_deliveries,
        SUM(quantity_kg) as today_kg,
        SUM(total_amount) as today_sales,
        SUM(payment_received) as today_payments
    FROM flower_deliveries 
    WHERE delivery_date = CURDATE()
")->fetch();

// Get pending payments
$pending_payments = $pdo->query("
    SELECT SUM(payment_pending) as total_pending 
    FROM flower_deliveries 
    WHERE payment_status IN ('pending', 'partial')
")->fetch();

// Set page title
$page_title = "Sales Management - Flower Buyers";

// Include header
include '../app/views/layouts/header.php';
?>

<style>
/* Global Responsive Table CSS */
.responsive-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.responsive-table {
    width: 100%;
    min-width: 100%;
}

.responsive-table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 1rem 0.75rem;
    white-space: nowrap;
    border-bottom: 2px solid #dee2e6;
}

.responsive-table tbody td {
    padding: 0.75rem;
    vertical-align: middle;
}

.responsive-table tbody tr {
    border-bottom: 1px solid #dee2e6;
    transition: background-color 0.15s ease-in-out;
}

.responsive-table tbody tr:hover {
    background-color: #f5f5f5;
}

/* Mobile responsive */
@media (max-width: 576px) {
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 0.5rem 0.4rem;
        font-size: 0.85rem;
    }
    
    .btn-group.flex-wrap .btn {
        margin-bottom: 0.25rem;
        padding: 0.35rem 0.5rem;
        font-size: 0.70rem;
    }
}

/* Tablet responsive */
@media (max-width: 768px) {
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 0.65rem;
        font-size: 0.90rem;
    }
    
    .btn-group.flex-wrap {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
}

/* Desktop */
@media (min-width: 1200px) {
    .responsive-table {
        width: 100%;
    }
}

/* Column visibility */
@media (max-width: 767px) {
    .d-md-table-cell {
        display: none !important;
    }
}

@media (max-width: 991px) {
    .d-lg-table-cell {
        display: none !important;
    }
}

@media (max-width: 1199px) {
    .d-xl-table-cell {
        display: none !important;
    }
}

@media (min-width: 1200px) {
    .d-md-table-cell,
    .d-lg-table-cell,
    .d-xl-table-cell {
        display: table-cell !important;
    }
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Sales Management</h1>
 <div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="sales/add_buyer.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add Buyer
        </a>
        <a href="sales/add_delivery.php" class="btn btn-sm btn-success">
            <i class="fas fa-truck me-1"></i> Add Delivery
        </a>
        <!-- ADD THESE NEW LINKS -->
        <a href="sales/add_payment.php" class="btn btn-sm btn-warning">
            <i class="fas fa-money-bill me-1"></i> Add Payment
        </a>
        <a href="sales/pending_payments.php" class="btn btn-sm btn-danger">
            <i class="fas fa-clock me-1"></i> Pending Payments
        </a>
              <a href="sales/reports.php" class="btn btn-sm btn-info">
    <i class="fas fa-chart-bar me-1"></i> View Reports
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

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Sales Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Buyers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_buyers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            Today's Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($today_stats['today_sales'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
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
                            Today's Delivery (KG)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($today_stats['today_kg'] ?? 0, 2); ?> kg</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-weight fa-2x text-gray-300"></i>
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
</div>

<!-- Buyer Type Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Small Buyers</h6>
                        <h3><?php echo $stats['small_buyers']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Medium Buyers</h6>
                        <h3><?php echo $stats['medium_buyers']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-tie fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Large Buyers</h6>
                        <h3><?php echo $stats['large_buyers']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="typeFilter" onchange="window.location.href='sales.php?type='+this.value">
            <option value="" <?php echo empty($type_filter) ? 'selected' : ''; ?>>All Types</option>
            <option value="small" <?php echo $type_filter == 'small' ? 'selected' : ''; ?>>Small</option>
            <option value="medium" <?php echo $type_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
            <option value="large" <?php echo $type_filter == 'large' ? 'selected' : ''; ?>>Large</option>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" id="statusFilter" onchange="window.location.href='sales.php?status='+this.value">
            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </div>
    <div class="col-md-6">
        <input type="text" class="form-control" placeholder="Search buyers..." id="searchInput">
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive responsive-table-wrapper">
            <table class="table table-striped table-hover responsive-table" id="buyersTable">
                <thead>
                    <tr>
                        <th class="d-none d-lg-table-cell">ID</th>
                        <th>Buyer Name</th>
                        <th class="d-none d-md-table-cell">Contact</th>
                        <th class="d-none d-md-table-cell">Type</th>
                        <th class="d-none d-lg-table-cell">Special Rate</th>
                        <th>Status</th>
                        <th class="d-none d-xl-table-cell">Total Purchases</th>
                        <th class="d-none d-lg-table-cell">Pending Amount</th>
                        <th class="d-none d-lg-table-cell">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buyers as $buyer): 
                        // Get buyer statistics
                        $buyer_stats = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total_deliveries,
                                SUM(total_amount) as total_purchases,
                                SUM(payment_pending) as total_pending
                            FROM flower_deliveries 
                            WHERE buyer_id = ?
                        ");
                        $buyer_stats->execute([$buyer['buyer_id']]);
                        $stats = $buyer_stats->fetch();
                    ?>
                    <tr>
                        <td class="d-none d-lg-table-cell"><?php echo $buyer['buyer_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($buyer['buyer_name']); ?></strong>
                            <div class="d-md-none small text-muted"><?php echo htmlspecialchars($buyer['contact_number1']); ?></div>
                            <?php if (!empty($buyer['business_address'])): ?>
                            <br><small class="text-muted d-none d-md-block"><?php echo htmlspecialchars(substr($buyer['business_address'], 0, 40)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div><?php echo htmlspecialchars($buyer['contact_number1']); ?></div>
                            <?php if (!empty($buyer['contact_number2'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($buyer['contact_number2']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
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
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php if (!empty($buyer['special_rate'])): ?>
                                Rs. <?php echo number_format($buyer['special_rate'], 2); ?>
                            <?php else: ?>
                                <span class="text-muted">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $buyer['status'] == 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($buyer['status']); ?>
                            </span>
                        </td>
                        <td class="d-none d-xl-table-cell">Rs. <?php echo number_format($stats['total_purchases'] ?? 0, 0); ?></td>
                        <td class="d-none d-lg-table-cell">
                            <?php if (($stats['total_pending'] ?? 0) > 0): ?>
                                <span class="badge bg-warning">Rs. <?php echo number_format($stats['total_pending'], 0); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell"><?php echo date('M d, Y', strtotime($buyer['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm flex-wrap">
                                <a href="sales/view_buyer.php?id=<?php echo $buyer['buyer_id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="sales/edit_buyer.php?id=<?php echo $buyer['buyer_id']; ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Buyer">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="sales/add_delivery.php?buyer_id=<?php echo $buyer['buyer_id']; ?>" class="btn btn-success" data-bs-toggle="tooltip" title="Add Delivery">
                                    <i class="fas fa-truck"></i>
                                </a>
                                <a href="sales/delete_buyer.php?id=<?php echo $buyer['buyer_id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Buyer" onclick="return confirm('Are you sure you want to delete this buyer? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Deliveries -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-truck me-2"></i>Recent Deliveries
        </h5>
    </div>
    <div class="card-body">
        <?php
        $recent_deliveries = $pdo->query("
            SELECT fd.*, b.buyer_name 
            FROM flower_deliveries fd 
            JOIN buyers b ON fd.buyer_id = b.buyer_id 
            ORDER BY fd.delivery_date DESC, fd.created_at DESC 
            LIMIT 10
        ")->fetchAll();
        ?>
        
        <?php if (count($recent_deliveries) > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Buyer</th>
                        <th>Flower Type</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_deliveries as $delivery): ?>
                    <tr>
                        <td><?php echo date('M d', strtotime($delivery['delivery_date'])); ?></td>
                        <td><?php echo htmlspecialchars($delivery['buyer_name']); ?></td>
                        <td><?php echo htmlspecialchars($delivery['flower_type']); ?></td>
                        <td><?php echo number_format($delivery['quantity_kg'], 2); ?> kg</td>
                        <td>Rs. <?php echo number_format($delivery['rate_per_kg'], 2); ?></td>
                        <td>Rs. <?php echo number_format($delivery['total_amount'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($delivery['payment_status']) {
                                    case 'paid': echo 'success'; break;
                                    case 'partial': echo 'warning'; break;
                                    case 'pending': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst($delivery['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted text-center">No recent deliveries found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const rows = document.querySelectorAll('#buyersTable tbody tr');
    
    rows.forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        const contact = row.cells[2].textContent.toLowerCase();
        
        if (name.includes(searchText) || contact.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>