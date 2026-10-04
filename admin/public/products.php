<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle category filters
$main_category_id = isset($_GET['main_category']) ? intval($_GET['main_category']) : 0;
$subcategory_id = isset($_GET['subcategory']) ? intval($_GET['subcategory']) : 0;

// Build WHERE clause for category filtering
$where_clause = "";
$params = [];

if ($main_category_id > 0) {
    if ($subcategory_id > 0) {
        // Filter by specific subcategory
        $where_clause = "WHERE EXISTS (
            SELECT 1 FROM product_categories pc 
            WHERE pc.product_id = p.id AND pc.category_id = ?
        )";
        $params[] = $subcategory_id;
    } else {
        // Filter by main category (including all its subcategories)
        // First, get all subcategory IDs for this main category
        $subcat_ids = [];
        $subcat_stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
        $subcat_stmt->execute([$main_category_id]);
        $subcategories = $subcat_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($subcategories)) {
            $placeholders = str_repeat('?,', count($subcategories) - 1) . '?';
            $where_clause = "WHERE EXISTS (
                SELECT 1 FROM product_categories pc 
                WHERE pc.product_id = p.id 
                AND pc.category_id IN (?, $placeholders)
            )";
            $params[] = $main_category_id;
            $params = array_merge($params, $subcategories);
        } else {
            // No subcategories, only filter by main category
            $where_clause = "WHERE EXISTS (
                SELECT 1 FROM product_categories pc 
                WHERE pc.product_id = p.id AND pc.category_id = ?
            )";
            $params[] = $main_category_id;
        }
    }
}

// Get all products with category filter
$products_query = "
    SELECT p.*,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
";

if (!empty($where_clause)) {
    $products_query .= " " . $where_clause;
}

$products_query .= " ORDER BY p.created_at DESC";

$products = $pdo->prepare($products_query);
$products->execute($params);
$products = $products->fetchAll();

// Get all main categories (categories with no parent)
$main_categories = $pdo->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    AND status = 'active' 
    ORDER BY sort_order, name_en
")->fetchAll();

