<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication products/edit.php
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get product details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

// Redirect if product not found
if (!$product) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: ../products.php");
    exit;
}

// Get product categories
$categories_stmt = $pdo->prepare("
    SELECT pc.category_id, c.name_en, c.parent_id 
    FROM product_categories pc 
    JOIN categories c ON pc.category_id = c.id 
    WHERE pc.product_id = ?
");
$categories_stmt->execute([$id]);
$product_categories = $categories_stmt->fetchAll();

// Determine main category and subcategory
$main_category_id = 0;
$subcategory_id = 0;

foreach ($product_categories as $cat) {
    if ($cat['parent_id'] === null) {
        $main_category_id = $cat['category_id'];
    } else {
        $subcategory_id = $cat['category_id'];
    }
}

// Get main categories for the form
$main_categories = $pdo->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    AND status='active' 
    AND is_product_allowed=1 
    ORDER BY sort_order, name_en
")->fetchAll();

// Get product images
$images_stmt = $pdo->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, id ASC
");
$images_stmt->execute([$id]);
$product_images = $images_stmt->fetchAll();

// Handle image deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image_id'])) {
    $image_id = intval($_POST['delete_image_id']);
    
    // Verify the image belongs to this product
    $verify_stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
$verify_stmt->execute([$image_id, $id]);
$image = $verify_stmt->fetch();
    
    if ($image) {
        $upload_dir = '../../storage/uploads/products/';
        
        // Delete file from server
        if (file_exists($upload_dir . $image['image_path'])) {
            unlink($upload_dir . $image['image_path']);
        }
        
        // Delete from database
        $delete_stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
        if ($delete_stmt->execute([$image_id])) {
            echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete image from database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
    }
    exit;
}

// Process form submission for product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name_en'])) {
    $name_en = trim($_POST['name_en']);
    $name_ne = trim($_POST['name_ne']);
    $description_en = trim($_POST['description_en']);
    $description_ne = trim($_POST['description_ne']);
    
    // Get categories from hierarchical structure
    $main_category = isset($_POST['main_category']) ? intval($_POST['main_category']) : 0;
    $subcategory = isset($_POST['subcategory']) ? intval($_POST['subcategory']) : 0;
    
    // Prepare category IDs array
    $category_ids = [];
    
    if ($main_category) {
        $category_ids[] = $main_category;
    }
    
    if ($subcategory) {
        $category_ids[] = $subcategory;
    }
    
    // Validate at least one category is selected
    if (empty($category_ids)) {
        $_SESSION['error_message'] = "Please select at least one main category.";
    } else {
        $price = floatval($_POST['price']);
        $bulk_price = floatval($_POST['bulk_price']);
        $event_price = floatval($_POST['event_price']);
        $unit = $_POST['unit'];
        $stock_quantity = intval($_POST['stock_quantity']);
        $min_stock_alert = intval($_POST['min_stock_alert']);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $status = $_POST['status'];
        
        // Generate slugs
        $slug_en = generateSlug($name_en);
        $slug_ne = generateSlug($name_ne);
        
        // Update product
        $stmt = $pdo->prepare("
            UPDATE products SET 
            name_en = ?, name_ne = ?, slug_en = ?, slug_ne = ?, 
            description_en = ?, description_ne = ?, 
            price = ?, bulk_price = ?, event_price = ?, unit = ?, 
            stock_quantity = ?, min_stock_alert = ?, 
            expiry_date = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt->execute([
            $name_en, $name_ne, $slug_en, $slug_ne, 
            $description_en, $description_ne, 
            $price, $bulk_price, $event_price, $unit, 
            $stock_quantity, $min_stock_alert, 
            $expiry_date, $status, $id
        ])) {
            
            // Update product categories
            // First, remove existing categories
            $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]);
            
            // Then insert new categories
            $category_stmt = $pdo->prepare("
                INSERT INTO product_categories (product_id, category_id) 
                VALUES (?, ?)
            ");
            foreach ($category_ids as $category_id) {
                $category_stmt->execute([$id, intval($category_id)]);
            }
            
            $upload_dir = '../../storage/uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Process primary image update (with compression)
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                // Delete old primary image if exists
                $old_primary = $pdo->prepare("
                    SELECT image_path FROM product_images 
                    WHERE product_id = ? AND is_primary = 1
                ");
                $old_primary->execute([$id]);
                $old_primary_image = $old_primary->fetch();
                
                if ($old_primary_image && file_exists($upload_dir . $old_primary_image['image_path'])) {
                    unlink($upload_dir . $old_primary_image['image_path']);
                }
                
                // Delete old primary image record
                $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND is_primary = 1")->execute([$id]);
                
                // Upload and process new primary image with compression
                $file_extension = pathinfo($_FILES['primary_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $filename;
                
                if (processAndSaveImage($_FILES['primary_image']['tmp_name'], $target_path)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_path, is_primary) 
                        VALUES (?, ?, 1)
                    ");
                    $stmt->execute([$id, $filename]);
                }
            }
            
            // Process gallery images (with compression)
            if (isset($_FILES['gallery_images'])) {
                $gallery_images = $_FILES['gallery_images'];
                
                for ($i = 0; $i < count($gallery_images['name']); $i++) {
                    if ($gallery_images['error'][$i] === UPLOAD_ERR_OK) {
                        $file_extension = pathinfo($gallery_images['name'][$i], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (processAndSaveImage($gallery_images['tmp_name'][$i], $target_path)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO product_images (product_id, image_path, is_primary) 
                                VALUES (?, ?, 0)
                            ");
                            $stmt->execute([$id, $filename]);
                        }
                    }
                }
            }
            
            $_SESSION['success_message'] = "Product updated successfully!";
            header("Location: ../products.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update product.";
        }
    }
}

// Helper function to generate slug
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// Image processing helper: aggressively compresses images to ~80KB target
function processAndSaveImage($tmpPath, $targetPath, $maxWidth = 1000, $maxHeight = 1000, $jpegQuality = 75) {
    $info = @getimagesize($tmpPath);
    if (!$info) {
        return false;
    }

    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    // Calculate new dimensions preserving aspect ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newW = (int) max(1, floor($width * $ratio));
    $newH = (int) max(1, floor($height * $ratio));

    // Create image resource from uploaded file
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($tmpPath);
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($tmpPath);
            break;
        default:
            return false;
    }

    if (!$src) {
        return false;
    }

    // Always resize and re-encode for maximum compression
    $dst = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG and GIF
    if (in_array($mime, ['image/png', 'image/gif'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Save according to extension with aggressive compression
    $saved = false;
    if (in_array($ext, ['jpg', 'jpeg'])) {
        // JPEG quality 75 for aggressive compression while maintaining visual quality
        $saved = imagejpeg($dst, $targetPath, $jpegQuality);
    } elseif ($ext === 'png') {
        // PNG compression level 9 (maximum compression)
        $saved = imagepng($dst, $targetPath, 9);
    } elseif ($ext === 'gif') {
        $saved = imagegif($dst, $targetPath);
    } else {
        // Fallback: save as JPEG to ensure compression
        $saved = imagejpeg($dst, $targetPath, $jpegQuality);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $saved;
}

// Set page title
$page_title = "Edit Product - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Product</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../products.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Products
        </a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
            <div class="row">
                <div class="col-md-6">
                    <!-- Product Basic Information -->
                    <div class="mb-3">
                        <label for="name_en" class="form-label">Product Name (English) *</label>
                        <input type="text" class="form-control" id="name_en" name="name_en" 
                               value="<?php echo htmlspecialchars($product['name_en']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name_ne" class="form-label">Product Name (Nepali) *</label>
                        <input type="text" class="form-control" id="name_ne" name="name_ne" 
                               value="<?php echo htmlspecialchars($product['name_ne']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_en" class="form-label">Description (English)</label>
                        <textarea class="form-control" id="description_en" name="description_en" rows="3"><?php echo htmlspecialchars($product['description_en']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_ne" class="form-label">Description (Nepali)</label>
                        <textarea class="form-control" id="description_ne" name="description_ne" rows="3"><?php echo htmlspecialchars($product['description_ne']); ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Pricing Information -->
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" 
                               value="<?php echo $product['price']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulk_price" class="form-label">Bulk Price (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="bulk_price" name="bulk_price" 
                               value="<?php echo $product['bulk_price']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_price" class="form-label">Event Price (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="event_price" name="event_price" 
                               value="<?php echo $product['event_price']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="unit" class="form-label">Unit *</label>
                        <select class="form-select" id="unit" name="unit" required>
                            <option value="piece" <?php echo $product['unit'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                            <option value="bunch" <?php echo $product['unit'] == 'bunch' ? 'selected' : ''; ?>>Bunch</option>
                            <option value="kg" <?php echo $product['unit'] == 'kg' ? 'selected' : ''; ?>>Kilogram</option>
                            <option value="garland" <?php echo $product['unit'] == 'garland' ? 'selected' : ''; ?>>Garland</option>
                            <option value="dozen" <?php echo $product['unit'] == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                            <option value="meter" <?php echo $product['unit'] == 'meter' ? 'selected' : ''; ?>>Meter</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                               value="<?php echo $product['stock_quantity']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_stock_alert" class="form-label">Low Stock Alert *</label>
                        <input type="number" class="form-control" id="min_stock_alert" name="min_stock_alert" 
                               value="<?php echo $product['min_stock_alert']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                               value="<?php echo $product['expiry_date']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="out_of_stock" <?php echo $product['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Hierarchical Categories Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Category Selection</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="main_category" class="form-label">Main Category *</label>
                                        <select class="form-select" id="main_category" name="main_category" required onchange="loadSubcategories(this.value)">
                                            <option value="">-- Select Main Category --</option>
                                            <?php foreach ($main_categories as $main_cat): ?>
                                            <option value="<?php echo $main_cat['id']; ?>" 
                                                <?php echo $main_category_id == $main_cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($main_cat['name_en']); ?>
                                                (<?php echo htmlspecialchars($main_cat['name_ne']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3" id="subcategory_container">
                                        <label for="subcategory" class="form-label">Subcategory</label>
                                        <select class="form-select" id="subcategory" name="subcategory">
                                            <option value="">-- Select Subcategory (Optional) --</option>
                                            <!-- Subcategories will be loaded dynamically -->
                                        </select>
                                        <small class="form-text text-muted">
                                            Optional: Select a specific subcategory within the main category
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Current Categories:</strong> 
                                <?php 
                                $category_names = [];
                                foreach ($product_categories as $cat) {
                                    $category_names[] = htmlspecialchars($cat['name_en']);
                                }
                                echo implode(', ', $category_names);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Current Images Section -->
            <?php if (!empty($product_images)): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Product Images</h5>
                            <small class="text-muted">(Images are automatically compressed and resized)</small>
                        </div>
                        <div class="card-body">
                            <div class="row" id="currentImages">
                                <?php foreach ($product_images as $img): ?>
                                <div class="col-md-3 mb-3" id="imageCard_<?php echo $img['id']; ?>">
                                    <div class="card h-100">
                                        <img src="<?php echo getProductImageUrl($img['image_path']); ?>" 
                                             class="card-img-top" 
                                             alt="Product Image"
                                             style="height: 150px; object-fit: cover;">
                                        <div class="card-body text-center">
                                            <?php if ($img['is_primary']): ?>
                                            <span class="badge bg-primary mb-2">
                                                <i class="fas fa-star me-1"></i>Primary
                                            </span>
                                            <?php endif; ?>
                                            
                                            <div class="btn-group w-100">
                                                <?php if (!$img['is_primary']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary set-primary-btn"
                                                        data-image-id="<?php echo $img['id']; ?>"
                                                        title="Set as Primary">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-image-btn"
                                                        data-image-id="<?php echo $img['id']; ?>"
                                                        title="Delete Image">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- New Image Upload Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upload New Images</h5>
                            <small class="text-muted">(Images will be automatically compressed and resized to 1200x1200)</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="primary_image" class="form-label">Update Primary Image</label>
                                        <input type="file" class="form-control" id="primary_image" name="primary_image" accept="image/*">
                                        <small class="form-text text-muted">
                                            Leave empty to keep current primary image. Max size: 5MB. Will be compressed and resized.
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gallery_images" class="form-label">Add More Gallery Images</label>
                                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                                        <small class="form-text text-muted">
                                            Select multiple images to add to gallery. Each image will be compressed and resized.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="image-preview-container d-none" id="imagePreview">
                                <div class="row" id="previewRow"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" name="edit_product" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Update Product
                            </button>
                            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger me-2" 
                               onclick="return confirm('Are you sure you want to delete this product?')">
                                <i class="fas fa-trash me-1"></i> Delete Product
                            </a>
                        </div>
                        <a href="../products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function loadSubcategories(mainCategoryId) {
    const subcategorySelect = document.getElementById('subcategory');
    
    if (!mainCategoryId) {
        subcategorySelect.innerHTML = '<option value="">-- Select Subcategory (Optional) --</option>';
        return;
    }
    
    // Show loading state
    subcategorySelect.innerHTML = '<option value="">Loading subcategories...</option>';
    subcategorySelect.disabled = true;
    
    // AJAX call to load subcategories
    fetch('get_subcategories.php?parent_id=' + mainCategoryId)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">-- Select Subcategory (Optional) --</option>';
            if (data.length > 0) {
                data.forEach(subcat => {
                    const selected = <?php echo $subcategory_id; ?> == subcat.id ? 'selected' : '';
                    options += `<option value="${subcat.id}" ${selected}>${subcat.name_en} (${subcat.name_ne})</option>`;
                });
            } else {
                options = '<option value="">No subcategories available for this main category</option>';
            }
            subcategorySelect.innerHTML = options;
            subcategorySelect.disabled = false;
        })
        .catch(error => {
            console.error('Error loading subcategories:', error);
            subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
            subcategorySelect.disabled = false;
        });
}

// Initialize subcategories on page load
document.addEventListener('DOMContentLoaded', function() {
    const mainCategory = document.getElementById('main_category');
    if (mainCategory.value) {
        loadSubcategories(mainCategory.value);
    }
    
    // Image preview functionality
    const primaryImageInput = document.getElementById('primary_image');
    const galleryImagesInput = document.getElementById('gallery_images');
    const previewContainer = document.getElementById('imagePreview');
    const previewRow = document.getElementById('previewRow');
    
    function previewImage(file, isPrimary = false) {
        if (!file.type.match('image.*')) {
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const colDiv = document.createElement('div');
            colDiv.className = 'col-md-3 mb-3';
            
            const cardDiv = document.createElement('div');
            cardDiv.className = 'card';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'card-img-top';
            img.style.height = '150px';
            img.style.objectFit = 'cover';
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body text-center';
            
            const badge = document.createElement('span');
            badge.className = 'badge ' + (isPrimary ? 'bg-primary' : 'bg-secondary');
            badge.textContent = isPrimary ? 'New Primary' : 'New Gallery';
            
            const infoText = document.createElement('small');
            infoText.className = 'text-muted d-block mt-1';
            infoText.textContent = Math.round(file.size / 1024) + 'KB';
            
            cardBody.appendChild(badge);
            cardBody.appendChild(infoText);
            cardDiv.appendChild(img);
            cardDiv.appendChild(cardBody);
            colDiv.appendChild(cardDiv);
            previewRow.appendChild(colDiv);
            
            previewContainer.classList.remove('d-none');
        };
        
        reader.readAsDataURL(file);
    }
    
    primaryImageInput.addEventListener('change', function(e) {
        previewRow.innerHTML = '';
        if (this.files && this.files[0]) {
            previewImage(this.files[0], true);
        }
    });
    
    galleryImagesInput.addEventListener('change', function(e) {
        if (this.files) {
            Array.from(this.files).forEach(file => {
                previewImage(file, false);
            });
        }
    });
    
    // Delete image button functionality
    document.querySelectorAll('.delete-image-btn').forEach(button => {
        button.addEventListener('click', function() {
            const imageId = this.getAttribute('data-image-id');
            const imageCard = document.getElementById('imageCard_' + imageId);
            
            if (confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                // Send AJAX request to delete image
                const formData = new FormData();
                formData.append('delete_image_id', imageId);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove image card from UI
                        imageCard.remove();
                        
                        // Show success message
                        showAlert('Image deleted successfully!', 'success');
                        
                        // If no images left, hide the section
                        const remainingImages = document.querySelectorAll('#currentImages .col-md-3');
                        if (remainingImages.length === 0) {
                            document.querySelector('.card-header h5').textContent = 'No images available';
                        }
                    } else {
                        showAlert('Failed to delete image: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while deleting the image.', 'danger');
                });
            }
        });
    });
    
    // Set as primary button functionality
    document.querySelectorAll('.set-primary-btn').forEach(button => {
        button.addEventListener('click', function() {
            const imageId = this.getAttribute('data-image-id');
            
            // Send AJAX request to set as primary
            fetch('gallery.php?id=<?php echo $id; ?>&set_primary=' + imageId)
                .then(response => response.text())
                .then(() => {
                    location.reload(); // Reload page to update primary badge
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Failed to set as primary image.', 'danger');
                });
        });
    });
    
    // Form validation
    const form = document.getElementById('productForm');
    form.addEventListener('submit', function(e) {
        const mainCategory = document.getElementById('main_category');
        const price = document.getElementById('price');
        const stock = document.getElementById('stock_quantity');
        
        let isValid = true;
        
        // Reset validation
        [mainCategory, price, stock].forEach(el => {
            el.classList.remove('is-invalid');
        });
        
        // Validate main category
        if (!mainCategory.value) {
            mainCategory.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate price
        if (!price.value || parseFloat(price.value) <= 0) {
            price.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate stock
        if (!stock.value || parseInt(stock.value) < 0) {
            stock.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate file sizes
        const primaryImage = document.getElementById('primary_image');
        const galleryImages = document.getElementById('gallery_images');
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (primaryImage.files[0] && primaryImage.files[0].size > maxSize) {
            alert('Primary image size exceeds 5MB limit.');
            isValid = false;
        }
        
        if (galleryImages.files.length > 0) {
            for (let i = 0; i < galleryImages.files.length; i++) {
                if (galleryImages.files[i].size > maxSize) {
                    alert(`Gallery image ${i + 1} exceeds 5MB limit.`);
                    isValid = false;
                    break;
                }
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
        }
    });
    
    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.custom-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show custom-alert`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert after the main heading
        const heading = document.querySelector('.border-bottom');
        heading.parentNode.insertBefore(alertDiv, heading.nextSibling);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
});
</script>

<style>
.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.card-img-top {
    transition: transform 0.2s;
}

.card-img-top:hover {
    transform: scale(1.05);
}

.btn-group .btn {
    flex: 1;
}

.image-preview-container {
    border: 1px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-top: 1rem;
    background-color: #f8f9fa;
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>