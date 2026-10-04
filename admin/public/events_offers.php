<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Events & Offers Management";

// Fetch all events
try {
    $events = $pdo->query("
        SELECT * FROM special_events 
        ORDER BY start_date DESC, created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $events = [];
}

// Fetch all product offers with product and city info
try {
    $offers = $pdo->query("
        SELECT po.*, p.name_en as product_name, dc.city_name,
               CASE 
                   WHEN po.offer_type = 'buy_x_get_y' THEN CONCAT('Buy ', po.buy_quantity, ' Get ', po.get_quantity, ' free')
                   WHEN po.offer_type = 'percentage_discount' THEN CONCAT(po.discount_percentage, '% Discount')
                   WHEN po.offer_type = 'fixed_discount' THEN CONCAT('Rs. ', po.fixed_discount, ' off')
               END as offer_description
        FROM product_offers po
        LEFT JOIN products p ON po.product_id = p.id
        LEFT JOIN delivery_cities dc ON po.city_id = dc.id
        ORDER BY po.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching offers: " . $e->getMessage());
    $offers = [];
}

include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Events & Offers Management</h1>
    <div>
        <a href="events/add_event.php" class="btn btn-primary me-2">
            <i class="fas fa-calendar-plus me-1"></i> Add New Event
        </a>
        <a href="events/add_offer.php" class="btn btn-success">
            <i class="fas fa-gift me-1"></i> Add Product Offer
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

<!-- Special Events Section -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Special Events & Festivals</h5>
        <span class="badge bg-primary"><?php echo count($events); ?> Events</span>
    </div>
    <div class="card-body">
        <?php if (!empty($events)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event Name</th>
                        <th>Discount</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): 
                        $is_active = $event['is_active'] == 1;
                        $is_current = date('Y-m-d') >= $event['start_date'] && date('Y-m-d') <= $event['end_date'];
                    ?>
                    <tr>
                        <td><?php echo $event['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                            <?php if ($is_current): ?>
                            <span class="badge bg-success ms-1">Active Now</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($event['event_description']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <?php echo $event['discount_percentage']; ?>% OFF
                            </span>
                        </td>
                        <td>
                            <small>
                                <?php echo date('M j, Y', strtotime($event['start_date'])); ?> 
                                to 
                                <?php echo date('M j, Y', strtotime($event['end_date'])); ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge <?php echo $is_active ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($event['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="events/edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="events/delete_event.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-danger" 
                                   onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($event['event_name']); ?>?')">
                                    <i class="fas fa-trash"></i> Delete
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
            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
            <h4>No Events Added Yet</h4>
            <p class="text-muted">Create special event discounts for festivals like Dashain, Tihar, etc.</p>
            <a href="events/add_event.php" class="btn btn-primary">
                <i class="fas fa-calendar-plus me-1"></i> Add Your First Event
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Product Offers Section -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Product Offers & Promotions</h5>
        <span class="badge bg-success"><?php echo count($offers); ?> Offers</span>
    </div>
    <div class="card-body">
        <?php if (!empty($offers)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Offer Type</th>
                        <th>Offer Details</th>
                        <th>City</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $offer): 
                        $is_active = $offer['is_active'] == 1;
                        $is_current = (!$offer['start_date'] || date('Y-m-d') >= $offer['start_date']) && 
                                     (!$offer['end_date'] || date('Y-m-d') <= $offer['end_date']);
                    ?>
                    <tr>
                        <td><?php echo $offer['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($offer['product_name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo ucfirst(str_replace('_', ' ', $offer['offer_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $offer['offer_description']; ?>
                            <?php if ($offer['min_order_amount'] > 0): ?>
                            <br><small class="text-muted">Min order: Rs. <?php echo $offer['min_order_amount']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($offer['city_name']): ?>
                                <span class="badge bg-secondary"><?php echo $offer['city_name']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">All Cities</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($offer['start_date'] && $offer['end_date']): ?>
                            <small>
                                <?php echo date('M j', strtotime($offer['start_date'])); ?> 
                                to 
                                <?php echo date('M j, Y', strtotime($offer['end_date'])); ?>
                            </small>
                            <?php else: ?>
                            <small class="text-muted">No time limit</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $is_active && $is_current ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $is_active && $is_current ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="events/edit_offer.php?id=<?php echo $offer['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="events/delete_offer.php?id=<?php echo $offer['id']; ?>" class="btn btn-outline-danger" 
                                   onclick="return confirm('Are you sure you want to delete this offer?')">
                                    <i class="fas fa-trash"></i> Delete
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
            <i class="fas fa-gift fa-3x text-muted mb-3"></i>
            <h4>No Product Offers Yet</h4>
            <p class="text-muted">Create buy X get Y free offers, percentage discounts, or fixed discounts.</p>
            <a href="events/add_offer.php" class="btn btn-success">
                <i class="fas fa-gift me-1"></i> Add Your First Offer
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include '../app/views/layouts/footer.php';
?>