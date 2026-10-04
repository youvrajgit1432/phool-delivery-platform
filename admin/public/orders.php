<?php
// orders.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle rider assigned filter
$rider_assigned_filter = isset($_GET['rider_assigned']) ? $_GET['rider_assigned'] : '';

// Handle time filter
$time_filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Handle ordering
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at_desc';

// Build WHERE clause
$where_conditions = [];
$params = [];

// ALWAYS exclude soft-deleted orders (unless viewing trash)
$viewing_trash = isset($_GET['view']) && $_GET['view'] === 'trash' ? true : false;

if ($viewing_trash) {
    // Viewing trash: only show deleted orders
    $where_conditions[] = "o.deleted_at IS NOT NULL";
} else {
    // Normal view: only show non-deleted orders
    $where_conditions[] = "o.deleted_at IS NULL";
}

// Rider assigned filter
if (!empty($rider_assigned_filter) && $rider_assigned_filter === 'yes') {
    $where_conditions[] = "o.assigned_rider_id IS NOT NULL";
}

// Status filter
if (!empty($status_filter)) {
    if (in_array($status_filter, ['assigned', 'accepted', 'rejected'])) {
        // For assignment response filters, check assign_status column
        $where_conditions[] = "o.assign_status = ?";
        $params[] = $status_filter;
    } else {
        // For other statuses, use main status column
        $where_conditions[] = "o.status = ?";
        $params[] = $status_filter;
    }
}

// Time filter
if (!empty($time_filter)) {
    $today = date('Y-m-d');
    switch ($time_filter) {
        case 'today':
            $where_conditions[] = "DATE(o.created_at) = ?";
            $params[] = $today;
            break;
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $where_conditions[] = "DATE(o.created_at) = ?";
            $params[] = $yesterday;
            break;
        case 'this_week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $where_conditions[] = "DATE(o.created_at) >= ?";
            $params[] = $startOfWeek;
            break;
        case 'last_week':
            $startOfLastWeek = date('Y-m-d', strtotime('monday last week'));
            $endOfLastWeek = date('Y-m-d', strtotime('sunday last week'));
            $where_conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $startOfLastWeek;
            $params[] = $endOfLastWeek;
            break;
        case 'this_month':
            $startOfMonth = date('Y-m-01');
            $where_conditions[] = "DATE(o.created_at) >= ?";
            $params[] = $startOfMonth;
            break;
        case 'last_month':
            $startOfLastMonth = date('Y-m-01', strtotime('first day of last month'));
            $endOfLastMonth = date('Y-m-t', strtotime('last month'));
            $where_conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $startOfLastMonth;
            $params[] = $endOfLastMonth;
            break;
    }
}

// Date range filter
if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $start_date;
} elseif (!empty($end_date)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $end_date;
}

// Build WHERE clause
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Handle ordering
$order_clause = "ORDER BY ";
switch ($order_by) {
    case 'created_at_asc':
        $order_clause .= "o.created_at ASC";
        break;
    case 'sn_asc':
        $order_clause .= "o.id ASC";
        break;
    case 'sn_desc':
        $order_clause .= "o.id DESC";
        break;
    case 'amount_asc':
        $order_clause .= "o.total_amount ASC";
        break;
    case 'amount_desc':
        $order_clause .= "o.total_amount DESC";
        break;
    default:
        $order_clause .= "o.created_at DESC";
        break;
}

$sql = "SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.verification_status,"
    . " (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,"
    . " GROUP_CONCAT(DISTINCT p.name_en SEPARATOR ', ') as product_names,"
    . " v.id as assigned_vendor_id, v.store_name as assigned_vendor_name,"
    . " r.id as assigned_rider_id, CONCAT(r.first_name, ' ', r.last_name) as assigned_rider_name, r.phone as rider_phone"
    . " FROM orders o"
    . " LEFT JOIN customers c ON o.customer_id = c.id"
    . " LEFT JOIN order_items oi ON o.id = oi.order_id"
    . " LEFT JOIN products p ON oi.product_id = p.id"
    . " LEFT JOIN vendors v ON o.assigned_vendor_id = v.id"
    . " LEFT JOIN riders r ON o.assigned_rider_id = r.id"
    . " " . $where_clause
    . " GROUP BY o.id"
    . " " . $order_clause;

$orders = $pdo->prepare($sql);
$orders->execute($params);
$orders = $orders->fetchAll();

// Fetch active vendors for inline assign controls
$vendors = [];
try {
    $vstmt = $pdo->prepare("SELECT id, store_name FROM vendors WHERE status = 'active' ORDER BY store_name ASC");
    $vstmt->execute();
    $vendors = $vstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ve) {
    $vendors = [];
}

// Fetch active riders for inline assign controls
$riders = [];
try {
    $rstmt = $pdo->prepare("SELECT id, first_name, last_name, phone, is_available FROM riders WHERE status = 'active' ORDER BY first_name ASC");
    $rstmt->execute();
    $riders = $rstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $re) {
    $riders = [];
}

// Pre-render vendor <option> HTML to reuse in each row
$vendorOptionsHtml = '';
foreach ($vendors as $vopt) {
    $vendorOptionsHtml .= '<option value="' . $vopt['id'] . '">' . htmlspecialchars($vopt['store_name']) . '</option>';
}

// Pre-render rider <option> HTML to reuse in each row
$riderOptionsHtml = '';
foreach ($riders as $ropt) {
    $riderName = htmlspecialchars($ropt['first_name'] . ' ' . $ropt['last_name']);
    $riderAvailability = $ropt['is_available'] ? ' (Online)' : ' (Offline)';
    $riderOptionsHtml .= '<option value="' . $ropt['id'] . '">' . $riderName . $riderAvailability . '</option>';
}

// Set page title
$page_title = ($viewing_trash ? "Trash - " : "") . "Orders Management - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';

// Store orders as JSON for client-side vendor availability lookup
$ordersJson = json_encode(array_map(function($o) { return ['id' => $o['id'], 'status' => $o['status']]; }, $orders));
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2"><?php echo $viewing_trash ? "Trash" : "Orders Management"; ?></h1>
    <a href="orders/add.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> New Order
    </a>
</div>

<?php if ($viewing_trash): ?>
    <!-- Trash view back button -->
    <div class="mb-3">
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>
    </div>
