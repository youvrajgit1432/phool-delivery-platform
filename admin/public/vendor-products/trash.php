<?php
/**
 * Trashed Products - Admin UI
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

$page_title = 'Trash - Vendor Products';
$current_page = 'vendor-products';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between">
                <h1 class="h3">Trashed Products</h1>
                <a href="manage.php?vendor_id=<?php echo htmlspecialchars($vendor_id); ?>" class="btn btn-outline-secondary">Back to Products</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="trashListContainer">Loading trashed products…</div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTrash() {
            const url = '../ajax/product-trash-list.php' + (<?php echo $vendor_id ? 'true' : 'false'; ?> ? '?vendor_id=<?php echo (int)$vendor_id; ?>' : '');
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return document.getElementById('trashListContainer').innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    const rows = data.data;
                    if (!rows.length) return document.getElementById('trashListContainer').innerHTML = '<div class="alert alert-info">No trashed products.</div>';
                    let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Image</th><th>Product</th><th>Vendor</th><th>Deleted At</th><th>Actions</th></tr></thead><tbody>';
                    rows.forEach(p => {
                        const img = p.primary_image_url ? '<img src="' + p.primary_image_url + '" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" />' : '<div style="width:60px;height:60px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999">No</div>';
                        html += '<tr>' +
                            '<td>' + img + '</td>' +
                            '<td><strong>' + (p.product_name || '') + '</strong><br/><small>' + (p.category||'') + '</small></td>' +
                            '<td>' + (p.store_name || '') + '</td>' +
                            '<td>' + (p.deleted_at || '') + '</td>' +
                            '<td>' +
                            '<div class="btn-group">' +
                            '<button class="btn btn-sm btn-success" onclick="restoreProduct(' + p.id + ')"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>' +
                            '<button class="btn btn-sm btn-danger" onclick="permDelete(' + p.id + ')"><i class="bi bi-trash"></i> Delete Permanently</button>' +
                            '</div>' +
                            '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table></div>';
                    document.getElementById('trashListContainer').innerHTML = html;
                })
                .catch(err => document.getElementById('trashListContainer').innerHTML = '<div class="alert alert-danger">Error loading trash</div>');
        }

        function restoreProduct(id) {
            if (!confirm('Restore this product?')) return;
            fetch('../ajax/product-restore.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'product_id=' + id
            }).then(r => r.json()).then(d => { if (d.success) { alert('Restored'); loadTrash(); } else alert('Error: ' + d.message); }).catch(e=>alert('Error'));
        }

        function permDelete(id) {
            if (!confirm('Permanently delete this product? This cannot be undone.')) return;
            fetch('../ajax/product-permanent-delete.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'product_id=' + id
            }).then(r => r.json()).then(d => { if (d.success) { alert('Deleted'); loadTrash(); } else alert('Error: ' + d.message); }).catch(e=>alert('Error'));
        }

        loadTrash();
    </script>
</body>
</html>
