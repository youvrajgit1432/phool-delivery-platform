<?php
// transaction.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $order_id = intval($_POST['order_id']);
    $payment_status = $_POST['payment_status'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get current order details
        $order_stmt = $pdo->prepare("SELECT customer_id, order_number, payment_status FROM orders WHERE id = ?");
        $order_stmt->execute([$order_id]);
        $order = $order_stmt->fetch();
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Update payment status (removed admin_notes)
        $update_stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$payment_status, $order_id]);
        
        // Create notification only if status changed
        if ($order['payment_status'] !== $payment_status) {
            $message_title = "Payment Status Updated";
            $message_content = "Your order #" . $order['order_number'] . " payment status has been updated to: " . ucfirst(str_replace('_', ' ', $payment_status));
            
            $message_stmt = $pdo->prepare("INSERT INTO messages (customer_id, title, message, type, related_id, created_at) VALUES (?, ?, ?, 'order', ?, NOW())");
            $message_stmt->execute([$order['customer_id'], $message_title, $message_content, $order_id]);
            
            // If status changed to paid, update requires_verification flag
            if ($payment_status === 'paid') {
                $verification_stmt = $pdo->prepare("UPDATE orders SET requires_verification = 0 WHERE id = ?");
                $verification_stmt->execute([$order_id]);
            }
        }
        
        $pdo->commit();
        
        // Return JSON response for AJAX
        if ($order['payment_status'] !== $payment_status && $payment_status === 'paid_pending_verification') {
            echo json_encode(['success' => true, 'show_alert' => true, 'message' => 'Payment status updated successfully!']);
        } else {
            echo json_encode(['success' => true, 'show_alert' => false, 'message' => 'Payment status updated successfully!']);
        }
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating payment status: ' . $e->getMessage()]);
        exit;
    }
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $order_id = intval($_POST['order_id']);
    $verification_status = $_POST['verification_status'];
    
    try {
        $pdo->beginTransaction();
        
        // Update payment status based on verification
        if ($verification_status === 'confirmed') {
            $update_stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', requires_verification = 0, updated_at = NOW() WHERE id = ?");
            $message = "Payment verified successfully!";
        } else {
            $update_stmt = $pdo->prepare("UPDATE orders SET payment_status = 'failed', requires_verification = 0, updated_at = NOW() WHERE id = ?");
            $message = "Payment marked as failed!";
        }
        
        $update_stmt->execute([$order_id]);
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error verifying payment: ' . $e->getMessage()]);
        exit;
    }
}

// Get filter parameters with validation
$payment_filter = isset($_GET['payment_status']) && in_array($_GET['payment_status'], ['all', 'pending', 'paid_pending_verification', 'paid', 'failed', 'refunded', 'paid_confirmed']) 
    ? $_GET['payment_status'] 
    : 'pending';

$date_filter = isset($_GET['date_filter']) && in_array($_GET['date_filter'], ['', 'today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month'])
    ? $_GET['date_filter']
    : '';

$sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['old_first', 'new_first'])
    ? $_GET['sort_order']
    : 'old_first';

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build the base query and count query
$query = "
    SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";

// Apply filters
$params = [];
$count_params = [];

// Apply payment status filter
if (!empty($payment_filter) && $payment_filter != 'all') {
    $query .= " AND o.payment_status = ?";
    $count_query .= " AND o.payment_status = ?";
    $params[] = $payment_filter;
    $count_params[] = $payment_filter;
}

