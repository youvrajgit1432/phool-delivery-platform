<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log what we received
    error_log("POST data received: " . json_encode($_POST));
    
    if (isset($_POST['add_category'])) {
        $category_name_en = trim($_POST['category_name_en']);
        $category_name_ne = trim($_POST['category_name_ne']);
        $category_description_en = trim($_POST['category_description_en']);
        $category_description_ne = trim($_POST['category_description_ne']);
        $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] != '' ? intval($_POST['parent_id']) : NULL;
        $type = $parent_id ? 'subcategory' : 'main';
        $is_product_allowed = isset($_POST['is_product_allowed']) ? 1 : 0;
        
        if (empty($category_name_en) || empty($category_name_ne)) {
            $_SESSION['error_message'] = "Category name in both languages is required.";
        } else {
            try {
                // Generate slugs
                $slug_en = generateSlug($category_name_en);
                $slug_ne = generateSlug($category_name_ne);
                
                // Get max sort order for this level
                $sort_order = 0;
                if ($parent_id) {
                    $stmt = $pdo->prepare("SELECT MAX(sort_order) as max_sort FROM categories WHERE parent_id = ?");
                    $stmt->execute([$parent_id]);
                } else {
                    $stmt = $pdo->query("SELECT MAX(sort_order) as max_sort FROM categories WHERE parent_id IS NULL");
                }
                $result = $stmt->fetch();
                $sort_order = $result['max_sort'] ? $result['max_sort'] + 1 : 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO categories 
                    (name_en, name_ne, slug_en, slug_ne, description_en, description_ne, 
                     parent_id, type, is_product_allowed, sort_order, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                if ($stmt->execute([
                    $category_name_en, $category_name_ne, $slug_en, $slug_ne, 
                    $category_description_en, $category_description_ne, 
                    $parent_id, $type, $is_product_allowed, $sort_order
                ])) {
                    $_SESSION['success_message'] = "Category added successfully!";
                    header("Location: categories.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Failed to add category.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_category'])) {
        error_log("UPDATE CATEGORY TRIGGERED - POST data: " . json_encode($_POST));
        
        $category_id = intval($_POST['category_id']);
        $category_name_en = trim($_POST['category_name_en']);
        $category_name_ne = trim($_POST['category_name_ne']);
        $category_description_en = trim($_POST['category_description_en']);
        $category_description_ne = trim($_POST['category_description_ne']);
        $is_product_allowed = isset($_POST['is_product_allowed']) ? 1 : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';
        
        error_log("Update values: ID=$category_id, EN=$category_name_en, NE=$category_name_ne, Status=$status");
        
        if (empty($category_name_en) || empty($category_name_ne)) {
            $_SESSION['error_message'] = "Category name in both languages is required.";
            error_log("Validation failed: Empty names");
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE categories SET 
                    name_en = ?, name_ne = ?, description_en = ?, description_ne = ?,
                    is_product_allowed = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $category_name_en, $category_name_ne, $category_description_en, 
                    $category_description_ne, $is_product_allowed, $status, $category_id
                ]);
                
                error_log("Execute result: " . ($result ? 'TRUE' : 'FALSE'));
                error_log("Rows affected: " . $stmt->rowCount());
                
                if ($result && $stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Category updated successfully!";
                    error_log("Update successful, redirecting...");
                    header("Location: categories.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "No changes made. " . implode(" | ", $stmt->errorInfo());
                    error_log("Update failed or no rows affected: " . implode(" | ", $stmt->errorInfo()));
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
                error_log("PDO Exception: " . $e->getMessage());
            }
        }
    }
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    try {
        // Check if category has products
        $check_products = $pdo->prepare("
            SELECT COUNT(*) as product_count 
            FROM product_categories 
            WHERE category_id = ?
        ");
        $check_products->execute([$category_id]);
        $product_count = $check_products->fetch()['product_count'];
        
        // Check if category has subcategories
        $check_subcategories = $pdo->prepare("
            SELECT COUNT(*) as subcategory_count 
            FROM categories 
            WHERE parent_id = ?
        ");
        $check_subcategories->execute([$category_id]);
        $subcategory_count = $check_subcategories->fetch()['subcategory_count'];
        
        if ($product_count > 0) {
            $_SESSION['error_message'] = "Cannot delete category that has products assigned to it.";
        } elseif ($subcategory_count > 0) {
            $_SESSION['error_message'] = "Cannot delete category that has subcategories. Delete subcategories first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$category_id])) {
                $_SESSION['success_message'] = "Category deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete category.";
            }
        }
        
        header("Location: categories.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: categories.php");
        exit;
    }
}

// Get main categories
try {
    $main_categories = $pdo->query("
        SELECT * FROM categories 
        WHERE parent_id IS NULL 
        ORDER BY sort_order, name_en
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $main_categories = [];
}

// Get all categories with hierarchy for display
$all_categories = [];
try {
    $query = "
        SELECT c1.*, 
               c2.name_en as parent_name_en,
               c2.name_ne as parent_name_ne,
               (SELECT COUNT(*) FROM categories c3 WHERE c3.parent_id = c1.id) as subcategory_count,
               (SELECT COUNT(*) FROM product_categories pcm WHERE pcm.category_id = c1.id) as product_count
        FROM categories c1
        LEFT JOIN categories c2 ON c1.parent_id = c2.id
        ORDER BY c1.parent_id, c1.sort_order, c1.name_en
    ";
    $all_categories = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching all categories: " . $e->getMessage());
}

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $category_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $edit_category = $stmt->fetch();
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

// Set page title
$page_title = "Product Categories - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Product Categories</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../products.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Products
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
    <!-- Add/Edit Category Form -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="categoryForm">
                    <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$edit_category): ?>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">-- Main Category (No Parent) --</option>
                            <?php foreach ($main_categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name_en']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Select a parent category to create a subcategory, or leave empty for main category.
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Category Type:</strong> 
                        <?php 
                        echo $edit_category['type'] == 'main' ? 'Main Category' : 'Subcategory';
                        if ($edit_category['parent_id']) {
                            $parent_stmt = $pdo->prepare("SELECT name_en FROM categories WHERE id = ?");
                            $parent_stmt->execute([$edit_category['parent_id']]);
                            $parent = $parent_stmt->fetch();
                            echo ' (Parent: ' . htmlspecialchars($parent['name_en']) . ')';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="category_name_en" class="form-label">Category Name (English) *</label>
                        <input type="text" class="form-control" id="category_name_en" name="category_name_en" 
                               value="<?php echo $edit_category ? htmlspecialchars($edit_category['name_en']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_name_ne" class="form-label">Category Name (Nepali) *</label>
                        <input type="text" class="form-control" id="category_name_ne" name="category_name_ne" 
                               value="<?php echo $edit_category ? htmlspecialchars($edit_category['name_ne']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_description_en" class="form-label">Description (English)</label>
                        <textarea class="form-control" id="category_description_en" name="category_description_en" rows="2"><?php echo $edit_category ? htmlspecialchars($edit_category['description_en']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_description_ne" class="form-label">Description (Nepali)</label>
                        <textarea class="form-control" id="category_description_ne" name="category_description_ne" rows="2"><?php echo $edit_category ? htmlspecialchars($edit_category['description_ne']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($edit_category): ?>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $edit_category['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $edit_category['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_product_allowed" name="is_product_allowed" value="1" 
                               <?php echo $edit_category ? ($edit_category['is_product_allowed'] ? 'checked' : '') : 'checked'; ?>>
                        <label class="form-check-label" for="is_product_allowed">Allow products in this category</label>
                        <small class="form-text text-muted d-block">
                            Uncheck if this is only for grouping subcategories (category-only, no products)
                        </small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <?php if ($edit_category): ?>
                        <button type="submit" name="update_category" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Category
                        </button>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <?php else: ?>
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Category
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Category Statistics -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Category Statistics</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-layer-group text-primary me-2"></i>
                        <strong>Total Categories:</strong> <?php echo count($all_categories); ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-folder text-success me-2"></i>
                        <strong>Main Categories:</strong> 
                        <?php echo count(array_filter($all_categories, function($cat) { return $cat['parent_id'] === null; })); ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-folder-open text-info me-2"></i>
                        <strong>Subcategories:</strong> 
                        <?php echo count(array_filter($all_categories, function($cat) { return $cat['parent_id'] !== null; })); ?>
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-box text-warning me-2"></i>
                        <strong>Categories with Products:</strong> 
                        <?php 
                        $with_products = array_filter($all_categories, function($cat) { return $cat['product_count'] > 0; });
                        echo count($with_products);
                        ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Categories List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    All Categories (<?php echo count($all_categories); ?>)
                </h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleView()">
                        <i class="fas fa-exchange-alt me-1"></i> Toggle View
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($all_categories)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                        <h5>No categories found</h5>
                        <p class="mb-0">Start by adding your first category using the form on the left.</p>
                    </div>
                <?php else: ?>
                    <!-- Table View -->
                    <div class="table-responsive d-none" id="tableView">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Type</th>
                                    <th>Products</th>
                                    <th>Subcategories</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_categories as $category): ?>
                                <tr style="<?php echo $category['parent_id'] ? 'background-color: #f8f9fa;' : 'font-weight: 600;'; ?>">
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <?php if ($category['parent_id']): ?>
                                            <i class="fas fa-level-up-alt text-muted me-2"></i>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($category['name_en']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($category['name_ne']); ?></small>
                                        <?php if ($category['parent_name_en']): ?>
                                            <br>
                                            <small>
                                                <i class="fas fa-folder text-info me-1"></i>
                                                Parent: <?php echo htmlspecialchars($category['parent_name_en']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $category['type'] == 'main' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($category['type']); ?>
                                        </span>
                                        <?php if (!$category['is_product_allowed']): ?>
                                            <span class="badge bg-warning ms-1">Group Only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $category['product_count'] > 0 ? 'success' : 'secondary'; ?>">
                                            <?php echo $category['product_count']; ?> products
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($category['subcategory_count'] > 0): ?>
                                        <span class="badge bg-info">
                                            <?php echo $category['subcategory_count']; ?> subcategories
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $category['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="categories.php?edit=<?php echo $category['id']; ?>" 
                                               class="btn btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($category['subcategory_count'] == 0 && $category['product_count'] == 0): ?>
                                            <a href="categories.php?delete=<?php echo $category['id']; ?>" 
                                               class="btn btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this category?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php else: ?>
                                            <button class="btn btn-danger" disabled 
                                                    title="Cannot delete category with subcategories or products">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Hierarchical View -->
                    <div id="hierarchyView">
                        <div class="category-tree">
                            <?php
                            // Function to display categories hierarchically
                            function displayCategoryTree($categories, $parent_id = NULL, $level = 0) {
                                $output = '';
                                foreach ($categories as $category) {
                                    if ($category['parent_id'] == $parent_id) {
                                        $indent = str_repeat('<div class="category-indent"></div>', $level);
                                        $badge_class = $category['product_count'] > 0 ? 'bg-success' : 'bg-secondary';
                                        $type_badge = $category['type'] == 'main' ? 'bg-primary' : 'bg-info';
                                        
                                        $output .= '<div class="category-item mb-2">';
                                        $output .= $indent;
                                        $output .= '<div class="card border-' . ($level > 0 ? 'info' : 'primary') . '">';
                                        $output .= '<div class="card-body py-2">';
                                        $output .= '<div class="d-flex justify-content-between align-items-center">';
                                        $output .= '<div class="flex-grow-1">';
                                        $output .= '<h6 class="mb-1 d-flex align-items-center">';
                                        if ($level > 0) {
                                            $output .= '<i class="fas fa-angle-right text-info me-2"></i>';
                                        }
                                        $output .= htmlspecialchars($category['name_en']);
                                        $output .= '<small class="text-muted ms-2">(' . htmlspecialchars($category['name_ne']) . ')</small>';
                                        $output .= '</h6>';
                                        $output .= '<div class="small d-flex align-items-center">';
                                        $output .= '<span class="badge ' . $type_badge . ' me-2">' . ucfirst($category['type']) . '</span>';
                                        $output .= '<span class="badge ' . $badge_class . ' me-2">';
                                        $output .= $category['product_count'] . ' product' . ($category['product_count'] != 1 ? 's' : '');
                                        $output .= '</span>';
                                        $output .= '<span class="badge bg-info me-2">';
                                        $output .= $category['subcategory_count'] . ' subcategor' . ($category['subcategory_count'] != 1 ? 'ies' : 'y');
                                        $output .= '</span>';
                                        $output .= '<span class="badge bg-' . ($category['status'] == 'active' ? 'success' : 'secondary') . '">';
                                        $output .= ucfirst($category['status']);
                                        $output .= '</span>';
                                        if (!$category['is_product_allowed']) {
                                            $output .= '<span class="badge bg-warning ms-2">Group Only</span>';
                                        }
                                        $output .= '</div>';
                                        $output .= '</div>';
                                        $output .= '<div class="btn-group btn-group-sm">';
                                        $output .= '<a href="categories.php?edit=' . $category['id'] . '" class="btn btn-primary"><i class="fas fa-edit"></i></a>';
                                        if ($category['subcategory_count'] == 0 && $category['product_count'] == 0) {
                                            $output .= '<a href="categories.php?delete=' . $category['id'] . '" class="btn btn-danger" onclick="return confirm(\'Are you sure?\')"><i class="fas fa-trash"></i></a>';
                                        } else {
                                            $output .= '<button class="btn btn-danger" disabled title="Cannot delete"><i class="fas fa-trash"></i></button>';
                                        }
                                        $output .= '</div>';
                                        $output .= '</div>';
                                        $output .= '</div>';
                                        $output .= '</div>';
                                        $output .= displayCategoryTree($categories, $category['id'], $level + 1);
                                        $output .= '</div>';
                                    }
                                }
                                return $output;
                            }
                            
                            echo displayCategoryTree($all_categories);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle between table and hierarchical view
function toggleView() {
    const tableView = document.getElementById('tableView');
    const hierarchyView = document.getElementById('hierarchyView');
    
    if (tableView.classList.contains('d-none')) {
        tableView.classList.remove('d-none');
        hierarchyView.classList.add('d-none');
    } else {
        tableView.classList.add('d-none');
        hierarchyView.classList.remove('d-none');
    }
}

// Dynamic form behavior
document.addEventListener('DOMContentLoaded', function() {
    const parentSelect = document.getElementById('parent_id');
    const isProductAllowed = document.getElementById('is_product_allowed');
    
    if (parentSelect) {
        parentSelect.addEventListener('change', function() {
            const label = isProductAllowed.nextElementSibling;
            
            if (this.value) {
                // Subcategory selected
                label.textContent = 'Allow products in this subcategory';
                label.parentElement.querySelector('.form-text').textContent = 
                    'Uncheck if this subcategory is only for organization (no products)';
            } else {
                // Main category selected
                label.textContent = 'Allow products in this category';
                label.parentElement.querySelector('.form-text').textContent = 
                    'Uncheck if this is only for grouping subcategories (category-only, no products)';
            }
        });
    }
    
    // Form validation
    const form = document.querySelector('form');
    if (form) {
        const nameEn = document.getElementById('category_name_en');
        const nameNe = document.getElementById('category_name_ne');
        
        form.addEventListener('submit', function(e) {
            let valid = true;
            
            // Reset validation
            if (nameEn) nameEn.classList.remove('is-invalid');
            if (nameNe) nameNe.classList.remove('is-invalid');
            
            // Check English name
            if (!nameEn || !nameEn.value.trim()) {
                if (nameEn) nameEn.classList.add('is-invalid');
                valid = false;
            }
            
            // Check Nepali name
            if (!nameNe || !nameNe.value.trim()) {
                if (nameNe) nameNe.classList.add('is-invalid');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Allow form submission
            return true;
        });
    }
    
    // Initialize with current view preference
    const savedView = localStorage.getItem('categoryView') || 'hierarchy';
    if (savedView === 'table') {
        toggleView();
    }
    
    // Save view preference
    const toggleBtn = document.querySelector('[onclick="toggleView()"]');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            setTimeout(() => {
                const currentView = document.getElementById('tableView').classList.contains('d-none') ? 'hierarchy' : 'table';
                localStorage.setItem('categoryView', currentView);
            }, 100);
        });
    }
});
</script>

<style>
.category-indent {
    display: inline-block;
    width: 40px;
    height: 1px;
}

.category-item .card {
    border-left-width: 4px;
    transition: all 0.2s ease-in-out;
}

.category-item .card:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transform: translateY(-2px);
}

.is-invalid {
    border-color: #dc3545;
}

.category-tree {
    max-height: 600px;
    overflow-y: auto;
    padding: 10px;
}

/* Custom scrollbar */
.category-tree::-webkit-scrollbar {
    width: 8px;
}

.category-tree::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.category-tree::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.category-tree::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .category-indent {
        width: 20px;
    }
    
    .category-item .card-body {
        padding: 0.75rem;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 2px;
    }
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>