// Get subcategories if main category is selected
$subcategories = [];
if ($main_category_id > 0) {
    $subcategories_stmt = $pdo->prepare("
        SELECT * FROM categories 
        WHERE parent_id = ? 
        AND status = 'active' 
        ORDER BY sort_order, name_en
    ");
    $subcategories_stmt->execute([$main_category_id]);
    $subcategories = $subcategories_stmt->fetchAll();
}

// Set page title
$page_title = "Products Management - Phool Delivery Admin";

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
    <h1 class="h2">Products Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="products.php" class="btn btn-sm btn-outline-secondary active">Products</a>
            <a href="products/categories.php" class="btn btn-sm btn-outline-secondary">Categories</a>
        </div>
        <a href="products/add.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add New Product
        </a>
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

<!-- Product Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Total Products</h6>
                        <h3 class="mb-0"><?php echo count($products); ?></h3>
                    </div>
                    <i class="fas fa-box fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Active Products</h6>
                        <h3 class="mb-0">
                            <?php 
                            $active_count = 0;
                            foreach ($products as $product) {
                                if ($product['status'] == 'active') $active_count++;
                            }
                            echo $active_count;
                            ?>
                        </h3>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Low Stock</h6>
                        <h3 class="mb-0">
                            <?php 
                            $low_stock_count = 0;
                            foreach ($products as $product) {
                                if ($product['stock_quantity'] <= $product['min_stock_alert']) $low_stock_count++;
                            }
                            echo $low_stock_count;
                            ?>
                        </h3>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Out of Stock</h6>
                        <h3 class="mb-0">
                            <?php 
                            $out_of_stock_count = 0;
                            foreach ($products as $product) {
                                if ($product['status'] == 'out_of_stock') $out_of_stock_count++;
                            }
                            echo $out_of_stock_count;
                            ?>
                        </h3>
                    </div>
                    <i class="fas fa-times-circle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Filter Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filter Products</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="main_category_filter" class="form-label">Main Category</label>
                    <select class="form-select" id="main_category_filter">
                        <option value="0">All Main Categories</option>
                        <?php foreach ($main_categories as $main_cat): ?>
                        <option value="<?php echo $main_cat['id']; ?>" 
                            <?php echo isset($_GET['main_category']) && $_GET['main_category'] == $main_cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($main_cat['name_en']); ?>
                            (<?php echo htmlspecialchars($main_cat['name_ne']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="subcategory_filter" class="form-label">Subcategory</label>
                    <select class="form-select" id="subcategory_filter" 
                        <?php echo !isset($_GET['main_category']) ? 'disabled' : ''; ?>>
                        <option value="0">All Subcategories</option>
                        <?php 
                        if (isset($_GET['main_category'])) {
                            foreach ($subcategories as $subcat):
                            ?>
                            <option value="<?php echo $subcat['id']; ?>" 
                                <?php echo isset($_GET['subcategory']) && $_GET['subcategory'] == $subcat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subcat['name_en']); ?>
                                (<?php echo htmlspecialchars($subcat['name_ne']); ?>)
                            </option>
                            <?php 
                            endforeach;
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <div class="btn-group w-100">
                    <button class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-filter-circle-xmark me-1"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search products by name, category, or description...">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Indicator -->
<div id="filterLoading" class="alert alert-info d-none" role="alert">
    <i class="bi bi-hourglass-split"></i> Loading products...
</div>

<!-- Products List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            Products List (<?php echo count($products); ?>)
        </h5>
        <div>
            <span class="badge bg-primary me-2">
                <i class="fas fa-layer-group me-1"></i>
                Showing: <?php echo count($products); ?> products
            </span>
            <a href="products/add.php" class="btn btn-sm btn-success">
                <i class="fas fa-plus me-1"></i> Add Product
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($products)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
            <h5>No Products Found</h5>
            <p class="mb-3"><?php echo isset($_GET['main_category']) ? 'No products found for the selected category.' : 'No products have been added yet.'; ?></p>
            <a href="products/add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Your First Product
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive responsive-table-wrapper">
            <table class="table table-striped table-hover responsive-table" id="productsTable">
                <thead>
                    <tr>
                        <th class="d-none d-lg-table-cell">ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Categories</th>
                        <th>Price</th>
                        <th class="d-none d-md-table-cell">Stock</th>
                        <th>Status</th>
                        <th class="d-none d-lg-table-cell">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="d-none d-lg-table-cell"><?php echo $product['id']; ?></td>
                        <td>
                            <?php if (!empty($product['primary_image'])): ?>
                            <img src="<?php echo getProductImageUrl($product['primary_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name_en']); ?>" 
                                 class="rounded" 
                                 width="40" height="40" 
                                 style="object-fit: cover; border: 1px solid #dee2e6;">
                            <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                 style="width: 40px; height: 40px; border: 1px solid #dee2e6;">
                                <i class="fas fa-box text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name_en']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($product['name_ne']); ?></small>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php
                            $product_cats = $pdo->prepare("
                                SELECT c.* 
                                FROM categories c 
                                JOIN product_categories pc ON c.id = pc.category_id 
                                WHERE pc.product_id = ? 
                                ORDER BY c.parent_id, c.name_en
                            ");
                            $product_cats->execute([$product['id']]);
                            $categories = $product_cats->fetchAll();
                            
                            if (!empty($categories)) {
                                foreach ($categories as $cat):
                                    $badge_class = $cat['parent_id'] ? 'bg-info' : 'bg-primary';
                            ?>
                                <span class="badge <?php echo $badge_class; ?> me-1 mb-1" style="font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($cat['name_en']); ?>
                                </span>
                            <?php 
                                endforeach;
                            } else {
                                echo '<span class="text-danger small">No categories</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <strong>Rs. <?php echo number_format($product['price'], 0); ?></strong>
                            <?php if ($product['bulk_price'] > 0): ?>
                            <br>
                            <small class="text-muted">Bulk: Rs. <?php echo number_format($product['bulk_price'], 0); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div class="d-flex align-items-center">
                                <span class="<?php echo $product['stock_quantity'] <= $product['min_stock_alert'] ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo number_format($product['stock_quantity']); ?>
                                </span>
                                <?php if ($product['stock_quantity'] <= $product['min_stock_alert']): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.70rem;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Low
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $status_badge = [
                                'active' => ['bg-success', 'Active'],
                                'inactive' => ['bg-secondary', 'Inactive'],
                                'out_of_stock' => ['bg-danger', 'Out of Stock']
                            ];
                            $status = $product['status'];
                            $badge_class = $status_badge[$status][0] ?? 'bg-secondary';
                            $badge_text = $status_badge[$status][1] ?? ucfirst($status);
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $badge_text; ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php echo date('M d, Y', strtotime($product['created_at'])); ?><br>
                            <small class="text-muted">
                                <?php echo date('h:i A', strtotime($product['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm flex-wrap">
                                <a href="products/edit.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-primary" 
                                   title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="products/delete.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-danger" 
                                   title="Delete Product">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="products/gallery.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-info" 
                                   title="Manage Gallery">
                                    <i class="fas fa-images"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Store current filters in memory
let currentMainCategory = 0;
let currentSubcategory = 0;
let currentSearch = '';

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    const mainCategoryFilter = document.getElementById('main_category_filter');
    const subcategoryFilter = document.getElementById('subcategory_filter');
    const searchInput = document.getElementById('searchInput');

    // Main category change
    mainCategoryFilter.addEventListener('change', function() {
        currentMainCategory = parseInt(this.value);
        loadSubcategories(currentMainCategory);
    });

    // Subcategory change
    subcategoryFilter.addEventListener('change', function() {
        currentSubcategory = parseInt(this.value);
        loadProductsAjax();
    });

    // Real-time search
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        currentSearch = this.value.trim();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadProductsAjax, 300);
    });

    // Search on Enter key
    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            loadProductsAjax();
        }
    });

    // Initialize tooltips
    initializeTooltips();
});