<?php else: ?>
    <!-- Modern Filter Layout with Hierarchical Grouping -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <!-- Filter Title -->
            <div class="mb-3">
                <h6 class="text-uppercase fw-bold text-muted mb-0" style="font-size: 0.75rem; letter-spacing: 1px;">
                    <i class="fas fa-filter me-2"></i>Filters
                </h6>
            </div>

            <!-- FILTER TABS & ACTIONS ROW -->
            <div class="mb-3">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Tab Buttons -->
                    <button type="button" class="btn btn-sm btn-outline-primary filter-tab active" data-tab="order">
                        <i class="fas fa-box-open me-1"></i>Order Status
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary filter-tab" data-tab="vendor">
                        <i class="fas fa-store me-1"></i>Vendor Status
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary filter-tab" data-tab="rider">
                        <i class="fas fa-motorcycle me-1"></i>Rider Status
                    </button>
                    
                    <!-- Divider -->
                    <div style="width: 1px; height: 30px; background-color: #dee2e6; margin: 0 0.5rem;"></div>
                    
                    <!-- Collapsible Time Filter Button -->
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#timeFilterPanel">
                        <i class="fas fa-calendar me-1"></i>Time Filter
                    </button>
                    
                    <!-- Reset & Trash (Right aligned) -->
                    <div class="ms-auto d-flex gap-2">
                        <a href="#" onclick="resetFilters(); return false;" class="btn btn-sm btn-outline-secondary" title="Reset all filters">
                            <i class="fas fa-redo me-1"></i>Reset
                        </a>
                        <a href="orders.php?view=trash" class="btn btn-sm btn-outline-danger" title="View deleted records">
                            <i class="fas fa-trash me-1"></i>Trash
                        </a>
                    </div>
                </div>
            </div>

            <!-- STATUS FILTER CONTENT (Tab Content) -->
            <div class="mb-4">
                <!-- Order Status Tab -->
                <div id="order-tab" class="filter-tab-content active">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#" onclick="applyFilter('status', ''); return false;" class="btn btn-sm btn-outline-secondary filter-btn <?php echo empty($status_filter) && !isset($_GET['rider_assigned']) ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="">
                            All Orders
                        </a>
                        <a href="#" onclick="applyFilter('status', 'pending'); return false;" class="btn btn-sm btn-outline-warning filter-btn <?php echo $status_filter == 'pending' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="pending">
                            <i class="fas fa-clock me-1"></i>Pending
                        </a>
                        <a href="#" onclick="applyFilter('status', 'confirmed'); return false;" class="btn btn-sm btn-outline-info filter-btn <?php echo $status_filter == 'confirmed' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="confirmed">
                            <i class="fas fa-check me-1"></i>Confirmed
                        </a>
                        <a href="#" onclick="applyFilter('status', 'cancelled'); return false;" class="btn btn-sm btn-outline-danger filter-btn <?php echo $status_filter == 'cancelled' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="cancelled">
                            <i class="fas fa-ban me-1"></i>Cancelled
                        </a>
                    </div>
                </div>

                <!-- Vendor Status Tab -->
                <div id="vendor-tab" class="filter-tab-content" style="display: none;">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#" onclick="applyFilter('status', 'assigned'); return false;" class="btn btn-sm btn-outline-primary filter-btn <?php echo $status_filter == 'assigned' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="assigned">
                            <i class="fas fa-envelope-open me-1"></i>Assigned
                        </a>
                        <a href="#" onclick="applyFilter('status', 'accepted'); return false;" class="btn btn-sm btn-outline-success filter-btn <?php echo $status_filter == 'accepted' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="accepted">
                            <i class="fas fa-thumbs-up me-1"></i>Accepted
                        </a>
                        <a href="#" onclick="applyFilter('status', 'rejected'); return false;" class="btn btn-sm btn-outline-danger filter-btn <?php echo $status_filter == 'rejected' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="rejected">
                            <i class="fas fa-thumbs-down me-1"></i>Rejected
                        </a>
                    </div>
                </div>

                <!-- Rider Status Tab -->
                <div id="rider-tab" class="filter-tab-content" style="display: none;">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#" onclick="applyFilter('rider', 'yes'); return false;" class="btn btn-sm btn-outline-info filter-btn <?php echo isset($_GET['rider_assigned']) && $_GET['rider_assigned'] == 'yes' ? 'active fw-bold' : ''; ?>" data-filter-type="rider" data-filter-value="yes">
                            <i class="fas fa-person-hiking me-1"></i>Rider Assigned
                        </a>
                        <a href="#" onclick="applyFilter('status', 'delivered'); return false;" class="btn btn-sm btn-outline-success filter-btn <?php echo $status_filter == 'delivered' ? 'active fw-bold' : ''; ?>" data-filter-type="status" data-filter-value="delivered">
                            <i class="fas fa-truck-check me-1"></i>Delivered
                        </a>
                    </div>
                </div>
            </div>

            <!-- LOADING INDICATOR -->
            <div id="filterLoading" class="alert alert-info d-none" role="alert">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading filtered data...
            </div>

            <!-- COLLAPSED TIME FILTER PANEL -->
            <div class="collapse" id="timeFilterPanel">
                <div class="card card-body p-3 mb-4">
                    <form method="GET" action="orders.php" class="d-flex flex-wrap gap-3 align-items-end">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <div class="d-flex flex-column">
                                <label for="time_filter" class="form-label small fw-600">Time Filter</label>
                                <select class="form-select form-select-sm" id="time_filter" name="time_filter" onchange="toggleDatePickers()" style="min-width: 130px;">
                                    <option value="">All Time</option>
                                    <option value="today" <?php echo $time_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="yesterday" <?php echo $time_filter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                    <option value="this_week" <?php echo $time_filter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="last_week" <?php echo $time_filter == 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                                    <option value="this_month" <?php echo $time_filter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="last_month" <?php echo $time_filter == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                    <option value="custom" <?php echo $time_filter == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>
                            
                            <div id="datePickersContainer" class="d-flex flex-wrap gap-2 align-items-end" style="display: none;">
                                <div class="d-flex flex-column">
                                    <label for="start_date" class="form-label small fw-600">From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>" onchange="this.form.submit()">
                                </div>
                                <div class="d-flex flex-column">
                                    <label for="end_date" class="form-label small fw-600">To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>" onchange="this.form.submit()">
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column flex-grow-1">
                                <label for="order_by" class="form-label small fw-600">Sort By</label>
                                <select class="form-select form-select-sm" id="order_by" name="order_by" onchange="this.form.submit()">
                                    <option value="created_at_desc" <?php echo $order_by == 'created_at_desc' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="created_at_asc" <?php echo $order_by == 'created_at_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="sn_desc" <?php echo $order_by == 'sn_desc' ? 'selected' : ''; ?>>SN: Recent First</option>
                                    <option value="sn_asc" <?php echo $order_by == 'sn_asc' ? 'selected' : ''; ?>>SN: Oldest First</option>
                                    <option value="amount_desc" <?php echo $order_by == 'amount_desc' ? 'selected' : ''; ?>>Amount: High to Low</option>
                                    <option value="amount_asc" <?php echo $order_by == 'amount_asc' ? 'selected' : ''; ?>>Amount: Low to High</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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


