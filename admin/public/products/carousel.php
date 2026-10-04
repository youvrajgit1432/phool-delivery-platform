<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get all main categories
$main_categories = $pdo->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    AND status='active' 
    AND is_product_allowed=1 
    ORDER BY sort_order, name_en
")->fetchAll();

// Get selected category (for filtering)
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Build carousel query with filtering
if ($selected_category > 0) {
    $carousels_query = "
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        WHERE ci.main_category_id = ?
        ORDER BY ci.sort_order, ci.id DESC
    ";
    $stmt = $pdo->prepare($carousels_query);
    $stmt->execute([$selected_category]);
    $carousels = $stmt->fetchAll();
} else {
    $carousels_query = "
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        ORDER BY ci.main_category_id, ci.sort_order, ci.id DESC
    ";
    $carousels = $pdo->query($carousels_query)->fetchAll();
}

// Get count of carousels by category
$category_counts = [];
foreach ($main_categories as $cat) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM carousel_images WHERE main_category_id = ?");
    $stmt->execute([$cat['id']]);
    $result = $stmt->fetch();
    $category_counts[$cat['id']] = $result['total'];
}

// Set page title
$page_title = "Manage Carousel Images - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-images me-2"></i>Manage Carousel Images
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="carousel_form.php" class="btn btn-sm btn-success">
            <i class="fas fa-plus me-1"></i> Add New Carousel
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Category Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="category_filter" class="form-label">Filter by Main Category</label>
                <select id="category_filter" name="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($main_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                            <?php echo ($selected_category == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name_en']); ?>
                            (<?php echo isset($category_counts[$cat['id']]) ? $category_counts[$cat['id']] : 0; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Carousel Images Table -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Carousel Images List
            <span class="badge bg-primary ms-2"><?php echo count($carousels); ?> Items</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($carousels) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th style="width: 100px;">Order</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carousels as $carousel): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($carousel['image_path'])): ?>
                                        <img src="<?php echo getCarouselImageUrl($carousel['image_path']); ?>" 
                                             alt="Carousel" 
                                             class="img-thumbnail" 
                                             style="max-width: 80px; height: 60px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light text-muted d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 60px; border: 1px solid #dee2e6;">
                                            <small>No Image</small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($carousel['category_name_en']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($carousel['category_name_ne']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($carousel['title_en'] ?? '-'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($carousel['title_ne'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <small><?php echo substr(htmlspecialchars($carousel['description_en'] ?? '-'), 0, 50); ?>...</small>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" 
                                           value="<?php echo $carousel['sort_order']; ?>" 
                                           data-id="<?php echo $carousel['id']; ?>"
                                           onchange="updateSortOrder(this)"
                                           style="width: 70px;">
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($carousel['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($carousel['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($carousel['created_at'])); ?></small>
                                </td>
                                <td>
                                    <a href="carousel_form.php?id=<?php echo $carousel['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="javascript:void(0)" 
                                       onclick="confirmDelete(<?php echo $carousel['id']; ?>, '<?php echo $carousel['title_en'] ?? 'this carousel'; ?>')" 
                                       class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info m-3 mb-0">
                <i class="fas fa-info-circle me-2"></i>No carousel images found. 
                <a href="carousel_form.php" class="alert-link">Add your first carousel image</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Carousel Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="deleteModalText">Are you sure you want to delete this carousel image? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(carouselId, title) {
    document.getElementById('deleteModalText').textContent = 
        'Are you sure you want to delete carousel "' + title + '"? This action cannot be undone.';
    document.getElementById('confirmDeleteBtn').href = 'carousel_delete.php?id=' + carouselId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function updateSortOrder(element) {
    const carouselId = element.getAttribute('data-id');
    const sortOrder = element.value;
    
    // AJAX request to update sort order
    fetch('../../api/CarouselApi.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'update_sort_order',
            id: carouselId,
            sort_order: sortOrder
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show toast notification
            showToast('Sort order updated successfully', 'success');
        } else {
            showToast('Failed to update sort order', 'danger');
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'danger');
    });
}

function showToast(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}
</script>

<?php include '../../app/views/layouts/footer.php'; ?>