// Apply date filter
if (!empty($date_filter)) {
    if ($date_filter == 'today') {
        $query .= " AND DATE(o.created_at) = CURDATE()";
        $count_query .= " AND DATE(o.created_at) = CURDATE()";
    } elseif ($date_filter == 'yesterday') {
        $query .= " AND DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $count_query .= " AND DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date_filter == 'this_week') {
        $query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $count_query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($date_filter == 'last_week') {
        $query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
        $count_query .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
    } elseif ($date_filter == 'this_month') {
        $query .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
        $count_query .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    } elseif ($date_filter == 'last_month') {
        $query .= " AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                   AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $count_query .= " AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                         AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    }
}

// Apply search filter
if (!empty($search_term)) {
    $query .= " AND (o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $count_query .= " AND (o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// Apply sorting
if ($sort_order == 'old_first') {
    $query .= " ORDER BY o.created_at ASC";
} else {
    $query .= " ORDER BY o.created_at DESC";
}

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Get total count for pagination
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_orders = $count_stmt->fetch()['total'];
$total_pages = ceil($total_orders / $per_page);

// Prepare and execute the main query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Set page title
$page_title = "Transaction Management - Phool Delivery Admin";

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
    <h1 class="h2">Transaction Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="transaction_export.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-download"></i> Export
            </a>
        </div>
    </div>
</div>

<div id="alertContainer"></div>

<div class="row">
    <div class="col-12">
        <!-- Filter Section -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="all" <?php echo $payment_filter == 'all' ? 'selected' : ''; ?>>All Payments</option>
                            <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid_pending_verification" <?php echo $payment_filter == 'paid_pending_verification' ? 'selected' : ''; ?>>Paid - Pending Verification</option>
                            <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="paid_confirmed" <?php echo $payment_filter == 'paid_confirmed' ? 'selected' : ''; ?>>Paid Confirmed</option>
                            <option value="failed" <?php echo $payment_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $payment_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_filter" class="form-label">Date Filter</label>
                        <select class="form-select" id="date_filter" name="date_filter">
                            <option value="">All Dates</option>
                            <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $date_filter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo $date_filter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo $date_filter == 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo $date_filter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $date_filter == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort_order" class="form-label">Sort By Date</label>
                        <select class="form-select" id="sort_order" name="sort_order">
                            <option value="old_first" <?php echo $sort_order == 'old_first' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="new_first" <?php echo $sort_order == 'new_first' ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Order #, Customer, Phone..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                        <a href="transaction.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">All Orders</h5>
                <span class="badge bg-info"><?php echo $total_orders; ?> total orders • <?php echo count($orders); ?> on this page</span>
            </div>
            <div class="card-body">
                <div class="table-responsive responsive-table-wrapper">
                    <table class="table table-striped table-hover responsive-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th class="d-none d-lg-table-cell">SN</th>
                                <th>Order #</th>
                                <th class="d-none d-md-table-cell">Customer</th>
                                <th>Amount</th>
                                <th class="d-none d-md-table-cell">Status</th>
                                <th>Payment</th>
                                <th class="d-none d-lg-table-cell">Method</th>
                                <th class="d-none d-xl-table-cell">Screenshot</th>
                                <th class="d-none d-lg-table-cell">Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial_number = $offset + 1;
                            foreach ($orders as $order): 
                                $requires_attention = $order['payment_status'] === 'paid_pending_verification' || 
                                                    ($order['payment_status'] === 'pending' && $order['payment_method'] !== 'cod');
                            ?>
                            <tr class="<?php echo $requires_attention ? 'table-warning' : ''; ?>" id="order-row-<?php echo $order['id']; ?>">
                                <td class="d-none d-lg-table-cell"><?php echo $serial_number++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    <?php if ($order['requires_verification']): ?>
                                    <span class="badge bg-warning ms-2" title="Requires verification"><i class="fas fa-exclamation-circle"></i></span>
                                    <?php endif; ?>
                                    <div class="d-md-none small text-muted mt-1">Rs. <?php echo number_format($order['total_amount'], 0); ?></div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                    <?php if (!empty($order['customer_email'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($order['customer_email'], 0, 30)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>Rs. <?php echo number_format($order['total_amount'], 0); ?></strong>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-<?php 
                                    switch($order['status']) {
                                        case 'pending': echo 'warning'; break;
                                        case 'confirmed': echo 'info'; break;
                                        case 'preparing': echo 'primary'; break;
                                        case 'out_for_delivery': echo 'secondary'; break;
                                        case 'delivered': echo 'success'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>" style="font-size: 0.75rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <select name="payment_status" class="form-select form-select-sm payment-status-dropdown" style="font-size: 0.75rem;">
                                        <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid_pending_verification" <?php echo $order['payment_status'] == 'paid_pending_verification' ? 'selected' : ''; ?>>Paid - Pending Ver.</option>
                                        <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="paid_confirmed" <?php echo $order['payment_status'] == 'paid_confirmed' ? 'selected' : ''; ?>>Paid Confirmed</option>
                                        <option value="failed" <?php echo $order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="refunded" <?php echo $order['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <small><?php echo strtoupper(str_replace('_', ' ', $order['payment_method'])); ?></small>
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    <?php if (!empty($order['payment_screenshot'])): 
                                        // Use relative paths from admin/public/
                                        $screenshot_path = '../storage/uploads/payment_screenshots/' . $order['payment_screenshot'];
                                        $public_path = 'payment_screenshots/' . $order['payment_screenshot'];
                                        
                                        // Check if file exists in the storage location
                                        if (file_exists($screenshot_path)) {
                                            // Copy to public directory if not exists
                                            if (!file_exists($public_path)) {
                                                $publicDir = dirname($public_path);
                                                if (!is_dir($publicDir)) {
                                                    mkdir($publicDir, 0755, true);
                                                }
                                                copy($screenshot_path, $public_path);
                                            }
                                            $display_path = $public_path;
                                        } else if (file_exists($public_path)) {
                                            $display_path = $public_path;
                                        } else {
                                            $display_path = false;
                                        }
                                    ?>
                                        <?php if ($display_path): ?>
                                        <a href="<?php echo $display_path; ?>" 
                                           target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-image"></i> View Screenshot
                                        </a>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Screenshot missing</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No screenshot</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info mb-1">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($order['payment_status'] === 'paid_pending_verification'): ?>
                                    <br>
                                    
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($orders) === 0): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No orders found with the selected filters.</p>
                                    <a href="transaction.php" class="btn btn-primary">Reset Filters</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Orders pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filter values change
    const filterForm = document.getElementById('filterForm');
    const filterSelects = filterForm.querySelectorAll('select');
    
    // Store current filter values
    let currentFilters = {
        payment_status: document.getElementById('payment_status').value,
        date_filter: document.getElementById('date_filter').value,
        sort_order: document.getElementById('sort_order').value,
        search: document.getElementById('search').value
    };
    
    // Add change event listeners to all filter selects
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Check if any filter has actually changed
            const newFilters = {
                payment_status: document.getElementById('payment_status').value,
                date_filter: document.getElementById('date_filter').value,
                sort_order: document.getElementById('sort_order').value,
                search: document.getElementById('search').value
            };
            
            // Compare with current filters
            const filtersChanged = 
                newFilters.payment_status !== currentFilters.payment_status ||
                newFilters.date_filter !== currentFilters.date_filter ||
                newFilters.sort_order !== currentFilters.sort_order ||
                newFilters.search !== currentFilters.search;
            
            if (filtersChanged) {
                // Update current filters
                currentFilters = newFilters;
                
                // Show a notification that filters will be applied
                const applyButton = filterForm.querySelector('button[type="submit"]');
                applyButton.textContent = 'Applying...';
                applyButton.disabled = true;
                
                // Auto-submit the form after a brief delay
                setTimeout(() => {
                    filterForm.submit();
                }, 300);
            }
        });
    });
    
    // Add input debounce for search
    const searchInput = document.getElementById('search');
    let searchTimeout = null;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterForm.submit();
        }, 800);
    });
    
    // Function to show alert message
    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alertContainer');
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertContainer.appendChild(alertDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 150);
            }
        }, 5000);
    }
    
    // Handle payment status form submission with AJAX
    const paymentForms = document.querySelectorAll('.payment-status-form');
    paymentForms.forEach(form => {
        form.addEventListener('change', function(e) {
            e.preventDefault();
            
            const orderId = this.querySelector('input[name="order_id"]').value;
            const newStatus = this.querySelector('select').value;
            
            if (confirm(`Are you sure you want to change payment status for order #${orderId} to "${newStatus.replace(/_/g, ' ')}"?`)) {
                // Show loading indicator
                const selectElement = this.querySelector('select');
                const originalText = selectElement.options[selectElement.selectedIndex].text;
                selectElement.options[selectElement.selectedIndex].text = 'Updating...';
                
                // Submit the form via AJAX
                fetch('', {
                    method: 'POST',
                    body: new FormData(this),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.show_alert) {
                            showAlert(data.message, 'success');
                        }
                        
                        // Update the row styling if needed
                        const row = document.getElementById(`order-row-${orderId}`);
                        if (newStatus === 'paid_pending_verification') {
                            row.classList.add('table-warning');
                        } else {
                            row.classList.remove('table-warning');
                        }
                        
                        // Reload the page to reflect changes
                        location.reload();
                    } else {
                        showAlert(data.message, 'danger');
                        // Reset to original value
                        selectElement.value = selectElement.dataset.originalValue;
                        selectElement.options[selectElement.selectedIndex].text = originalText;
                    }
                })
                .catch(error => {
                    showAlert('An error occurred while updating payment status.', 'danger');
                    selectElement.value = selectElement.dataset.originalValue;
                    selectElement.options[selectElement.selectedIndex].text = originalText;
                });
            } else {
                // Reset to original value if user cancels
                this.querySelector('select').value = this.querySelector('select').dataset.originalValue;
            }
        });
        
        // Store original value on focus
        const dropdown = form.querySelector('select');
        dropdown.addEventListener('focus', function() {
            this.dataset.originalValue = this.value;
        });
    });
    
    // Handle payment verification forms with AJAX
    const verifyForms = document.querySelectorAll('.verification-form');
    verifyForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const orderId = this.querySelector('input[name="order_id"]').value;
            const action = this.querySelector('input[name="verification_status"]').value === 'confirmed' ? 'verify' : 'reject';
            const button = this.querySelector('button[type="submit"]');
            
            if (confirm(`Are you sure you want to ${action} payment for order #${orderId}?`)) {
                button.disabled = true;
                button.textContent = action === 'verify' ? 'Verifying...' : 'Rejecting...';
                
                // Submit the form via AJAX
                fetch('', {
                    method: 'POST',
                    body: new FormData(this),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        // Reload the page to reflect changes
                        location.reload();
                    } else {
                        showAlert(data.message, 'danger');
                        button.disabled = false;
                        button.textContent = action === 'verify' ? 'Verify' : 'Reject';
                    }
                })
                .catch(error => {
                    showAlert('An error occurred while processing your request.', 'danger');
                    button.disabled = false;
                    button.textContent = action === 'verify' ? 'Verify' : 'Reject';
                });
            }
        });
    });
});
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>