<!-- AJAX Orders Container -->
<div id="ordersTableContainer">

<div class="card mb-4">
 
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="ordersTable">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Amount</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>A.S</th>
                        <?php if ($status_filter === 'accepted'): ?>
                        <th>R.A.S</th>
                        <th>Rider</th>
                        <?php endif; ?>
                        <th>Payment</th>
                        <th><?php echo $viewing_trash ? 'Deleted At' : 'Date'; ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial_number = 1;
                    foreach ($orders as $order): 
                    ?>
                    <tr>
                        <td><?php echo $serial_number++; ?></td>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                            <?php if ($order['verification_status'] !== 'verified'): ?>
                            <div><span class="badge bg-warning">Unverified</span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($order['product_names'])) {
                                echo htmlspecialchars($order['product_names']);
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo number_format($order['quantity'], 2); ?></td>
                        <td>Rs. <?php echo number_format($order['rate'], 2); ?></td>
                        <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <?php if (!empty($order['assigned_vendor_name'])): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($order['assigned_vendor_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="order-status" data-order-id="<?php echo $order['id']; ?>">
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <span class="badge bg-<?php 
                                switch($order['status']) {
                                    case 'pending': echo 'warning'; break;
                                    case 'assigned': echo 'info'; break;
                                    case 'confirmed': echo 'info'; break;
                                    case 'preparing': echo 'primary'; break;
                                    case 'out_for_delivery': echo 'secondary'; break;
                                    case 'delivered': echo 'success'; break;
                                    case 'cancelled': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                                ?>" style="min-width: 100px;">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($order['status'] !== 'confirmed'): ?>
                                    <button type="button" class="btn btn-outline-info quick-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-new-status="confirmed" 
                                            title="Mark as Confirmed">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                    <button type="button" class="btn btn-outline-success quick-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-new-status="delivered" 
                                            title="Mark as Delivered">
                                        <i class="fas fa-truck"></i> Deliver
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-center as-cell" data-order-id="<?php echo $order['id']; ?>">
                            <?php 
                            $assign_status = $order['assign_status'] ?? 'unassigned';
                            $assign_badge_color = '';
                            switch($assign_status) {
                                case 'unassigned': 
                                    $assign_badge_color = 'secondary';
                                    $assign_display = 'n';
                                    break;
                                case 'assigned': 
                                    $assign_badge_color = 'warning';
                                    $assign_display = 'pending';
                                    break;
                                case 'accepted': 
                                    $assign_badge_color = 'success';
                                    $assign_display = 'y';
                                    break;
                                case 'rejected': 
                                    $assign_badge_color = 'danger';
                                    $assign_display = 'rejected';
                                    break;
                                default: 
                                    $assign_badge_color = 'secondary';
                                    $assign_display = 'unknown';
                            }
                            ?>
                            <span class="badge bg-<?php echo $assign_badge_color; ?>" title="Assignment Status: <?php echo ucfirst($assign_status); ?>">
                                <?php echo $assign_display; ?>
                            </span>
                        </td>
                        <?php if ($status_filter === 'accepted'): ?>
                        <td class="text-center rider-as-cell" data-order-id="<?php echo $order['id']; ?>">
                            <?php 
                            $rider_assignment_status = $order['rider_assignment_status'] ?? 'unassigned';
                            $rider_badge_color = '';
                            switch($rider_assignment_status) {
                                case 'unassigned': 
                                    $rider_badge_color = 'secondary';
                                    $rider_display = 'N';
                                    break;
                                case 'assigned': 
                                    $rider_badge_color = 'warning';
                                    $rider_display = 'Sent';
                                    break;
                                case 'accepted': 
                                    $rider_badge_color = 'success';
                                    $rider_display = 'Y';
                                    break;
                                case 'rejected': 
                                    $rider_badge_color = 'danger';
                                    $rider_display = 'Rej';
                                    break;
                                case 'picked_up':
                                    $rider_badge_color = 'info';
                                    $rider_display = 'PU';
                                    break;
                                case 'in_delivery':
                                    $rider_badge_color = 'info';
                                    $rider_display = 'OD';
                                    break;
                                case 'delivered':
                                    $rider_badge_color = 'success';
                                    $rider_display = 'D';
                                    break;
                                case 'failed':
                                    $rider_badge_color = 'danger';
                                    $rider_display = 'F';
                                    break;
                                default: 
                                    $rider_badge_color = 'secondary';
                                    $rider_display = '?';
                            }
                            ?>
                            <span class="badge bg-<?php echo $rider_badge_color; ?>" title="Rider Assignment Status: <?php echo ucfirst(str_replace('_', ' ', $rider_assignment_status)); ?>">
                                <?php echo $rider_display; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($order['assigned_rider_name'])): ?>
                                <div class="d-flex flex-column gap-1">
                                    <strong><?php echo htmlspecialchars($order['assigned_rider_name']); ?></strong>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['rider_phone']); ?></small>
                                    <a href="riders/view.php?id=<?php echo $order['assigned_rider_id']; ?>" class="btn btn-sm btn-info" title="View Rider Details">
                                        <i class="fas fa-eye"></i> View Rider
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <span class="badge bg-<?php 
                            switch($order['payment_status']) {
                                case 'pending': echo 'warning'; break;
                                case 'paid': echo 'success'; break;
                                case 'failed': echo 'danger'; break;
                                case 'refunded': echo 'secondary'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($order['confirmation_email_status']) {
                                    case 'sent': echo 'success'; break;
                                    case 'failed': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst($order['confirmation_email_status']); ?>
                            </span>
                            <?php if ($order['confirmation_email_status'] === 'failed'): ?>
                            <br>
                            <small class="text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Failed to send
                            </small>
                            <?php elseif ($order['confirmation_email_status'] === 'sent' && $order['confirmation_email_sent_at']): ?>
                            <br>
                            <small class="text-muted">
                                <?php echo date('M d, H:i', strtotime($order['confirmation_email_sent_at'])); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($viewing_trash): ?>
                                <small class="text-muted">Deleted: <?php echo ($order['deleted_at'] ? date('M d, Y H:i', strtotime($order['deleted_at'])) : 'N/A'); ?></small>
                            <?php else: ?>
                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="orders/edit.php?id=<?php echo $order['id']; ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Order">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="orders/status.php?id=<?php echo $order['id']; ?>" class="btn btn-warning" data-bs-toggle="tooltip" title="Change Status">
                                    <i class="fas fa-sync"></i>
                                </a>
                                <a href="orders/generate_customer_invoice.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-success" data-bs-toggle="tooltip" title="Generate Invoice">
                                    <i class="fas fa-receipt"></i>
                                </a>
                                <a href="orders/delete.php?id=<?php echo $order['id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Order" onclick="return confirm('Are you sure you want to delete this order?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php if ($viewing_trash): ?>
                            <div class="btn-group btn-group-sm mt-2" role="group">
                                <button type="button" class="btn btn-success" onclick="restoreSingleOrder(<?php echo $order['id']; ?>)" data-bs-toggle="tooltip" title="Restore this order">
                                    <i class="fas fa-undo"></i> Recover
                                </button>
                                <button type="button" class="btn btn-danger" onclick="permanentDeleteSingleOrder(<?php echo $order['id']; ?>)" data-bs-toggle="tooltip" title="Permanently delete this order">
                                    <i class="fas fa-trash-alt"></i> Delete Permanent
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($status_filter !== 'accepted'): ?>
                            <!-- NORMAL VIEW: Show vendor assign field (not accepted) -->
                            <div class="mt-2">
                                <?php
                                $currentAssignStatus = strtolower(trim($order['assign_status'] ?? 'unassigned'));
                                $canAssign = in_array($currentAssignStatus, ['unassigned', 'rejected']);
                                $selectDisabled = $canAssign ? '' : 'disabled';
                                $btnClass = $canAssign ? 'btn-outline-secondary' : 'btn-success';
                                $btnText = $canAssign ? 'Assign' : 'Assigned';
                                $btnDisabled = $canAssign ? '' : 'disabled';
                                ?>
                                <div class="d-flex gap-1 align-items-center flex-wrap">
                                    <select class="form-select form-select-sm assign-vendor-select" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            style="width:180px;" <?php echo $selectDisabled; ?> >
                                        <option value="">Assign vendor...</option>
                                        <?php echo $vendorOptionsHtml; ?>
                                    </select>
                                    <button class="btn btn-sm <?php echo $btnClass; ?> assign-vendor-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            <?php echo $btnDisabled; ?>><?php echo $btnText; ?></button>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- ACCEPTED VIEW: Show rider assign field only -->
                            <div class="mt-2">
                                <?php
                                $currentRiderStatus = strtolower(trim($order['rider_assignment_status'] ?? 'unassigned'));
                                $canAssignRider = in_array($currentRiderStatus, ['unassigned', 'rejected']);
                                $riderSelectDisabled = $canAssignRider ? '' : 'disabled';
                                $riderBtnClass = $canAssignRider ? 'btn-outline-info' : 'btn-success';
                                $riderBtnText = $canAssignRider ? 'Assign Rider' : 'Assigned';
                                $riderBtnDisabled = $canAssignRider ? '' : 'disabled';
                                ?>
                                <div class="d-flex gap-1 align-items-center flex-wrap">
                                    <select class="form-select form-select-sm assign-rider-select" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            style="width:180px;" <?php echo $riderSelectDisabled; ?> >
                                        <option value="">Select rider...</option>
                                        <?php echo $riderOptionsHtml; ?>
                                    </select>
                                    <button class="btn btn-sm <?php echo $riderBtnClass; ?> assign-rider-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            <?php echo $riderBtnDisabled; ?>><?php echo $riderBtnText; ?></button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($orders)): ?>
            <div class="alert alert-info mt-3">
                No orders found with the selected filters.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
<!-- End AJAX Orders Container -->

<style>
/* Filter Section Styling */
.card.border-0 {
    background-color: #ffffff;
    border-radius: 8px;
}

/* Filter Category Headers */
.card-body p {
    display: flex;
    align-items: center;
    margin-bottom: 1rem !important;
    font-weight: 600;
    font-size: 0.95rem;
    color: #212529;
    letter-spacing: 0.3px;
}

.card-body p i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

/* Filter Buttons */
.btn-sm {
    font-weight: 500;
    letter-spacing: 0.3px;
    transition: all 0.2s ease;
}

.btn-outline-secondary:not(.active) {
    color: #6c757d;
    border-color: #dee2e6;
}

.btn-outline-secondary:not(.active):hover {
    background-color: #f8f9fa;
    border-color: #adb5bd;
}

.btn-outline-secondary.active,
.btn-outline-warning.active,
.btn-outline-info.active,
.btn-outline-success.active,
.btn-outline-danger.active,
.btn-outline-primary.active {
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

/* Color-coded Button Styling */

/* Order Status - Semantic Colors */
.btn-outline-warning {
    color: #ff9800;
    border-color: #ffe0b2;
}

.btn-outline-warning:hover {
    background-color: #fff3e0;
    border-color: #ffb74d;
}

.btn-outline-info {
    color: #2196f3;
    border-color: #bbdefb;
}

.btn-outline-info:hover {
    background-color: #e3f2fd;
    border-color: #64b5f6;
}

.btn-outline-success {
    color: #4caf50;
    border-color: #c8e6c9;
}

.btn-outline-success:hover {
    background-color: #f1f8e9;
    border-color: #81c784;
}

.btn-outline-danger {
    color: #f44336;
    border-color: #ffcdd2;
}

.btn-outline-danger:hover {
    background-color: #ffebee;
    border-color: #ef5350;
}

.btn-outline-primary {
    color: #0d6efd;
    border-color: #cfe2ff;
}

.btn-outline-primary:hover {
    background-color: #f0f6ff;
    border-color: #0a58ca;
}

/* Filter Container Spacing */
.card-body {
    padding: 2rem !important;
}

.card-body > div {
    margin-bottom: 1.5rem;
}

.card-body > div:last-child {
    margin-bottom: 0;
}

/* Responsive Filter Buttons */
@media (max-width: 768px) {
    .card-body {
        padding: 1.5rem !important;
    }
    
    .d-flex.flex-wrap {
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
    }
}

/* Compact filters on mobile */
@media (max-width: 600px) {
    .btn-sm {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
    }
}

/* Border Separator */
.border-top {
    border-top: 2px solid #e9ecef !important;
    padding-top: 1.5rem;
}

/* Icon Styling */
.card-body p i {
    opacity: 0.8;
    font-size: 1.1rem;
}

/* Active Button Styling */
.btn.active {
    transform: translateY(-1px);
}

/* Category Section Titles */
h6.text-uppercase {
    font-size: 0.7rem;
    letter-spacing: 1px;
    font-weight: 700;
    color: #9ca3af;
}

/* Tooltip styling */
[data-bs-toggle="tooltip"] {
    cursor: help;
}

/* Focus states for accessibility */
.btn:focus {
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.btn:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
</style>

<script>
// Store current filters in memory
let currentFilters = {
    status: '<?php echo $status_filter; ?>',
    rider: '<?php echo isset($_GET['rider_assigned']) && $_GET['rider_assigned'] == 'yes' ? 'yes' : ''; ?>',
    time_filter: '<?php echo $time_filter; ?>',
    start_date: '<?php echo $start_date; ?>',
    end_date: '<?php echo $end_date; ?>',
    order_by: '<?php echo $order_by; ?>'
};

// Apply AJAX filter
function applyFilter(filterType, filterValue) {
    // Update current filters
    if (filterType === 'status') {
        currentFilters.status = filterValue;
        currentFilters.rider = '';
    } else if (filterType === 'rider') {
        currentFilters.rider = filterValue;
        currentFilters.status = '';
    }
    
    // Update UI - mark button as active
    const buttons = document.querySelectorAll(`.filter-btn[data-filter-type="${filterType}"]`);
    buttons.forEach(btn => btn.classList.remove('active', 'fw-bold'));
    
    const activeBtn = document.querySelector(`.filter-btn[data-filter-type="${filterType}"][data-filter-value="${filterValue}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'fw-bold');
    }
    
    // Show loading indicator
    document.getElementById('filterLoading').classList.remove('d-none');
    
    // Build query string
    const params = new URLSearchParams({
        status: currentFilters.status,
        rider_assigned: currentFilters.rider,
        time_filter: currentFilters.time_filter,
        start_date: currentFilters.start_date,
        end_date: currentFilters.end_date,
        order_by: currentFilters.order_by,
        ajax: '1'
    });
    
    // Make AJAX request
    fetch(`?${params}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(html => {
        // Extract orders table from response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const ordersTable = doc.querySelector('#ordersTableContainer');
        
        if (ordersTable) {
            document.getElementById('ordersTableContainer').innerHTML = ordersTable.innerHTML;
            
            // Re-initialize tooltips if using Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Hide loading indicator
        document.getElementById('filterLoading').classList.add('d-none');
        
        // Scroll to results
        document.getElementById('ordersTableContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('filterLoading').classList.add('d-none');
        alert('Error loading filtered data');
    });
}

// Reset all filters
function resetFilters() {
    currentFilters = {
        status: '',
        rider: '',
        time_filter: '',
        start_date: '',
        end_date: '',
        order_by: 'created_at_desc'
    };
    
    // Remove active class from all filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active', 'fw-bold');
    });
    
    // Mark "All Orders" as active
    const allOrdersBtn = document.querySelector('.filter-btn[data-filter-value=""]');
    if (allOrdersBtn) {
        allOrdersBtn.classList.add('active', 'fw-bold');
    }
    
    // Reset filters via AJAX
    applyFilter('status', '');
}

// Toggle date pickers based on time filter selection
function toggleDatePickers() {
    const timeFilter = document.getElementById('time_filter').value;
    const datePickersContainer = document.getElementById('datePickersContainer');
    
    if (timeFilter === 'custom') {
        datePickersContainer.style.display = 'flex';
        setTimeout(() => {
            document.querySelector('form').submit();
        }, 100);
    } else {
        datePickersContainer.style.display = 'none';
        document.querySelector('form').submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const timeFilter = document.getElementById('time_filter');
    const datePickersContainer = document.getElementById('datePickersContainer');
    const timeFilterToggle = document.querySelector('[data-bs-toggle="collapse"][data-bs-target="#timeFilterPanel"]');
    
    if (timeFilter && timeFilter.value === 'custom') {
        datePickersContainer.style.display = 'flex';
    }
    
    // Toggle time filter panel visibility
    if (timeFilterToggle) {
        timeFilterToggle.addEventListener('click', function() {
            const panel = document.getElementById('timeFilterPanel');
            const isVisible = panel.classList.contains('show');
            
            if (isVisible) {
                // Hide panel
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Show panel
                this.setAttribute('aria-expanded', 'true');
            }
        });
    }
    
    // Setup filter tab switching
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('data-tab');
            
            // Hide all tabs
            document.querySelectorAll('.filter-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tab buttons
            filterTabs.forEach(t => t.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to clicked tab
            this.classList.add('active');
        });
    });
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Reusable confirmation modal helper
function confirmAction(message, onConfirm, confirmText) {
    confirmText = confirmText || 'Confirm';
    var modalEl = document.getElementById('confirmModal');
    if (!modalEl) {
        // fallback to window.confirm
        if (window.confirm(message)) onConfirm();
        return;
    }
    document.getElementById('confirmModalMessage').innerText = message;
    var okBtn = document.getElementById('confirmModalOk');
    okBtn.innerText = confirmText;

    // remove previous handlers
    okBtn.onclick = function() {
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        onConfirm();
    };

    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function confirmNavigate(message, url, confirmText) {
    confirmAction(message, function(){ window.location.href = url; }, confirmText || 'OK');
}

// Set max date for date inputs to today
document.addEventListener('DOMContentLoaded', function() {
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').setAttribute('max', today);
    document.getElementById('end_date').setAttribute('max', today);
});

// Optional: Add confirmation for invoice generation if needed
document.addEventListener('DOMContentLoaded', function() {
    // If you want to add any confirmation or additional behavior for invoice generation
    // you can add it here without affecting the tab behavior
    var invoiceButtons = document.querySelectorAll('.btn-success[href*="generate_customer_invoice"]');
    invoiceButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            // Optional: Add any pre-processing here
            // The link will open in the same tab by default now
            console.log('Generating invoice for order...');
        });
    });

        // Quick status update buttons
    setupQuickStatusButtons();
    
    // Auto-cleanup trash (silently run in background)
    runTrashCleanup();
});

/**
 * Run trash cleanup in background (30-day auto-delete)
 */
function runTrashCleanup() {
    fetch('ajax/cleanup-trash.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.deleted_count > 0) {
            console.log('Trash cleanup: Auto-deleted ' + data.deleted_count + ' expired orders');
        }
    })
    .catch(function(error) {
        console.log('Trash cleanup skipped (not critical)');
    });
}


</script>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>

<script>
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('assign-vendor-btn')) return;
    var btn = e.target;
    var orderId = btn.getAttribute('data-order-id');
    var select = document.querySelector('.assign-vendor-select[data-order-id="' + orderId + '"]');
    if (!select) return alert('Select not found');
    var vendorId = select.value;
    if (!vendorId) return alert('Please select a vendor');
    btn.disabled = true; btn.innerText = 'Assigning...';

    var fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('vendor_id', vendorId);

    fetch('ajax/assign-order-to-vendor.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(json){
        if (json.success) {
            // update status badge and AS cell
            var statusTd = document.querySelector('.order-status[data-order-id="' + orderId + '"]');
            if (statusTd) {
                statusTd.innerHTML = '<span class="badge bg-info">Assigned</span>';
            }
            var asTd = document.querySelector('.as-cell[data-order-id="' + orderId + '"]');
            if (asTd) asTd.innerText = 'y';
            btn.innerText = 'Assigned';
            btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-success');
            btn.disabled = true;
        } else {
            alert(json.message || 'Failed to assign');
            btn.disabled = false; btn.innerText = 'Assign';
            console.error(json);
        }
    }).catch(function(err){
        alert('Request failed');
        btn.disabled = false; btn.innerText = 'Assign';
        console.error(err);
    });
});

