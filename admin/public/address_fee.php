<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "City Delivery Fee & Minimum Quantity Management";

// Fetch all cities with product counts
try {
    $cities = $pdo->query("
        SELECT dc.*, 
               COUNT(pmq.id) as product_rules_count,
               (SELECT COUNT(*) FROM products) as total_products
        FROM delivery_cities dc
        LEFT JOIN product_minimum_quantities pmq ON dc.id = pmq.city_id
        GROUP BY dc.id
        ORDER BY dc.city_name
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching cities: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading cities: " . $e->getMessage();
    $cities = [];
}

include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">City Delivery Fee & Minimum Quantity Management</h1>
    <div>
        <a href="address/add.php" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i> Add New City
        </a>
        <a href="address/manage_minimum_quantities.php" class="btn btn-success">
            <i class="fas fa-cog me-1"></i> Manage Minimum Quantities
        </a>
            <a href="address/manage_product_availability.php" class="btn btn-info">
        <i class="fas fa-toggle-on me-1"></i> Manage Availability
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

<?php if (isset($_SESSION['info_message'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Cities List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Cities & Delivery Settings</h5>
        <span class="badge bg-primary"><?php echo count($cities); ?> Cities</span>
    </div>
    <div class="card-body">
        <?php if (!empty($cities)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="citiesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>City Name</th>
                        <th>Standard Delivery Fee (Rs.)</th>
                        <th>Nighttime Urgent Fee (Rs.)</th>
                        <th>Min Order Amount (Rs.)</th>
                        <th>Product Rules</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cities as $city): ?>
                    <tr>
                        <td><?php echo $city['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($city['city_name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge <?php echo $city['standard_delivery_fee'] == 0 ? 'bg-success' : 'bg-primary'; ?>">
                                Rs. <?php echo number_format($city['standard_delivery_fee'], 2); ?>
                                <?php echo $city['standard_delivery_fee'] == 0 ? ' (FREE)' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                Rs. <?php echo number_format($city['nighttime_urgent_fee'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                Rs. <?php echo number_format($city['min_order_amount'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $city['product_rules_count'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $city['product_rules_count']; ?>/<?php echo $city['total_products']; ?> Products
                            </span>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($city['created_at'])); ?></small>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($city['updated_at'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="address/edit.php?id=<?php echo $city['id']; ?>" class="btn btn-outline-primary" title="Edit City">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="address/delete.php?id=<?php echo $city['id']; ?>" class="btn btn-outline-danger" 
                                   title="Delete City"
                                   onclick="return confirmDelete('<?php echo addslashes(htmlspecialchars($city['city_name'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-city fa-3x text-muted mb-3"></i>
            <h4>No Cities Added Yet</h4>
            <p class="text-muted">Start by adding your first city using the "Add New City" button.</p>
            <a href="address/add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Your First City
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(cityName) {
    return confirm('Are you sure you want to delete "' + cityName + '"? This will also delete all minimum quantity rules for this city. This action cannot be undone.');
}

document.addEventListener('DOMContentLoaded', function() {
    // Add search functionality
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search cities...';
    searchInput.className = 'form-control mb-3';
    searchInput.style.maxWidth = '300px';
    
    const cardHeader = document.querySelector('.card-header');
    cardHeader.appendChild(searchInput);
    
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#citiesTable tbody tr');
        
        rows.forEach(row => {
            const cityName = row.cells[1].textContent.toLowerCase();
            if (cityName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php
include '../app/views/layouts/footer.php';
?>