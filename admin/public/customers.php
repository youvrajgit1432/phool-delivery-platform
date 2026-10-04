<?php
// public/customers.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get customers with filters
$where_clause = "";
$params = [];
$conditions = [];

if (!empty($type_filter)) {
    $conditions[] = "customer_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

$customers = $pdo->prepare("
    SELECT * FROM customers 
    $where_clause 
    ORDER BY created_at DESC
");
$customers->execute($params);
$customers = $customers->fetchAll();

// Get customer stats
$customer_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned,
        SUM(CASE WHEN customer_type = 'normal' THEN 1 ELSE 0 END) as normal,
        SUM(CASE WHEN customer_type = 'bulk' THEN 1 ELSE 0 END) as bulk,
        SUM(CASE WHEN customer_type = 'event_planner' THEN 1 ELSE 0 END) as event_planner,
        SUM(CASE WHEN customer_type = 'wholesaler' THEN 1 ELSE 0 END) as wholesaler
    FROM customers
")->fetch();

// Set page title
$page_title = "Customers Management - Phool Delivery Admin";

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
    <h1 class="h2">Customers Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="add_customer.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add New Customer
        </a>
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

<!-- Customer Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $customer_stats['total']; ?></div>
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
                            Active Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $customer_stats['active']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                            Bulk Buyers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $customer_stats['bulk']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
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
                            Event Planners</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $customer_stats['event_planner']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Indicator -->
<div id="filterLoading" class="alert alert-info d-none" role="alert">
    <i class="bi bi-hourglass-split"></i> Loading customers...
</div>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="typeFilter">
            <option value="" <?php echo empty($type_filter) ? 'selected' : ''; ?>>All Types</option>
            <option value="normal" <?php echo $type_filter == 'normal' ? 'selected' : ''; ?>>Normal</option>
            <option value="bulk" <?php echo $type_filter == 'bulk' ? 'selected' : ''; ?>>Bulk Buyer</option>
            <option value="event_planner" <?php echo $type_filter == 'event_planner' ? 'selected' : ''; ?>>Event Planner</option>
            <option value="wholesaler" <?php echo $type_filter == 'wholesaler' ? 'selected' : ''; ?>>Wholesaler</option>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" id="statusFilter">
            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            <option value="banned" <?php echo $status_filter == 'banned' ? 'selected' : ''; ?>>Banned</option>
        </select>
    </div>
    <div class="col-md-6">
        <input type="text" class="form-control" placeholder="Search customers..." id="searchInput">
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive responsive-table-wrapper">
            <table class="table table-striped table-hover responsive-table" id="customersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Contact</th>
                        <th class="d-none d-lg-table-cell">Address</th>
                        <th class="d-none d-md-table-cell">Type</th>
                        <th>Status</th>
                        <th class="d-none d-lg-table-cell">Verification</th>
                        <th class="d-none d-xl-table-cell">Loyalty Points</th>
                        <th class="d-none d-lg-table-cell">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                            <div class="d-md-none small text-muted mt-1"><?php echo htmlspecialchars($customer['phone']); ?></div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div><?php echo htmlspecialchars($customer['phone']); ?></div>
                            <?php if (!empty($customer['email'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell"><?php echo !empty($customer['address']) ? htmlspecialchars(substr($customer['address'], 0, 50)) . (strlen($customer['address']) > 50 ? '...' : '') : 'N/A'; ?></td>
                        <td class="d-none d-md-table-cell">
                            <span class="badge bg-<?php 
                            switch($customer['customer_type']) {
                                case 'normal': echo 'secondary'; break;
                                case 'bulk': echo 'primary'; break;
                                case 'event_planner': echo 'info'; break;
                                case 'wholesaler': echo 'success'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $customer['customer_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($customer['status']) {
                                case 'active': echo 'success'; break;
                                case 'inactive': echo 'warning'; break;
                                case 'banned': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span class="badge bg-<?php 
                                switch($customer['verification_status']) {
                                    case 'verified': echo 'success'; break;
                                    case 'rejected': echo 'danger'; break;
                                    default: echo 'warning';
                                }
                            ?>">
                                <?php echo ucfirst($customer['verification_status']); ?>
                            </span>
                            <?php if ($customer['verification_status'] === 'pending'): ?>
                            <br>
                            <a href="customer-verification.php?status=pending&search=<?php echo urlencode($customer['name']); ?>" class="small">
                                <i class="fas fa-user-check me-1"></i> Verify Now
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-xl-table-cell"><?php echo $customer['loyalty_points']; ?></td>
                        <td class="d-none d-lg-table-cell"><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm flex-wrap">
                                <a href="view_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Customer">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Customer" onclick="deleteCustomer(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Store current filters in memory
let currentTypeFilter = '';
let currentStatusFilter = '';
let currentSearch = '';

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');

    // Type filter change
    typeFilter.addEventListener('change', function() {
        currentTypeFilter = this.value;
        loadCustomersAjax();
    });

    // Status filter change
    statusFilter.addEventListener('change', function() {
        currentStatusFilter = this.value;
        loadCustomersAjax();
    });

    // Real-time search
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        currentSearch = this.value.trim();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadCustomersAjax, 300);
    });

    // Initialize tooltips
    initializeTooltips();
});

/**
 * Load customers via AJAX
 */
function loadCustomersAjax() {
    const loadingDiv = document.getElementById('filterLoading');
    if (loadingDiv) {
        loadingDiv.classList.remove('d-none');
    }

    const params = new URLSearchParams({
        type: currentTypeFilter,
        status: currentStatusFilter,
        search: currentSearch
    });

    fetch('ajax/get-customers.php?' + params, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                document.getElementById('customersTableBody').innerHTML = data.html;
                initializeTooltips();
                showSuccessMessage('Customers loaded successfully (' + data.count + ' found)');
            } else {
                showErrorMessage(data.message || 'Error loading customers');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response text:', text.substring(0, 200));
            showErrorMessage('Error parsing server response');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage('Error loading customers: ' + error.message);
    })
    .finally(() => {
        if (loadingDiv) {
            loadingDiv.classList.add('d-none');
        }
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Show success message toast
 */
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.border-bottom');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container.nextSibling);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

/**
 * Show error message toast
 */
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.border-bottom');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container.nextSibling);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

/**
 * Delete customer via AJAX
 */
function deleteCustomer(customerId) {
    if (!confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        return;
    }

    // Redirect to delete page (keeping original functionality)
    window.location.href = 'delete_customer.php?id=' + customerId;
}
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>