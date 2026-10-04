<?php
/**
 * Admin - Products Management
 * List and manage all master products
 */

session_start();

// Check admin access
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/public/login.php');
    exit;
}

require_once '../../../config/Database.php';

$db = new Database();
$conn = $db->connect();

// Get all products with vendor count
$query = "SELECT 
            p.*,
            COUNT(vpm.id) as vendor_count,
            MIN(vpm.vendor_price) as min_vendor_price,
            MAX(vpm.vendor_price) as max_vendor_price,
            SUM(vpm.vendor_stock) as total_vendor_stock,
            c.name_en as category_name
         FROM products p
         LEFT JOIN vendor_product_map vpm ON p.id = vpm.product_id
         LEFT JOIN categories c ON p.category_id = c.id
         GROUP BY p.id
         ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $product_id = $_POST['product_id'] ?? null;
    
    if ($product_id) {
        $delete_query = "UPDATE products SET status = 'inactive' WHERE id = :id";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':id', $product_id);
        
        if ($delete_stmt->execute()) {
            header("Location: ?success=deleted");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
        }
        
        .admin-container {
            padding: 20px;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-section h1 {
            margin: 0;
            color: #333;
        }
        
        .btn-add-product {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-add-product:hover {
            background: #45a049;
            color: white;
        }
        
        .products-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .table {
            margin: 0;
        }
        
        .table thead {
            background: #f9f9f9;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table th {
            padding: 15px;
            font-weight: 600;
            color: #333;
            border: none;
        }
        
        .table td {
            padding: 12px 15px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
        }
        
        .product-name small {
            display: block;
            color: #999;
            font-weight: normal;
        }
        
        .vendor-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .price-range {
            background: #fff3e0;
            color: #e65100;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-group-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm-custom {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view {
            background: #2196F3;
            color: white;
        }
        
        .btn-view:hover {
            background: #1976d2;
        }
        
        .btn-edit {
            background: #FF9800;
            color: white;
        }
        
        .btn-edit:hover {
            background: #f57c00;
        }
        
        .btn-vendors {
            background: #9C27B0;
            color: white;
        }
        
        .btn-vendors:hover {
            background: #7b1fa2;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
        
        .alert {
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1><i class="fas fa-boxes me-2 text-success"></i>Master Products</h1>
                <small class="text-muted">Manage all platform products</small>
            </div>
            <a href="/admin/app/views/products/add-product.php" class="btn-add-product">
                <i class="fas fa-plus me-2"></i>Add Master Product
            </a>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php if ($_GET['success'] === 'created'): ?>
                Product created successfully!
            <?php elseif ($_GET['success'] === 'updated'): ?>
                Product updated successfully!
            <?php elseif ($_GET['success'] === 'deleted'): ?>
                Product deleted successfully!
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Products Table -->
        <?php if (empty($products)): ?>
        <div class="no-data">
            <i class="fas fa-inbox"></i>
            <h3>No Products Yet</h3>
            <p>Create your first master product to get started</p>
            <a href="/admin/app/views/products/add-product.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Create Product
            </a>
        </div>
        <?php else: ?>
        <div class="products-table">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Product Name</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 12%;">Master Price</th>
                        <th style="width: 12%;">Unit</th>
                        <th style="width: 10%;">Vendors</th>
                        <th style="width: 13%;">Price Range</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <div class="product-name">
                                <?php echo htmlspecialchars($product['name_en']); ?>
                                <?php if (!empty($product['name_ne'])): ?>
                                    <small><?php echo htmlspecialchars($product['name_ne']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                        <td><strong>Rs. <?php echo number_format($product['price'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($product['unit']); ?></td>
                        <td>
                            <span class="vendor-badge">
                                <i class="fas fa-store me-1"></i><?php echo $product['vendor_count'] ?? 0; ?> vendor(s)
                            </span>
                        </td>
                        <td>
                            <?php if ($product['vendor_count'] > 0): ?>
                            <span class="price-range">
                                Rs. <?php echo number_format($product['min_vendor_price'], 2); ?> - 
                                Rs. <?php echo number_format($product['max_vendor_price'], 2); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size: 12px;">No vendors</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $product['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group-actions">
                                <a href="/admin/app/views/products/view-product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn-sm-custom btn-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="/admin/app/views/products/edit-product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn-sm-custom btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="/admin/app/views/products/manage-vendors.php?id=<?php echo $product['id']; ?>" 
                                   class="btn-sm-custom btn-vendors" title="Manage Vendors">
                                    <i class="fas fa-users"></i>
                                </a>
                                <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name_en']); ?>')" 
                                        class="btn-sm-custom btn-delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteProduct(id, name) {
        if (confirm('Delete product "' + name + '"? This will not affect existing vendor mappings.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
