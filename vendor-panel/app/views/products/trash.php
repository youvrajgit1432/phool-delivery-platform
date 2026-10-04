<?php
/**
 * Vendor Products Trash - Unlinked Products Recovery
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$trashedProducts = [];

if ($vendorId) {
    // Get vendor's unlinked (trashed) products
    $stmt = $db->query(
        'SELECT vpm.*, p.id as product_id, p.name_en, p.category_id, p.price as master_price, p.unit
         FROM vendor_product_map vpm
         JOIN products p ON vpm.product_id = p.id
         WHERE vpm.vendor_id = ? AND vpm.unlinked_at IS NOT NULL
         ORDER BY vpm.unlinked_at DESC',
        [$vendorId]
    );
    $trashedProducts = $stmt->fetchAll();
}
?>

<div class="products-container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>
                <i class="fas fa-trash me-2"></i>Trash
            </h2>
            <p class="text-muted">Unlinked products - recover or permanently delete</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Products
            </a>
        </div>
    </div>

    <?php if (empty($trashedProducts)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Your trash is empty. All your linked products are active!
        </div>
    <?php else: ?>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width: 50px;">S.N.</th>
                    <th style="width: 80px;">Image</th>
                    <th>Product Name</th>
                    <th>Your Price</th>
                    <th>Stock</th>
                    <th>Unlinked Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($trashedProducts as $p): ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td>
                            <?php 
                                $vendorImages = [];
                                
                                // Parse vendor images from notes if they exist
                                if (!empty($p['notes'])) {
                                    try {
                                        $notesData = json_decode($p['notes'], true);
                                        if (isset($notesData['vendor_images'])) {
                                            $vendorImages = $notesData['vendor_images'];
                                        }
                                    } catch (Exception $e) {}
                                }
                                
                                // Show only vendor images, not master product image
                                if (!empty($vendorImages) && isset($vendorImages[0])) {
                                    $imagePath = vendor_url('/uploads/vendor_' . htmlspecialchars($_SESSION['vendor_id']) . '/products/' . htmlspecialchars($vendorImages[0]));
                            ?>
                                <img src="<?php echo $imagePath; ?>" 
                                     alt="<?php echo htmlspecialchars($p['name_en']); ?>" 
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 2px solid #ffc107; opacity: 0.6;"
                                     title="Your vendor image">
                            <?php 
                                } else {
                            ?>
                                <div style="width: 60px; height: 60px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; opacity: 0.6;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <strong style="opacity: 0.7;"><?php echo htmlspecialchars($p['name_en']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($p['unit']); ?></small>
                        </td>
                        <td>
                            <strong class="text-primary">₹<?php echo number_format($p['vendor_price'], 2); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo $p['vendor_stock']; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('M d, Y', strtotime($p['unlinked_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-success" onclick="restoreProduct(<?php echo $p['id']; ?>)">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="permanentlyDeleteProduct(<?php echo $p['id']; ?>)">
                                <i class="fas fa-times"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
function restoreProduct(mapId) {
    const formData = new FormData();
    formData.append('map_id', mapId);
    formData.append('action', 'restore');

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/trash-product')); ?>', {
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
            throw new Error(text || 'Network response was not ok');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Product restored successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

function permanentlyDeleteProduct(mapId) {
    if (!confirm('Are you sure? This will permanently delete the product and cannot be undone!')) {
        return;
    }

    const formData = new FormData();
    formData.append('map_id', mapId);
    formData.append('action', 'permanent_delete');

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/trash-product')); ?>', {
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
            throw new Error(text || 'Network response was not ok');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Product permanently deleted!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}
</script>