// Rider assignment button handler
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('assign-rider-btn')) return;
    var btn = e.target;
    var orderId = btn.getAttribute('data-order-id');
    var select = document.querySelector('.assign-rider-select[data-order-id="' + orderId + '"]');
    if (!select) return alert('Select not found');
    var riderId = select.value;
    if (!riderId) return alert('Please select a rider');
    btn.disabled = true; 
    btn.innerText = 'Assigning...';

    fetch('ajax/assign-order-to-rider.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: parseInt(orderId),
            rider_id: parseInt(riderId)
        }),
        credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(json){
        if (json.success) {
            // update rider status badge and rider cell
            var riderTd = document.querySelector('.rider-as-cell[data-order-id="' + orderId + '"]');
            if (riderTd) {
                riderTd.innerHTML = '<span class="badge bg-warning" title="Rider Assignment Status: Assigned">Sent</span>';
            }
            btn.innerText = 'Assigned';
            btn.classList.remove('btn-outline-info'); 
            btn.classList.add('btn-success');
            btn.disabled = true;
            // Reload page to show rider details
            setTimeout(function() {
                location.reload();
            }, 1000);
        } else {
            alert(json.message || 'Failed to assign rider');
            btn.disabled = false; 
            btn.innerText = 'Assign Rider';
            console.error(json);
        }
    }).catch(function(err){
        alert('Request failed');
        btn.disabled = false; 
        btn.innerText = 'Assign Rider';
        console.error(err);
    });
});


