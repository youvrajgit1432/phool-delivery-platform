<?php
/**
 * Vendor Products Management
 * Admin can manage products for specific vendors
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}

// Get database connection
$db = getDBConnection();

// Helper function to convert image paths for display
function convertImagePath($imagePath) {
    if (empty($imagePath)) return null;
    
    // If it's already an absolute URL (http/https), return as-is
    if (strpos($imagePath, 'http') === 0) {
        return $imagePath;
    }
    
    // DO NOT remove /public/ - it's needed because vendor-panel/public/ is the web root
    // Paths should remain as: /phool-delivery-platform/vendor-panel/public/assets/img/products/
    
    // Just ensure path starts with /
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }
    
    return $imagePath;
}

// Fetch vendor details
try {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }
    
    // Fetch vendor's products
    $stmt = $db->prepare("
        SELECT vp.id,
               vp.product_name,
               vp.price,
               vp.category,
               vp.quantity_in_stock,
               COALESCE(vp.primary_image_url, (
                   SELECT vpi.image_url FROM vendor_product_images vpi 
                   WHERE vpi.product_id = vp.id 
                   ORDER BY vpi.display_order ASC, vpi.id ASC 
                   LIMIT 1
               )) as primary_image_url,
               vp.status,
               COUNT(DISTINCT vpi.id) as total_images
        FROM vendor_products vp
        LEFT JOIN vendor_product_images vpi ON vp.id = vpi.product_id
        WHERE vp.vendor_id = ?
        GROUP BY vp.id
        ORDER BY vp.created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also include products linked via vendor_product_map (master catalog links)
    $stmt2 = $db->prepare(
        "SELECT vpm.id as map_id, p.id as master_id, p.name_en as product_name, vpm.vendor_price as price, p.category_id as category, vpm.vendor_stock as quantity_in_stock, p.images as images, p.gallery_images as gallery_images, 'mapped' as source, vpm.created_at as created_at
         FROM vendor_product_map vpm
         JOIN products p ON p.id = vpm.product_id
         WHERE vpm.vendor_id = ?
         ORDER BY vpm.created_at DESC"
    );
    $stmt2->execute([$vendor_id]);
    $mapped = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Append mapped entries to products list, marking their source
    foreach ($mapped as $m) {
        // derive a usable primary image from master product images/gallery
        $primary = null;
        if (!empty($m['images'])) {
            $imgField = trim($m['images']);
            if (str_starts_with($imgField, '[') || str_starts_with($imgField, '{')) {
                $arr = json_decode($imgField, true);
                if (is_array($arr) && !empty($arr)) $primary = $arr[0];
            } else {
                // comma-separated
                $parts = array_filter(array_map('trim', explode(',', $imgField)));
                if (!empty($parts)) $primary = $parts[0];
            }
        }
        if (empty($primary) && !empty($m['gallery_images'])) {
            $g = trim($m['gallery_images']);
            if (str_starts_with($g, '[') || str_starts_with($g, '{')) {
                $arr = json_decode($g, true);
                if (is_array($arr) && !empty($arr)) $primary = $arr[0];
            } else {
                $parts = array_filter(array_map('trim', explode(',', $g)));
                if (!empty($parts)) $primary = $parts[0];
            }
        }

        $m['primary_image_url'] = $primary ?: null;
        $m['total_images'] = 0; // counted separately if needed
        $m['status'] = 'linked';
        $m['id'] = 'map_' . $m['map_id'];
        $m['source'] = 'mapped';
        $products[] = $m;
    }
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
    $products = [];
}

$page_title = 'Products - ' . htmlspecialchars($vendor['store_name']);
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
        <div class="row mb-4">
            <div class="col-12">
                <a href="../vendors/edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Back to Vendor
                </a>
                <h1 class="h3">Products - <?php echo htmlspecialchars($vendor['store_name']); ?></h1>
                <p class="text-muted">Manage all products for this vendor store</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <input type="text" class="form-control" id="searchProducts" placeholder="Search products...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <a href="add.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle"></i> Add Product
                </a>
                <a href="trash.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-outline-secondary" title="View Trash">
                    <i class="bi bi-trash"></i> View Trash
                </a>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">Image</th>
                            <th style="width: 25%;">Product Name</th>
                            <th style="width: 12%;">Price</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 10%;">Stock</th>
                            <th style="width: 10%;">Images</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 13%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox"></i> No products found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="product-row" data-product-status="<?php echo strtolower($product['status']); ?>">
                                    <td>
                                        <?php $imgPath = convertImagePath($product['primary_image_url']); ?>
                                        <?php if (!empty($imgPath)): ?>
                                            <img src="<?php echo htmlspecialchars($imgPath); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background: #f8f9fa; border: 1px solid #dee2e6;"
                                                 loading="lazy"
                                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2750%27 height=%2750%27%3E%3Crect fill=%22%23e9ecef%22 width=%2750%27 height=%2750%27/%3E%3C/svg%3E';">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; border: 1px solid #dee2e6;">
                                                <i class="bi bi-image" style="color: #6c757d; font-size: 1.5rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['product_name'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['quantity_in_stock'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $product['quantity_in_stock'] ?? 0; ?> units
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $product['total_images']; ?> images</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit.php?vendor_id=<?php echo $vendor['id']; ?>&product_id=<?php echo rawurlencode($product['id']); ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="../vendor-images/manage.php?vendor_id=<?php echo $vendor['id']; ?>&product_id=<?php echo rawurlencode($product['id']); ?>" 
                                               class="btn btn-outline-info" title="Manage Images">
                                                <i class="bi bi-images"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" onclick="deleteProduct('<?php echo addslashes($product['id']); ?>')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchProducts').addEventListener('keyup', filterProducts);
        document.getElementById('filterStatus').addEventListener('change', filterProducts);

        function filterProducts() {
            const searchTerm = document.getElementById('searchProducts').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const productRows = document.querySelectorAll('.product-row');

            productRows.forEach(row => {
                const status = row.dataset.productStatus;
                const productName = row.textContent.toLowerCase();
                
                const matchesSearch = productName.includes(searchTerm) || searchTerm === '';
                const matchesStatus = status === statusFilter || statusFilter === '';
                
                row.style.display = (matchesSearch && matchesStatus) ? 'table-row' : 'none';
            });
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                fetch('../ajax/product-delete.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId + '&vendor_id=' + <?php echo $vendor['id']; ?>
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product deleted successfully');
                        location.reload();
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
