<?php
/**
 * Add Product for Vendor
 * Admin panel page to add new products for a specific vendor
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

// Fetch master products for optional selection
$masterProducts = [];
try {
    $q = $db->prepare("SELECT id, name_en, category_id, price FROM products WHERE status = 'active' ORDER BY name_en ASC");
    $q->execute();
    $masterProducts = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
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
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = 'Add Product - ' . htmlspecialchars($vendor['store_name']);
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
                <h1 class="h3 mb-0">Add New Product</h1>
                <p class="text-muted mt-1">Store: <strong><?php echo htmlspecialchars($vendor['store_name']); ?></strong></p>
            </div>
        </div>

        <!-- Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form id="addProductForm" method="POST" action="../../api/product-add.php" enctype="multipart/form-data">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">

                            <!-- Optional: Select from Master Products -->
                            <div class="mb-3">
                                <label class="form-label">Select Master Product (optional)</label>
                                <select id="masterProductSelect" class="form-select">
                                    <option value="">-- Choose a master product --</option>
                                    <?php foreach ($masterProducts as $mp): ?>
                                        <option value="<?php echo $mp['id']; ?>" data-name="<?php echo htmlspecialchars($mp['name_en'], ENT_QUOTES); ?>" data-price="<?php echo $mp['price']; ?>" data-category="<?php echo htmlspecialchars($mp['category_id'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($mp['name_en']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-help-text">Select to pre-fill fields from the master catalog, or leave blank to create a custom product.</small>
                            </div>

                            <!-- Product Name -->
                            <div class="mb-3">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="productNameInput" name="product_name" required>
                                <small class="text-muted">Enter the product name</small>
                            </div>

                            <!-- Category -->
                            <div class="row">
                                 <div class="form-group">
                    <label for="category" class="form-label">
                        <i class="fas fa-folder me-2"></i>Category <span class="text-danger">*</span>
                    </label>
                    <select class="form-control" id="category" name="category" required>
                        <option value="">Select a category</option>
                        <option value="Bouquets">🌹 Bouquets</option>
                        <option value="Arrangements">🌺 Arrangements</option>
                        <option value="Baskets">🧺 Baskets</option>
                        <option value="Boxes">📦 Boxes</option>
                        <option value="Wreaths">🎀 Wreaths</option>
                        <option value="Potted">🪴 Potted Plants</option>
                    </select>
                    <small class="form-help-text">Choose the product category</small>
                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product SKU</label>
                                    <div class="form-control-plaintext text-muted">Auto-generated after saving (not editable)</div>
                                    <input type="hidden" name="product_sku" value="">
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"></textarea>
                            </div>

                            <!-- Pricing -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="priceInput" name="price" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bulk Price</label>
                                    <input type="number" class="form-control" name="bulk_price" step="0.01">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="quantity_in_stock" min="0" required>
                                </div>
                            </div>

                            <!-- Images (vendor-panel style) -->
                            <div class="form-section-header mt-3">
                                <i class="bi bi-image"></i> Images & Media
                            </div>

                            <div class="image-upload-section mb-3">
                                <div class="alert alert-info mb-3">
                                    <strong>Image Tips:</strong> First image will be set as product profile/primary image. Additional images will be added to gallery.
                                </div>

                                <label class="form-label d-block mb-2">Product Images <span class="text-danger">*</span></label>

                                <div class="image-upload-wrapper border rounded p-4 text-center" id="imageUploadArea" style="cursor:pointer;">
                                    <div class="mb-2"><i class="bi bi-cloud-upload" style="font-size:28px;"></i></div>
                                    <div class="fw-medium">Click or drag images here</div>
                                    <div class="text-muted">PNG, JPG or JPEG (max. 5MB each)</div>
                                    <input type="file" id="imageInput" name="images[]" multiple accept="image/*" class="d-none">
                                </div>

                                <div id="imagePreviewContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
                                <small class="form-text text-muted">Upload at least 1 image. First will be primary.</small>
                            </div>

                            <!-- Status & Flags -->
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // When master product selected, pre-fill name, category and price
        const masterSelect = document.getElementById('masterProductSelect');
        if (masterSelect) {
            masterSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (!this.value) return;
                const name = opt.dataset.name || '';
                const price = opt.dataset.price || '';
                const category = opt.dataset.category || '';
                if (name) document.getElementById('productNameInput').value = name;
                if (price) document.getElementById('priceInput').value = price;
                if (category) document.getElementById('category').value = category;
            });
        }
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageInput = document.getElementById('imageInput');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const form = document.getElementById('addProductForm');
        const maxFiles = 5;
        const maxSize = 5 * 1024 * 1024; // 5MB

        imageUploadArea.addEventListener('click', () => imageInput.click());

        imageUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUploadArea.classList.add('drag-over');
        });
        imageUploadArea.addEventListener('dragleave', () => {
            imageUploadArea.classList.remove('drag-over');
        });
        imageUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUploadArea.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files);
        });

        imageInput.addEventListener('change', (e) => handleFiles(e.target.files));

        function handleFiles(files) {
            const arr = Array.from(files);
            if ((imagePreviewContainer.children.length + arr.length) > maxFiles) {
                alert(`Maximum ${maxFiles} images allowed`);
                return;
            }
            arr.forEach((file) => {
                if (file.size > maxSize) { alert(`File ${file.name} is too large (max 5MB)`); return; }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const idx = imagePreviewContainer.children.length;
                    const preview = document.createElement('div');
                    preview.className = 'border rounded p-1 position-relative';
                    preview.style.width = '120px';
                    preview.style.height = '120px';
                    preview.style.overflow = 'hidden';
                    preview.innerHTML = `
                        <img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover;" />
                        <button type="button" class="btn btn-sm btn-danger position-absolute" style="top:6px;right:6px;" title="Remove">&times;</button>
                        ${idx === 0 ? '<span class="badge bg-warning text-dark position-absolute" style="left:6px;bottom:6px;">Primary</span>' : ''}
                    `;
                    const btn = preview.querySelector('button');
                    btn.addEventListener('click', () => { preview.remove(); refreshPrimaryBadges(); });
                    imagePreviewContainer.appendChild(preview);
                };
                reader.readAsDataURL(file);
            });
        }

        function refreshPrimaryBadges() {
            const previews = Array.from(imagePreviewContainer.children);
            previews.forEach((p, i) => {
                const badge = p.querySelector('.badge');
                if (i === 0) {
                    if (!badge) {
                        const s = document.createElement('span');
                        s.className = 'badge bg-warning text-dark position-absolute';
                        s.style.left = '6px'; s.style.bottom = '6px';
                        s.textContent = 'Primary';
                        p.appendChild(s);
                    }
                } else {
                    if (badge) badge.remove();
                }
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (imagePreviewContainer.children.length === 0) { alert('Please upload at least one product image'); return; }

            const submitBtn = this.querySelector('button[type="submit"]');
            const origHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            // append File objects from input (if any)
            // If files selected via input, they are already included; but ensure in case only drag-drop used
            const inputFiles = imageInput.files;
            if (inputFiles && inputFiles.length > 0) {
                for (let i = 0; i < inputFiles.length; i++) formData.append('images[]', inputFiles[i]);
            }

            fetch(this.action, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Product saved successfully!');
                    window.location.href = 'manage.php?vendor_id=' + encodeURIComponent(<?php echo (int)$vendor_id; ?>);
                } else {
                    alert('Error: ' + (data.message || 'Failed to save product'));
                }
            })
            .catch(err => { alert('Error: ' + err.message); })
            .finally(() => { submitBtn.innerHTML = origHtml; submitBtn.disabled = false; });
        });
    });
    </script>

                            <!-- Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Add Product
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
                        <h6 class="card-title mb-3"><i class="bi bi-info-circle"></i> Product Information</h6>
                        <div class="alert alert-info">
                            <small>
                                <strong>Note:</strong> 
                                <ul class="mb-0 ps-3">
                                    <li>All required fields must be filled</li>
                                    <li>Product name should be unique per vendor</li>
                                    <li>Price should be in Indian Rupees</li>
                                    <li>Stock quantity must be at least 0</li>
                                    <li>You can upload images after creating the product</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
