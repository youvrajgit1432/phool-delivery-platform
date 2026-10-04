<?php
/**
 * Vendor Products List - Shows linked master products
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$linkedProducts = [];

if ($vendorId) {
    // Get vendor's linked products from vendor_product_map (exclude unlinked)
    $stmt = $db->query(
        'SELECT vpm.id as map_id, vpm.product_id, vpm.vendor_price, vpm.vendor_stock, 
                vpm.is_available, vpm.preparation_time, vpm.status, vpm.notes,
                p.id, p.name_en, p.category_id, p.price as master_price, p.unit,
                pi.image_path FROM vendor_product_map vpm
         JOIN products p ON vpm.product_id = p.id
         LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
         WHERE vpm.vendor_id = ? AND vpm.status = "active" AND vpm.unlinked_at IS NULL
         ORDER BY vpm.created_at DESC',
        [$vendorId]
    );
    $linkedProducts = $stmt->fetchAll();
}
?>

<style>
    /* Products Header Layout */
    .products-header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .products-header-title {
        flex: 1;
        min-width: 0;
    }
    
    .products-header-title h2 {
        margin-bottom: 4px;
        font-size: 1.8rem;
        font-weight: 600;
    }
    
    .products-header-title p {
        margin: 0;
        font-size: 0.9rem;
    }
    
    /* Desktop: Buttons in horizontal layout */
    .products-header-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .products-header-buttons .btn {
        padding: 10px 16px;
        font-size: 0.95rem;
        white-space: nowrap;
    }

    /* Mobile 2x2 Button Grid Layout - 40% width on right */
    @media (max-width: 768px) {
        .products-header-wrapper {
            display: block;
        }
        
        .products-header-title {
            margin-bottom: 12px;
        }
        
        .products-header-title h2 {
            font-size: 1.4rem;
            margin-bottom: 3px;
        }
        
        .products-header-title p {
            font-size: 0.8rem;
        }
        
        .products-header-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            width: 40%;
            margin-left: auto;
        }
        
        .products-header-buttons .btn {
            padding: 8px 10px;
            font-size: 0.75rem;
            text-align: center;
        }
        
        .products-header-buttons .btn i {
            margin-right: 4px;
        }
    }
    
    @media (max-width: 480px) {
        .products-header-buttons {
            width: 40%;
            gap: 6px;
        }
        
        .products-header-buttons .btn {
            padding: 7px 8px;
            font-size: 0.7rem;
        }
        
        .products-header-title h2 {
            font-size: 1.2rem;
        }
        
        .products-header-title p {
            font-size: 0.75rem;
        }
    }
</style>

<div class="products-container">
    <div class="products-header-wrapper">
        <div class="products-header-title">
            <h2>
                <i class="fas fa-link me-2"></i>Linked Products
            </h2>
            <p class="text-muted">Master products you supply</p>
        </div>
        <div class="products-header-buttons">
            <a href="<?php echo htmlspecialchars(vendor_url('/products/trash')); ?>" class="btn btn-warning">
                <i class="fas fa-trash"></i> Trash
            </a>
            <a href="<?php echo htmlspecialchars(vendor_url('/products/add')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Link New
            </a>
        </div>
    </div>

    <?php if (empty($linkedProducts)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You haven't linked any products yet. 
            <a href="<?php echo htmlspecialchars(vendor_url('/products/add')); ?>">Click here</a> to link your first master product!
        </div>
    <?php else: ?>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width: 50px;">S.N.</th>
                    <th style="width: 80px;">Image</th>
                    <th>Product Name</th>
                    <th>Master Price</th>
                    <th>Your Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($linkedProducts as $p): ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td>
                            <?php 
                                $vendorImages = [];
                                $masterImage = '';
                                
                                // Parse vendor images from notes if they exist
                                if (!empty($p['notes'])) {
                                    try {
                                        $notesData = json_decode($p['notes'], true);
                                        if (isset($notesData['vendor_images'])) {
                                            $vendorImages = $notesData['vendor_images'];
                                        }
                                    } catch (Exception $e) {}
                                }
                                
                                // Show vendor image if exists, otherwise master image
                                if (!empty($vendorImages) && isset($vendorImages[0])) {
                                    $vendorUploadUrl = vendor_url('/uploads/vendor_' . htmlspecialchars($_SESSION['vendor_id']) . '/products');
                                    $imagePath = $vendorUploadUrl . '/' . htmlspecialchars($vendorImages[0]);
                            ?>
                                <img src="<?php echo $imagePath; ?>" 
                                     alt="<?php echo htmlspecialchars($p['name_en']); ?>" 
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 2px solid #ffc107;"
                                     title="Your vendor image">
                            <?php 
                                } elseif (!empty($p['image_path'])) {
                            ?>
                                <img src="<?php echo htmlspecialchars(vendor_image_url($p['image_path'], 'product')); ?>" 
                                     alt="<?php echo htmlspecialchars($p['name_en']); ?>" 
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                            <?php 
                                } else {
                            ?>
                                <div style="width: 60px; height: 60px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['name_en']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($p['unit']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info">₹<?php echo number_format($p['master_price'], 2); ?></span>
                        </td>
                        <td>
                            <strong class="text-primary">₹<?php echo number_format($p['vendor_price'], 2); ?></strong>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['vendor_stock'] > 10 ? 'bg-success' : ($p['vendor_stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                <?php echo $p['vendor_stock']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $p['is_available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo htmlspecialchars(vendor_url('/products/edit?id=' . $p['map_id'])); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-sm btn-outline-danger" onclick="unlinkProduct(<?php echo $p['map_id']; ?>)">
                                <i class="fas fa-unlink"></i> Unlink
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
function editProduct(mapId) {
    // Open modal for editing
    const modal = document.getElementById('editModal' + mapId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function saveProductEdits(mapId) {
    const form = document.querySelector(`[data-map-id="${mapId}"]`);
    if (!form) return;

    const formData = new FormData(form);
    formData.append('map_id', mapId);

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/update-vendor-product')); ?>', {
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
            showToast('Product updated successfully!', 'success');
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

function unlinkProduct(mapId) {
    const formData = new FormData();
    formData.append('map_id', mapId);

    fetch('<?php echo htmlspecialchars(vendor_url('/ajax/unlink-product')); ?>', {
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
            showToast('Product moved to trash successfully! You can restore it from the Trash page.', 'success');
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

