<?php
// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$products = [];

if ($vendorId) {
    // Get all linked products with details from vendor_product_map and products tables
    $stmt = $db->query(
        'SELECT vpm.id, vpm.is_available, p.name_en as product_name, p.id as product_id, 
                vpm.vendor_price, vpm.vendor_stock, vpm.preparation_time
         FROM vendor_product_map vpm
         JOIN products p ON vpm.product_id = p.id
         WHERE vpm.vendor_id = ? AND vpm.unlinked_at IS NULL
         ORDER BY p.name_en',
        [$vendorId]
    );
    $products = $stmt->fetchAll();
}
?>

<div class="availability-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-check-circle me-2"></i>Product Availability</h2>
            <p class="text-muted">Toggle products available or unavailable</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width: 50px;">S.N.</th>
                    <th>Product Name</th>
                    <th>Your Price</th>
                    <th>Your Stock</th>
                    <th>Prep Time</th>
                    <th>Availability</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php $sn = 1; foreach ($products as $p): ?>
                        <tr id="product-row-<?php echo $p['id']; ?>">
                            <td><strong><?php echo $sn++; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                            <td>₹<?php echo number_format((float)$p['vendor_price'], 2); ?></td>
                            <td><?php echo (int)$p['vendor_stock']; ?> units</td>
                            <td><?php echo (int)$p['preparation_time']; ?> mins</td>
                            <td>
                                <span id="status-badge-<?php echo $p['id']; ?>">
                                    <?php if (!empty($p['is_available'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Unavailable</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm <?php echo $p['is_available'] ? 'btn-warning' : 'btn-success'; ?>" 
                                        onclick="toggleAvailability(<?php echo $p['id']; ?>, <?php echo $p['is_available'] ? '1' : '0'; ?>)">
                                    <i class="fas <?php echo $p['is_available'] ? 'fa-ban' : 'fa-check'; ?> me-1"></i>
                                    <?php echo $p['is_available'] ? 'Make Unavailable' : 'Make Available'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No products found. Link products first.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAvailability(productId, currentStatus) {
    // Determine new status (toggle: if 1, make 0; if 0, make 1)
    const newStatus = currentStatus === 1 ? 0 : 1;
    
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    const originalClass = btn.className;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('is_available', newStatus);

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/toggle-product-availability')); ?>', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Response error:', text);
            throw new Error('Network response was not ok: ' + response.status);
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Product availability updated successfully!', 'success');
            
            // Update badge
            const badgeSpan = document.getElementById('status-badge-' + productId);
            if (newStatus === 1) {
                // Making available
                badgeSpan.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Available</span>';
                btn.className = 'btn btn-sm btn-warning';
                btn.innerHTML = '<i class="fas fa-ban me-1"></i>Make Unavailable';
                btn.disabled = false;
                btn.onclick = function(e) { toggleAvailability(productId, 1); };
            } else {
                // Making unavailable
                badgeSpan.innerHTML = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Unavailable</span>';
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Make Available';
                btn.disabled = false;
                btn.onclick = function(e) { toggleAvailability(productId, 0); };
            }
        } else {
            showToast('Error: ' + (data.message || 'Failed to update availability'), 'error');
            btn.className = originalClass;
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Error: ' + err.message, 'error');
        btn.className = originalClass;
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}
</script>

