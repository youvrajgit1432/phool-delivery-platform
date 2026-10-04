<?php
/**
 * Account Dashboard - Vendor Account Information & Management
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$vendor = null;
$galleryImages = [];

if ($vendorId) {
    $stmt = $db->query('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    $vendor = $stmt->fetch();
    
    // Fetch order-related statistics for dashboard (ensure totals are accurate)
    $orderCountStmt = $db->query('SELECT COUNT(*) AS total_orders FROM vendor_orders WHERE vendor_id = ?', [$vendorId]);
    $orderCountRow = $orderCountStmt->fetch();
    $vendor['total_orders'] = (int)($orderCountRow['total_orders'] ?? 0);

    // Total products for this vendor - count active linked products from vendor_product_map
    $productCountStmt = $db->query('SELECT COUNT(*) AS total_products FROM vendor_product_map WHERE vendor_id = ? AND unlinked_at IS NULL', [$vendorId]);
    $vendor['total_products'] = (int)($productCountStmt->fetch()['total_products'] ?? 0);

    // Recent orders (last 30 days)
    $recentStmt = $db->query('SELECT COUNT(*) AS recent_orders FROM vendor_orders WHERE vendor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', [$vendorId]);
    $vendor['recent_orders_30d'] = (int)($recentStmt->fetch()['recent_orders'] ?? 0);

    // Orders grouped by status
    $statusStmt = $db->query('SELECT status, COUNT(*) AS cnt FROM vendor_orders WHERE vendor_id = ? GROUP BY status', [$vendorId]);
    $statusRows = $statusStmt->fetchAll() ?: [];
    $vendor['order_status_counts'] = [];
    foreach ($statusRows as $sr) {
        $vendor['order_status_counts'][$sr['status']] = (int)$sr['cnt'];
    }

    // Total revenue for vendor orders
    $revStmt = $db->query('SELECT IFNULL(SUM(total_amount),0) AS total_revenue FROM vendor_orders WHERE vendor_id = ?', [$vendorId]);
    $vendor['total_revenue'] = (float)($revStmt->fetch()['total_revenue'] ?? 0);

    // Fetch gallery images
    $galStmt = $db->query('SELECT * FROM vendor_gallery_images WHERE vendor_id = ? ORDER BY display_order ASC, created_at DESC LIMIT 10', [$vendorId]);
    $galleryImages = $galStmt->fetchAll() ?? [];
}

if (!$vendor) {
    echo '<div class="alert alert-danger">Vendor information not found.</div>';
    exit;
}

// Helper function to format dates
function formatDate($date) {
    return $date ? date('M d, Y', strtotime($date)) : 'N/A';
}

// Helper function to get status badge color
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'bg-success';
        case 'pending':
            return 'bg-warning';
        case 'suspended':
            return 'bg-danger';
        case 'inactive':
            return 'bg-secondary';
        default:
            return 'bg-info';
    }
}
?>

<div class="account-dashboard">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title">
                    <?php if (!empty($vendor['profile_image_url'])): ?>
                        <img src="<?php echo htmlspecialchars(get_image_url($vendor['profile_image_url'])); ?>" alt="Profile" class="header-profile">
                    <?php else: ?>
                        <i class="fas fa-user-circle me-2"></i>
                    <?php endif; ?>
                    Account Dashboard
                </h1>
                <p class="text-muted">Manage your vendor profile and business information</p>
            </div>
            <div class="col-auto d-flex align-items-center gap-2">
                <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-sm btn-outline-primary">View Orders</a>
                <a href="<?php echo htmlspecialchars(vendor_url('/products')); ?>" class="btn btn-sm btn-outline-secondary">View Products</a>
            </div>
            <div class="col-auto">
                <span class="badge badge-lg <?php echo getStatusBadgeClass($vendor['status']); ?>">
                    <?php echo ucfirst($vendor['status']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Store Profile Overview - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-store"></i>
                <h6>Store Profile</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Left Column: Store Info -->
                <div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Store Name</label>
                        <h6><?php echo htmlspecialchars($vendor['store_name'] ?? 'N/A'); ?></h6>
                    </div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Store Category</label>
                        <h6><?php echo htmlspecialchars($vendor['store_category'] ?? 'N/A'); ?></h6>
                    </div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Total Products</label>
                        <h6><?php echo $vendor['total_products'] ?? 0; ?></h6>
                    </div>
                    <div class="accordion-body-item">
                        <label>Total Orders</label>
                        <h6><?php echo $vendor['total_orders'] ?? 0; ?></h6>
                    </div>
                </div>
                
                <!-- Right Column: Store Images -->
                <div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Profile Image -->
                        <div>
                            <small style="font-size: 0.75rem; color: #999; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 6px;">Profile Picture</small>
                            <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; min-height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <img id="mainStoreImage" src="<?php echo htmlspecialchars(get_image_url($vendor['profile_image_url'] ?? '')); ?>" alt="Profile" style="max-width: 100%; max-height: 100px; object-fit: cover;" <?php echo empty($vendor['profile_image_url']) ? 'style="display:none;"' : ''; ?>>
                                <div id="mainPlaceholder" style="text-align: center; padding: 20px; color: #ccc;" <?php echo !empty($vendor['profile_image_url']) ? 'style="display:none;"' : ''; ?>>
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logo and Banner Quick Links -->
                        <div style="display: flex; gap: 8px;">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#editStoreImagesModal">
                                <i class="fas fa-edit me-1"></i>Manage Images
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Store Description -->
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.75rem; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; display: block;">Store Description</label>
                <p style="margin: 0; color: #333; line-height: 1.5;"><?php echo htmlspecialchars($vendor['store_description'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Gallery Images - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-images"></i>
                <h6>Gallery Images (<?php echo count($galleryImages); ?>/10)</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <?php if (!empty($galleryImages)): ?>
                <?php
                    $totalGallery = count($galleryImages);
                ?>
                <div class="gallery-grid">
                    <?php foreach ($galleryImages as $idx => $image): ?>
                        <div class="gallery-item <?php echo $idx >= 4 ? 'd-none extra-gallery-image' : ''; ?>" data-image-id="<?php echo $image['id']; ?>">
                            <div class="gallery-image-box">
                                <img src="<?php echo htmlspecialchars(get_image_url($image['image_url'])); ?>" alt="<?php echo htmlspecialchars($image['alt_text'] ?? 'Gallery Image'); ?>" class="img-fluid rounded">
                                <div class="gallery-overlay">
                                    <button class="btn btn-sm btn-danger delete-gallery-image" data-image-id="<?php echo $image['id']; ?>">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                            <small class="d-block mt-2 text-muted"><?php echo htmlspecialchars($image['image_type']); ?></small>
                            <?php if (!empty($image['description'])): ?>
                                <small class="d-block text-secondary"><?php echo htmlspecialchars($image['description']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <?php if ($totalGallery > 4): ?>
                        <button id="toggleGalleryBtn" class="btn btn-sm btn-outline-primary">View all images (<?php echo $totalGallery; ?>)</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGalleryImagesModal">
                        <i class="fas fa-plus me-1"></i>Add Images
                    </button>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-image fa-3x text-muted mb-3 d-block"></i>
                    <p style="color: #999; margin-bottom: 20px;">No gallery images yet. Add images to showcase your store!</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGalleryImagesModal">
                        <i class="fas fa-plus me-1"></i>Add Gallery Images
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ratings & Reviews - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-star"></i>
                <h6>Performance Overview</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="accordion-body-item">
                    <label>Average Rating</label>
                    <h6 style="color: #F7B801;">
                        <?php echo number_format($vendor['average_rating'] ?? 0, 1); ?>
                        <i class="fas fa-star"></i>
                        <small style="color: #999; font-weight: normal; margin-left: 5px;">(<?php echo $vendor['total_reviews'] ?? 0; ?> reviews)</small>
                    </h6>
                </div>
                <div class="accordion-body-item">
                    <label>Featured Status</label>
                    <h6>
                        <i class="fas fa-star <?php echo $vendor['is_featured'] ? 'text-warning' : 'text-muted'; ?>"></i>
                        <small style="margin-left: 5px;"><?php echo $vendor['is_featured'] ? 'Featured' : 'Not Featured'; ?></small>
                    </h6>
                </div>
                <div class="accordion-body-item">
                    <label>Total Products</label>
                    <h6 style="color: #667eea;"><?php echo $vendor['total_products'] ?? 0; ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Total Orders</label>
                    <h6 style="color: #1AAB8A;"><?php echo $vendor['total_orders'] ?? 0; ?></h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-user"></i>
                <h6>Personal Information</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>First Name</label>
                    <h6><?php echo htmlspecialchars($vendor['first_name'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Last Name</label>
                    <h6><?php echo htmlspecialchars($vendor['last_name'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Email Address</label>
                    <h6><?php echo htmlspecialchars($vendor['email'] ?? 'N/A'); ?></h6>
                    <small class="<?php echo $vendor['email_verified'] ? 'text-success' : 'text-warning'; ?>">
                        <i class="fas fa-<?php echo $vendor['email_verified'] ? 'check-circle' : 'exclamation-circle'; ?> me-1"></i>
                        <?php echo $vendor['email_verified'] ? 'Verified' : 'Not Verified'; ?>
                    </small>
                </div>
                <div class="accordion-body-item">
                    <label>Phone Number</label>
                    <h6><?php echo htmlspecialchars($vendor['phone'] ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPersonalModal">
                    <i class="fas fa-edit me-1"></i>Edit Information
                </button>
            </div>
        </div>
    </div>

    <!-- Business Information - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-briefcase"></i>
                <h6>Business Information</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Registration Number</label>
                    <h6><?php echo htmlspecialchars($vendor['registration_number'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Tax ID</label>
                    <h6><?php echo htmlspecialchars($vendor['tax_id'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>City</label>
                    <h6><?php echo htmlspecialchars($vendor['city'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>State</label>
                    <h6><?php echo htmlspecialchars($vendor['state'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Postal Code</label>
                    <h6><?php echo htmlspecialchars($vendor['postal_code'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Country</label>
                    <h6><?php echo htmlspecialchars($vendor['country'] ?? 'India'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <label style="font-size: 0.75rem; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; display: block;">Business Address</label>
                <p style="margin: 0 0 20px 0; color: #333;"><?php echo htmlspecialchars($vendor['business_address'] ?? 'N/A'); ?></p>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editBusinessModal">
                    <i class="fas fa-edit me-1"></i>Edit Information
                </button>
            </div>
        </div>
    </div>

    <!-- Bank Information - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-university"></i>
                <h6>Bank Information</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Bank Name</label>
                    <h6><?php echo htmlspecialchars($vendor['bank_name'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Account Holder Name</label>
                    <h6><?php echo htmlspecialchars($vendor['account_holder'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Account Number</label>
                    <h6><i class="fas fa-lock me-1"></i>****<?php echo substr($vendor['account_number'] ?? '', -4); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>IFSC Code</label>
                    <h6><?php echo htmlspecialchars($vendor['ifsc_code'] ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Verification Status</label>
                    <h6>
                        <small class="<?php echo $vendor['bank_verified'] ? 'text-success' : 'text-warning'; ?>">
                            <i class="fas fa-<?php echo $vendor['bank_verified'] ? 'check-circle' : 'exclamation-circle'; ?> me-1"></i>
                            <?php echo $vendor['bank_verified'] ? 'Verified' : 'Not Verified'; ?>
                        </small>
                    </h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editBankModal">
                    <i class="fas fa-edit me-1"></i>Edit Bank Details
                </button>
            </div>
        </div>
    </div>

    <!-- Account Status & Activity -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-secondary text-white p-3">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Account Activity
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Account Created</small>
                        <h6><?php echo formatDate($vendor['created_at']); ?></h6>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Last Login</small>
                        <h6><?php echo $vendor['last_login'] ? formatDate($vendor['last_login']) : 'Never'; ?></h6>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Email Verified</small>
                        <h6><?php echo $vendor['verified_at'] ? formatDate($vendor['verified_at']) : 'Not Verified'; ?></h6>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>Account Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="<?php echo htmlspecialchars(vendor_url('/profile/change-password')); ?>" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-key me-2"></i>Change Password
                        </a>
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#viewMoreModal">
                            <i class="fas fa-cogs me-2"></i>View More Settings
                        </button>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo htmlspecialchars(vendor_url('/logout')); ?>" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Store Modal -->
<div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Store Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStoreForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Store Name</label>
                        <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store Category</label>
                        <input type="text" class="form-control" name="store_category" value="<?php echo htmlspecialchars($vendor['store_category'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store Description</label>
                        <textarea class="form-control" name="store_description" rows="4"><?php echo htmlspecialchars($vendor['store_description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Personal Modal -->
<div class="modal fade" id="editPersonalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Personal Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPersonalForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($vendor['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($vendor['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Business Modal -->
<div class="modal fade" id="editBusinessModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Business Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBusinessForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Registration Number</label>
                            <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($vendor['registration_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax ID</label>
                            <input type="text" class="form-control" name="tax_id" value="<?php echo htmlspecialchars($vendor['tax_id'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Business Address</label>
                        <textarea class="form-control" name="business_address" rows="3"><?php echo htmlspecialchars($vendor['business_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($vendor['state'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($vendor['country'] ?? 'India'); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bank Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bank Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBankForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($vendor['bank_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" class="form-control" name="account_holder" value="<?php echo htmlspecialchars($vendor['account_holder'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($vendor['account_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" name="ifsc_code" value="<?php echo htmlspecialchars($vendor['ifsc_code'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Store Images Modal -->
<div class="modal fade" id="editStoreImagesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Store Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStoreImagesForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-circle me-2"></i>Profile Picture
                        </label>
                        <input type="file" class="form-control" name="profile_image" accept="image/*">
                        <small class="text-muted">Primary store profile/avatar image (JPG, PNG - max 5MB)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-image me-2"></i>Store Logo
                        </label>
                        <input type="file" class="form-control" name="logo_image" accept="image/*">
                        <small class="text-muted">Store logo/brand mark (JPG, PNG - max 5MB)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-images me-2"></i>Banner Image
                        </label>
                        <input type="file" class="form-control" name="banner_image" accept="image/*">
                        <small class="text-muted">Store banner/cover image (JPG, PNG - max 5MB). Recommended: 1200x400px</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Images</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Gallery Images Modal -->
<div class="modal fade" id="addGalleryImagesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Gallery Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addGalleryImagesForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>You can upload up to 10 gallery images. Current: <?php echo count($galleryImages); ?>
                    </div>
                    
                    <div id="galleryImageInputs">
                        <div class="gallery-input-group mb-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Select Image 1</label>
                                    <input type="file" class="form-control gallery-image-input" name="gallery_images[]" accept="image/*" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Image Type</label>
                                    <select class="form-control form-select" name="image_types[]">
                                        <option value="gallery">Gallery</option>
                                        <option value="storefront">Storefront</option>
                                        <option value="team">Team</option>
                                        <option value="facility">Facility</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Description (optional)</label>
                                <input type="text" class="form-control" name="image_descriptions[]" placeholder="e.g., Our flower arrangement workshop">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addMoreGalleryInputs">
                        <i class="fas fa-plus me-1"></i>Add Another Image
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload Gallery Images</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.page-header {
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.page-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
}

.badge-lg {
    font-size: 14px;
    padding: 8px 16px;
    font-weight: 500;
}

.store-images-section {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.image-container {
    margin: 0;
}

.image-display-box {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    min-height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.image-display-box img {
    max-width: 100%;
    max-height: 120px;
    object-fit: cover;
}

.image-display-box.banner-box {
    min-height: 80px;
}

.banner-box img {
    max-height: 80px;
}

.placeholder-image {
    text-align: center;
    padding: 20px;
    color: #ccc;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

/* Mobile: 3 columns layout */
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
}