document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('vendor-quick-lookup-btn') && !e.target.closest('.vendor-quick-lookup-btn')) return;
    var btn = e.target.closest('.vendor-quick-lookup-btn');
    var orderId = btn.getAttribute('data-order-id');
    var panel = document.querySelector('.vendor-quick-lookup-panel[data-order-id="' + orderId + '"]');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        showVendorQuickLookup(orderId, panel);
    } else {
        panel.style.display = 'none';
    }
});

// Auto-show vendor availability when select field is focused
document.addEventListener('focus', function(e) {
    if (!e.target.classList.contains('assign-vendor-select')) return;
    var select = e.target;
    var orderId = select.getAttribute('data-order-id');
    var panel = document.querySelector('.vendor-quick-lookup-panel[data-order-id="' + orderId + '"]');
    
    if (panel && panel.style.display === 'none') {
        panel.style.display = 'block';
        if (panel.querySelector('.fa-spinner')) {
            showVendorQuickLookup(orderId, panel);
        }
    }
}, true);

/**
 * Show vendor quick lookup panel with availability + price
 */
function showVendorQuickLookup(orderId, panelEl) {
    var formData = new FormData();
    formData.append('order_id', orderId);

    console.log('Fetching vendor availability for order:', orderId);
    
    fetch('ajax/get-vendor-availability.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) {
        console.log('Response status:', r.status);
        if (!r.ok) {
            throw new Error('HTTP ' + r.status);
        }
        return r.text();
    })
    .then(function(text) {
        console.log('Response text:', text);
        try {
            var data = JSON.parse(text);
            if (data.success) {
                var html = '<table style="width:100%; border-collapse:collapse; table-layout:fixed;">';
                html += '<tr style="border-bottom:1px solid #dee2e6;"><th style="text-align:left; padding:4px; font-weight:bold; width:50%;">Vendor</th><th style="text-align:center; padding:4px; font-weight:bold; width:25%;">Status</th><th style="text-align:right; padding:4px; font-weight:bold; width:25%;">Price</th></tr>';
                
                data.vendors.forEach(function(v) {
                    var statusBadge = v.has_all_products ? '<span style="color:#28a745; font-weight:bold;">✓ YES</span>' : '<span style="color:#ffc107; font-weight:bold;">⚠ PARTIAL</span>';
                    var priceStr = v.total_price > 0 ? 'Rs. ' + v.total_price.toFixed(2) : '—';
                    html += '<tr style="border-bottom:1px solid #f0f0f0; cursor:pointer; hover:{background:#e9ecef}" onmouseover="this.style.backgroundColor=\'#e9ecef\'" onmouseout="this.style.backgroundColor=\'\';" onclick="selectVendorQuick(' + v.id + ', ' + orderId + ')">';
                    html += '<td style="padding:6px 4px; font-size:0.9em; word-break:break-word;"><strong>' + v.store_name + '</strong>' + (v.rating ? ' ⭐' + v.rating.toFixed(1) : '') + '</td>';
                    html += '<td style="text-align:center; padding:4px;">' + statusBadge + '</td>';
                    html += '<td style="text-align:right; padding:4px; color:#28a745; font-weight:bold; white-space:nowrap;">' + priceStr + '</td>';
                    html += '</tr>';
                });
                html += '</table>';
                panelEl.innerHTML = html;
            } else {
                panelEl.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle"></i> Error: ' + (data.error || 'Failed') + '</small>';
            }
        } catch (parseErr) {
            console.error('JSON parse error:', parseErr);
            panelEl.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle"></i> Server error (invalid response)</small>';
        }
    })
    .catch(function(err) {
        console.error('Fetch error:', err);
        panelEl.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle"></i> Request failed: ' + err.message + '</small>';
    });
}

