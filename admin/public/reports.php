<?php
// public/reports.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_type = isset($_GET['customer_type']) ? $_GET['customer_type'] : '';

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'sales':
        $report_title = 'Sales Report';
        
        $where_clause = "WHERE o.created_at BETWEEN ? AND ?";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        
        if (!empty($customer_type)) {
            $where_clause .= " AND c.customer_type = ?";
            $params[] = $customer_type;
        }
        
        $report_data = $pdo->prepare("
            SELECT 
                DATE(o.created_at) as order_date,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_sales,
                AVG(o.total_amount) as avg_order_value,
                COUNT(DISTINCT o.customer_id) as unique_customers
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            $where_clause
            GROUP BY DATE(o.created_at)
            ORDER BY order_date DESC
        ");
        $report_data->execute($params);
        $report_data = $report_data->fetchAll();
        break;
        
    case 'products':
        $report_title = 'Product Performance Report';
        
        $where_clause = "WHERE o.created_at BETWEEN ? AND ?";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        
        $report_data = $pdo->prepare("
            SELECT 
                p.name as product_name,
                c.name as category_name,
                COUNT(oi.id) as units_sold,
                SUM(oi.total_price) as total_revenue,
                AVG(oi.unit_price) as avg_price
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            $where_clause
            GROUP BY oi.product_id
            ORDER BY total_revenue DESC
        ");
        $report_data->execute($params);
        $report_data = $report_data->fetchAll();
        break;
        
    case 'customers':
        $report_title = 'Customer Analysis Report';
        
        $where_clause = "WHERE o.created_at BETWEEN ? AND ?";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        
        if (!empty($customer_type)) {
            $where_clause .= " AND c.customer_type = ?";
            $params[] = $customer_type;
        }
        
        $report_data = $pdo->prepare("
            SELECT 
                c.id,
                c.name as customer_name,
                c.customer_type,
                c.phone,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order_value,
                MAX(o.created_at) as last_order_date
            FROM customers c
            LEFT JOIN orders o ON c.id = o.customer_id
            $where_clause
            GROUP BY c.id
            HAVING order_count > 0
            ORDER BY total_spent DESC
        ");
        $report_data->execute($params);
        $report_data = $report_data->fetchAll();
        break;
        
    case 'inventory':
        $report_title = 'Inventory Report';
        
        $report_data = $pdo->prepare("
            SELECT 
                p.name as product_name,
                c.name as category_name,
                p.stock_quantity,
                p.min_stock_alert,
                p.price,
                p.status,
                CASE 
                    WHEN p.stock_quantity <= p.min_stock_alert THEN 'Low Stock'
                    WHEN p.stock_quantity = 0 THEN 'Out of Stock'
                    ELSE 'In Stock'
                END as stock_status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.stock_quantity ASC, p.name ASC
        ");
        $report_data->execute();
        $report_data = $report_data->fetchAll();
        break;
}

// Get total summary for sales report
$summary = [];
if ($report_type === 'sales') {
    $where_clause = "WHERE o.created_at BETWEEN ? AND ?";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if (!empty($customer_type)) {
        $where_clause .= " AND c.customer_type = ?";
        $params[] = $customer_type;
    }
    
    $summary_query = $pdo->prepare("
        SELECT 
            COUNT(o.id) as total_orders,
            SUM(o.total_amount) as total_sales,
            AVG(o.total_amount) as avg_order_value,
            COUNT(DISTINCT o.customer_id) as unique_customers
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        $where_clause
    ");
    $summary_query->execute($params);
    $summary = $summary_query->fetch();
}

// Set page title
$page_title = "Reports - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Reports & Analytics</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Report Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="type">
                            <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="products" <?php echo $report_type == 'products' ? 'selected' : ''; ?>>Product Performance</option>
                            <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>Customer Analysis</option>
                            <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label for="customer_type" class="form-label">Customer Type</label>
                        <select class="form-select" id="customer_type" name="customer_type">
                            <option value="">All Types</option>
                            <option value="normal" <?php echo $customer_type == 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="bulk" <?php echo $customer_type == 'bulk' ? 'selected' : ''; ?>>Bulk Buyer</option>
                            <option value="event_planner" <?php echo $customer_type == 'event_planner' ? 'selected' : ''; ?>>Event Planner</option>
                            <option value="wholesaler" <?php echo $customer_type == 'wholesaler' ? 'selected' : ''; ?>>Wholesaler</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Generate Report
                            </button>
                            <a href="reports.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Summary -->
<?php if ($report_type === 'sales' && !empty($summary)): ?>
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Orders</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $summary['total_orders']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                            Total Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($summary['total_sales'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                            Avg Order Value</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs. <?php echo number_format($summary['avg_order_value'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                            Unique Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $summary['unique_customers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Report Data -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo $report_title; ?> 
            <small class="text-muted">(<?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>)</small>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="reportTable">
                <thead>
                    <tr>
                        <?php if ($report_type === 'sales'): ?>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Total Sales</th>
                        <th>Avg Order Value</th>
                        <th>Unique Customers</th>
                        
                        <?php elseif ($report_type === 'products'): ?>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Units Sold</th>
                        <th>Total Revenue</th>
                        <th>Avg Price</th>
                        
                        <?php elseif ($report_type === 'customers'): ?>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Avg Order Value</th>
                        <th>Last Order</th>
                        
                        <?php elseif ($report_type === 'inventory'): ?>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Stock Quantity</th>
                        <th>Min Stock Alert</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Stock Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="<?php echo $report_type === 'sales' ? 5 : ($report_type === 'products' ? 5 : ($report_type === 'customers' ? 6 : 7)); ?>" class="text-center">
                            No data found for the selected criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($report_data as $row): ?>
                    <tr>
                        <?php if ($report_type === 'sales'): ?>
                        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                        <td><?php echo $row['order_count']; ?></td>
                        <td>Rs. <?php echo number_format($row['total_sales'], 2); ?></td>
                        <td>Rs. <?php echo number_format($row['avg_order_value'], 2); ?></td>
                        <td><?php echo $row['unique_customers']; ?></td>
                        
                        <?php elseif ($report_type === 'products'): ?>
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td><?php echo $row['units_sold']; ?></td>
                        <td>Rs. <?php echo number_format($row['total_revenue'], 2); ?></td>
                        <td>Rs. <?php echo number_format($row['avg_price'], 2); ?></td>
                        
                        <?php elseif ($report_type === 'customers'): ?>
                        <td>
                            <div><?php echo htmlspecialchars($row['customer_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($row['phone']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($row['customer_type']) {
                                case 'normal': echo 'secondary'; break;
                                case 'bulk': echo 'primary'; break;
                                case 'event_planner': echo 'info'; break;
                                case 'wholesaler': echo 'success'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['customer_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo $row['order_count']; ?></td>
                        <td>Rs. <?php echo number_format($row['total_spent'], 2); ?></td>
                        <td>Rs. <?php echo number_format($row['avg_order_value'], 2); ?></td>
                        <td><?php echo $row['last_order_date'] ? date('M d, Y', strtotime($row['last_order_date'])) : 'N/A'; ?></td>
                        
                        <?php elseif ($report_type === 'inventory'): ?>
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td>
                            <span class="<?php echo $row['stock_quantity'] <= $row['min_stock_alert'] ? 'text-danger fw-bold' : ''; ?>">
                                <?php echo $row['stock_quantity']; ?>
                            </span>
                        </td>
                        <td><?php echo $row['min_stock_alert']; ?></td>
                        <td>Rs. <?php echo number_format($row['price'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($row['status']) {
                                case 'active': echo 'success'; break;
                                case 'inactive': echo 'secondary'; break;
                                case 'out_of_stock': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($row['stock_status']) {
                                case 'Low Stock': echo 'warning'; break;
                                case 'Out of Stock': echo 'danger'; break;
                                default: echo 'success';
                            }
                            ?>">
                                <?php echo $row['stock_status']; ?>
                            </span>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Simple Excel export implementation
    let table = document.getElementById('reportTable');
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + escape(html);
    let link = document.createElement('a');
    link.href = url;
    link.download = '<?php echo $report_type; ?>_report_<?php echo date('Y-m-d'); ?>.xls';
    link.click();
}
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>