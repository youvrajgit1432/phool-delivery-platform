<?php
/**
 * Assign Orders to Rider
 * Admin interface to assign pending orders to a specific delivery rider
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'Assign Orders to Rider';
$current_page = 'riders';

$db = getDBConnection();

// Get rider ID
$rider_id = isset($_GET['rider_id']) ? intval($_GET['rider_id']) : 0;
if ($rider_id <= 0) {
    header('Location: ../riders.php');
    exit;
}

// Fetch rider
$stmt = $db->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->execute([$rider_id]);
$rider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rider) {
    $_SESSION['error_message'] = 'Rider not found';
    header('Location: ../riders.php');
    exit;
}

// Fetch unassigned orders ready for assignment (only orders assigned, accepted, preparing, or ready by vendors)
$orders_sql = "SELECT o.id, o.order_number, vo.customer_name, vo.status as vendor_status,
                      vo.total_amount, vo.customer_address as delivery_address, o.status,
                      (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
               FROM orders o
               INNER JOIN vendor_orders vo ON o.id = vo.order_id
               LEFT JOIN (
                   SELECT DISTINCT order_id FROM rider_orders
               ) ro ON o.id = ro.order_id
               WHERE vo.status IN ('assigned', 'accepted', 'preparing', 'ready')
               AND ro.order_id IS NULL
               ORDER BY vo.created_at DESC
               LIMIT 50";

$orders = $db->query($orders_sql)->fetchAll(PDO::FETCH_ASSOC);

// Get rider's current orders
$rider_orders_sql = "SELECT ro.*, o.order_number 
                     FROM rider_orders ro
                     LEFT JOIN orders o ON ro.order_id = o.id
                     WHERE ro.rider_id = ?
                     AND ro.delivery_status NOT IN ('delivered', 'failed', 'cancelled')
                     ORDER BY ro.assigned_at DESC";

$stmt = $db->prepare($rider_orders_sql);
$stmt->execute([$rider_id]);
$rider_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../app/views/layouts/hheader.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="../riders.php" class="btn btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Riders
            </a>
            <h1 class="h3">Assign Orders to <?php echo htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']); ?></h1>
        </div>
    </div>

    <!-- Rider Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Status</h6>
                    <span class="badge bg-<?php 
                        echo $rider['status'] === 'active' ? 'success' : 
                             ($rider['status'] === 'pending' ? 'warning' : 'danger');
                    ?>" style="font-size: 1rem;">
                        <?php echo ucfirst($rider['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Vehicle</h6>
                    <p class="mb-0"><strong><?php echo ucfirst($rider['vehicle_type']); ?></strong></p>
                    <small><?php echo htmlspecialchars($rider['vehicle_number']); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Active Deliveries</h6>
                    <h4><?php echo count($rider_orders); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Availability</h6>
                    <?php if ($rider['is_available']): ?>
                        <span class="badge bg-success" style="font-size: 1rem;">Online</span>
                    <?php else: ?>
                        <span class="badge bg-secondary" style="font-size: 1rem;">Offline</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Available Orders for Assignment -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Available Orders for Assignment</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Delivery Location</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>V. Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            $address = htmlspecialchars(substr($order['delivery_address'], 0, 40));
                                            echo $address . (strlen($order['delivery_address']) > 40 ? '...' : '');
                                            ?>
                                        </small>
                                    </td>
                                    <td><?php echo $order['item_count']; ?></td>
                                    <td><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $order['vendor_status'] === 'preparing' ? 'warning' :
                                                 ($order['vendor_status'] === 'ready' ? 'success' :
                                                  ($order['vendor_status'] === 'accepted' ? 'info' : 'secondary'));
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['vendor_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="assignOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                                            <i class="fas fa-check me-1"></i> Assign
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="alert alert-info mb-0">No unassigned orders available</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rider's Current Orders -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Current Orders (<?php echo count($rider_orders); ?>)</h6>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <?php if (!empty($rider_orders)): ?>
                        <?php foreach ($rider_orders as $ro): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><?php echo htmlspecialchars($ro['order_number'] ?? 'N/A'); ?></strong>
                                <span class="badge bg-<?php 
                                    echo $ro['delivery_status'] === 'assigned' ? 'warning' : 
                                         ($ro['delivery_status'] === 'accepted' ? 'info' : 
                                          ($ro['delivery_status'] === 'delivered' ? 'success' : 'secondary'));
                                ?>" style="font-size: 0.75rem;">
                                    <?php echo ucfirst(str_replace('_', ' ', $ro['delivery_status'])); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                Rs. <?php echo number_format($ro['total_amount'], 2); ?><br>
                                <?php echo date('M d, H:i', strtotime($ro['assigned_at'])); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No active orders</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function assignOrder(orderId, orderNumber) {
    if (!confirm('Assign order ' + orderNumber + ' to this rider?')) return;
    
    fetch('../ajax/assign-order-to-rider.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            rider_id: <?php echo $rider_id; ?>,
            order_id: orderId
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Order assigned successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to assign order'));
        }
    })
    .catch(function(err) {
        console.error('Error:', err);
        alert('Request failed: ' + err.message);
    });
}
</script>

<?php
include '../../app/views/layouts/footer.php';
?>
