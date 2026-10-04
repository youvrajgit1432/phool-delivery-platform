<?php
/**
 * Vendor Analytics Dashboard
 * Admin can view detailed analytics for vendor performance
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}

try {
    $db = getDBConnection();
    
    // Get vendor info
    $vendor_query = "SELECT * FROM vendors WHERE id = ?";
    $vendor_stmt = $db->prepare($vendor_query);
    $vendor_stmt->execute([$vendor_id]);
    $vendor = $vendor_stmt->fetch();
    
    if (!$vendor) {
        header('Location: ../vendors.php?error=vendor_not_found');
        exit;
    }
    
    // Get orders analytics
    $orders_query = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                        SUM(total_amount) as total_revenue
                     FROM vendor_orders 
                     WHERE vendor_id = ?";
    $orders_stmt = $db->prepare($orders_query);
    $orders_stmt->execute([$vendor_id]);
    $orders = $orders_stmt->fetch();
    
    // Get products analytics
    $products_query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(quantity_in_stock) as total_stock,
                        COUNT(DISTINCT category) as categories
                      FROM vendor_products 
                      WHERE vendor_id = ? AND status = 'active'";
    $products_stmt = $db->prepare($products_query);
    $products_stmt->execute([$vendor_id]);
    $products = $products_stmt->fetch();
    
    // Get payout analytics
    $payout_query = "SELECT 
                        COUNT(*) as total_payouts,
                        SUM(payout_amount) as total_paid,
                        SUM(CASE WHEN status = 'pending' THEN payout_amount ELSE 0 END) as pending_amount,
                        SUM(CASE WHEN status = 'completed' THEN payout_amount ELSE 0 END) as completed_amount
                     FROM vendor_payouts 
                     WHERE vendor_id = ?";
    $payout_stmt = $db->prepare($payout_query);
    $payout_stmt->execute([$vendor_id]);
    $payout = $payout_stmt->fetch();
    
    // Get top products
    $top_products_query = "SELECT 
                            id, product_name, price, 
                            (SELECT COUNT(*) FROM vendor_order_items WHERE product_id = vp.id) as times_ordered
                          FROM vendor_products vp 
                          WHERE vendor_id = ? AND status = 'active'
                          ORDER BY times_ordered DESC 
                          LIMIT 10";
    $top_products_stmt = $db->prepare($top_products_query);
    $top_products_stmt->execute([$vendor_id]);
    $top_products = $top_products_stmt->fetchAll();
    
    // Get monthly sales
    $monthly_query = "SELECT 
                        MONTH(assigned_at) as month,
                        SUM(total_amount) as revenue,
                        COUNT(*) as orders
                      FROM vendor_orders 
                      WHERE vendor_id = ? AND YEAR(assigned_at) = YEAR(NOW())
                      GROUP BY MONTH(assigned_at)
                      ORDER BY month";
    $monthly_stmt = $db->prepare($monthly_query);
    $monthly_stmt->execute([$vendor_id]);
    $monthly_data = $monthly_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Database error occurred";
}

$page_title = 'Vendor Analytics';
$current_page = 'vendor-analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .metric-card.revenue { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .metric-card.orders { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .metric-card.products { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .metric-card.payouts { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .metric-value {
            font-size: 32px;
            font-weight: bold;
        }
        .metric-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .product-table {
            font-size: 14px;
        }
        .product-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                    <h1><i class="fas fa-chart-bar"></i> Vendor Analytics</h1>
                    <a href="../vendors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Vendors
                    </a>
                </div>

                <!-- Vendor Info -->
                <div class="alert alert-info mb-4">
                    <strong>Store:</strong> <?php echo htmlspecialchars($vendor['store_name']); ?> 
                    | <strong>Email:</strong> <?php echo htmlspecialchars($vendor['email']); ?>
                    | <strong>Status:</strong> <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($vendor['status']); ?>
                    </span>
                </div>

                <!-- Key Metrics -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric-card revenue">
                            <div class="metric-value">₹<?php echo number_format($orders['total_revenue'] ?? 0, 2); ?></div>
                            <div class="metric-label">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card orders">
                            <div class="metric-value"><?php echo $orders['total_orders'] ?? 0; ?></div>
                            <div class="metric-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card products">
                            <div class="metric-value"><?php echo $products['total_products'] ?? 0; ?></div>
                            <div class="metric-label">Active Products</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card payouts">
                            <div class="metric-value">₹<?php echo number_format($payout['total_paid'] ?? 0, 2); ?></div>
                            <div class="metric-label">Total Payouts</div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Metrics -->
                <div class="row">
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5><?php echo $orders['delivered'] ?? 0; ?></h5>
                            <small class="text-success">Delivered</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5><?php echo $orders['cancelled'] ?? 0; ?></h5>
                            <small class="text-danger">Cancelled</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5><?php echo $products['categories'] ?? 0; ?></h5>
                            <small class="text-info">Categories</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5><?php echo $products['total_stock'] ?? 0; ?></h5>
                            <small class="text-warning">Total Stock</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5><?php echo $payout['total_payouts'] ?? 0; ?></h5>
                            <small>Payout Requests</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center p-3">
                            <h5>₹<?php echo number_format($payout['pending_amount'] ?? 0, 2); ?></h5>
                            <small class="text-warning">Pending Payout</small>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line"></i> Monthly Revenue (This Year)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Monthly Orders (This Year)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ordersChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-star"></i> Top 10 Products by Orders</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover product-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product Name</th>
                                                <th>Price</th>
                                                <th>Times Ordered</th>
                                                <th>Revenue Estimate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($top_products)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No products found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($top_products as $index => $product): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                        <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                                        <td><span class="badge bg-info"><?php echo $product['times_ordered']; ?></span></td>
                                                        <td>₹<?php echo number_format($product['price'] * $product['times_ordered'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            const labels = monthlyData.map(d => new Date(2025, d.month - 1).toLocaleString('default', { month: 'short' }));
            const revenue = monthlyData.map(d => d.revenue);
            
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenue,
                        borderColor: '#f5576c',
                        backgroundColor: 'rgba(245, 87, 108, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Monthly Orders Chart
        const ordersCtx = document.getElementById('ordersChart')?.getContext('2d');
        if (ordersCtx) {
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            const labels = monthlyData.map(d => new Date(2025, d.month - 1).toLocaleString('default', { month: 'short' }));
            const orders = monthlyData.map(d => d.orders);
            
            new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Orders',
                        data: orders,
                        backgroundColor: '#4facfe',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</body>
</html>