/* Small mobile: 3 columns */
@media (max-width: 480px) {
    .gallery-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
}

.gallery-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gallery-image-box {
    position: relative;
    width: 100%;
    height: 150px;
    overflow: hidden;
    background-color: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mobile responsive gallery image height */
@media (max-width: 768px) {
    .gallery-image-box {
        height: 130px;
    }
}

@media (max-width: 480px) {
    .gallery-image-box {
        height: 120px;
    }
}

.gallery-image-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.gallery-item:hover .gallery-overlay {
    opacity: 1;
}

.gallery-overlay .btn {
    margin: 0 5px;
}

.store-logo-container {
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.placeholder-logo {
    text-align: center;
    padding: 20px;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 24px;
}

h6 {
    color: #333;
    font-weight: 500;
    margin-bottom: 8px;
}

.text-muted {
    font-size: 12px;
    font-weight: 500;
}

.btn-light {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.btn-light:hover {
    background-color: #e9ecef;
}

.gallery-input-group {
    padding: 12px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.header-profile {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 8px;
    vertical-align: middle;
}
</style>

<script>
// Handle form submissions
document.addEventListener('DOMContentLoaded', function() {
    ['editStoreForm', 'editPersonalForm', 'editBusinessForm', 'editBankForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('vendor_id', '<?php echo $vendorId; ?>');
                
                fetch('<?php echo htmlspecialchars(vendor_url('/ajax/account-update')); ?>', {
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
                        throw new Error(text || 'Network error');
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
                        showToast('Updated successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to update'), 'error');
                    }
                })
                .catch(error => {
                    showToast('Error: ' + error.message, 'error');
                });
            });
        }
    });

    // Handle store images upload
    const editStoreImagesForm = document.getElementById('editStoreImagesForm');
    if (editStoreImagesForm) {
        editStoreImagesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('vendor_id', '<?php echo $vendorId; ?>');
            formData.append('action', 'upload_store_images');
            
            fetch('<?php echo htmlspecialchars(vendor_url('/ajax/image-upload')); ?>', {
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
                    throw new Error(text || 'Network error');
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
                    showToast('Images uploaded successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to upload images'), 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        });
    }

    // Handle gallery images upload
    const addGalleryImagesForm = document.getElementById('addGalleryImagesForm');
    if (addGalleryImagesForm) {
        addGalleryImagesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Check if we have any files
            const fileInputs = this.querySelectorAll('.gallery-image-input');
            let hasFiles = false;
            fileInputs.forEach(input => {
                if (input.files.length > 0) {
                    hasFiles = true;
                }
            });

            if (!hasFiles) {
                showToast('Please select at least one image', 'warning');
                return;
            }

            const formData = new FormData(this);
            formData.append('vendor_id', '<?php echo $vendorId; ?>');
            formData.append('action', 'upload_gallery_images');
            
            fetch('<?php echo htmlspecialchars(vendor_url('/ajax/image-upload')); ?>', {
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
                    throw new Error(text || 'Network error');
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
                    showToast('Gallery images uploaded successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to upload images'), 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        });
    }

    // Add more gallery image inputs
    const addMoreGalleryInputsBtn = document.getElementById('addMoreGalleryInputs');
    if (addMoreGalleryInputsBtn) {
        addMoreGalleryInputsBtn.addEventListener('click', function() {
            const container = document.getElementById('galleryImageInputs');
            const currentCount = container.querySelectorAll('.gallery-input-group').length;
            const maxImages = 10;

            if (currentCount >= maxImages) {
                showToast('Maximum 10 images allowed', 'warning');
                return;
            }

            const newInputGroup = document.createElement('div');
            newInputGroup.className = 'gallery-input-group mb-3';
            newInputGroup.innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Image ${currentCount + 1}</label>
                        <input type="file" class="form-control gallery-image-input" name="gallery_images[]" accept="image/*">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Image Type</label>
                        <select class="form-control form-select" name="image_types[]">
                            <option value="gallery">Gallery</option>
                            <option value="storefront">Storefront</option>
                            <option value="team">Team</option>
                            <option value="facility">Facility</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Description (optional)</label>
                    <input type="text" class="form-control" name="image_descriptions[]" placeholder="e.g., Our flower arrangement workshop">
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-gallery-input">
                    <i class="fas fa-times me-1"></i>Remove
                </button>
            `;
            container.appendChild(newInputGroup);

            // Add remove button functionality
            newInputGroup.querySelector('.remove-gallery-input').addEventListener('click', function() {
                newInputGroup.remove();
            });
        });
    }

    // Delete gallery image
    document.querySelectorAll('.delete-gallery-image').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const imageId = this.dataset.imageId;
            
            if (confirm('Are you sure you want to delete this image?')) {
                const formData = new FormData();
                formData.append('vendor_id', '<?php echo $vendorId; ?>');
                formData.append('image_id', imageId);
                formData.append('action', 'delete_gallery_image');
                
                fetch('<?php echo htmlspecialchars(vendor_url('/ajax/image-upload')); ?>', {
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
                        throw new Error(text || 'Network error');
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
                        showToast('Image deleted successfully!', 'success');
                        document.querySelector(`[data-image-id="${imageId}"]`).remove();
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to delete image'), 'error');
                    }
                })
                .catch(error => {
                    showToast('Error: ' + error.message, 'error');
                });
            }
        });
    });

    // Swap main display image to logo/banner and toggle gallery expansion
    const profileSrc = '<?php echo htmlspecialchars($vendor['profile_image_url'] ?? ''); ?>';

    function setMainImage(url) {
        const img = document.getElementById('mainStoreImage');
        const ph = document.getElementById('mainPlaceholder');
        if (!img) return;
        if (!url) return;
        img.src = url;
        img.style.display = '';
        if (ph) ph.style.display = 'none';
    }

    const viewLogoBtn = document.getElementById('viewLogoBtn');
    if (viewLogoBtn) {
        viewLogoBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.imageUrl;
            if (url) {
                setMainImage(url);
            } else {
                showToast('No logo uploaded yet.', 'info');
            }
        });
    }

    const viewBannerBtn = document.getElementById('viewBannerBtn');
    if (viewBannerBtn) {
        viewBannerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.imageUrl;
            if (url) {
                setMainImage(url);
            } else {
                showToast('No banner uploaded yet.', 'info');
            }
        });
    }

    const showProfileBtn = document.getElementById('showProfileBtn');
    if (showProfileBtn) {
        showProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const img = document.getElementById('mainStoreImage');
            const ph = document.getElementById('mainPlaceholder');
            if (profileSrc) {
                setMainImage(profileSrc);
            } else {
                if (img) img.style.display = 'none';
                if (ph) ph.style.display = '';
            }
        });
    }

    const toggleGalleryBtn = document.getElementById('toggleGalleryBtn');
    if (toggleGalleryBtn) {
        let expanded = false;
        toggleGalleryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const extras = document.querySelectorAll('.extra-gallery-image');
            if (!extras.length) return;
            expanded = !expanded;
            extras.forEach(el => {
                if (expanded) el.classList.remove('d-none'); else el.classList.add('d-none');
            });
            this.textContent = expanded ? ('Show less') : ('View all images (<?php echo isset($totalGallery) ? $totalGallery : count($galleryImages); ?>)');
        });
    }

    // Accordion toggle function
    window.toggleAccordion = function(headerElement) {
        const section = headerElement.closest('.accordion-section');
        const isOpen = section.classList.contains('open');
        
        // Close all other sections
        document.querySelectorAll('.accordion-section').forEach(el => {
            if (el !== section) {
                el.classList.remove('open');
            }
        });
        
        // Toggle current section
        section.classList.toggle('open');
    };
});
</script>

