<?php
/**
 * Add New Vendor
 * Admin panel page to create a new vendor
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'Add New Vendor';
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
                <h1 class="h3">Add New Vendor</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Vendor Information</h6>
                    </div>
                    <div class="card-body">
                        <form id="addVendorForm" enctype="multipart/form-data">
                            <!-- Personal Information -->
                            <h6 class="mb-3 text-primary">Personal Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="owner_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" pattern="\d{10}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="passwordField" name="password" minlength="8" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordVisibility" title="Show/Hide password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-info" type="button" id="generatePasswordBtn" title="Generate password">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Store Information -->
                            <h6 class="mb-3 text-primary mt-4">Store Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Store Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="store_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Store Category <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="store_category" placeholder="e.g., Grocery, Restaurant, Clothing" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Store Description</label>
                                    <textarea class="form-control" name="store_description" rows="3" placeholder="Brief description of the store"></textarea>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Store Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="store_address" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <select class="form-select" name="city" required>
                                        <option value="">Select city</option>
                                        <option>Kathmandu</option>
                                        <option>Lalitpur</option>
                                        <option>Bhaktapur</option>
                                        <option>Pokhara</option>
                                        <option>Banepa</option>
                                        <option>Dhulikhel</option>
                                        <option>Biratnagar</option>
                                        <option>Birgunj</option>
                                        <option>Butwal</option>
                                        <option>Hetauda</option>
                                        <option>Mahendranagar</option>
                                        <option>Dhangadhi</option>
                                        <option>Janakpur</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="state" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select class="form-select" name="country" required>
                                        <option value="Nepal">Nepal</option>
                                        <option value="India">India</option>
                                        <option value="China">China</option>
                                        <option value="United States">United States</option>
                                        <option value="Japan">Japan</option>
                                        <option value="United Kingdom">United Kingdom</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Canada">Canada</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Postal Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="postal_code" required>
                                </div>
                            </div>

                            <!-- Business Details -->
                            <h6 class="mb-3 text-primary mt-4">Business Details</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Registration Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="registration_number" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tax ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="tax_id" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Bank Information -->
                            <h6 class="mb-3 text-primary mt-4">Bank Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                    <select class="form-select" id="bankNameSelect" name="bank_name" required>
                                        <option value="">Select bank</option>
                                        <option>Nabil Bank</option>
                                        <option>Nepal SBI Bank</option>
                                        <option>Himalayan Bank</option>
                                        <option>Standard Chartered Bank Nepal</option>
                                        <option>Everest Bank</option>
                                        <option>Nepal Investment Bank</option>
                                        <option>NIC ASIA Bank</option>
                                        <option>NMB Bank</option>
                                        <option>Global IME Bank</option>
                                        <option>Prabhu Bank</option>
                                        <option>Sanima Bank</option>
                                        <option>Siddhartha Bank</option>
                                        <option>Citizens Bank</option>
                                        <option>Kumari Bank</option>
                                        <option>Laxmi Bank</option>
                                        <option>Machhapuchchhre Bank</option>
                                        <option>Rastriya Banijya Bank</option>
                                        <option>Nepal Bank Limited</option>
                                        <option>Agricultural Development Bank</option>
                                        <option>Prabhu Bank</option>
                                        <option>Prime Commercial Bank</option>
                                        <option>Others</option>
                                    </select>
                                    <input type="text" id="bankNameOther" name="bank_name_other" class="form-control mt-2" placeholder="Enter bank name" style="display:none;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="account_number" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Wallet / Payment Provider</label>
                                    <select class="form-select" name="wallet_name">
                                        <option value="">Select wallet</option>
                                        <option>eSewa</option>
                                        <option>Khalti</option>
                                        <option>IME Pay</option>
                                        <option>PrabhuPAY</option>
                                        <option>Fonepay</option>
                                        <option>ConnectIPS</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="account_holder_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IFSC Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ifsc_code" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Create Vendor
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
                            <i class="bi bi-image"></i> Store Images (Optional)
                        </h6>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-sm btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addStoreImagesModal">
                            <i class="bi bi-plus"></i> Add Store Images
                        </button>
                        <button type="button" class="btn btn-sm btn-success w-100" data-bs-toggle="modal" data-bs-target="#addGalleryImagesModal">
                            <i class="bi bi-plus"></i> Add Gallery Images
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Image uploads are optional during registration.
                        </small>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Tips</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-lightbulb"></i>
                            <strong>Required Fields:</strong> All fields marked with * are mandatory.
                        </div>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Password Requirements</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Minimum 8 characters</li>
                            <li>Mix of uppercase and lowercase</li>
                            <li>Include numbers</li>
                            <li>Include special characters</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <!-- Add Store Images Modal -->
    <div class="modal fade" id="addStoreImagesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Store Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addStoreImagesForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> All image uploads are optional.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-image"></i> Profile Image
                            </label>
                            <input type="file" class="form-control" name="profile_image" accept="image/*" id="profileImageInput">
                            <small class="text-muted">Store profile/avatar image (JPG, PNG - max 5MB)</small>
                            <div id="profileImagePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-bookmark"></i> Logo Image
                            </label>
                            <input type="file" class="form-control" name="logo_image" accept="image/*" id="logoImageInput">
                            <small class="text-muted">Store logo/brand mark (JPG, PNG - max 5MB)</small>
                            <div id="logoImagePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-card-image"></i> Banner Image
                            </label>
                            <input type="file" class="form-control" name="banner_image" accept="image/*" id="bannerImageInput">
                            <small class="text-muted">Store banner/cover (JPG, PNG - max 5MB). Recommended: 1200x400px</small>
                            <div id="bannerImagePreview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
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
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> You can upload up to 10 gallery images (optional).
                        </div>
                        
                        <div id="galleryImageInputs">
                            <div class="gallery-input-group mb-3 p-3 bg-light border rounded">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="form-label">Select Image 1</label>
                                        <input type="file" class="form-control gallery-image-input" name="gallery_images[]" accept="image/*">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Image Type</label>
                                        <select class="form-select" name="image_types[]">
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
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate a secure password
        function generatePassword() {
            const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lowercase = 'abcdefghijklmnopqrstuvwxyz';
            const numbers = '0123456789';
            const special = '!@#$%^&*_-+=';
            
            const allChars = uppercase + lowercase + numbers + special;
            
            let password = '';
            password += uppercase.charAt(Math.floor(Math.random() * uppercase.length));
            password += lowercase.charAt(Math.floor(Math.random() * lowercase.length));
            password += numbers.charAt(Math.floor(Math.random() * numbers.length));
            password += special.charAt(Math.floor(Math.random() * special.length));
            
            // Fill remaining characters randomly
            for (let i = password.length; i < 12; i++) {
                password += allChars.charAt(Math.floor(Math.random() * allChars.length));
            }
            
            // Shuffle the password
            return password.split('').sort(() => Math.random() - 0.5).join('');
        }

        // Initialize password on page load
        window.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('passwordField');
            const generatedPassword = generatePassword();
            passwordField.value = generatedPassword;
        });

        // Toggle password visibility
        document.getElementById('togglePasswordVisibility').addEventListener('click', function() {
            const passwordField = document.getElementById('passwordField');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Generate new password
        document.getElementById('generatePasswordBtn').addEventListener('click', function() {
            const passwordField = document.getElementById('passwordField');
            const newPassword = generatePassword();
            passwordField.value = newPassword;
            // Show the password briefly to confirm generation
            passwordField.type = 'text';
            const icon = document.getElementById('togglePasswordVisibility').querySelector('i');
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
            
            // Auto-hide after 2 seconds
            setTimeout(() => {
                passwordField.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }, 2000);
        });

        // Bank select - show other input
        document.addEventListener('DOMContentLoaded', function() {
            var bankSelect = document.getElementById('bankNameSelect');
            var bankOther = document.getElementById('bankNameOther');
            if (bankSelect) {
                bankSelect.addEventListener('change', function() {
                    if (this.value === 'Others') {
                        bankOther.style.display = 'block';
                        bankOther.required = true;
                    } else {
                        bankOther.style.display = 'none';
                        bankOther.required = false;
                    }
                });
            }
        });

        // Image preview function
        function previewImage(input, previewElementId) {
            const previewElement = document.getElementById(previewElementId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.innerHTML = `
                        <div style="position: relative; display: inline-block;">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px; border-radius: 4px; object-fit: cover;">
                            <small class="d-block mt-1 text-success"><i class="bi bi-check-circle"></i> Image selected</small>
                        </div>
                    `;
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                previewElement.innerHTML = '';
            }
        }

        // Setup image preview listeners
        document.getElementById('profileImageInput')?.addEventListener('change', function() {
            previewImage(this, 'profileImagePreview');
        });
        document.getElementById('logoImageInput')?.addEventListener('change', function() {
            previewImage(this, 'logoImagePreview');
        });
        document.getElementById('bannerImageInput')?.addEventListener('change', function() {
            previewImage(this, 'bannerImagePreview');
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
                        <select class="form-select" name="image_types[]">
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

        document.getElementById('addVendorForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Collect form data including images
            const formData = new FormData(this);
            
            // Add store images if selected
            const profileImage = document.getElementById('profileImageInput');
            const logoImage = document.getElementById('logoImageInput');
            const bannerImage = document.getElementById('bannerImageInput');
            
            if (profileImage.files.length > 0) {
                formData.append('profile_image', profileImage.files[0]);
            }
            if (logoImage.files.length > 0) {
                formData.append('logo_image', logoImage.files[0]);
            }
            if (bannerImage.files.length > 0) {
                formData.append('banner_image', bannerImage.files[0]);
            }
            
            // Add gallery images if selected
            const galleryInputs = document.querySelectorAll('.gallery-image-input');
            const galleryImages = [];
            const galleryTypes = [];
            const galleryDescriptions = [];
            
            galleryInputs.forEach((input, index) => {
                if (input.files.length > 0) {
                    galleryImages.push(input.files[0]);
                    galleryTypes.push(document.querySelectorAll('select[name="image_types[]"]')[index]?.value || 'gallery');
                    galleryDescriptions.push(document.querySelectorAll('input[name="image_descriptions[]"]')[index]?.value || '');
                }
            });
            
            galleryImages.forEach((image) => {
                formData.append('gallery_images[]', image);
            });
            galleryTypes.forEach((type) => {
                formData.append('image_types[]', type);
            });
            galleryDescriptions.forEach((desc) => {
                formData.append('image_descriptions[]', desc);
            });
            
            try {
                const response = await fetch('../ajax/vendor-add.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const vendorId = data.vendor_id;
                    alert('Vendor created successfully!' + (data.upload_messages && data.upload_messages.length ? '\n\n' + data.upload_messages.join('\n') : ''));
                    window.location.href = '../vendors/edit.php?id=' + vendorId;
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while creating the vendor');
            }
        });
    </script>
</body>
</html>
