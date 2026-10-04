<?php
/**
 * Admin - Manage Vendors for Product
 * Add/remove vendors and manage their pricing for a specific product
 */

session_start();

if (!isset($_SESSION['admin_id'])) {
    // Environment-aware redirect
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $basePath = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/phool-delivery-platform' : '';
    header('Location: ' . $basePath . '/admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../config/Database.php';
require_once __DIR__ . '/../../models/VendorProductMap.php';

$db = new Database();
$conn = $db->connect();

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $basePath = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/phool-delivery-platform' : '';
    header('Location: ' . $basePath . '/admin/products.php');
    exit;
}

// Get product details
$p_query = "SELECT * FROM products WHERE id = :id";
$p_stmt = $conn->prepare($p_query);
$p_stmt->bindParam(':id', $product_id);
$p_stmt->execute();
$product = $p_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $basePath = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/phool-delivery-platform' : '';
    header('Location: ' . $basePath . '/admin/products.php?error=not_found');
    exit;
}

// Get vendors supplying this product
$v_query = "SELECT 
            vpm.id as mapping_id,
            vpm.vendor_price,
            vpm.vendor_stock,
            vpm.is_available,
            vpm.preparation_time,
            vpm.status,
            v.id as vendor_id,
            v.store_name,
            v.average_rating,
            v.status as vendor_status
         FROM vendor_product_map vpm
         JOIN vendors v ON vpm.vendor_id = v.id
         WHERE vpm.product_id = :product_id
         ORDER BY vpm.vendor_price ASC";

$v_stmt = $conn->prepare($v_query);
$v_stmt->bindParam(':product_id', $product_id);
$v_stmt->execute();
$vendors = $v_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active vendors for adding new
$all_v_query = "SELECT v.id, v.store_name, v.average_rating FROM vendors v
               WHERE v.status = 'active'
               AND v.id NOT IN (
                   SELECT vendor_id FROM vendor_product_map 
                   WHERE product_id = :product_id
               )
               ORDER BY v.store_name";