/**
 * Select vendor from quick lookup and fill dropdown, then hide panel
 */
function selectVendorQuick(vendorId, orderId, rowEl) {
    var select = document.querySelector('.assign-vendor-select[data-order-id="' + orderId + '"]');
    if (select) {
        select.value = vendorId;
    }
    
    // Hide panel after selection
    var panel = document.querySelector('.vendor-quick-lookup-panel[data-order-id="' + orderId + '"]');
    if (panel) {
        panel.style.display = 'none';
    }
}

/**
 * Setup quick status update buttons (hide in trash view)
 */
function setupQuickStatusButtons() {
    // Check if we're in trash view
    var isTrashView = new URLSearchParams(window.location.search).get('view') === 'trash';
    
    // Hide all quick status buttons in trash view
    if (isTrashView) {
        var statusButtons = document.querySelectorAll('.quick-status-btn');
        statusButtons.forEach(function(btn) {
            btn.style.display = 'none';
        });
        // Also hide the button groups
        var buttonGroups = document.querySelectorAll('.btn-group');
        buttonGroups.forEach(function(group) {
            group.style.display = 'none';
        });
        return; // Don't setup event listeners in trash view
    }
    
    // In normal view, setup quick status buttons
    var statusButtons = document.querySelectorAll('.quick-status-btn');
    
    statusButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var orderId = this.getAttribute('data-order-id');
            var newStatus = this.getAttribute('data-new-status');
            updateOrderStatus(orderId, newStatus, this);
        });
    });
}

