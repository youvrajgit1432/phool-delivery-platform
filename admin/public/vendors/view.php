<?php
/**
 * View Vendor Details
 * Admin panel page to view detailed vendor information
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}

// Get database connection
$db = getDBConnection();

// Fetch vendor details using subqueries for accurate aggregates
try {
    $stmt = $db->prepare("
        SELECT v.*,
               (
                   (
                       SELECT COUNT(*) FROM vendor_products vp2 WHERE vp2.vendor_id = v.id AND (vp2.deleted_at IS NULL OR vp2.deleted_at = '')
                   )
                   +
                   (
                       SELECT COUNT(*) FROM vendor_product_map vpm2 WHERE vpm2.vendor_id = v.id
                   )
               ) AS total_products,
               (
                   SELECT COUNT(*) FROM vendor_orders vo2 WHERE vo2.vendor_id = v.id
               ) AS total_orders,
               (
                   SELECT COALESCE(SUM(vo3.total_amount), 0) FROM vendor_orders vo3 WHERE vo3.vendor_id = v.id AND vo3.status = 'completed'
               ) AS total_sales,
               (
                   SELECT COALESCE(AVG(vr2.rating), 0) FROM vendor_reviews vr2 WHERE vr2.vendor_id = v.id
               ) AS avg_rating,
               (
                   SELECT COUNT(*) FROM vendor_reviews vr3 WHERE vr3.vendor_id = v.id
               ) AS total_reviews
        FROM vendors v
        WHERE v.id = ?
        GROUP BY v.id
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fetch recent notifications for this vendor
try {
    $notStmt = $db->prepare("SELECT vn.id, vn.notification_type, vn.title, vn.message, vn.data, vn.is_read, vn.read_at, vn.created_at, vn.performed_by_admin, u.full_name as performed_by_name FROM vendor_notifications vn LEFT JOIN users u ON vn.performed_by_admin = u.id WHERE vn.vendor_id = ? ORDER BY vn.created_at DESC LIMIT 10");
    $notStmt->execute([$vendor_id]);
    $vendorNotifications = $notStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ne) {
    $vendorNotifications = [];
}

$page_title = 'View Vendor - ' . htmlspecialchars($vendor['store_name']);
$current_page = 'vendors';

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
    <style>
        .vendor-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .info-box {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #ddd;
            margin-bottom: 1rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <!-- Vendor Header -->
        <div class="vendor-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-1"><?php echo htmlspecialchars($vendor['store_name']); ?></h1>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?> | 
                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($vendor['email']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-light text-dark me-2">ID: <?php echo $vendor['id']; ?></span>
                    <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : ($vendor['status'] === 'suspended' ? 'danger' : 'warning'); ?>">
                        <?php echo ucfirst($vendor['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Edit Vendor
            </a>
            
                <button id="resendCredsBtn" class="btn btn-primary">
                    <i class="bi bi-envelope-paper"></i> Resend Credentials
                </button>

            <a href="../vendor-products/manage.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-info">
                <i class="bi bi-box"></i> Manage Products
            </a>
            <a href="../vendor-orders/view.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-secondary">
                <i class="bi bi-receipt"></i> View Orders
            </a>
            <a href="../vendors.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Vendors
            </a>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $vendor['total_products']; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $vendor['total_orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">₹<?php echo number_format($vendor['total_sales'], 0); ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($vendor['avg_rating'], 1); ?> ⭐</div>
                    <div class="stat-label"><?php echo $vendor['total_reviews']; ?> Reviews</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Vendor Information -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Vendor Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <h6 class="text-muted mb-3">Personal Details</h6>
                            <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></p>
                            <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>"><?php echo htmlspecialchars($vendor['email']); ?></a></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($vendor['phone']); ?></p>
                        </div>

                        <div class="info-box">
                            <h6 class="text-muted mb-3">Store Details</h6>
                            <p><strong>Store Name:</strong> <?php echo htmlspecialchars($vendor['store_name']); ?></p>
                            <p><strong>Store Email:</strong> <?php echo htmlspecialchars($vendor['store_email'] ?? 'N/A'); ?></p>
                            <p><strong>Category:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($vendor['store_category'] ?? 'N/A'); ?></span></p>
                        </div>

                        <div class="info-box">
                            <h6 class="text-muted mb-3">Location</h6>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($vendor['business_address'] ?? 'N/A'); ?></p>
                            <p><strong>City:</strong> <?php echo htmlspecialchars($vendor['city'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business Information -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-briefcase"></i> Business Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <h6 class="text-muted mb-3">Registration Details</h6>
                            <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($vendor['registration_number'] ?? 'N/A'); ?></p>
                            <p><strong>Tax ID (GST):</strong> <?php echo htmlspecialchars($vendor['tax_id'] ?? 'N/A'); ?></p>
                            <p><strong>Commission Rate:</strong> <span class="badge bg-success"><?php echo $vendor['commission_rate']; ?>%</span></p>
                        </div>

                        <div class="info-box">
                            <h6 class="text-muted mb-3">Bank Information</h6>
                            <p><strong>Bank Name:</strong> <?php echo htmlspecialchars($vendor['bank_name'] ?? 'N/A'); ?></p>
                            <p><strong>Account Holder:</strong> <?php echo htmlspecialchars($vendor['account_holder_name'] ?? 'N/A'); ?></p>
                            <p><strong>Account Number:</strong> ••••••••<?php echo substr($vendor['account_number'] ?? '', -4); ?></p>
                            <p><strong>IFSC Code:</strong> <?php echo htmlspecialchars($vendor['ifsc_code'] ?? 'N/A'); ?></p>
                        </div>

                        <div class="info-box">
                            <h6 class="text-muted mb-3">Account Status</h6>
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : ($vendor['status'] === 'suspended' ? 'danger' : 'warning'); ?>"><?php echo ucfirst($vendor['status']); ?></span></p>
                            <p><strong>Email Verified:</strong> <span class="badge bg-<?php echo $vendor['email_verified'] ? 'success' : 'warning'; ?>"><?php echo $vendor['email_verified'] ? 'Yes' : 'No'; ?></span></p>
                            <p><strong>Bank Verified:</strong> <span class="badge bg-<?php echo $vendor['bank_verified'] ? 'success' : 'warning'; ?>"><?php echo $vendor['bank_verified'] ? 'Yes' : 'No'; ?></span></p>
                        </div>

                        <div class="info-box">
                            <h6 class="text-muted mb-3">Dates</h6>
                            <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($vendor['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($vendor['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="col-md-6 mt-3">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> Recent Notifications / Sends</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vendorNotifications)): ?>
                            <p class="text-muted">No notifications recorded for this vendor.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($vendorNotifications as $n):
                                    $data = json_decode($n['data'], true) ?: [];
                                    if (!empty($data['method'])) {
                                        $method = $data['method'];
                                    } elseif (!empty($data['to'])) {
                                        // guess method from recipient format
                                        $to = $data['to'];
                                        if (filter_var($to, FILTER_VALIDATE_EMAIL)) $method = 'email';
                                        else $method = 'sms';
                                    } elseif (!empty($data['email_error']) && empty($data['sms_response'])) {
                                        $method = 'email';
                                    } elseif (!empty($data['sms_response']) && empty($data['email_error'])) {
                                        $method = 'sms';
                                    } else {
                                        $method = 'unknown';
                                    }
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?php echo htmlspecialchars($n['title']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($n['message']); ?></div>
                                            <div class="small text-muted"><?php echo date('M d, Y H:i', strtotime($n['created_at'])); ?>
                                                <?php if (!empty($n['performed_by_name'])): ?>
                                                    &nbsp;•&nbsp; <span class="text-muted">By: <?php echo htmlspecialchars($n['performed_by_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?php echo ($method === 'email' ? 'primary' : ($method === 'sms' ? 'info' : 'secondary')); ?> rounded-pill"><?php echo strtoupper($method); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <?php include '../../app/views/partials/admin-auth-modal.php'; ?>
    <script>
        document.getElementById('resendCredsBtn')?.addEventListener('click', async function() {
            if (!confirm('Resend temporary credentials to this vendor? This will reset their password and send a temporary password.')) return;
            const vendorId = '<?php echo $vendor['id']; ?>';

            async function doSend(adminPassword) {
                const form = new FormData();
                form.append('vendor_id', vendorId);
                if (adminPassword) form.append('admin_password', adminPassword);
                try {
                    const res = await fetch('../ajax/vendor-resend-credentials.php', { method: 'POST', body: form });
                    const data = await res.json();
                    if (res.status === 401 && data.auth_required) {
                        // show modal to collect admin password
                        const modalEl = document.getElementById('adminAuthModal');
                        const modal = new bootstrap.Modal(modalEl);
                        document.getElementById('adminAuthPassword').value = '';
                        document.getElementById('adminAuthError').style.display = 'none';
                        modal.show();
                        document.getElementById('adminAuthSubmit').onclick = async function() {
                            const pwd = document.getElementById('adminAuthPassword').value;
                            if (!pwd) { document.getElementById('adminAuthError').textContent = 'Password required'; document.getElementById('adminAuthError').style.display = 'block'; return; }
                            this.disabled = true;
                            await doSend(pwd);
                            this.disabled = false;
                            modal.hide();
                        };
                        return;
                    }
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Could not resend credentials'));
                    }
                } catch (err) {
                    console.error(err);
                    alert('Request failed');
                }
            }

            doSend();
        });
    </script>
</body>
</html>
