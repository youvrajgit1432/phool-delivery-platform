<?php
/**
 * Manage Product Images
 * Admin panel page to manage product images for vendors
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

    // Fetch product details
    $stmt = $db->prepare("SELECT * FROM vendor_products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: ../vendor-products/manage.php?vendor_id=' . $vendor_id);
        exit;
    }

    // Fetch product images
    $stmt = $db->prepare("
        SELECT * FROM vendor_product_images 
        WHERE product_id = ? 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$product_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = 'Product Images - ' . htmlspecialchars($product['product_name']);
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
    <style>
        .image-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            background: #f8f9fa;
            min-height: 250px;
        }
        .image-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .image-card:hover .image-overlay {
            opacity: 1;
        }
        .image-overlay button {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area.dragover {
            background-color: #e7f3ff;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-0">Product Images</h1>
                <p class="text-muted mt-1">
                    Store: <strong><?php echo htmlspecialchars($vendor['store_name']); ?></strong> | 
                    Product: <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                </p>
            </div>
        </div>

        <!-- Upload Area -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-cloud-upload"></i> Upload Images</h6>
            </div>
            <div class="card-body">
                <div class="upload-area" id="uploadArea">
                    <div>
                        <i class="bi bi-image" style="font-size: 2rem; color: #007bff;"></i>
                        <p class="mt-3 mb-0">
                            <strong>Drag and drop images here</strong><br>
                            <small class="text-muted">or click to browse</small>
                        </p>
                    </div>
                    <input type="file" id="imageInput" multiple accept="image/*" style="display: none;">
                </div>
                <small class="text-muted mt-2 d-block">Supported formats: JPG, PNG, GIF. Max size: 5MB per image</small>
            </div>
        </div>

        <!-- Current Images -->
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">Current Images (<?php echo count($images); ?>)</h5>
                
                <?php if (empty($images)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No images uploaded yet. Upload images above.
                    </div>
                <?php else: ?>
                    <div class="row g-3" id="imagesContainer">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="col-md-4 col-lg-3" id="image-<?php echo $image['id']; ?>">
                                <div class="image-card">
                                    <?php $imgPath = convertImagePath($image['image_url']); ?>
                                    <img src="<?php echo htmlspecialchars($imgPath); ?>" 
                                         alt="<?php echo htmlspecialchars($image['alt_text'] ?? 'Product Image'); ?>"
                                         title="<?php echo htmlspecialchars($imgPath); ?>"
                                         style="display: block;"
                                         onerror="console.error('Image failed to load:', this.src); this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%25%22 height=%22250%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%25%22 height=%22250%22/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-size=%2716%27 fill=%27%236c757d%27%3EImage not found%3C/text%3E%3C/svg%3E';">
                                    <div class="image-overlay">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteImage(<?php echo $image['id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Uploaded: <?php echo date('M d, Y', strtotime($image['created_at'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-4">
            <a href="../vendor-products/edit.php?vendor_id=<?php echo $vendor_id; ?>&product_id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Product
            </a>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');

        uploadArea.addEventListener('click', () => imageInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            // Handle dropped files directly
            const droppedFiles = e.dataTransfer.files;
            uploadImages(droppedFiles);
        });

        imageInput.addEventListener('change', (e) => uploadImages(e.target.files));

        function uploadImages(files) {
            const fileList = files || imageInput.files;
            if (!fileList || fileList.length === 0) return;

            const formData = new FormData();
            formData.append('product_id', <?php echo $product_id; ?>);
            formData.append('vendor_id', <?php echo $vendor_id; ?>);

            // validate and append
            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024;
            let appended = 0;
            for (let i = 0; i < fileList.length; i++) {
                const file = fileList[i];
                if (!allowed.includes(file.type)) continue;
                if (file.size > maxSize) continue;
                formData.append('images[]', file);
                appended++;
            }

            if (appended === 0) {
                alert('No valid images to upload (allowed: JPG, PNG, GIF; max 5MB)');
                return;
            }

            fetch('../../api/image-upload.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Images uploaded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading images');
            });
        }

        function deleteImage(imageId) {
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('../../api/image-delete.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'image_id=' + imageId + '&product_id=' + <?php echo $product_id; ?> + '&vendor_id=' + <?php echo $vendor_id; ?>
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const imageElement = document.getElementById('image-' + imageId);
                        if (imageElement) {
                            imageElement.remove();
                        }
                        alert('Image deleted successfully');
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