/**
 * Update order status with AJAX
 */
function updateOrderStatus(orderId, newStatus, buttonElement) {
    // Disable button during submission
    buttonElement.disabled = true;
    var originalText = buttonElement.innerHTML;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('ajax/update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: parseInt(orderId),
            new_status: newStatus
        })
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('HTTP error, status = ' + response.status);
        }
        return response.text();
    })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.success) {
                // Show success message
                alert('Order status updated to: ' + data.message.replace('Order status updated to ', ''));
                
                // Update the status badge in the table
                var statusCell = document.querySelector('.order-status[data-order-id="' + orderId + '"]');
                if (statusCell) {
                    var statusBadge = statusCell.querySelector('.badge');
                    if (statusBadge) {
                        // Update badge text
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1).replace(/_/g, ' ');
                        
                        // Update badge color
                        statusBadge.className = 'badge bg-' + data.badge_color;
                    }
                    
                    // Update or hide quick action buttons based on new status
                    var buttonGroup = statusCell.querySelector('.btn-group');
                    if (buttonGroup) {
                        // Remove old buttons and recreate based on new status
                        buttonGroup.innerHTML = '';
                        
                        if (newStatus !== 'confirmed') {
                            var confirmBtn = document.createElement('button');
                            confirmBtn.type = 'button';
                            confirmBtn.className = 'btn btn-outline-info quick-status-btn btn-sm';
                            confirmBtn.setAttribute('data-order-id', orderId);
                            confirmBtn.setAttribute('data-new-status', 'confirmed');
                            confirmBtn.title = 'Mark as Confirmed';
                            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm';
                            confirmBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                updateOrderStatus(orderId, 'confirmed', this);
                            });
                            buttonGroup.appendChild(confirmBtn);
                        }
                        
                        if (newStatus !== 'delivered' && newStatus !== 'cancelled') {
                            var deliverBtn = document.createElement('button');
                            deliverBtn.type = 'button';
                            deliverBtn.className = 'btn btn-outline-success quick-status-btn btn-sm';
                            deliverBtn.setAttribute('data-order-id', orderId);
                            deliverBtn.setAttribute('data-new-status', 'delivered');
                            deliverBtn.title = 'Mark as Delivered';
                            deliverBtn.innerHTML = '<i class="fas fa-truck"></i> Deliver';
                            deliverBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                updateOrderStatus(orderId, 'delivered', this);
                            });
                            buttonGroup.appendChild(deliverBtn);
                        }
                    }
                }
                
                // Reload page after short delay
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                alert('Error: ' + data.message);
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
            }
        } catch (jsonError) {
            console.error('JSON Parse Error:', jsonError);
            console.error('Response Text:', text);
            buttonElement.disabled = false;
            buttonElement.innerHTML = originalText;
            alert('Server error: Invalid response format. Check browser console for details.');
        }
    })
    .catch(function(error) {
        console.error('Fetch Error:', error);
        alert('An error occurred while updating order status. Error: ' + error.message);
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalText;
    });
}



/**
 * Restore a single order from trash
 */
