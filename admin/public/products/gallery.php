<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name_en as category_name_en, c.name_ne as category_name_ne 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

// Redirect if product not found
if (!$product) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: ../products.php");
    exit;
}

// Get all product images
$images_stmt = $pdo->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, id ASC
");
$images_stmt->execute([$id]);
$product_images = $images_stmt->fetchAll();

// Set page title
$page_title = "Product Gallery - " . htmlspecialchars($product['name_en']) . " - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        Product Gallery
        <small class="text-muted"><?php echo htmlspecialchars($product['name_en']); ?></small>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../products.php" class="btn btn-sm btn-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Back to Products
        </a>
        <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-edit me-1"></i> Edit Product
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

<!-- Product Info Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <?php 
                $primary_image = null;
                foreach ($product_images as $img) {
                    if ($img['is_primary']) {
                        $primary_image = $img;
                        break;
                    }
                }
                ?>
                
                <?php if ($primary_image): ?>
                <img src="<?php echo getProductImageUrl($primary_image['image_path']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name_en']); ?>" 
                     class="img-fluid rounded"
                     style="max-height: 120px; object-fit: cover;">
                <?php else: ?>
                <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                     style="height: 120px;">
                    <i class="fas fa-box text-muted fa-2x"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-10">
                <h5 class="card-title"><?php echo htmlspecialchars($product['name_en']); ?></h5>
                <p class="card-text mb-1">
                    <strong>Nepali Name:</strong> <?php echo htmlspecialchars($product['name_ne']); ?>
                </p>
                <p class="card-text mb-1">
                    <strong>Category:</strong> <?php echo htmlspecialchars($product['category_name_en']); ?>
                </p>
                <p class="card-text mb-1">
                    <strong>Price:</strong> Rs. <?php echo number_format($product['price'], 2); ?>
                </p>
                <p class="card-text mb-0">
                    <strong>Stock:</strong> <?php echo $product['stock_quantity']; ?> 
                    <?php if ($product['stock_quantity'] <= $product['min_stock_alert']): ?>
                    <span class="badge bg-danger ms-1">Low Stock!</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Upload Section -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>Upload Images
                </h5>
            </div>
            <div class="card-body">
                <div id="uploadLoading" class="alert alert-info d-none" role="alert">
                    <i class="bi bi-hourglass-split"></i> Uploading images...
                </div>
                <form id="galleryUploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="gallery_images" class="form-label">Select Images</label>
                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" 
                               accept="image/*" multiple required>
                        <small class="form-text text-muted">
                            You can select multiple images (JPG, PNG, GIF). Maximum file size: 5MB per image.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-upload me-2"></i>Upload Images
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6>Gallery Statistics</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-images me-2 text-primary"></i> Total Images: <span id="totalImagesCount"><?php echo count($product_images); ?></span></li>
                        <li><i class="fas fa-star me-2 text-warning"></i> Primary Image: 
                            <span id="primaryImageStatus"><?php echo !empty($product_images) && $product_images[0]['is_primary'] ? 'Set' : 'Not Set'; ?></span>
                        </li>
                        <li><i class="fas fa-info-circle me-2 text-info"></i> Max recommended: 10 images</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gallery Images -->
    <div class="col-md-8">
        <?php if (empty($product_images)): ?>
        <div class="card text-center">
            <div class="card-body py-5">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <h5 class="card-title">No Images Found</h5>
                <p class="card-text">This product doesn't have any images yet. Upload some images to get started.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-images me-2"></i>Product Images (<?php echo count($product_images); ?>)
                </h5>
                <div class="form-text">
                    Click on images to view larger
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($product_images as $image): ?>
                    <div class="col-md-4 col-sm-6 mb-4">
                        <div class="card h-100">
                            <a href="<?php echo getProductImageUrl($image['image_path']); ?>" 
                               data-lightbox="product-gallery" 
                               data-title="<?php echo htmlspecialchars($product['name_en']); ?>">
                                <img src="<?php echo getProductImageUrl($image['image_path']); ?>" 
                                     class="card-img-top" 
                                     alt="Product Image"
                                     style="height: 200px; object-fit: cover; cursor: pointer;">
                            </a>
                            <div class="card-body text-center">
                                <?php if ($image['is_primary']): ?>
                                <span class="badge bg-warning mb-2">
                                    <i class="fas fa-star me-1"></i>Primary
                                </span>
                                <?php endif; ?>
                                
                                <div class="btn-group w-100">
                                    <?php if (!$image['is_primary']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                       title="Set as Primary"
                                       onclick="setPrimaryImage(<?php echo $image['id']; ?>)">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                       title="Delete Image"
                                       onclick="deleteImage(<?php echo $image['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <small class="text-muted d-block mt-1">
                                    <?php 
                                    $file_path = '../../storage/uploads/products/' . $image['image_path'];
                                    if (file_exists($file_path)) {
                                        echo round(filesize($file_path) / 1024, 1) . ' KB';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lightbox CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">

<!-- Lightbox JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<script>
const PRODUCT_ID = <?php echo $id; ?>;

// Lightbox configuration
lightbox.option({
    'resizeDuration': 200,
    'wrapAround': true,
    'imageFadeDuration': 300,
    'positionFromTop': 50,
    'showImageNumberLabel': true,
    'alwaysShowNavOnTouchDevices': true
});

// Initialize upload form
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('galleryUploadForm');
    const galleryInput = document.getElementById('gallery_images');

    // Form validation
    galleryInput.addEventListener('change', function(e) {
        const files = e.target.files;
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check file size
            if (file.size > maxSize) {
                showErrorMessage('File "' + file.name + '" is too large. Maximum file size is 5MB.');
                e.target.value = '';
                return;
            }
            
            // Check file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                showErrorMessage('File "' + file.name + '" is not a valid image type. Please use JPG, PNG, or GIF.');
                e.target.value = '';
                return;
            }
        }
        
        // Limit to 10 files
        if (files.length > 10) {
            showErrorMessage('You can upload maximum 10 images at once.');
            e.target.value = '';
        }
    });

    // Upload form submission via AJAX
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const loadingDiv = document.getElementById('uploadLoading');
        
        loadingDiv.classList.remove('d-none');

        fetch('ajax/manage-gallery.php?product_id=' + PRODUCT_ID + '&action=upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showSuccessMessage(data.message);
                    // Reset form
                    uploadForm.reset();
                    // Reload gallery after brief delay
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showErrorMessage(data.message || 'Error uploading images');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showErrorMessage('Error parsing server response');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            showErrorMessage('Error uploading images: ' + error.message);
        })
        .finally(() => {
            loadingDiv.classList.add('d-none');
        });
    });
});

