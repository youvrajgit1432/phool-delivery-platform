<?php
/**
 * Edit Vendor Product - Vendor Panel
 * Edit linked master product details and manage images
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$mapId = $_GET['id'] ?? null;
$product = null;
$productImages = [];
$errorMessage = '';
$successMessage = '';

// Get product details
if ($mapId && $vendorId) {
    $stmt = $db->query(
        'SELECT vpm.*, p.id as product_id, p.name_en, p.category_id, p.price as master_price, 
                p.unit, p.description_en
         FROM vendor_product_map vpm
         JOIN products p ON vpm.product_id = p.id
         WHERE vpm.id = ? AND vpm.vendor_id = ? AND vpm.unlinked_at IS NULL',
        [$mapId, $vendorId]
    );
    
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($result) {
        $product = $result;
    } else {
        $errorMessage = 'Product not found or you do not have permission to edit it.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product && !$errorMessage) {
    try {
        $vendor_price = isset($_POST['vendor_price']) ? (float)$_POST['vendor_price'] : $product['vendor_price'];
        $vendor_stock = isset($_POST['vendor_stock']) ? (int)$_POST['vendor_stock'] : $product['vendor_stock'];
        $preparation_time = isset($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : $product['preparation_time'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $minimum_order_quantity = isset($_POST['minimum_order_quantity']) ? (float)$_POST['minimum_order_quantity'] : $product['minimum_order_quantity'];

        // Validate inputs
        if ($vendor_price <= 0) {
            throw new Exception('Price must be greater than 0');
        }
        if ($vendor_stock < 0) {
            throw new Exception('Stock cannot be negative');
        }

        // Update vendor_product_map
        $db->query(
            'UPDATE vendor_product_map SET vendor_price = ?, vendor_stock = ?, 
             preparation_time = ?, is_available = ?, minimum_order_quantity = ?, updated_at = NOW()
             WHERE id = ? AND vendor_id = ?',
            [$vendor_price, $vendor_stock, $preparation_time, $is_available, $minimum_order_quantity, $mapId, $vendorId]
        );

        $successMessage = 'Product updated successfully!';
        
        // Refresh product data
        $stmt = $db->query(
            'SELECT vpm.*, p.id as product_id, p.name_en, p.category_id, p.price as master_price, 
                    p.unit, p.description_en
             FROM vendor_product_map vpm
             JOIN products p ON vpm.product_id = p.id
             WHERE vpm.id = ? AND vpm.vendor_id = ? AND vpm.unlinked_at IS NULL',
            [$mapId, $vendorId]
        );
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $errorMessage = 'Error updating product: ' . $e->getMessage();
    }
}

// Get vendor uploaded images from notes
$vendorImages = [];
if ($product && !empty($product['notes'])) {
    try {
        $notesData = json_decode($product['notes'], true);
        if (isset($notesData['vendor_images']) && is_array($notesData['vendor_images'])) {
            $vendorImages = $notesData['vendor_images'];
        }
    } catch (Exception $e) {
        // Ignore json decode errors
    }
}
?>

<div class="edit-product-container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>
                <i class="fas fa-edit me-2"></i>Edit Product
            </h2>
            <p class="text-muted">Update product details and manage images</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Products
            </a>
        </div>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($product): ?>

    <div class="row">
        <!-- Left Column: Product Details -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-box me-2"></i>Product Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="product-form">
                        <!-- Product Name (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['name_en']); ?>" readonly>
                            <small class="text-muted">Master product name (cannot be changed)</small>
                        </div>

                        <!-- Unit -->
                        <div class="mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['unit']); ?>" readonly>
                            <small class="text-muted">Master product unit</small>
                        </div>

                        <!-- Your Price -->
                        <div class="mb-3">
                            <label class="form-label" style="color: #0d6efd; font-weight: 600;">Your Supply Price (₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" class="form-control" name="vendor_price" 
                                       value="<?php echo number_format($product['vendor_price'], 2); ?>" required>
                            </div>
                            <small class="text-success">Enter the price you will supply this product at</small>
                        </div>

                        <!-- Stock -->
                        <div class="mb-3">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" min="0" class="form-control" name="vendor_stock" 
                                   value="<?php echo $product['vendor_stock']; ?>" required>
                            <small class="text-muted">How many units you can supply</small>
                        </div>

                        <!-- Preparation Time -->
                        <div class="mb-3">
                            <label class="form-label">Preparation Time (minutes)</label>
                            <input type="number" min="0" class="form-control" name="preparation_time" 
                                   value="<?php echo $product['preparation_time'] ?? 30; ?>">
                            <small class="text-muted">How long to prepare this product</small>
                        </div>

                        <!-- Minimum Order Quantity -->
                        <div class="mb-3">
                            <label class="form-label">Minimum Order Quantity</label>
                            <input type="number" step="0.01" min="1" class="form-control" name="minimum_order_quantity" 
                                   value="<?php echo $product['minimum_order_quantity'] ?? 1; ?>">
                            <small class="text-muted">Minimum units required per order</small>
                        </div>

                        <!-- Availability Toggle -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_available" id="is_available" 
                                       <?php echo $product['is_available'] ? 'checked' : ''; ?> style="width: 50px; height: 25px;">
                                <label class="form-check-label" for="is_available">
                                    <strong>Available for Orders</strong>
                                </label>
                            </div>
                            <small class="text-muted d-block mt-2">Uncheck to temporarily hide this product</small>
                        </div>

                        <!-- Save Button -->
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Images -->
        <div class="col-lg-6 mb-4">
            <!-- Vendor Product Images Gallery -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-gallery me-2"></i>Your Product Images
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Upload Section -->
                    <div class="mb-4">
                        <label class="form-label">Upload Images</label>
                        <div class="upload-area" id="uploadArea" style="border: 2px dashed #0d6efd; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3" style="display: block;"></i>
                            <p><strong>Click to upload or drag & drop</strong></p>
                            <p class="text-muted small">JPG, PNG, JPEG (Max 5MB each)</p>
                            <input type="file" id="imageInput" multiple accept=".jpg,.jpeg,.png" style="display: none;">
                        </div>
                        <button type="button" class="btn btn-outline-success w-100 mt-2" onclick="document.getElementById('imageInput').click()">
                            <i class="fas fa-plus me-2"></i>Browse Files
                        </button>
                    </div>

                    <!-- Images Grid -->
                    <div class="mb-4">
                        <label class="form-label">Uploaded Images</label>
                        <div id="imagesGrid" class="row g-3">
                            <?php if (empty($vendorImages)): ?>
                                <div class="col-12">
                                    <p class="text-muted text-center">No images uploaded yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($vendorImages as $index => $image): ?>
                                    <div class="col-md-6" id="image-item-<?php echo $index; ?>">
                                        <div class="position-relative">
                                            <img src="<?php echo htmlspecialchars(vendor_url('/uploads/vendor_' . $vendorId . '/products/' . $image)); ?>" 
                                                 alt="Product image" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6;">
                                            <button type="button" class="btn btn-sm btn-danger position-absolute" 
                                                    onclick="deleteProductImage('<?php echo htmlspecialchars($image); ?>', <?php echo $mapId; ?>)"
                                                    style="top: 5px; right: 5px;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="uploadProgress" style="display: none;">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%;">
                                <span id="progressText">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Product not found.</strong> Please go back and select a valid product to edit.
        </div>
        <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Products
        </a>
    <?php endif; ?>
</div>

<!-- JavaScript for Image Upload -->
<script>
const mapId = <?php echo $mapId ?? 'null'; ?>;
const uploadArea = document.getElementById('uploadArea');
const imageInput = document.getElementById('imageInput');
const imagesGrid = document.getElementById('imagesGrid');
const uploadProgress = document.getElementById('uploadProgress');

// Drag and drop
if (uploadArea) {
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.backgroundColor = '#e7f3ff';
        uploadArea.style.borderColor = '#0d6efd';
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.backgroundColor = 'transparent';
        uploadArea.style.borderColor = '#0d6efd';
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.backgroundColor = 'transparent';
        const files = e.dataTransfer.files;
        handleImageUpload(files);
    });

    uploadArea.addEventListener('click', () => {
        imageInput.click();
    });
}

// File input change
if (imageInput) {
    imageInput.addEventListener('change', (e) => {
        handleImageUpload(e.target.files);
    });
}

function handleImageUpload(files) {
    if (files.length === 0) return;

    const formData = new FormData();
    formData.append('map_id', mapId);

    for (let file of files) {
        // Validate file
        if (file.size > 5 * 1024 * 1024) {
            showToast('File ' + file.name + ' is too large (max 5MB)', 'warning');
            continue;
        }
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            showToast('File ' + file.name + ' is not a valid image', 'warning');
            continue;
        }
        formData.append('images[]', file);
    }

    // Show progress
    uploadProgress.style.display = 'block';
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/upload-product-images')); ?>', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Response error:', text);
            throw new Error(text || 'Upload failed');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        uploadProgress.style.display = 'none';
        if (data.success) {
            showToast('Images uploaded successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.message || 'Upload failed'), 'error');
        }
    })
    .catch(error => {
        uploadProgress.style.display = 'none';
        console.error('Error:', error);
        showToast('Error uploading images: ' + error.message, 'error');
    });
}

function deleteProductImage(imageName, mapId) {
    if (!confirm('Delete this image?')) return;

    const formData = new FormData();
    formData.append('map_id', mapId);
    formData.append('image_name', imageName);

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/delete-product-image')); ?>', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Response error:', text);
            throw new Error(text || 'Delete failed');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Image deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.message || 'Delete failed'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error deleting image: ' + error.message, 'error');
    });
}
</script>

<style>
.edit-product-container {
    padding: 20px;
}

.card {
    border: 1px solid #dee2e6;
    transition: box-shadow 0.3s;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.card-header {
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    font-weight: 600;
}

.product-form input[readonly] {
    background-color: #f8f9fa;
}

#uploadArea {
    transition: all 0.3s ease;
}

#uploadArea:hover {
    background-color: #f0f7ff;
}
</style>