$all_v_stmt = $conn->prepare($all_v_query);
$all_v_stmt->bindParam(':product_id', $product_id);
$all_v_stmt->execute();
$available_vendors = $all_v_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add vendor
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_vendor') {
            $vendor_id = $_POST['vendor_id'] ?? null;
            $vendor_price = $_POST['vendor_price'] ?? null;
            $vendor_stock = $_POST['vendor_stock'] ?? null;
            
            if (!$vendor_id || !$vendor_price || !isset($vendor_stock)) {
                $error = "Please fill all required fields";
            } else {
                $vpm = new VendorProductMap($conn);
                $result = $vpm->create([
                    'vendor_id' => $vendor_id,
                    'product_id' => $product_id,
                    'vendor_price' => $vendor_price,
                    'vendor_stock' => $vendor_stock,
                    'status' => 'active'
                ]);
                
                if ($result) {
                    $success = "Vendor added successfully";
                    // Refresh vendors list
                    $v_stmt->execute();
                    $vendors = $v_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $all_v_stmt->execute();
                    $available_vendors = $all_v_stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to add vendor";
                }
            }
        } elseif ($_POST['action'] === 'remove_vendor') {
            $mapping_id = $_POST['mapping_id'] ?? null;
            
            if ($mapping_id) {
                $delete_query = "DELETE FROM vendor_product_map WHERE id = :id AND product_id = :product_id";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bindParam(':id', $mapping_id);
                $delete_stmt->bindParam(':product_id', $product_id);
                
                if ($delete_stmt->execute()) {
                    $success = "Vendor removed successfully";
                    // Refresh vendors list
                    $v_stmt->execute();
                    $vendors = $v_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $all_v_stmt->execute();
                    $available_vendors = $all_v_stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to remove vendor";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vendors - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
        }
        
        .admin-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-section {
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-section h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .product-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #4CAF50;
        }
        
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .card h3 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .vendor-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid #2196F3;
        }
        
        .vendor-details {
            flex: 1;
        }
        
        .vendor-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .vendor-meta {
            font-size: 12px;
            color: #666;
        }
        
        .vendor-meta span {
            margin-right: 15px;
        }
        
        .vendor-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-remove {
            background: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-remove:hover {
            background: #da190b;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .btn-add {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
        }
        
        .btn-add:hover {
            background: #45a049;
        }
        
        .btn-back {
            background: #666;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 20px;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #555;
            color: white;
        }
        
        .alert {
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .content-row {
                grid-template-columns: 1fr;
            }
            
            .vendor-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .vendor-actions {
                margin-top: 10px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="header-section">
            <h1>
                <i class="fas fa-users me-2 text-primary"></i>Manage Vendors
            </h1>
            <?php $host = $_SERVER['HTTP_HOST'] ?? ''; $basePath = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/phool-delivery-platform' : ''; ?>
            <a href="<?php echo $basePath; ?>/admin/products.php" class="text-muted" style="text-decoration: none;">
                <i class="fas fa-arrow-left me-1"></i>Back to Products
            </a>
            
            <div class="product-info">
                <strong><?php echo htmlspecialchars($product['name_en']); ?></strong>
                <?php if (!empty($product['name_ne'])): ?>
                    <small class="text-muted">(<?php echo htmlspecialchars($product['name_ne']); ?>)</small>
                <?php endif; ?>
                <br>
                <small class="text-muted">
                    Master Price: <strong>Rs. <?php echo number_format($product['price'], 2); ?></strong> | 
                    Unit: <strong><?php echo htmlspecialchars($product['unit']); ?></strong>
                </small>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-row">
            <!-- Vendors List -->
            <div class="card">
                <h3>
                    <i class="fas fa-store me-2"></i>Supplying Vendors (<?php echo count($vendors); ?>)
                </h3>
                
                <?php if (empty($vendors)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No vendors are supplying this product yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($vendors as $vendor): ?>
                <div class="vendor-item">
                    <div class="vendor-details">
                        <div class="vendor-name">
                            <?php echo htmlspecialchars($vendor['store_name']); ?>
                        </div>
                        <div class="vendor-meta">
                            <span>
                                <i class="fas fa-tag"></i>
                                Price: Rs. <?php echo number_format($vendor['vendor_price'], 2); ?>
                            </span>
                            <span>
                                <i class="fas fa-boxes"></i>
                                Stock: <?php echo $vendor['vendor_stock']; ?> units
                            </span>
                            <span>
                                <i class="fas fa-star"></i>
                                Rating: <?php echo number_format($vendor['average_rating'], 2); ?>
                            </span>
                            <span>
                                <i class="fas fa-clock"></i>
                                Prep: <?php echo $vendor['preparation_time']; ?> mins
                            </span>
                        </div>
                    </div>
                    <div class="vendor-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="remove_vendor">
                            <input type="hidden" name="mapping_id" value="<?php echo $vendor['mapping_id']; ?>">
                            <button type="submit" class="btn-remove" onclick="return confirm('Remove this vendor?');">
                                <i class="fas fa-trash me-1"></i>Remove
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Add Vendor Form -->
            <div class="card">
                <h3>
                    <i class="fas fa-plus-circle me-2"></i>Add New Vendor
                </h3>
                
                <?php if (empty($available_vendors)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>All active vendors are already supplying this product.</p>
                </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_vendor">
                    
                    <div class="form-group">
                        <label class="form-label">Select Vendor *</label>
                        <select name="vendor_id" class="form-control" required>
                            <option value="">-- Choose Vendor --</option>
                            <?php foreach ($available_vendors as $av): ?>
                            <option value="<?php echo $av['id']; ?>">
                                <?php echo htmlspecialchars($av['store_name']); ?> (★ <?php echo number_format($av['average_rating'], 2); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Vendor Price (Rs.) *</label>
                        <input type="number" name="vendor_price" class="form-control" step="0.01" min="0" required 
                               placeholder="<?php echo $product['price']; ?>">
                        <small style="color: #666;">Set the price this vendor will supply at</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Initial Stock *</label>
                        <input type="number" name="vendor_stock" class="form-control" min="0" required 
                               placeholder="0">
                        <small style="color: #666;">How many units the vendor has</small>
                    </div>
                    
                    <button type="submit" class="btn-add">
                        <i class="fas fa-plus me-2"></i>Add Vendor
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?php echo $basePath; ?>/admin/products.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Products
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
