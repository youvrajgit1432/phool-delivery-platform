<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication products/add.php
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get main categories and all categories for the form
$main_categories = $pdo->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    AND status='active' 
    AND is_product_allowed=1 
    ORDER BY sort_order, name_en
")->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Insert new product
        $stmt = $pdo->prepare("
            INSERT INTO products 
            (name_en, name_ne, slug_en, slug_ne, description_en, description_ne, 
             price, bulk_price, event_price, unit, stock_quantity, 
             min_stock_alert, expiry_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $name_en, $name_ne, $slug_en, $slug_ne, $description_en, $description_ne, 
            $price, $bulk_price, $event_price, $unit, $stock_quantity, 
            $min_stock_alert, $expiry_date, $status
        ])) {
            $product_id = $pdo->lastInsertId();
            
            // Insert product categories
            $category_stmt = $pdo->prepare("
                INSERT INTO product_categories (product_id, category_id) 
                VALUES (?, ?)
            ");
            foreach ($category_ids as $category_id) {
                $category_stmt->execute([$product_id, intval($category_id)]);
            }
            
            // Handle image uploads
            $upload_dir = '../../storage/uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Process primary image (resize + compress)
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['primary_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $filename;

                if (processAndSaveImage($_FILES['primary_image']['tmp_name'], $target_path)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_path, is_primary) 
                        VALUES (?, ?, 1)
                    ");
                    $stmt->execute([$product_id, $filename]);
                }
            }
            
            // Process gallery images
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
                            $stmt->execute([$product_id, $filename]);
                        }
                    }
                }
            }
            
            $_SESSION['success_message'] = "Product added successfully!";
            header("Location: ../products.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add product.";
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

// Image processing helper: resizes and compresses while preserving quality
function processAndSaveImage($tmpPath, $targetPath, $maxWidth = 1200, $maxHeight = 1200, $jpegQuality = 85) {
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
            // Fallback to generic loader
            $src = @imagecreatefromstring(file_get_contents($tmpPath));
    }

    if (!$src) {
        return false;
    }

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

    // Save according to extension (preserve original where possible)
    $saved = false;
    if (in_array($ext, ['jpg', 'jpeg'])) {
        $saved = imagejpeg($dst, $targetPath, $jpegQuality);
    } elseif ($ext === 'png') {
        // PNG compression level 0 (no compression) to 9
        $pngLevel = 6;
        $saved = imagepng($dst, $targetPath, $pngLevel);
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
$page_title = "Add Product - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Product</h1>
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
                        <input type="text" class="form-control" id="name_en" name="name_en" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name_ne" class="form-label">Product Name (Nepali) *</label>
                        <input type="text" class="form-control" id="name_ne" name="name_ne" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_en" class="form-label">Description (English)</label>
                        <textarea class="form-control" id="description_en" name="description_en" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_ne" class="form-label">Description (Nepali)</label>
                        <textarea class="form-control" id="description_ne" name="description_ne" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Pricing Information -->
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulk_price" class="form-label">Bulk Price (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="bulk_price" name="bulk_price">
                        <small class="form-text text-muted">Price for bulk orders (optional)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_price" class="form-label">Event Price (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="event_price" name="event_price">
                        <small class="form-text text-muted">Price for event bookings (optional)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="unit" class="form-label">Unit *</label>
                        <select class="form-select" id="unit" name="unit" required>
                            <option value="piece">Piece</option>
                            <option value="bunch">Bunch</option>
                            <option value="kg">Kilogram</option>
                            <option value="garland">Garland</option>
                            <option value="dozen">Dozen</option>
                            <option value="meter">Meter</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_stock_alert" class="form-label">Low Stock Alert *</label>
                        <input type="number" class="form-control" id="min_stock_alert" name="min_stock_alert" value="10" required>
                        <small class="form-text text-muted">Alert when stock falls below this number</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        <small class="form-text text-muted">For perishable items only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="out_of_stock">Out of Stock</option>
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
                                            <option value="<?php echo $main_cat['id']; ?>">
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
                                <strong>Category Information:</strong> 
                                Each product must belong to a main category. You can optionally assign it to a specific subcategory.
                                Categories are shown in their respective tables - main categories and subcategories.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Image Upload Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Product Images</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="primary_image" class="form-label">Primary Image *</label>
                                        <input type="file" class="form-control" id="primary_image" name="primary_image" accept="image/*" required>
                                        <small class="form-text text-muted">
                                            This will be the main display image for the product. 
                                            Recommended size: 800x800 pixels.
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gallery_images" class="form-label">Gallery Images</label>
                                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                                        <small class="form-text text-muted">
                                            You can select up to 9 additional images for the product gallery.
                                            Each image max size: 5MB.
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
                            <button type="submit" name="add_product" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Add Product
                            </button>
                            <button type="reset" class="btn btn-secondary me-2">
                                <i class="fas fa-redo me-1"></i> Reset Form
                            </button>
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
                    options += `<option value="${subcat.id}">${subcat.name_en} (${subcat.name_ne})</option>`;
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

// Image preview functionality
document.addEventListener('DOMContentLoaded', function() {
    // Primary image preview
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
            badge.textContent = isPrimary ? 'Primary' : 'Gallery';
            
            cardBody.appendChild(badge);
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
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
        }
    });
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

.image-preview-container {
    border: 1px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-top: 1rem;
    background-color: #f8f9fa;
}

.card-img-top {
    transition: transform 0.2s;
}

.card-img-top:hover {
    transform: scale(1.05);
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>