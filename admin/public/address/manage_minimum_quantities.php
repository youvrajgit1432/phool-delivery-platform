<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Manage Product Minimum Quantities";

// Fetch all cities and products
try {
    $cities = $pdo->query("SELECT * FROM delivery_cities ORDER BY city_name")->fetchAll();
    $products = $pdo->query("SELECT * FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();
    
    // Fetch existing minimum quantity rules
    $minQuantityRules = [];
    if (!empty($cities) && !empty($products)) {
        $stmt = $pdo->query("
            SELECT pmq.*, p.name_en, p.unit as product_unit, dc.city_name 
            FROM product_minimum_quantities pmq
            JOIN products p ON pmq.product_id = p.id
            JOIN delivery_cities dc ON pmq.city_id = dc.id
        ");
        $minQuantityRules = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $cities = [];
    $products = [];
    $minQuantityRules = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_minimum_quantities'])) {
        $city_id = $_POST['city_id'];
        $rules = $_POST['rules'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Delete existing rules for this city
            $deleteStmt = $pdo->prepare("DELETE FROM product_minimum_quantities WHERE city_id = ?");
            $deleteStmt->execute([$city_id]);
            
            // Insert new rules
            $insertStmt = $pdo->prepare("
                INSERT INTO product_minimum_quantities (product_id, city_id, minimum_quantity, unit, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $rulesAdded = 0;
            foreach ($rules as $product_id => $rule) {
                if (!empty($rule['minimum_quantity']) && $rule['minimum_quantity'] > 0) {
                    $insertStmt->execute([
                        $product_id,
                        $city_id,
                        $rule['minimum_quantity'],
                        $rule['unit']
                    ]);
                    $rulesAdded++;
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Successfully updated minimum quantities for " . $rulesAdded . " products!";
            header("Location: manage_minimum_quantities.php?city_id=" . $city_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error updating minimum quantities: " . $e->getMessage();
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Product Minimum Quantities</h1>
    <a href="../address_fee.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Cities
    </a>
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
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Set Minimum Quantities by City</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="minQuantityForm">
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
                            <div class="d-flex align-items-end h-100">
                                <?php if (isset($_GET['city_id']) || isset($_POST['city_id'])): ?>
                                <button type="submit" name="save_minimum_quantities" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Save All Changes
                                </button>
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
                    ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Setting minimum order quantities for <strong><?php echo htmlspecialchars($selected_city_name); ?></strong>. 
                        Leave quantity empty to use product's default minimum (usually 1).
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Default Unit</th>
                                    <th>Minimum Quantity</th>
                                    <th>Unit</th>
                                    <th>Current Rule</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): 
                                    $existing_rule = null;
                                    foreach ($minQuantityRules as $rules) {
                                        foreach ($rules as $rule) {
                                            if ($rule['product_id'] == $product['id'] && $rule['city_id'] == $selected_city_id) {
                                                $existing_rule = $rule;
                                                break 2;
                                            }
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name_en']); ?></strong>
                                        <br><small class="text-muted">Rs. <?php echo number_format($product['price'], 2); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $product['unit']; ?></span>
                                    </td>
                                    <td style="width: 200px;">
                                        <input type="number" 
                                               class="form-control" 
                                               name="rules[<?php echo $product['id']; ?>][minimum_quantity]" 
                                               min="0" 
                                               step="0.01"
                                               value="<?php echo $existing_rule ? $existing_rule['minimum_quantity'] : ''; ?>"
                                               placeholder="Default: 1">
                                    </td>
                                    <td style="width: 150px;">
                                        <select class="form-select" name="rules[<?php echo $product['id']; ?>][unit]">
                                            <option value="piece" <?php echo ($existing_rule && $existing_rule['unit'] == 'piece') || $product['unit'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                            <option value="bunch" <?php echo ($existing_rule && $existing_rule['unit'] == 'bunch') || $product['unit'] == 'bunch' ? 'selected' : ''; ?>>Bunch</option>
                                            <option value="kg" <?php echo ($existing_rule && $existing_rule['unit'] == 'kg') || $product['unit'] == 'kg' ? 'selected' : ''; ?>>Kg</option>
                                            <option value="garland" <?php echo ($existing_rule && $existing_rule['unit'] == 'garland') || $product['unit'] == 'garland' ? 'selected' : ''; ?>>Garland</option>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($existing_rule): ?>
                                        <span class="badge bg-success">
                                            <?php echo $existing_rule['minimum_quantity'] . ' ' . $existing_rule['unit']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" name="save_minimum_quantities" class="btn btn-success">
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
    const form = document.getElementById('minQuantityForm');
    
    form.addEventListener('submit', function(e) {
        const cityId = document.getElementById('city_id').value;
        
        if (!cityId) {
            e.preventDefault();
            alert('Please select a city.');
            return false;
        }
    });
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>