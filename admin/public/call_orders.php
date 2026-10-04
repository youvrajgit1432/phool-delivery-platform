<?php
// public/call_orders.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Get call orders with filters
$where_clause = "";
$params = [];
$conditions = [];

if (!empty($status_filter)) {
    $conditions[] = "co.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $conditions[] = "co.payment_method = ?";
    $params[] = $payment_filter;
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(co.order_date) = ?";
    $params[] = $date_filter;
}

if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

$orders = $pdo->prepare("
    SELECT 
        co.*,
        cc.name as customer_name,
        cc.phone_number,
        cc.city,
        cc.street,
        p.name_en as product_name,
        ci.invoice_number,
        ci.status as invoice_status
    FROM call_customer_orders co
    LEFT JOIN call_customers cc ON co.call_customer_id = cc.id
    LEFT JOIN products p ON co.product_id = p.id
    LEFT JOIN call_invoices ci ON co.id = ci.call_order_id
    $where_clause 
    ORDER BY co.order_date DESC
");
$orders->execute($params);
$orders = $orders->fetchAll();

// Get order stats
$order_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
        SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN payment_method = 'Cash on Delivery' THEN 1 ELSE 0 END) as cod,
        SUM(CASE WHEN payment_method = 'Wallets' THEN 1 ELSE 0 END) as wallets,
        SUM(CASE WHEN payment_method = 'Bank Transfer' THEN 1 ELSE 0 END) as bank_transfer,
        SUM(total_amount) as total_revenue
    FROM call_customer_orders
")->fetch();

// Set page title
$page_title = "Call Orders Management - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<style>
/* Responsive Table CSS */
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
    <h1 class="h2">Call Orders Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="call/add_call_order.php" class="btn btn-sm btn-primary">
            <i class="fas fa-phone me-1"></i> Add New Call Order
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

<!-- Order Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Call Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $order_stats['total']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-phone fa-2x text-gray-300"></i>
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
                            Total Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($order_stats['total_revenue'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                            Pending Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $order_stats['pending']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            COD Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $order_stats['cod']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="statusFilter">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="preparing">Preparing</option>
            <option value="out_for_delivery">Out for Delivery</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" id="paymentFilter">
            <option value="">All Payment Methods</option>
            <option value="Cash on Delivery">Cash on Delivery</option>
            <option value="Wallets">Wallets</option>
            <option value="Bank Transfer">Bank Transfer</option>
        </select>
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" id="dateFilter">
    </div>
    <div class="col-md-3">
        <input type="text" class="form-control" placeholder="Search orders..." id="searchInput" autocomplete="off">
    </div>
</div>

<!-- Loading Indicator -->
<div id="filterLoading" class="alert alert-info d-none" role="alert">
    <i class="bi bi-hourglass-split"></i> Loading call orders...
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive responsive-table-wrapper">
            <table class="table table-striped table-hover" id="ordersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Invoice</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="callOrdersTableBody">
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                        <td><?php echo $order['quantity']; ?></td>
                        <td>Rs. <?php echo number_format($order['rate'], 2); ?></td>
                        <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($order['payment_method']) {
                                case 'Cash on Delivery': echo 'warning'; break;
                                case 'Wallets': echo 'info'; break;
                                case 'Bank Transfer': echo 'success'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo $order['payment_method']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($order['status']) {
                                case 'pending': echo 'warning'; break;
                                case 'confirmed': echo 'primary'; break;
                                case 'preparing': echo 'info'; break;
                                case 'out_for_delivery': echo 'secondary'; break;
                                case 'delivered': echo 'success'; break;
                                case 'cancelled': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($order['invoice_number']): ?>
                                <span class="badge bg-success" data-bs-toggle="tooltip" title="Invoice: <?php echo $order['invoice_number']; ?>">
                                    <i class="fas fa-file-invoice me-1"></i> Generated
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No Invoice</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="call/view_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="call/edit_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Order">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($order['invoice_number']): ?>
                                    <a href="call/generate_call_invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-success" data-bs-toggle="tooltip" title="Download Invoice">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="call/generate_call_invoice.php?id=<?php echo $order['id']; ?>&action=create" class="btn btn-warning" data-bs-toggle="tooltip" title="Create Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="call/delete_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Order" onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
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

<script>
// Store current filters in memory
let currentStatusFilter = '';
let currentPaymentFilter = '';
let currentDateFilter = '';
let currentSearch = '';

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentStatusFilter = this.value;
            loadCallOrdersAjax();
        });
    }

    // Payment filter
    const paymentFilter = document.getElementById('paymentFilter');
    if (paymentFilter) {
        paymentFilter.addEventListener('change', function() {
            currentPaymentFilter = this.value;
            loadCallOrdersAjax();
        });
    }

    // Date filter
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter) {
        dateFilter.addEventListener('change', function() {
            currentDateFilter = this.value;
            loadCallOrdersAjax();
        });
    }

    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            currentSearch = this.value;
            loadCallOrdersAjax();
        });
    }

    // Initialize tooltips
    initializeTooltips();
});

/**
 * Load call orders via AJAX
 */
function loadCallOrdersAjax() {
    const loadingDiv = document.getElementById('filterLoading');
    if (loadingDiv) {
        loadingDiv.classList.remove('d-none');
    }

    const params = new URLSearchParams({
        status: currentStatusFilter,
        payment: currentPaymentFilter,
        date: currentDateFilter,
        search: currentSearch
    });

    fetch('ajax/get-call-orders.php?' + params, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP Error: ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        // Try to parse JSON
        try {
            const data = JSON.parse(text);
            if (data.success) {
                const tbody = document.getElementById('callOrdersTableBody');
                if (tbody) {
                    tbody.innerHTML = data.html;
                    initializeTooltips();
                }
            } else {
                showErrorMessage('Error loading call orders: ' + (data.message || 'Unknown error'));
            }
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text.substring(0, 500));
            showErrorMessage('Server error: Invalid response');
        }
        if (loadingDiv) {
            loadingDiv.classList.add('d-none');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        showErrorMessage('Error loading call orders: ' + error.message);
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
        <i class="bi bi-check-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.card:first-of-type');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
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
        <i class="bi bi-exclamation-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.card:first-of-type');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

// Delete call order with AJAX reload
function deleteCallOrder(orderId) {
    if (!confirm('Are you sure you want to delete this order?')) return;
    
    fetch('call/delete_call_order.php?id=' + orderId, {
        method: 'DELETE',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || data.message) {
            showSuccessMessage('Call order deleted successfully');
            loadCallOrdersAjax();
        }
    })
    .catch(err => {
        // If the delete endpoint doesn't return JSON, try simple page reload
        console.error('Delete note:', err);
        showSuccessMessage('Call order deleted');
        loadCallOrdersAjax();
    });
}
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>