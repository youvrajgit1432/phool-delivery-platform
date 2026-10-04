<?php
// public/customer-verification.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Helper functions to get email and SMS status
function getEmailStatus($pdo, $customer_id) {
    $stmt = $pdo->prepare("
        SELECT status FROM email_logs 
        WHERE related_id = ? AND message_type = 'verification' 
        ORDER BY sent_at DESC LIMIT 1
    ");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch();
    
    return $result ? $result['status'] : 'not_sent';
}

function getSmsStatus($pdo, $customer_id) {
    $stmt = $pdo->prepare("
        SELECT status FROM sms_logs 
        WHERE related_id = ? AND message_type = 'verification' 
        ORDER BY sent_at DESC LIMIT 1
    ");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch();
    
    return $result ? $result['status'] : 'not_sent';
}

// Handle filters and pagination
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Set items per page
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

// Build query for customers with filters
$where_conditions = [];
$params = [];

if (!empty($status_filter) && $status_filter !== 'all') {
    $where_conditions[] = "c.verification_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM customers c 
    $where_clause
");
$count_stmt->execute($params);
$total_customers = $count_stmt->fetch()['total'];
$total_pages = ceil($total_customers / $items_per_page);

// Get customers with verification filter
$customers_stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(o.id) as order_count,
           u.full_name as verified_by_name
    FROM customers c 
    LEFT JOIN orders o ON c.id = o.customer_id 
    LEFT JOIN users u ON c.verified_by = u.id 
    $where_clause 
    GROUP BY c.id 
    ORDER BY 
        CASE WHEN c.verification_status = 'pending' THEN 1 
             WHEN c.verification_status = 'rejected' THEN 2
             ELSE 3 END,
        c.created_at DESC
    LIMIT ? OFFSET ?
");

$all_params = array_merge($params, [$items_per_page, $offset]);
$customers_stmt->execute($all_params);
$customers = $customers_stmt->fetchAll();

// Get verification statistics
$stats_stmt = $pdo->query("
    SELECT 
        verification_status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM customers), 1) as percentage
    FROM customers 
    GROUP BY verification_status
");
$verification_stats = $stats_stmt->fetchAll();

// Set page title
$page_title = "Customer Verification Center - Phool Delivery Admin";

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
}

@media (max-width: 768px) {
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 0.65rem;
        font-size: 0.90rem;
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
    <h1 class="h2">Customer Verification Center</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="customer-verification.php?status=all" class="btn btn-sm btn-outline-secondary <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="customer-verification.php?status=pending" class="btn btn-sm btn-outline-warning <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="customer-verification.php?status=verified" class="btn btn-sm btn-outline-success <?php echo $status_filter == 'verified' ? 'active' : ''; ?>">Verified</a>
            <a href="customer-verification.php?status=rejected" class="btn btn-sm btn-outline-danger <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
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

<!-- Verification Statistics -->
<div class="row mb-4">
    <?php foreach ($verification_stats as $stat): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-<?php 
            echo $stat['verification_status'] == 'verified' ? 'success' : 
                 ($stat['verification_status'] == 'pending' ? 'warning' : 'danger'); 
        ?> shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-<?php 
                            echo $stat['verification_status'] == 'verified' ? 'success' : 
                                 ($stat['verification_status'] == 'pending' ? 'warning' : 'danger'); 
                        ?> text-uppercase mb-1">
                            <?php echo ucfirst($stat['verification_status']); ?> Customers
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stat['count']; ?></div>
                        <div class="mt-1 text-muted"><?php echo $stat['percentage']; ?>% of total</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-<?php 
                            echo $stat['verification_status'] == 'verified' ? 'user-check' : 
                                 ($stat['verification_status'] == 'pending' ? 'user-clock' : 'user-times'); 
                        ?> fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Search and Filter Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Search & Filter</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search customers by name, email, or phone..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="customer-verification.php?status=<?php echo $status_filter; ?>" class="btn btn-secondary">Clear Search</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Customers 
            <small class="text-muted">(<?php echo $total_customers; ?> found)</small>
        </h5>
        <div>
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($customers)): ?>
        <div class="text-center py-5">
            <i class="fas fa-users fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No customers found</h5>
            <p class="text-muted"><?php echo $search ? 'Try adjusting your search criteria.' : 'No customers match the selected criteria.'; ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive responsive-table-wrapper">
            <table class="table table-striped table-hover" id="verificationTable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact Info</th>
                        <th>Orders</th>
                        <th>Verification Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($customer['name']); ?></div>
                            <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $customer['customer_type'])); ?></small>
                            
                            <!-- Email Status Badge -->
                            <?php if (!empty($customer['email'])): ?>
                            <?php
                            $email_status = getEmailStatus($pdo, $customer['id']);
                            $email_status_class = $email_status === 'sent' ? 'success' : ($email_status === 'failed' ? 'danger' : 'secondary');
                            ?>
                            <div class="mt-1">
                                <span class="badge bg-<?php echo $email_status_class; ?> badge-sm">
                                    <i class="fas fa-envelope me-1"></i>
                                    Email: <?php echo ucfirst($email_status); ?>
                                </span>
                                <?php if ($email_status === 'failed'): ?>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" title="Retry email" onclick="retryEmail(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- SMS Status Badge -->
                            <?php if (!empty($customer['phone'])): ?>
                            <?php
                            $sms_status = getSmsStatus($pdo, $customer['id']);
                            $sms_status_class = $sms_status === 'sent' ? 'success' : ($sms_status === 'failed' ? 'danger' : 'secondary');
                            ?>
                            <div class="mt-1">
                                <span class="badge bg-<?php echo $sms_status_class; ?> badge-sm">
                                    <i class="fas fa-sms me-1"></i>
                                    SMS: <?php echo ucfirst($sms_status); ?>
                                </span>
                                <?php if ($sms_status === 'failed'): ?>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" title="Retry SMS" onclick="retrySms(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($customer['verified_by_name']): ?>
 <br><small class="text-muted">Verified by: <?php echo htmlspecialchars($customer['verified_by_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div>
                                <i class="fas fa-phone text-muted me-1"></i>
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </div>
                            <?php if (!empty($customer['email'])): ?>
                            <div>
                                <i class="fas fa-envelope text-muted me-1"></i>
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $customer['order_count']; ?> orders</span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($customer['verification_status']) {
                                    case 'verified': echo 'success'; break;
                                    case 'rejected': echo 'danger'; break;
                                    default: echo 'warning';
                                }
                            ?>">
                                <?php echo ucfirst($customer['verification_status']); ?>
                            </span>
                            <?php if ($customer['verification_method']): ?>
                            <br><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $customer['verification_method'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="customers.php?action=view&id=<?php echo $customer['id']; ?>" class="btn btn-info" title="View Customer Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($customer['verification_status'] === 'pending'): ?>
                                <a href="actions/verify-customer.php?id=<?php echo $customer['id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-success" title="Verify Customer">
                                    <i class="fas fa-check-circle"></i>
                                </a>
                                <a href="actions/reject-verification.php?id=<?php echo $customer['id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-danger" title="Reject Verification">
                                    <i class="fas fa-times-circle"></i>
                                </a>
                                <?php if (!empty($customer['email'])): ?>
                                <form method="POST" action="actions/send-verification-email.php" class="d-inline">
                                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                    <button type="submit" class="btn btn-warning" title="Send Verification Email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (!empty($customer['phone'])): ?>
                                <form method="POST" action="actions/send-verification-sms.php" class="d-inline">
                                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                    <button type="submit" class="btn btn-primary" title="Send Verification SMS">
                                        <i class="fas fa-sms"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function retryEmail(customerId) {
    if (confirm('Resend verification email to this customer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'actions/send-verification-email.php';
        
        const customerInput = document.createElement('input');
        customerInput.type = 'hidden';
        customerInput.name = 'customer_id';
        customerInput.value = customerId;
        form.appendChild(customerInput);
        
        const redirectInput = document.createElement('input');
        redirectInput.type = 'hidden';
        redirectInput.name = 'redirect_url';
        redirectInput.value = window.location.href;
        form.appendChild(redirectInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function retrySms(customerId) {
    if (confirm('Resend verification SMS to this customer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'actions/send-verification-sms.php';
        
        const customerInput = document.createElement('input');
        customerInput.type = 'hidden';
        customerInput.name = 'customer_id';
        customerInput.value = customerId;
        form.appendChild(customerInput);
        
        const redirectInput = document.createElement('input');
        redirectInput.type = 'hidden';
        redirectInput.name = 'redirect_url';
        redirectInput.value = window.location.href;
        form.appendChild(redirectInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>