/**
 * Delete image via AJAX
 */
function deleteImage(imageId) {
    if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
        return;
    }

    const loadingDiv = document.getElementById('uploadLoading');
    loadingDiv.classList.remove('d-none');
    loadingDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> Deleting image...';

    fetch('ajax/manage-gallery.php?product_id=' + PRODUCT_ID + '&action=delete&image_id=' + imageId, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showSuccessMessage(data.message);
                // Reload gallery after brief delay
                setTimeout(() => location.reload(), 1000);
            } else {
                showErrorMessage(data.message || 'Error deleting image');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            showErrorMessage('Error parsing server response');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showErrorMessage('Error deleting image: ' + error.message);
    })
    .finally(() => {
        loadingDiv.classList.add('d-none');
    });
}

/**
 * Set image as primary via AJAX
 */
function setPrimaryImage(imageId) {
    if (!confirm('Set this image as primary?')) {
        return;
    }

    const loadingDiv = document.getElementById('uploadLoading');
    loadingDiv.classList.remove('d-none');
    loadingDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating primary image...';

    fetch('ajax/manage-gallery.php?product_id=' + PRODUCT_ID + '&action=set_primary&image_id=' + imageId, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showSuccessMessage(data.message);
                // Reload gallery after brief delay
                setTimeout(() => location.reload(), 1000);
            } else {
                showErrorMessage(data.message || 'Error updating primary image');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            showErrorMessage('Error parsing server response');
        }
    })
    .catch(error => {
        console.error('Set primary error:', error);
        showErrorMessage('Error updating primary image: ' + error.message);
    })
    .finally(() => {
        loadingDiv.classList.add('d-none');
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
    const container = document.querySelector('.border-bottom');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container.nextSibling);
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
    const container = document.querySelector('.border-bottom');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container.nextSibling);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}


<style>
.card-img-top {
    transition: transform 0.2s ease-in-out;
}

.card-img-top:hover {
    transform: scale(1.05);
}

.badge {
    font-size: 0.7em;
}

.btn-group .btn {
    flex: 1;
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>