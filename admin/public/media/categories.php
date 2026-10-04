<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name_en = trim($_POST['name_en']);
        $name_ne = trim($_POST['name_ne']);
        $description_en = trim($_POST['description_en']);
        $description_ne = trim($_POST['description_ne']);
        $parent_id = intval($_POST['parent_id']);
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO media_categories (name_en, name_ne, description_en, description_ne, parent_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_en, $name_ne, $description_en, $description_ne, $parent_id, $status]);
            
            $_SESSION['success_message'] = "Category added successfully!";
            header("Location: categories.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error adding category: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = intval($_POST['id']);
        $name_en = trim($_POST['name_en']);
        $name_ne = trim($_POST['name_ne']);
        $description_en = trim($_POST['description_en']);
        $description_ne = trim($_POST['description_ne']);
        $parent_id = intval($_POST['parent_id']);
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE media_categories SET name_en = ?, name_ne = ?, description_en = ?, description_ne = ?, parent_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$name_en, $name_ne, $description_en, $description_ne, $parent_id, $status, $id]);
            
            $_SESSION['success_message'] = "Category updated successfully!";
            header("Location: categories.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating category: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = intval($_POST['id']);
        
        try {
            // Check if category has media items
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM media_items WHERE category_id = ?");
            $check_stmt->execute([$id]);
            $media_count = $check_stmt->fetchColumn();
            
            if ($media_count > 0) {
                $_SESSION['error_message'] = "Cannot delete category. There are media items associated with this category.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM media_categories WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success_message'] = "Category deleted successfully!";
            }
            header("Location: categories.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting category: " . $e->getMessage();
        }
    }
}

// Get all categories - FIXED: Changed 'name' to 'name_en'
$categories = $pdo->query("SELECT * FROM media_categories ORDER BY name_en")->fetchAll();

// Set page title
$page_title = "Media Categories - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Media Categories</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../media.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Media
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

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="name_en" class="form-label">Category Name (English)</label>
                        <input type="text" class="form-control" id="name_en" name="name_en" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name_ne" class="form-label">Category Name (Nepali)</label>
                        <input type="text" class="form-control" id="name_ne" name="name_ne" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_en" class="form-label">Description (English)</label>
                        <textarea class="form-control" id="description_en" name="description_en" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_ne" class="form-label">Description (Nepali)</label>
                        <textarea class="form-control" id="description_ne" name="description_ne" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="0">No Parent (Top Level)</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_en']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Existing Categories</h5>
            </div>
            <div class="card-body">
                <?php if (count($categories) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name (EN)</th>
                                <th>Name (NE)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name_en']); ?></td>
                                <td><?php echo htmlspecialchars($category['name_ne']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $category['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" 
                                                data-bs-target="#editCategoryModal" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name_en="<?php echo htmlspecialchars($category['name_en']); ?>"
                                                data-name_ne="<?php echo htmlspecialchars($category['name_ne']); ?>"
                                                data-description_en="<?php echo htmlspecialchars($category['description_en']); ?>"
                                                data-description_ne="<?php echo htmlspecialchars($category['description_ne']); ?>"
                                                data-parent-id="<?php echo $category['parent_id']; ?>"
                                                data-status="<?php echo $category['status']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" name="delete_category" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this category?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No categories found. Add your first category using the form on the left.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name_en" class="form-label">Category Name (English)</label>
                        <input type="text" class="form-control" id="edit_name_en" name="name_en" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_name_ne" class="form-label">Category Name (Nepali)</label>
                        <input type="text" class="form-control" id="edit_name_ne" name="name_ne" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description_en" class="form-label">Description (English)</label>
                        <textarea class="form-control" id="edit_description_en" name="description_en" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description_ne" class="form-label">Description (Nepali)</label>
                        <textarea class="form-control" id="edit_description_ne" name="description_ne" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="edit_parent_id" name="parent_id">
                            <option value="0">No Parent (Top Level)</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_en']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit modal data
var editCategoryModal = document.getElementById('editCategoryModal');
editCategoryModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var name_en = button.getAttribute('data-name_en');
    var name_ne = button.getAttribute('data-name_ne');
    var description_en = button.getAttribute('data-description_en');
    var description_ne = button.getAttribute('data-description_ne');
    var parentId = button.getAttribute('data-parent-id');
    var status = button.getAttribute('data-status');
    
    var modal = this;
    modal.querySelector('#edit_id').value = id;
    modal.querySelector('#edit_name_en').value = name_en;
    modal.querySelector('#edit_name_ne').value = name_ne;
    modal.querySelector('#edit_description_en').value = description_en;
    modal.querySelector('#edit_description_ne').value = description_ne;
    modal.querySelector('#edit_parent_id').value = parentId;
    modal.querySelector('#edit_status').value = status;
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>