<?php
// public/expense/reports.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

$pdo = getDBConnection();

// Process report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $report_type = $_POST['report_type'];
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : '';
    
    // Validate dates
    if (empty($start_date) || empty($end_date)) {
        $_SESSION['error_message'] = "Please select both start and end dates.";
        header("Location: reports.php");
        exit;
    }
    
    if ($start_date > $end_date) {
        $_SESSION['error_message'] = "Start date cannot be after end date.";
        header("Location: reports.php");
        exit;
    }
    
    // Build query based on report type
    $where_clause = "WHERE e.expense_date BETWEEN ? AND ? AND e.status = 'approved'";
    $params = [$start_date, $end_date];
    
    if (!empty($category_id)) {
        $where_clause .= " AND e.category_id = ?";
        $params[] = $category_id;
    }
    
    switch ($report_type) {
        case 'summary':
            $query = "
                SELECT 
                    ec.parent_category as type,
                    COUNT(*) as count,
                    SUM(e.amount) as total_amount,
                    AVG(e.amount) as avg_amount
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.id
                $where_clause
                GROUP BY ec.parent_category
                ORDER BY total_amount DESC
            ";
            break;
            
        case 'category_wise':
            $query = "
                SELECT 
                    ec.name as category_name,
                    ec.parent_category as type,
                    COUNT(*) as count,
                    SUM(e.amount) as total_amount
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.id
                $where_clause
                GROUP BY e.category_id
                ORDER BY total_amount DESC
            ";
            break;
            
        case 'daily':
            $query = "
                SELECT 
                    e.expense_date,
                    COUNT(*) as count,
                    SUM(e.amount) as total_amount
                FROM expenses e
                $where_clause
                GROUP BY e.expense_date
                ORDER BY e.expense_date DESC
            ";
            break;
            
        case 'detailed':
        default:
            $query = "
                SELECT 
                    e.*,
                    ec.name as category_name,
                    ec.parent_category as type,
                    u.full_name as recorded_by_name
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.id
                JOIN users u ON e.recorded_by = u.id
                $where_clause
                ORDER BY e.expense_date DESC, e.created_at DESC
            ";
            break;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
    // Calculate totals
    $total_amount = 0;
    $total_count = 0;
    foreach ($report_data as $row) {
        if (isset($row['total_amount'])) {
            $total_amount += $row['total_amount'];
        }
        if (isset($row['count'])) {
            $total_count += $row['count'];
        }
    }
}

// Get expense categories for filter
$categories = $pdo->query("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY parent_category, name")->fetchAll();

// Set page title
$page_title = "Expense Reports - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Expense Reports</h1>
    <a href="index.php" class="btn btn-secondary">Back to Expenses</a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Generate Report</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="report_type" required>
                            <option value="summary">Summary Report</option>
                            <option value="category_wise">Category-wise Report</option>
                            <option value="daily">Daily Summary</option>
                            <option value="detailed">Detailed Report</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category (Optional)</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-01'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-chart-bar me-1"></i> Generate Report
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
            </div>
            <div class="card-body">
                <?php
                $today = date('Y-m-d');
                $month_start = date('Y-m-01');
                
                $stats = $pdo->query("
                    SELECT 
                        COUNT(*) as total_count,
                        SUM(amount) as total_amount,
                        SUM(CASE WHEN expense_date = '$today' THEN amount ELSE 0 END) as today_total,
                        SUM(CASE WHEN expense_date >= '$month_start' THEN amount ELSE 0 END) as month_total
                    FROM expenses 
                    WHERE status = 'approved'
                ")->fetch();
                ?>
                
                <div class="mb-3">
                    <h6 class="text-primary">Today</h6>
                    <p class="mb-0 fw-bold text-danger">Rs. <?php echo number_format($stats['today_total'] ?? 0, 2); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-success">This Month</h6>
                    <p class="mb-0 fw-bold text-danger">Rs. <?php echo number_format($stats['month_total'] ?? 0, 2); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-info">All Time</h6>
                    <p class="mb-0 fw-bold text-danger">Rs. <?php echo number_format($stats['total_amount'] ?? 0, 2); ?></p>
                    <small class="text-muted"><?php echo $stats['total_count'] ?? 0; ?> records</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <?php if (isset($report_data) && !empty($report_data)): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php 
                    $report_titles = [
                        'summary' => 'Summary Report',
                        'category_wise' => 'Category-wise Report', 
                        'daily' => 'Daily Summary Report',
                        'detailed' => 'Detailed Expense Report'
                    ];
                    echo $report_titles[$report_type] . ' (' . $start_date . ' to ' . $end_date . ')';
                    ?>
                </h6>
                <button class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
            <div class="card-body">
                <!-- Report Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center py-3">
                                <h6 class="card-title text-muted">Total Expenses</h6>
                                <h4 class="text-danger fw-bold">Rs. <?php echo number_format($total_amount, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center py-3">
                                <h6 class="card-title text-muted">Total Records</h6>
                                <h4 class="text-primary fw-bold"><?php echo $total_count; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center py-3">
                                <h6 class="card-title text-muted">Average per Record</h6>
                                <h4 class="text-success fw-bold">Rs. <?php echo number_format($total_count > 0 ? $total_amount / $total_count : 0, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Data -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <?php if ($report_type == 'summary'): ?>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Average</th>
                                    <th>Percentage</th>
                                <?php elseif ($report_type == 'category_wise'): ?>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Percentage</th>
                                <?php elseif ($report_type == 'daily'): ?>
                                    <th>Date</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                <?php else: ?>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Recorded By</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php if ($report_type == 'summary'): ?>
                                    <td>
                                        <span class="badge bg-<?php echo $row['type'] == 'phool_delivery' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td class="fw-bold text-danger">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['avg_amount'], 2); ?></td>
                                    <td><?php echo number_format(($row['total_amount'] / $total_amount) * 100, 1); ?>%</td>
                                    
                                <?php elseif ($report_type == 'category_wise'): ?>
                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['type'] == 'phool_delivery' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td class="fw-bold text-danger">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td><?php echo number_format(($row['total_amount'] / $total_amount) * 100, 1); ?>%</td>
                                    
                                <?php elseif ($report_type == 'daily'): ?>
                                    <td><?php echo date('M d, Y', strtotime($row['expense_date'])); ?></td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td class="fw-bold text-danger">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                                    
                                <?php else: ?>
                                    <td><?php echo date('M d, Y', strtotime($row['expense_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['type'] == 'phool_delivery' ? 'info' : 'success'; ?>">
                                            <?php echo htmlspecialchars($row['category_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($row['description']) ? htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '') : 'N/A'; ?></td>
                                    <td class="fw-bold text-danger">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['recorded_by_name']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($report_data)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No data found for the selected criteria.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Generate Expense Report</h5>
                <p class="text-muted">Select report criteria and generate your first expense report.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Set default dates
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (!startDate.value) {
        startDate.value = '<?php echo date('Y-m-01'); ?>';
    }
    if (!endDate.value) {
        endDate.value = '<?php echo date('Y-m-d'); ?>';
    }
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>