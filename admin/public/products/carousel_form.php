<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication public/products/carousel_form.php
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get carousel ID if editing
$carousel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$carousel = null;

// Get main categories
$main_categories = $pdo->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    AND status='active' 
    AND is_product_allowed=1 
    ORDER BY sort_order, name_en
")->fetchAll();

// If editing, get carousel details
if ($carousel_id > 0) {
    $stmt = $pdo->prepare("
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        WHERE ci.id = ?
    ");
    $stmt->execute([$carousel_id]);
    $carousel = $stmt->fetch();
    
    if (!$carousel) {
        $_SESSION['error_message'] = "Carousel image not found.";
        header("Location: carousel.php");
        exit;
    }
}

// Get max sort order
$stmt = $pdo->query("SELECT MAX(sort_order) as max_sort FROM carousel_images");
$result = $stmt->fetch();
$default_sort_order = ($result['max_sort'] ?? 0) + 1;

// Helper function for upload error messages
function getUploadErrorMessage($error_code) {
    $errors = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded with success',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];
    
    return $errors[$error_code] ?? 'Unknown upload error';
}

// Set page title
$page_title = ($carousel ? "Edit Carousel Image" : "Add Carousel Image") . " - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-images me-2"></i><?php echo ($carousel ? "Edit" : "Add") ?> Carousel Image
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="carousel.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="carouselForm">
                    
                    <!-- Category Selection -->
                    <div class="mb-3">
                        <label for="main_category_id" class="form-label">Main Category <span class="text-danger">*</span></label>
                        <select id="main_category_id" name="main_category_id" class="form-select" required>
                            <option value="">-- Select Main Category --</option>
                            <?php foreach ($main_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($carousel && $carousel['main_category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name_en']); ?> / <?php echo htmlspecialchars($cat['name_ne']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the main product category for this carousel.</small>
                    </div>

                    <!-- English Title -->
                    <div class="mb-3">
                        <label for="title_en" class="form-label">Title (English) <span class="text-danger">*</span></label>
                        <input type="text" id="title_en" name="title_en" class="form-control" 
                               value="<?php echo htmlspecialchars($carousel['title_en'] ?? ''); ?>" 
                               placeholder="Enter carousel title in English" required>
                        <small class="form-text text-muted">The title displayed on the carousel.</small>
                    </div>

                    <!-- Nepali Title -->
                    <div class="mb-3">
                        <label for="title_ne" class="form-label">Title (नेपाली) <span class="text-danger">*</span></label>
                        <input type="text" id="title_ne" name="title_ne" class="form-control" 
                               value="<?php echo htmlspecialchars($carousel['title_ne'] ?? ''); ?>" 
                               placeholder="नेपालीमा शीर्षक प्रविष्ट गर्नुहोस्" required>
                    </div>

                    <!-- English Description -->
                    <div class="mb-3">
                        <label for="description_en" class="form-label">Description (English)</label>
                        <textarea id="description_en" name="description_en" class="form-control" rows="3"
                                  placeholder="Optional description in English"><?php echo htmlspecialchars($carousel['description_en'] ?? ''); ?></textarea>
                    </div>

                    <!-- Nepali Description -->
                    <div class="mb-3">
                        <label for="description_ne" class="form-label">Description (नेपाली)</label>
                        <textarea id="description_ne" name="description_ne" class="form-control" rows="3"
                                  placeholder="नेपालीमा वर्णन (वैकल्पिक)"><?php echo htmlspecialchars($carousel['description_ne'] ?? ''); ?></textarea>
                    </div>

                    <!-- Link URL -->
                    <div class="mb-3">
                        <label for="link_url" class="form-label">Link URL</label>
                        <input type="url" id="link_url" name="link_url" class="form-control" 
                               value="<?php echo htmlspecialchars($carousel['link_url'] ?? ''); ?>" 
                               placeholder="https://example.com (optional)">
                        <small class="form-text text-muted">URL to navigate when carousel is clicked.</small>
                    </div>

                    <!-- Sort Order -->
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Display Order</label>
                        <input type="number" id="sort_order" name="sort_order" class="form-control" 
                               value="<?php echo $carousel['sort_order'] ?? $default_sort_order; ?>" 
                               min="0" placeholder="0">
                        <small class="form-text text-muted">Lower numbers appear first. Leave 0 for automatic ordering.</small>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="active" <?php echo (!$carousel || $carousel['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($carousel && $carousel['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Image Upload Section -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Carousel Image <span class="text-danger">*</span></h5>
                        </div>
                        <div class="card-body">
                            <div id="imagePreview" class="mb-3">
                                <?php if ($carousel && !empty($carousel['image_path'])): ?>
                                    <img src="<?php echo getCarouselImageUrl($carousel['image_path']); ?>" 
                                         alt="Current Image" 
                                         class="img-fluid rounded" 
                                         style="max-height: 200px; width: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                         style="height: 200px; border: 2px dashed #dee2e6;">
                                        <small class="text-muted">No image uploaded</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-2">
                                <input type="file" id="carousel_image" name="carousel_image" class="form-control" 
                                       accept="image/jpeg,image/png,image/webp,image/gif"
                                       onchange="previewImage(event)" <?php echo (!$carousel_id) ? 'required' : ''; ?>>
                                <small class="form-text text-muted d-block mt-2">
                                    <?php if ($carousel_id): ?>
                                        <span class="text-info">Note: Image is optional when editing. Leave empty to keep current image.</span><br>
                                    <?php endif; ?>
                                    Recommended: 1200x400px or larger<br>
                                    Formats: JPEG, PNG, WebP, GIF<br>
                                    Max size: 5MB
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="carousel.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            <?php echo ($carousel_id ? 'Update' : 'Save'); ?> Carousel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Information Card -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Information</h5>
            </div>
            <div class="card-body">
                <h6 class="mb-2">Carousel Guidelines:</h6>
                <ul class="small" style="margin-bottom: 0;">
                    <li>Each main category can have multiple carousels</li>
                    <li>Carousels are displayed in sort order</li>
                    <li>Images should be landscape oriented</li>
                    <li>Use high-quality images for better appearance</li>
                    <li>Provide titles in both English and Nepali</li>
                    <li>Inactive carousels won't be displayed to users</li>
                    <li>Image is required for new carousels</li>
                    <li>Image is optional when editing (keeps current image)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const IS_EDIT_MODE = <?php echo ($carousel_id > 0) ? 'true' : 'false'; ?>;

function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').innerHTML = 
                `<img src="${e.target.result}" alt="Preview" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">`;
        };
        reader.readAsDataURL(file);
    }
}

// Form submission via AJAX
document.getElementById('carouselForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const mainCategory = document.getElementById('main_category_id').value;
    const titleEn = document.getElementById('title_en').value.trim();
    const titleNe = document.getElementById('title_ne').value.trim();
    const carouselImage = document.getElementById('carousel_image');
    
    // Validation
    if (!mainCategory) {
        showErrorMessage('Please select a main category');
        return false;
    }
    
    if (!titleEn || !titleNe) {
        showErrorMessage('Title in both languages is required');
        return false;
    }
    
    // If it's a new carousel, image is required
    if (!IS_EDIT_MODE && carouselImage.files.length === 0) {
        showErrorMessage('Please select an image file for new carousel');
        return false;
    }

    // Submit via AJAX
    const formData = new FormData(this);
    formData.append('carousel_id', IS_EDIT_MODE ? <?php echo $carousel_id; ?> : 0);
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    fetch('ajax/submit-carousel.php', {
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
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                showErrorMessage(data.message || 'Error saving carousel image');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response text:', text.substring(0, 200));
            showErrorMessage('Error parsing server response');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage('Error saving carousel image: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

/**
 * Show success message toast
 */
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-check-circle me-2"></i> ${message}
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
        <i class="fas fa-exclamation-circle me-2"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.border-bottom');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container.nextSibling);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}
</script>

<?php include '../../app/views/layouts/footer.php'; ?>