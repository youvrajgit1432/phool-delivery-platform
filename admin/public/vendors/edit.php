<?php
/**
 * Edit Vendor
 * Admin panel page to edit vendor details
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

// Fetch vendor details
try {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fetch linked master products (read-only display)
try {
    $linkedStmt = $db->prepare(
        "SELECT p.id, p.name_en FROM vendor_product_map vpm JOIN products p ON p.id = vpm.product_id WHERE vpm.vendor_id = ? AND vpm.status = 'active' ORDER BY p.name_en ASC"
    );
    $linkedStmt->execute([$vendor_id]);
    $linkedMasterProducts = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $le) {
    $linkedMasterProducts = [];
}

$page_title = 'Edit Vendor - ' . htmlspecialchars($vendor['store_name']);
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
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <a href="../vendors.php" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Back to Vendors
                </a>
                <h1 class="h3">Edit Vendor: <?php echo htmlspecialchars($vendor['store_name']); ?></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Vendor Information</h6>
                    </div>
                    <div class="card-body">
                        <form id="editVendorForm">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                            
                            <!-- Personal Information -->
                            <h6 class="mb-3 text-primary">Personal Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($vendor['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($vendor['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" pattern="\d{10}" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Password <small class="text-muted">(auto-generated — edit to override)</small></label>
                                    <div class="input-group mb-2">
                                        <input type="password" id="vendorPassword" class="form-control" name="password" minlength="8" placeholder="Auto-generated password">
                                        <button type="button" class="btn btn-outline-secondary" id="generatePasswordBtn" title="Regenerate password">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePasswordBtn" title="Show/Hide password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Password is auto-generated. Leave empty to keep the existing password.</small>
                                </div>
                            </div>

                            <!-- Store Information -->
                            <h6 class="mb-3 text-primary mt-4">Store Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Store Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Store Category <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="store_category" value="<?php echo htmlspecialchars($vendor['store_category'] ?? ''); ?>" placeholder="e.g., Grocery, Restaurant, Clothing" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Store Description</label>
                                    <textarea class="form-control" name="store_description" rows="3" placeholder="Brief description of the store"><?php echo htmlspecialchars($vendor['store_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Business Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="business_address" value="<?php echo htmlspecialchars($vendor['business_address'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <select class="form-select" name="city" required>
                                        <option value="">Select city</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Kathmandu' ? 'selected' : ''; ?>>Kathmandu</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Lalitpur' ? 'selected' : ''; ?>>Lalitpur</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Bhaktapur' ? 'selected' : ''; ?>>Bhaktapur</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Pokhara' ? 'selected' : ''; ?>>Pokhara</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Banepa' ? 'selected' : ''; ?>>Banepa</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Dhulikhel' ? 'selected' : ''; ?>>Dhulikhel</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Biratnagar' ? 'selected' : ''; ?>>Biratnagar</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Birgunj' ? 'selected' : ''; ?>>Birgunj</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Butwal' ? 'selected' : ''; ?>>Butwal</option>
                                        <option <?php echo ($vendor['city'] ?? '') === 'Hetauda' ? 'selected' : ''; ?>>Hetauda</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($vendor['state'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select class="form-select" name="country" required>
                                        <option value="Nepal" <?php echo ($vendor['country'] ?? '') === 'Nepal' ? 'selected' : ''; ?>>Nepal</option>
                                        <option value="India" <?php echo ($vendor['country'] ?? '') === 'India' ? 'selected' : ''; ?>>India</option>
                                        <option value="China" <?php echo ($vendor['country'] ?? '') === 'China' ? 'selected' : ''; ?>>China</option>
                                        <option value="United States" <?php echo ($vendor['country'] ?? '') === 'United States' ? 'selected' : ''; ?>>United States</option>
                                        <option value="Japan" <?php echo ($vendor['country'] ?? '') === 'Japan' ? 'selected' : ''; ?>>Japan</option>
                                        <option value="United Kingdom" <?php echo ($vendor['country'] ?? '') === 'United Kingdom' ? 'selected' : ''; ?>>United Kingdom</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Postal Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <!-- Business Details -->
                            <h6 class="mb-3 text-primary mt-4">Business Details</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Registration Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($vendor['registration_number'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tax ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="tax_id" value="<?php echo htmlspecialchars($vendor['tax_id'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="pending" <?php echo ($vendor['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="active" <?php echo ($vendor['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($vendor['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo ($vendor['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Bank Information -->
                            <h6 class="mb-3 text-primary mt-4">Bank Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                    <select class="form-select" id="bankNameSelectEdit" name="bank_name" required>
                                        <option value="">Select bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Nabil Bank' ? 'selected' : ''; ?>>Nabil Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Nepal SBI Bank' ? 'selected' : ''; ?>>Nepal SBI Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Himalayan Bank' ? 'selected' : ''; ?>>Himalayan Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Standard Chartered Bank Nepal' ? 'selected' : ''; ?>>Standard Chartered Bank Nepal</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Everest Bank' ? 'selected' : ''; ?>>Everest Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'NIC ASIA Bank' ? 'selected' : ''; ?>>NIC ASIA Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'NMB Bank' ? 'selected' : ''; ?>>NMB Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Global IME Bank' ? 'selected' : ''; ?>>Global IME Bank</option>
                                        <option <?php echo ($vendor['bank_name'] ?? '') === 'Prabhu Bank' ? 'selected' : ''; ?>>Prabhu Bank</option>
                                        <option>Others</option>
                                    </select>
                                    <input type="text" id="bankNameOtherEdit" name="bank_name_other" class="form-control mt-2" placeholder="Enter bank name" style="display:none;" value="<?php echo htmlspecialchars($vendor['bank_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($vendor['account_number'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="account_holder" value="<?php echo htmlspecialchars($vendor['account_holder'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IFSC Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ifsc_code" value="<?php echo htmlspecialchars($vendor['ifsc_code'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Update Vendor
                                </button>
                                <a href="../vendors.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Store Images Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-image"></i> Store Images
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 text-center">
                            <?php if (!empty($vendor['profile_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($vendor['profile_image_url']); ?>" alt="Profile" style="max-width: 100px; max-height: 100px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100px; height: 100px; background: #f0f0f0; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#editStoreImagesModal">
                            <i class="bi bi-pencil"></i> Manage Images
                        </button>
                    </div>
                </div>

                <!-- Gallery Images Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-images"></i> Gallery Images
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $galleryImages = $db->prepare("SELECT id, image_url FROM vendor_gallery_images WHERE vendor_id = ? LIMIT 4");
                        $galleryImages->execute([$vendor['id']]);
                        $gallery = $galleryImages->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($gallery)): ?>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px;">
                                <?php foreach (array_slice($gallery, 0, 4) as $img): ?>
                                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="Gallery" style="width: 100%; height: 80px; object-fit: cover; border-radius: 4px;">
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="background: #f0f0f0; padding: 20px; border-radius: 4px; text-align: center;">
                                <i class="bi bi-images text-muted" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                                <small class="text-muted">No gallery images yet</small>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-success w-100 mt-2" data-bs-toggle="modal" data-bs-target="#addGalleryImagesModal">
                            <i class="bi bi-plus"></i> Add Gallery
                        </button>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <a href="../vendor-products/manage.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-box"></i> Manage Products
                        </a>
                        <a href="../vendor-orders/view.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-outline-info">
                            <i class="bi bi-bag"></i> View Orders
                        </a>
                        <a href="../vendor-payouts/view.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-outline-success">
                            <i class="bi bi-cash-coin"></i> Manage Payouts
                        </a>
                        <a href="../vendor-reviews/view.php?vendor_id=<?php echo $vendor['id']; ?>" class="btn btn-outline-warning">
                            <i class="bi bi-star"></i> View Reviews
                        </a>
                    </div>
                </div>

                <!-- Linked Master Products (read-only) -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-link-45deg"></i> Linked Master Products</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($linkedMasterProducts)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($linkedMasterProducts as $mp): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($mp['name_en']); ?></span>
                                        <span class="badge bg-light text-dark">ID: <?php echo $mp['id']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="small text-muted mt-2">These are master catalog products this vendor has linked. Names are read-only here.</div>
                        <?php else: ?>
                            <div class="text-muted">No linked master products.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Vendor Stats -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Vendor Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Joined:</strong> <?php echo date('M d, Y', strtotime($vendor['created_at'])); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($vendor['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

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
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                        <input type="hidden" name="action" value="upload_store_images">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-person-circle"></i> Profile Picture
                            </label>
                            <input type="file" class="form-control" name="profile_image" accept="image/*">
                            <small class="text-muted">Store profile/avatar image (JPG, PNG - max 5MB)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-image"></i> Store Logo
                            </label>
                            <input type="file" class="form-control" name="logo_image" accept="image/*">
                            <small class="text-muted">Store logo/brand mark (JPG, PNG - max 5MB)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-images"></i> Banner Image
                            </label>
                            <input type="file" class="form-control" name="banner_image" accept="image/*">
                            <small class="text-muted">Store banner/cover (JPG, PNG - max 5MB). Recommended: 1200x400px</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                        <input type="hidden" name="action" value="upload_gallery_images">
                        
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> You can upload up to 10 gallery images total.
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
                                    <input type="text" class="form-control" name="image_descriptions[]" placeholder="e.g., Store interior">
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addMoreGalleryInputs">
                            <i class="bi bi-plus"></i> Add Another Image
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Gallery</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Vendor Form
        document.getElementById('editVendorForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../ajax/vendor-update.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Vendor updated successfully!');
                    window.location.href = '../vendors.php';
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the vendor');
            }
        });

        // Edit Store Images Form
        document.getElementById('editStoreImagesForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../ajax/vendor-image-upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Store images updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        });

        // Password generation helpers
        function generatePassword(length = 12) {
            const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lower = 'abcdefghijklmnopqrstuvwxyz';
            const digits = '0123456789';
            const symbols = '!@#$%&*()-_=+[]{}<>?';
            const all = upper + lower + digits + symbols;
            let pwd = '';
            // ensure at least one of each type
            pwd += upper[Math.floor(Math.random() * upper.length)];
            pwd += lower[Math.floor(Math.random() * lower.length)];
            pwd += digits[Math.floor(Math.random() * digits.length)];
            pwd += symbols[Math.floor(Math.random() * symbols.length)];
            for (let i = pwd.length; i < length; i++) {
                pwd += all[Math.floor(Math.random() * all.length)];
            }
            // shuffle
            return pwd.split('').sort(() => 0.5 - Math.random()).join('');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const pwdInput = document.getElementById('vendorPassword');
            const genBtn = document.getElementById('generatePasswordBtn');
            const toggleBtn = document.getElementById('togglePasswordBtn');

            // Initialize with a generated password (but leave blank on submit will keep existing)
            if (pwdInput && !pwdInput.value) {
                pwdInput.value = generatePassword(12);
            }

            if (genBtn) genBtn.addEventListener('click', function() {
                if (pwdInput) pwdInput.value = generatePassword(12);
            });

            if (toggleBtn) toggleBtn.addEventListener('click', function() {
                if (!pwdInput) return;
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    pwdInput.type = 'password';
                    toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
                }
            });
        });

        // Add Gallery Images Form
        document.getElementById('addGalleryImagesForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInputs = this.querySelectorAll('.gallery-image-input');
            let hasFiles = false;
            fileInputs.forEach(input => {
                if (input.files.length > 0) hasFiles = true;
            });
            
            if (!hasFiles) {
                alert('Please select at least one image');
                return;
            }
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../ajax/vendor-image-upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Gallery images added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        });

        // Add More Gallery Inputs
        document.getElementById('addMoreGalleryInputs').addEventListener('click', function() {
            const container = document.getElementById('galleryImageInputs');
            const currentCount = container.querySelectorAll('.gallery-input-group').length;
            
            if (currentCount >= 10) {
                alert('Maximum 10 images allowed');
                return;
            }
            
            const newGroup = document.createElement('div');
            newGroup.className = 'gallery-input-group mb-3 p-3 bg-light border rounded';
            newGroup.innerHTML = `
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
                    <input type="text" class="form-control" name="image_descriptions[]" placeholder="e.g., Store interior">
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-gallery-input">
                    <i class="bi bi-trash"></i> Remove
                </button>
            `;
            
            container.appendChild(newGroup);
            
            newGroup.querySelector('.remove-gallery-input').addEventListener('click', function() {
                newGroup.remove();
            });
        });

        // Bank select other toggle (edit form)
        document.addEventListener('DOMContentLoaded', function() {
            var bankSel = document.getElementById('bankNameSelectEdit');
            var bankOther = document.getElementById('bankNameOtherEdit');
            if (bankSel) {
                function toggle() {
                    if (bankSel.value === 'Others') {
                        bankOther.style.display = 'block';
                        bankOther.required = true;
                    } else {
                        bankOther.style.display = 'none';
                        bankOther.required = false;
                    }
                }
                bankSel.addEventListener('change', toggle);
                // run once to set initial state if vendor had custom bank
                if (bankOther && bankOther.value && bankSel.querySelector('option[selected]') == null) {
                    // if vendor bank is not in list, show other and set value
                    bankSel.value = 'Others';
                    bankOther.style.display = 'block';
                    bankOther.required = true;
                } else {
                    toggle();
                }
            }
        });
    </script>
</body>
</html>
