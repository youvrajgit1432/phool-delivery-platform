<?php
// public/email-status.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$emailService = getEmailService();

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "el.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "el.message_type = ?";
    $params[] = $type_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(el.sent_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get email logs
$stmt = $pdo->prepare("
    SELECT el.*, c.name as customer_name, c.email as customer_email
    FROM email_logs el
    LEFT JOIN customers c ON el.related_id = c.id AND el.message_type = 'verification'
    $where_clause
    ORDER BY el.sent_at DESC
    LIMIT 100
");
$stmt->execute($params);
$email_logs = $stmt->fetchAll();

// Get statistics
$stats_stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        message_type,
        DATE(sent_at) as sent_date
    FROM email_logs 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY status, message_type, DATE(sent_at)
    ORDER BY sent_date DESC, message_type, status
");
$email_stats = $stats_stmt->fetchAll();

// Set page title and current page for navigation
$page_title = "Email Delivery Status - Phool Delivery Admin";
$current_page = 'email-status';

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Email Delivery Status</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="check-config.php" class="btn btn-sm btn-outline-primary me-2">
            <i class="fas fa-cog"></i> Email Configuration
        </a>
        <button class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Emails (7 days)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo array_sum(array_column($email_stats, 'count')); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x text-gray-300"></i>
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
                            Successful
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo array_sum(array_column(array_filter($email_stats, fn($s) => $s['status'] === 'sent'), 'count')); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Failed
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo array_sum(array_column(array_filter($email_stats, fn($s) => $s['status'] === 'failed'), 'count')); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                            Success Rate
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php
                            $total = array_sum(array_column($email_stats, 'count'));
                            $success = array_sum(array_column(array_filter($email_stats, fn($s) => $s['status'] === 'sent'), 'count'));
                            echo $total > 0 ? round(($success / $total) * 100, 1) . '%' : 'N/A';
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filter Logs</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Message Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="verification" <?php echo $type_filter === 'verification' ? 'selected' : ''; ?>>Verification</option>
                            <option value="order_confirmation" <?php echo $type_filter === 'order_confirmation' ? 'selected' : ''; ?>>Order Confirmation</option>
                            <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="email-status.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Email Logs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Email Delivery Logs</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Sent At</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Error Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_logs as $log): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($log['recipient_email']); ?></div>
                            <?php if (!empty($log['customer_name'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($log['customer_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo ucfirst(str_replace('_', ' ', $log['message_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $log['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                            <span class="text-danger small" title="<?php echo htmlspecialchars($log['error_message']); ?>">
                                <?php echo strlen($log['error_message']) > 50 ? substr($log['error_message'], 0, 50) . '...' : $log['error_message']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">No error</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'failed'): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="retryEmailLog(<?php echo $log['id']; ?>)">
                                <i class="fas fa-redo"></i> Retry
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($email_logs)): ?>
        <div class="text-center py-5">
            <i class="fas fa-envelope-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No email logs found</h5>
            <p class="text-muted">Try adjusting your filter criteria.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function refreshStats() {
    window.location.reload();
}

function retryEmailLog(logId) {
    if (confirm('Retry sending this email?')) {
        fetch('../ajax/retry-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ log_id: logId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Email retry initiated successfully!');
                refreshStats();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error retrying email: ' + error);
        });
    }
}
</script>

<?php include '../app/views/layouts/footer.php'; ?>