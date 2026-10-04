<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Manage Product Availability by City";

// Fetch all cities and products
try {
    $cities = $pdo->query("SELECT * FROM delivery_cities ORDER BY city_name")->fetchAll();
    $products = $pdo->query("SELECT * FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();
    
    // Fetch existing availability rules
    $availabilityRules = [];
    if (!empty($cities) && !empty($products)) {
        $stmt = $pdo->query("
            SELECT pca.*, p.name_en, p.name_ne, dc.city_name 
            FROM product_city_availability pca
            JOIN products p ON pca.product_id = p.id
            JOIN delivery_cities dc ON pca.city_id = dc.id
        ");
        $results = $stmt->fetchAll();
        
        // Organize by city_id for easier access
        foreach ($results as $rule) {
            $availabilityRules[$rule['city_id']][$rule['product_id']] = $rule;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $cities = [];
    $products = [];
    $availabilityRules = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_availability'])) {
        $city_id = $_POST['city_id'];
        $availabilities = $_POST['availabilities'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Delete existing rules for this city
            $deleteStmt = $pdo->prepare("DELETE FROM product_city_availability WHERE city_id = ?");
            $deleteStmt->execute([$city_id]);
            
            // Insert new rules
            $insertStmt = $pdo->prepare("
                INSERT INTO product_city_availability (product_id, city_id, is_available, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $rulesAdded = 0;
            $rulesEnabled = 0;
            $rulesDisabled = 0;
            
            foreach ($availabilities as $product_id => $is_available) {
                $is_available = $is_available === '1' ? 1 : 0;
                
                $insertStmt->execute([
                    $product_id,
                    $city_id,
                    $is_available
                ]);
                
                $rulesAdded++;
                if ($is_available) {
                    $rulesEnabled++;
                } else {
                    $rulesDisabled++;
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Successfully updated product availability! " . 
                                          "Enabled: $rulesEnabled products, Disabled: $rulesDisabled products";
            header("Location: manage_product_availability.php?city_id=" . $city_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error updating product availability: " . $e->getMessage();
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action'])) {
        $city_id = $_POST['city_id'];
        $bulk_action = $_POST['bulk_action'];
        
        if ($city_id && in_array($bulk_action, ['enable_all', 'disable_all'])) {
            try {
                $is_available = $bulk_action === 'enable_all' ? 1 : 0;
                
                // First, ensure all products have entries for this city
                $pdo->beginTransaction();
                
                // Delete existing entries
                $deleteStmt = $pdo->prepare("DELETE FROM product_city_availability WHERE city_id = ?");
                $deleteStmt->execute([$city_id]);
                
                // Insert new entries for all products
                $insertStmt = $pdo->prepare("
                    INSERT INTO product_city_availability (product_id, city_id, is_available, created_at) 
                    SELECT id, ?, ?, NOW() FROM products WHERE status = 'active'
                ");
                $insertStmt->execute([$city_id, $is_available]);
                
                $pdo->commit();
                
                $action_text = $bulk_action === 'enable_all' ? 'enabled' : 'disabled';
                $_SESSION['success_message'] = "All products have been $action_text for this city!";
                header("Location: manage_product_availability.php?city_id=" . $city_id);
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Error performing bulk action: " . $e->getMessage();
            }
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Product Availability by City</h1>
    <div>
        <a href="../address_fee.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Back to Cities
        </a>
        <a href="manage_minimum_quantities.php" class="btn btn-outline-primary">
            <i class="fas fa-balance-scale me-1"></i> Manage Minimum Quantities
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>Product Availability by City
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="availabilityForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="city_id" class="form-label">Select City *</label>
                            <select class="form-select" id="city_id" name="city_id" required onchange="this.form.submit()">
                                <option value="">-- Select a City --</option>
                                <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city['id']; ?>" 
                                    <?php echo (isset($_GET['city_id']) && $_GET['city_id'] == $city['id']) || (isset($_POST['city_id']) && $_POST['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['city_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-end h-100 gap-2">
                                <?php if (isset($_GET['city_id']) || isset($_POST['city_id'])): ?>
                                <button type="submit" name="save_availability" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-bolt me-1"></i> Quick Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button type="submit" name="bulk_action" value="enable_all" class="dropdown-item" 
                                                    onclick="return confirm('Enable ALL products for this city?')">
                                                <i class="fas fa-check-circle text-success me-2"></i>Enable All Products
                                            </button>
                                        </li>
                                        <li>
                                            <button type="submit" name="bulk_action" value="disable_all" class="dropdown-item"
                                                    onclick="return confirm('Disable ALL products for this city?')">
                                                <i class="fas fa-times-circle text-danger me-2"></i>Disable All Products
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php 
                    $selected_city_id = isset($_GET['city_id']) ? $_GET['city_id'] : (isset($_POST['city_id']) ? $_POST['city_id'] : null);
                    if ($selected_city_id && !empty($products)): 
                        $selected_city_name = '';
                        foreach ($cities as $city) {
                            if ($city['id'] == $selected_city_id) {
                                $selected_city_name = $city['city_name'];
                                break;
                            }
                        }
                        
                        // Count statistics
                        $enabled_count = 0;
                        $disabled_count = 0;
                        foreach ($products as $product) {
                            $is_available = isset($availabilityRules[$selected_city_id][$product['id']]) ? 
                                          $availabilityRules[$selected_city_id][$product['id']]['is_available'] : 1;
                            if ($is_available) {
                                $enabled_count++;
                            } else {
                                $disabled_count++;
                            }
                        }
                    ?>
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                Managing product availability for <strong><?php echo htmlspecialchars($selected_city_name); ?></strong>. 
                                Toggle availability for each product below.
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success me-2">
                                    <i class="fas fa-check me-1"></i><?php echo $enabled_count; ?> Enabled
                                </span>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times me-1"></i><?php echo $disabled_count; ?> Disabled
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50px">#</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th width="120px">Availability</th>
                                    <th width="100px">Current Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $index => $product): 
                                    $is_available = isset($availabilityRules[$selected_city_id][$product['id']]) ? 
                                                  $availabilityRules[$selected_city_id][$product['id']]['is_available'] : 1;
                                    
                                    // Get category name
                                    $category_name = 'Uncategorized';
                                    if ($product['category_id']) {
                                        $cat_stmt = $pdo->prepare("SELECT name_en FROM categories WHERE id = ?");
                                        $cat_stmt->execute([$product['category_id']]);
                                        $category = $cat_stmt->fetch();
                                        $category_name = $category ? $category['name_en'] : 'Uncategorized';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name_en']); ?></strong>
                                        <?php if (!empty($product['name_ne'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['name_ne']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($category_name); ?></span>
                                    </td>
                                    <td>
                                        <strong>Rs. <?php echo number_format($product['price'], 2); ?></strong>
                                        <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                                        <br><small class="text-success">Bulk: Rs. <?php echo number_format($product['bulk_price'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $product['stock_quantity'] <= $product['min_stock_alert'] ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            <?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?>
                                        </span>
                                        <?php if ($product['stock_quantity'] <= $product['min_stock_alert']): ?>
                                        <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Low Stock</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input availability-toggle" 
                                                   type="checkbox" 
                                                   name="availabilities[<?php echo $product['id']; ?>]" 
                                                   value="1"
                                                   <?php echo $is_available ? 'checked' : ''; ?>
                                                   id="availability_<?php echo $product['id']; ?>">
                                            <label class="form-check-label" for="availability_<?php echo $product['id']; ?>">
                                                <span class="toggle-label">
                                                    <?php echo $is_available ? 'Enabled' : 'Disabled'; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($is_available): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Available
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times me-1"></i>Unavailable
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" name="save_availability" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-1"></i> Save All Changes
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('availabilityForm');
    const toggles = document.querySelectorAll('.availability-toggle');
    
    // Update toggle labels when clicked
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const label = this.parentElement.querySelector('.toggle-label');
            label.textContent = this.checked ? 'Enabled' : 'Disabled';
        });
    });
    
    form.addEventListener('submit', function(e) {
        const cityId = document.getElementById('city_id').value;
        
        if (!cityId) {
            e.preventDefault();
            alert('Please select a city.');
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
            submitBtn.disabled = true;
        }
    });
    
    // Quick select all/none functionality
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'btn btn-outline-info btn-sm me-2';
    selectAllBtn.innerHTML = '<i class="fas fa-check-double me-1"></i>Select All';
    
    const selectNoneBtn = document.createElement('button');
    selectNoneBtn.type = 'button';
    selectNoneBtn.className = 'btn btn-outline-warning btn-sm';
    selectNoneBtn.innerHTML = '<i class="fas fa-times-circle me-1"></i>Select None';
    
    selectAllBtn.addEventListener('click', function() {
        toggles.forEach(toggle => {
            toggle.checked = true;
            toggle.dispatchEvent(new Event('change'));
        });
    });
    
    selectNoneBtn.addEventListener('click', function() {
        toggles.forEach(toggle => {
            toggle.checked = false;
            toggle.dispatchEvent(new Event('change'));
        });
    });
    
    // Add buttons to form if we have products
    <?php if ($selected_city_id && !empty($products)): ?>
    const buttonGroup = document.createElement('div');
    buttonGroup.className = 'mb-3';
    buttonGroup.appendChild(selectAllBtn);
    buttonGroup.appendChild(selectNoneBtn);
    
    const table = document.querySelector('.table-responsive');
    if (table) {
        table.parentNode.insertBefore(buttonGroup, table);
    }
    <?php endif; ?>
});
</script>

<style>
.form-switch .form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}

.form-switch .form-check-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
}

.toggle-label {
    font-size: 0.875rem;
    font-weight: 500;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.badge {
    font-size: 0.75rem;
}
</style>

<?php
include '../../app/views/layouts/footer.php';
?>