<?php
/**
 * Edit Vendor Product
 * Admin panel page to edit vendor product details
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if (!$vendor_id || !$product_id) {
    header('Location: ../vendors.php');
    exit;
}

// Get database connection
$db = getDBConnection();

// Fetch vendor details
try {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }

    // Fetch product details
    $stmt = $db->prepare("SELECT * FROM vendor_products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: manage.php?vendor_id=' . $vendor_id);
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = 'Edit Product - ' . htmlspecialchars($product['product_name']);
$current_page = 'vendor-products';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-0">Edit Product</h1>
                <p class="text-muted mt-1">
                    Store: <strong><?php echo htmlspecialchars($vendor['store_name']); ?></strong> | 
                    Product: <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                </p>
            </div>
        </div>

        <!-- Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form id="editProductForm">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                            <!-- Product Name -->
                            <div class="mb-3">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                            </div>

                            <!-- Category -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product SKU</label>
                                    <input type="text" class="form-control" name="product_sku" value="<?php echo htmlspecialchars($product['product_sku'] ?? ''); ?>" readonly>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>

                            <!-- Pricing -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" name="price" value="<?php echo $product['price']; ?>" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Discount Price (₹)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" name="discount_price" value="<?php echo $product['discount_price'] ?? $product['price']; ?>" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="quantity_in_stock" value="<?php echo $product['quantity_in_stock'] ?? 0; ?>" min="0" required>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Product
                                </button>
                                <a href="manage.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="col-lg-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title mb-3"><i class="bi bi-info-circle"></i> Product Details</h6>
                        <div class="alert alert-info">
                            <small>
                                <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($product['created_at'])); ?><br>
                                <strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($product['updated_at'] ?? $product['created_at'])); ?>
                            </small>
                        </div>
                        <a href="../vendor-images/manage.php?vendor_id=<?php echo $vendor_id; ?>&product_id=<?php echo $product_id; ?>" class="btn btn-sm btn-info w-100 mb-2">
                            <i class="bi bi-image"></i> Manage Images
                        </a>
                        <button type="button" class="btn btn-sm btn-danger w-100" onclick="deleteProduct(<?php echo $product_id; ?>)">
                            <i class="bi bi-trash"></i> Delete Product
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('../../api/product-update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);
                if (data.success) {
                    alert('Product updated successfully!');
                    window.location.href = 'manage.php?vendor_id=' + formData.get('vendor_id');
                } else {
                    alert('Error: ' + data.message);
                    console.error('Update failed:', data);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('An error occurred while updating the product. Check the browser console for details.');
            });
        });

        function deleteProduct(productId) {
            if (confirm('Move this product to Trash? You can restore it later.')) {
                fetch('../ajax/product-delete.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product moved to Trash');
                        window.location.href = 'trash.php?vendor_id=<?php echo $vendor_id; ?>';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>