/**
 * Load products via AJAX
 */
function loadProductsAjax() {
    const loadingDiv = document.getElementById('filterLoading');
    if (loadingDiv) {
        loadingDiv.classList.remove('d-none');
    }

    const params = new URLSearchParams({
        main_category: currentMainCategory,
        subcategory: currentSubcategory,
        search: currentSearch
    });

    fetch('ajax/get-products.php?' + params, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                document.getElementById('productsTableBody').innerHTML = data.html;
                initializeTooltips();
                showSuccessMessage('Products loaded successfully (' + data.count + ' found)');
            } else {
                showErrorMessage(data.message || 'Error loading products');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response text:', text.substring(0, 200));
            showErrorMessage('Error parsing server response');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage('Error loading products: ' + error.message);
    })
    .finally(() => {
        if (loadingDiv) {
            loadingDiv.classList.add('d-none');
        }
    });
}

/**
 * Load subcategories when main category changes
 */
function loadSubcategories(mainCategoryId) {
    const subcategoryFilter = document.getElementById('subcategory_filter');
    
    if (mainCategoryId == 0) {
        subcategoryFilter.innerHTML = '<option value="0">All Subcategories</option>';
        subcategoryFilter.disabled = true;
        // Load all products when selecting "All Main Categories"
        currentSubcategory = 0;
        loadProductsAjax();
        return;
    }
    
    // Load subcategories via AJAX
    fetch('products/get_subcategories.php?parent_id=' + mainCategoryId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            let options = '<option value="0">All Subcategories</option>';
            if (data.length > 0) {
                data.forEach(subcat => {
                    options += `<option value="${subcat.id}">${subcat.name_en}</option>`;
                });
            }
            subcategoryFilter.innerHTML = options;
            subcategoryFilter.disabled = false;
            
            // Load products after loading subcategories
            currentSubcategory = 0;
            setTimeout(loadProductsAjax, 100);
        })
        .catch(error => {
            console.error('Error loading subcategories:', error);
            subcategoryFilter.innerHTML = '<option value="0">Error loading subcategories</option>';
            subcategoryFilter.disabled = false;
        });
}

/**
 * Clear all filters
 */
function clearFilters() {
    document.getElementById('main_category_filter').value = '0';
    document.getElementById('subcategory_filter').value = '0';
    document.getElementById('subcategory_filter').disabled = true;
    document.getElementById('searchInput').value = '';
    
    currentMainCategory = 0;
    currentSubcategory = 0;
    currentSearch = '';
    
    loadProductsAjax();
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Show success message toast
 */
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.card-header');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

/**
 * Show error message toast
 */
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.card-header');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

// Delete product - with AJAX reload
function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    fetch('ajax/delete-product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Product deleted successfully');
            loadProductsAjax();
        } else {
            showErrorMessage('Error: ' + (data.message || 'Failed to delete'));
        }
    })
    .catch(err => {
        console.error(err);
        showErrorMessage('Error deleting product');
    });
}

function exportToCSV() {
    const rows = document.querySelectorAll('#productsTableBody tr');
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    const headers = ['ID', 'Name (EN)', 'Name (NE)', 'Categories', 'Price', 'Stock', 'Status', 'Created'];
    csvContent += headers.join(',') + "\n";
    
    // Add data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            const rowData = [
                cells[0]?.textContent || '',
                cells[2]?.querySelector('strong')?.textContent || '',
                cells[2]?.querySelector('small')?.textContent || '',
                cells[3]?.textContent.replace(/\n/g, ' ').replace(/,/g, ';') || '',
                cells[4]?.querySelector('strong')?.textContent.replace('Rs. ', '') || '',
                cells[5]?.querySelector('span')?.textContent || '',
                cells[6]?.querySelector('.badge')?.textContent || '',
                cells[7]?.textContent.split('\n')[0] || ''
            ];
            csvContent += rowData.join(',') + "\n";
        }
    });
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "products_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

</script>

<style>
.badge {
    font-size: 0.8em;
    padding: 0.4em 0.6em;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.card {
    border: 1px solid rgba(0, 0, 0, 0.125);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    background-color: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.search-alert {
    margin-top: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9em;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 0.25rem;
        width: 100%;
    }
}
</style>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>