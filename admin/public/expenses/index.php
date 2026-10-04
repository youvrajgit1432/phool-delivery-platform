<?php
// public/expense/index.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

$pdo = getDBConnection();

// Handle filters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with filters
$where_clause = "WHERE 1=1";
$params = [];

if (!empty($category_filter)) {
    $where_clause .= " AND e.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($type_filter)) {
    $where_clause .= " AND ec.parent_category = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $where_clause .= " AND e.expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_clause .= " AND e.expense_date <= ?";
    $params[] = $date_to;
}

if (!empty($status_filter)) {
    $where_clause .= " AND e.status = ?";
    $params[] = $status_filter;
}

// Get expenses
$expenses = $pdo->prepare("
    SELECT e.*, ec.name as category_name, ec.parent_category, u.full_name as recorded_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN users u ON e.recorded_by = u.id
    $where_clause
    ORDER BY e.expense_date DESC, e.created_at DESC
");
$expenses->execute($params);
$expenses = $expenses->fetchAll();

// Get expense categories for filter
$categories = $pdo->query("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY parent_category, name")->fetchAll();

// Get expense statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(amount) as total_amount,
        SUM(CASE WHEN ec.parent_category = 'phool_delivery' THEN amount ELSE 0 END) as phool_total,
        SUM(CASE WHEN ec.parent_category = 'farm_expenses' THEN amount ELSE 0 END) as farm_total
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.status = 'approved'
")->fetch();

// Set page title
$page_title = "Expense Tracker - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Expense Tracker</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="add.php" class="btn btn-sm btn-primary me-2">
            <i class="fas fa-plus me-1"></i> Add Expense
        </a>
        <a href="reports.php" class="btn btn-sm btn-success">
            <i class="fas fa-chart-bar me-1"></i> Generate Report
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

<!-- Expense Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                            Phool Delivery Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($stats['phool_total'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                            Farm Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($stats['farm_total'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tractor fa-2x text-gray-300"></i>
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
                            Total Records</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_expenses'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Indicator -->
<div id="filterLoading" class="alert alert-info d-none" role="alert">
    <i class="bi bi-hourglass-split"></i> Loading expenses...
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">Filter Expenses</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="type_filter" class="form-label">Expense Type</label>
                    <select class="form-select" id="type_filter">
                        <option value="">All Types</option>
                        <option value="phool_delivery">Phool Delivery</option>
                        <option value="farm_expenses">Farm Expenses</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category_filter" class="form-label">Category</label>
                    <select class="form-select" id="category_filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status_filter" class="form-label">Status</label>
                    <select class="form-select" id="status_filter">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Expense Records</h6>
        <span class="badge bg-primary"><?php echo count($expenses); ?> records found</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="expensesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="expensesTableBody">
                    <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $expense['parent_category'] == 'phool_delivery' ? 'info' : 'success'; ?>">
                                <?php echo htmlspecialchars($expense['category_name']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo !empty($expense['description']) ? htmlspecialchars(substr($expense['description'], 0, 50)) . (strlen($expense['description']) > 50 ? '...' : '') : 'N/A'; ?>
                            <?php if (!empty($expense['remarks'])): ?>
                            <br><small class="text-muted">Remarks: <?php echo htmlspecialchars(substr($expense['remarks'], 0, 30)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-danger">Rs. <?php echo number_format($expense['amount'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $expense['parent_category'] == 'phool_delivery' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $expense['parent_category'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($expense['status']) {
                                    case 'approved': echo 'success'; break;
                                    case 'pending': echo 'warning'; break;
                                    case 'rejected': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst($expense['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($expense['recorded_by_name']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?php echo $expense['id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $expense['id']; ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Expense">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($_SESSION['admin_role'] == 'super_admin' || $_SESSION['admin_role'] == 'admin'): ?>
                                <a href="delete.php?id=<?php echo $expense['id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Expense" onclick="return confirm('Are you sure you want to delete this expense record? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No expense records found. <a href="add.php">Add your first expense</a></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Store current filters in memory
let currentTypeFilter = '';
let currentCategoryFilter = '';
let currentStatusFilter = '';
let currentDateFrom = '';
let currentDateTo = '';

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Type filter
    document.getElementById('type_filter').addEventListener('change', function() {
        currentTypeFilter = this.value;
        loadExpensesAjax();
    });
    
    // Category filter
    document.getElementById('category_filter').addEventListener('change', function() {
        currentCategoryFilter = this.value;
        loadExpensesAjax();
    });
    
    // Status filter
    document.getElementById('status_filter').addEventListener('change', function() {
        currentStatusFilter = this.value;
        loadExpensesAjax();
    });
    
    // Date From filter
    document.getElementById('date_from').addEventListener('change', function() {
        currentDateFrom = this.value;
        loadExpensesAjax();
    });
    
    // Date To filter
    document.getElementById('date_to').addEventListener('change', function() {
        currentDateTo = this.value;
        loadExpensesAjax();
    });
    
    // Initialize tooltips
    initializeTooltips();
});

/**
 * Load expenses via AJAX with current filters
 */
function loadExpensesAjax() {
    // Show loading indicator
    document.getElementById('filterLoading').classList.remove('d-none');
    
    // Build query parameters
    const params = new URLSearchParams();
    if (currentTypeFilter) params.append('type', currentTypeFilter);
    if (currentCategoryFilter) params.append('category', currentCategoryFilter);
    if (currentStatusFilter) params.append('status', currentStatusFilter);
    if (currentDateFrom) params.append('date_from', currentDateFrom);
    if (currentDateTo) params.append('date_to', currentDateTo);
    
    // Fetch data
    fetch(`../../ajax/get-expenses.php?${params.toString()}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid server response');
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Update table
            document.getElementById('expensesTableBody').innerHTML = data.html;
            
            // Reinitialize tooltips
            initializeTooltips();
            
            // Show success message
            showSuccessMessage(`Loaded ${data.count} expense record${data.count !== 1 ? 's' : ''}`);
        } else {
            showErrorMessage(data.message || 'Failed to load expenses');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error loading expenses: ' + error.message);
    })
    .finally(() => {
        // Hide loading indicator
        document.getElementById('filterLoading').classList.add('d-none');
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
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    
    // Insert at top of page
    const mainContent = document.querySelector('main') || document.querySelector('.container-fluid');
    if (mainContent) {
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
    }
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Show error message toast
 */
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    
    // Insert at top of page
    const mainContent = document.querySelector('main') || document.querySelector('.container-fluid');
    if (mainContent) {
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
    }
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>