function restoreSingleOrder(orderId) {
    if (!confirm('Are you sure you want to restore this order from trash?')) return;
    fetch('ajax/restore-orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ order_ids: [orderId] })
    }).then(r => r.json()).then(function(data){
        if (data.success) { alert('Order restored'); location.reload(); }
        else alert('Error: ' + (data.message || 'Failed to restore'));
    }).catch(function(err){ console.error(err); alert('Request failed'); });
}

/**
 * Permanently delete a single order from trash
 */
function permanentDeleteSingleOrder(orderId) {
    if (!confirm('WARNING: Permanently delete this order? This cannot be undone.')) return;
    fetch('ajax/permanent-delete-orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ order_ids: [orderId] })
    }).then(r => r.json()).then(function(data){
        if (data.success) { alert('Order permanently deleted'); location.reload(); }
        else alert('Error: ' + (data.message || 'Failed to delete'));
    }).catch(function(err){ console.error(err); alert('Request failed'); });
}

/**
 * Check vendor availability for order products
 */
function checkVendorAvailability(orderId) {
    var btn = document.querySelector('.check-vendor-btn[data-order-id="' + orderId + '"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    var formData = new FormData();
    formData.append('order_id', orderId);

    fetch('ajax/get-vendor-availability.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i>';
        }

        if (data.success) {
            showVendorAvailabilityModal(data, orderId);
        } else {
            alert('Error: ' + (data.error || 'Failed to fetch vendor availability'));
        }
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i>';
        }
        console.error(err);
        alert('Request failed: ' + err.message);
    });
}

/**
 * Show vendor availability modal with detailed product info
 */
function showVendorAvailabilityModal(data, orderId) {
    var modalBody = document.getElementById('vendorAvailabilityModalBody');
    var html = '';

    // Create vendor cards
    html += '<div class="vendor-availability-list">';
    
    data.vendors.forEach(function(vendor) {
        var allAvailable = vendor.has_all_products;
        var badgeColor = allAvailable ? 'success' : 'warning';
        var badgeText = allAvailable ? '✓ All Available' : '⚠ Partial';

        html += '<div class="vendor-availability-card mb-3 p-3" style="border: 2px solid ' + (allAvailable ? '#28a745' : '#ffc107') + '; border-radius: 8px;">';
        
        // Vendor header
        html += '<div class="row align-items-center mb-3">';
        html += '<div class="col-md-2">';
        if (vendor.logo_url) {
            html += '<img src="' + vendor.logo_url + '" alt="' + vendor.store_name + '" style="max-width: 80px; border-radius: 4px;">';
        } else {
            html += '<div style="width: 80px; height: 80px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-store fa-2x text-muted"></i></div>';
        }
        html += '</div>';
        
        html += '<div class="col-md-6">';
        html += '<h6 class="mb-1"><strong>' + vendor.store_name + '</strong></h6>';
        html += '<div class="mb-1">';
        html += '<span class="badge bg-' + badgeColor + '">' + badgeText + '</span> ';
        if (vendor.rating > 0) {
            html += '<span class="badge bg-info">⭐ ' + vendor.rating.toFixed(1) + '/5 (' + vendor.reviews + ' reviews)</span>';
        }
        html += '</div>';
        html += '<small class="text-muted">Status: <span class="badge bg-secondary">' + vendor.status.toUpperCase() + '</span></small>';
        html += '</div>';
        
        html += '<div class="col-md-4 text-end">';
        html += '<div class="mb-2">';
        html += '<h6 class="mb-1">Total Price</h6>';
        html += '<h5 style="color: #28a745;"><strong>₹' + vendor.total_price.toFixed(2) + '</strong></h5>';
        html += '</div>';
        html += '<button class="btn btn-sm btn-primary" onclick="selectVendorFromModal(' + vendor.id + ', ' + orderId + ')">';
        html += '<i class="fas fa-check-circle"></i> Select';
        html += '</button>';
        html += '</div>';
        html += '</div>';

        // Products table
        html += '<table class="table table-sm table-hover mb-0">';
        html += '<thead class="table-light">';
        html += '<tr>';
        html += '<th>Product</th>';
        html += '<th class="text-center">Need</th>';
        html += '<th class="text-center">Stock</th>';
        html += '<th class="text-end">Price</th>';
        html += '<th class="text-center">Status</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        vendor.products.forEach(function(product) {
            var statusColor = product.status === 'YES ✓' ? '#28a745' : (product.status === 'LIMITED ⚠' ? '#ffc107' : '#dc3545');
            var statusBg = product.status === 'YES ✓' ? 'success' : (product.status === 'LIMITED ⚠' ? 'warning' : 'danger');

            html += '<tr>';
            html += '<td><strong>' + product.product_name + '</strong></td>';
            html += '<td class="text-center">' + product.quantity_needed + '</td>';
            html += '<td class="text-center">' + (product.available ? product.stock : 0) + '</td>';
            html += '<td class="text-end">₹' + (product.price ? product.price.toFixed(2) : 'N/A') + '</td>';
            html += '<td class="text-center"><span class="badge bg-' + statusBg + '" style="font-size: 0.9em;">' + product.status + '</span></td>';
            html += '</tr>';
        });

        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    });

    html += '</div>';

    modalBody.innerHTML = html;
    
    var modal = new bootstrap.Modal(document.getElementById('vendorAvailabilityModal'));
    modal.show();
}

/**
 * Select vendor from modal and populate dropdown
 */
function selectVendorFromModal(vendorId, orderId) {
    var select = document.querySelector('.assign-vendor-select[data-order-id="' + orderId + '"]');
    if (select) {
        select.value = vendorId;
        
        // Scroll to the row
        var btn = document.querySelector('.assign-vendor-btn[data-order-id="' + orderId + '"]');
        if (btn) {
            btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Close modal
    var modal = bootstrap.Modal.getInstance(document.getElementById('vendorAvailabilityModal'));
    if (modal) modal.hide();

    // Show message
    alert('Vendor selected! Click "Assign" button to confirm assignment.');
}
</script>

<!-- Vendor Availability Modal -->
<div class="modal fade" id="vendorAvailabilityModal" tabindex="-1" aria-labelledby="vendorAvailabilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vendorAvailabilityModalLabel">
                    <i class="fas fa-cube"></i> Vendor Product Availability
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="vendorAvailabilityModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>