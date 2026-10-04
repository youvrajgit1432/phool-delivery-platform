<?php 
/**
 * Delivery Rider Account Dashboard
 * Professional dashboard matching vendor panel design
 */
require_once dirname(__FILE__, 3) . '/helpers/url.php';
require_once dirname(__FILE__, 3) . '/helpers/formatter.php';

if (!isset($rider)) {
    header('Location: ' . app_url('/logout'));
    exit;
}

// Nepal Provinces
$provinces = [
    'Koshi Province',
    'Madhesh Province',
    'Bagmati Province',
    'Gandaki Province',
    'Lumbini Province',
    'Karnali Province',
    'Sudurpashchim Province'
];

// Nepal Districts
$districts = [
    'Bhojpur', 'Dhankuta', 'Ilam', 'Jhapa', 'Khotang', 'Morang', 'Okhaldhunga', 'Panchthar', 'Sankhuwasabha', 'Solukhumbu', 'Sunsari', 'Taplejung', 'Terhathum', 'Udayapur',
    'Bara', 'Dhanusha', 'Mahottari', 'Parsa', 'Rautahat', 'Saptari', 'Sarlahi', 'Siraha',
    'Bhaktapur', 'Chitwan', 'Dhading', 'Dolakha', 'Kathmandu', 'Kavrepalanchok', 'Lalitpur', 'Makwanpur', 'Nuwakot', 'Ramechhap', 'Rasuwa', 'Sindhuli', 'Sindhupalchok',
    'Baglung', 'Gorkha', 'Kaski', 'Lamjung', 'Manang', 'Mustang', 'Myagdi', 'Nawalpur', 'Parbat', 'Syangja', 'Tanahun',
    'Arghakhanchi', 'Banke', 'Bardiya', 'Dang', 'Gulmi', 'Kapilvastu', 'Parasi', 'Palpa', 'Pyuthan', 'Rolpa', 'Rupandehi',
    'Dailekh', 'Dolpa', 'Humla', 'Jajarkot', 'Jumla', 'Kalikot', 'Mugu', 'Rukum West', 'Salyan', 'Surkhet',
    'Achham', 'Baitadi', 'Bajhang', 'Bajura', 'Dadeldhura', 'Darchula', 'Doti', 'Kailali', 'Kanchanpur', 'Rukum East'
];

// Nepal Cities (Major Urban Centers)
$cities = [
    'Kathmandu',
    'Pokhara',
    'Lalitpur',
    'Bharatpur',
    'Biratnagar',
    'Birgunj',
    'Dharan',
    'Butwal',
    'Hetauda',
    'Janakpur',
    'Itahari',
    'Bhaktapur',
    'Nepalgunj',
    'Dhangadhi',
    'Tikapur'
];

// Nepal Commercial Banks (NRB Licensed)
$banks = [
    'Nepal Bank Limited',
    'Rastriya Banijya Bank Limited',
    'Nabil Bank Limited',
    'Standard Chartered Bank Nepal Limited',
    'Himalayan Bank Limited',
    'Everest Bank Limited',
    'Nepal Investment Mega Bank Limited',
    'NIC Asia Bank Limited',
    'Global IME Bank Limited',
    'Machhapuchchhre Bank Limited',
    'Citizens Bank International Limited',
    'Sanima Bank Limited',
    'Siddhartha Bank Limited',
    'Prabhu Bank Limited',
    'Sunrise Bank Limited',
    'Laxmi Sunrise Bank Limited',
    'NMB Bank Limited',
    'Kumari Bank Limited',
    'Civil Bank Limited',
    'Prime Commercial Bank Limited'
];

sort($banks);
?>


<div class="account-dashboard">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <div class="header-avatar" style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; overflow: hidden; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                        <?php if ($rider->profile_image_url): ?>
                            <img src="<?php echo htmlspecialchars($rider->profile_image_url); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="page-title" style="margin: 0; padding: 0;">Account Dashboard</h1>
                    </div>
                </div>
                <p class="text-muted">Manage your profile and delivery information</p>
            </div>
            <div class="col-auto d-flex align-items-center gap-2">
                <a href="<?php echo app_url('/orders'); ?>" class="btn btn-sm btn-outline-primary">View Orders</a>
                <a href="<?php echo app_url('/earnings'); ?>" class="btn btn-sm btn-outline-secondary">View Earnings</a>
            </div>
            <div class="col-auto">
                <span class="badge badge-lg <?php echo getStatusBadgeClass($rider->status); ?>">
                    <?php echo ucfirst($rider->status); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Rider Profile Overview - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-id-card"></i>
                <h6>Rider Profile</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Left Column: Rider Info -->
                <div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Full Name</label>
                        <h6><?php echo htmlspecialchars(($rider->first_name ?? '') . ' ' . ($rider->last_name ?? '')); ?></h6>
                    </div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Email Address</label>
                        <h6><?php echo htmlspecialchars($rider->email ?? 'N/A'); ?></h6>
                    </div>
                    <div class="accordion-body-item" style="margin-bottom: 15px;">
                        <label>Phone Number</label>
                        <h6><?php echo htmlspecialchars($rider->phone ?? 'N/A'); ?></h6>
                    </div>
                    <div class="accordion-body-item">
                        <label>Registration Status</label>
                        <h6>
                            <span class="badge <?php echo getStatusBadgeClass($rider->status); ?>">
                                <?php echo ucfirst($rider->status); ?>
                            </span>
                        </h6>
                    </div>
                </div>
                
                <!-- Right Column: Profile Picture -->
                <div>
                    <div style="display: flex; flex-direction: column; gap: 12px; align-items: center;">
                        <div class="avatar-container position-relative" style="width: 120px; height: 120px;">
                            <div class="avatar-circle" id="profileAvatarDisplay" style="width: 120px; height: 120px;">
                                <?php if ($rider->profile_image_url): ?>
                                    <img src="<?php echo htmlspecialchars($rider->profile_image_url); ?>" alt="Profile Picture" class="avatar-img">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-sm btn-primary position-absolute avatar-edit-btn" data-bs-toggle="modal" data-bs-target="#editProfilePictureModal" title="Edit Profile Picture" style="padding: 8px;">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <p class="text-muted" style="margin: 0; text-align: center;"><?php echo htmlspecialchars($rider->first_name ?? 'Rider'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Stats - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-chart-line"></i>
                <h6>Performance Stats</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Average Rating</h6>
                            <h3 class="text-warning mb-2">
                                <?php echo number_format($rider->average_rating ?? 0, 1); ?>
                                <i class="fas fa-star"></i>
                            </h3>
                            <small class="text-muted"><?php echo intval($rider->total_reviews ?? 0); ?> reviews</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Total Deliveries</h6>
                            <h3 class="text-success"><?php echo intval($rider->total_deliveries ?? 0); ?></h3>
                            <a href="<?php echo app_url('/orders?status=completed'); ?>" class="btn btn-sm btn-link">View Orders</a>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Total Earnings</h6>
                            <h3 class="text-primary">₹<?php echo number_format($rider->total_earnings ?? 0, 0); ?></h3>
                            <a href="<?php echo app_url('/earnings'); ?>" class="btn btn-sm btn-link">View Details</a>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Account Status</h6>
                            <h3>
                                <i class="fas fa-check-circle <?php echo ($rider->status === 'active') ? 'text-success' : 'text-warning'; ?>"></i>
                            </h3>
                            <small><?php echo ucfirst($rider->status); ?></small>
                        </div>
                    </div>
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
                    <h6><?php echo htmlspecialchars($rider->first_name ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Last Name</label>
                    <h6><?php echo htmlspecialchars($rider->last_name ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Email Address</label>
                    <h6><?php echo htmlspecialchars($rider->email ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Phone Number</label>
                    <h6><?php echo htmlspecialchars($rider->phone ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPersonalModal">
                    <i class="fas fa-edit me-1"></i>Edit Information
                </button>
            </div>
        </div>
    </div>

    <!-- Address Information - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-map-marker-alt"></i>
                <h6>Address Information</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Home Address</label>
                    <h6><?php echo htmlspecialchars($rider->home_address ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>City</label>
                    <h6><?php echo htmlspecialchars($rider->city ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Province</label>
                    <h6><?php echo htmlspecialchars($rider->state ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>District</label>
                    <h6><?php echo htmlspecialchars($rider->district ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Postal Code</label>
                    <h6><?php echo htmlspecialchars($rider->postal_code ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editAddressModal">
                    <i class="fas fa-edit me-1"></i>Edit Address
                </button>
            </div>
        </div>
    </div>

    <!-- Banking & Payment Details - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-university"></i>
                <h6>Banking & Payment Details</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Bank Name</label>
                    <h6><?php echo htmlspecialchars($rider->bank_name ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Account Holder Name</label>
                    <h6><?php echo htmlspecialchars($rider->account_holder_name ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Account Number</label>
                    <h6><i class="fas fa-lock me-1"></i>****<?php echo substr($rider->account_number ?? '', -4); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editBankingModal">
                    <i class="fas fa-edit me-1"></i>Edit Banking Details
                </button>
            </div>
        </div>
    </div>

    <!-- Documents & Licenses - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-id-card"></i>
                <h6>Documents & Licenses</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>License Number</label>
                    <h6><?php echo htmlspecialchars($rider->license_number ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>License Expiry</label>
                    <h6><?php echo formatDate($rider->license_expiry); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Vehicle Type</label>
                    <h6>
                        <?php 
                        $vehicleTypes = ['bike' => 'Bike', 'scooter' => 'Scooter', 'car' => 'Car', 'van' => 'Van'];
                        echo htmlspecialchars($vehicleTypes[$rider->vehicle_type] ?? 'N/A');
                        ?>
                    </h6>
                </div>
                <div class="accordion-body-item">
                    <label>Vehicle Registration</label>
                    <h6><?php echo htmlspecialchars($rider->vehicle_number ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editDocumentsModal">
                    <i class="fas fa-edit me-1"></i>Edit Documents
                </button>
            </div>
        </div>
    </div>

    <!-- Emergency Contact - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-phone-alt"></i>
                <h6>Emergency Contact</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Contact Name</label>
                    <h6><?php echo htmlspecialchars($rider->emergency_contact_name ?? 'N/A'); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Contact Phone</label>
                    <h6><?php echo htmlspecialchars($rider->emergency_contact_phone ?? 'N/A'); ?></h6>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#editEmergencyModal">
                    <i class="fas fa-edit me-1"></i>Edit Contact
                </button>
            </div>
        </div>
    </div>

    <!-- Account Activity - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-clock"></i>
                <h6>Account Activity</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div class="accordion-body-content">
                <div class="accordion-body-item">
                    <label>Account Created</label>
                    <h6><?php echo formatDate($rider->created_at); ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Last Login</label>
                    <h6><?php echo $rider->last_login ? formatDate($rider->last_login) : 'Never'; ?></h6>
                </div>
                <div class="accordion-body-item">
                    <label>Account Status</label>
                    <h6>
                        <span class="badge <?php echo getStatusBadgeClass($rider->status); ?>">
                            <?php echo ucfirst($rider->status); ?>
                        </span>
                    </h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Settings - Accordion -->
    <div class="accordion-section">
        <div class="accordion-header" onclick="toggleAccordion(this)">
            <div class="accordion-header-title">
                <i class="fas fa-cog"></i>
                <h6>Account Settings</h6>
            </div>
            <div class="accordion-toggle">›</div>
        </div>
        <div class="accordion-body">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fas fa-key me-2"></i>Change Password
                </button>
                <a href="<?php echo app_url('/settings'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-cogs me-2"></i>More Settings
                </a>
                <a href="<?php echo app_url('/logout'); ?>" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
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
            <form id="editPersonalForm" class="profile-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($rider->first_name ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($rider->last_name ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($rider->email ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($rider->phone ?? ''); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Address Modal -->
<div class="modal fade" id="editAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Address Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAddressForm" class="profile-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Home Address</label>
                        <input type="text" class="form-control" name="home_address" value="<?php echo htmlspecialchars($rider->home_address ?? ''); ?>" placeholder="Street address">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Province</label>
                        <select class="form-select" name="state" required>
                            <option value="">Select province</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo htmlspecialchars($province); ?>" <?php echo ($rider->state === $province) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($province); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">District</label>
                        <select class="form-select" name="district" required>
                            <option value="">Select district</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district); ?>" <?php echo (isset($rider->district) && $rider->district === $district) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">City</label>
                        <select class="form-select" name="city" required>
                            <option value="">Select city</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo ($rider->city === $city) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($rider->postal_code ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Banking Modal -->
<div class="modal fade" id="editBankingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Banking & Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBankingForm" class="profile-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Bank Name</label>
                        <select class="form-select" name="bank_name">
                            <option value="">Select a bank</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo htmlspecialchars($bank); ?>" <?php echo ($rider->bank_name === $bank) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">NRB Licensed Commercial Banks</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($rider->account_number ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Holder Name</label>
                        <input type="text" class="form-control" name="account_holder_name" value="<?php echo htmlspecialchars($rider->account_holder_name ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Documents Modal -->
<div class="modal fade" id="editDocumentsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Documents & Licenses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDocumentsForm" class="profile-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">License Number</label>
                        <input type="text" class="form-control" name="license_number" value="<?php echo htmlspecialchars($rider->license_number ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">License Expiry</label>
                        <input type="date" class="form-control" name="license_expiry" value="<?php echo $rider->license_expiry ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Type</label>
                        <select class="form-select" name="vehicle_type">
                            <option value="">Select vehicle type</option>
                            <option value="bike" <?php echo ($rider->vehicle_type === 'bike') ? 'selected' : ''; ?>>Bike</option>
                            <option value="scooter" <?php echo ($rider->vehicle_type === 'scooter') ? 'selected' : ''; ?>>Scooter</option>
                            <option value="car" <?php echo ($rider->vehicle_type === 'car') ? 'selected' : ''; ?>>Car</option>
                            <option value="van" <?php echo ($rider->vehicle_type === 'van') ? 'selected' : ''; ?>>Van</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Registration Number</label>
                        <input type="text" class="form-control" name="vehicle_number" value="<?php echo htmlspecialchars($rider->vehicle_number ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Emergency Contact Modal -->
<div class="modal fade" id="editEmergencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Emergency Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEmergencyForm" class="profile-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Contact Name</label>
                        <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($rider->emergency_contact_name ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" name="emergency_contact_phone" value="<?php echo htmlspecialchars($rider->emergency_contact_phone ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changePasswordForm" class="profile-form">
                <input type="hidden" name="password_update" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-key me-2"></i>Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Profile Picture Modal -->
<div class="modal fade" id="editProfilePictureModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProfilePictureForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="profile-picture-preview mb-3" style="text-align: center;">
                            <div class="avatar-circle" style="width: 120px; height: 120px; margin: 0 auto; font-size: 60px;" id="previewAvatar">
                                <?php if ($rider->profile_image_url): ?>
                                    <img src="<?php echo htmlspecialchars($rider->profile_image_url); ?>" alt="Preview" class="avatar-img" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Profile Picture</label>
                        <input type="file" class="form-control" id="profilePictureInput" name="profile_picture" accept="image/*" required>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>Accepted formats: JPG, PNG, GIF (Max 5MB)
                        </small>
                    </div>
                    <div class="alert alert-info" style="display: none;" id="uploadMessage"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Upload Picture</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.account-dashboard {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f8f9fa;
    min-height: calc(100vh - 60px);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Modern Page Header */
.page-header {
    padding-bottom: 24px;
    border-bottom: 1px solid #e8e8e8;
    margin-bottom: 32px;
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
    letter-spacing: -0.02em;
}

.page-header .text-muted {
    color: #6c757d;
    font-size: 14px;
    margin-top: 4px;
    font-weight: 400;
}

/* Modern Badge Styles */
.badge {
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    letter-spacing: 0.3px;
}

.badge-lg {
    font-size: 13px;
    padding: 8px 16px;
}

/* Modern Accordion Design */
.accordion-section {
    background: #ffffff;
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    border: 1px solid #e8e8e8;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.accordion-section:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.accordion-header {
    padding: 20px 24px;
    background: #ffffff;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    border-bottom: 1px solid transparent;
}

.accordion-section.open .accordion-header {
    border-bottom: 1px solid #e8e8e8;
    background: #f8f9ff;
}

.accordion-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.accordion-header-title i {
    color: #667eea;
    font-size: 16px;
    width: 24px;
    text-align: center;
}

.accordion-header-title h6 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
}

.accordion-toggle {
    font-size: 20px;
    color: #6c757d;
    transition: transform 0.3s ease;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f8f9fa;
}

.accordion-section.open .accordion-toggle {
    transform: rotate(90deg);
    background: #667eea;
    color: white;
}

.accordion-body {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.accordion-section.open .accordion-body {
    padding: 24px;
    max-height: 2000px;
}

.accordion-body-content {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 24px;
}

.accordion-body-item {
    margin-bottom: 0;
}

.accordion-body-item label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.accordion-body-item h6 {
    font-size: 15px;
    font-weight: 500;
    color: #1a1a1a;
    margin: 0;
    line-height: 1.5;
}

/* Modern Card Design for Stats */
.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: #ffffff;
    position: relative;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}

.card-body {
    padding: 24px;
    text-align: center;
}

.card-body h6 {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.card-body h3 {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    color: #1a1a1a;
    line-height: 1.2;
}

.card-body .text-warning {
    color: #ffc107 !important;
}

.card-body .text-success {
    color: #28a745 !important;
}

.card-body .text-primary {
    color: #007bff !important;
}

.card-body small {
    font-size: 12px;
    color: #6c757d;
    display: block;
    margin-top: 8px;
}

.card-body .btn-link {
    font-size: 13px;
    font-weight: 500;
    color: #667eea;
    text-decoration: none;
    padding: 0;
    border: none;
    background: none;
    margin-top: 12px;
    display: inline-block;
}

.card-body .btn-link:hover {
    color: #5a67d8;
    text-decoration: underline;
}

/* Modern Button Styles */
.btn {
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-outline-primary {
    border: 2px solid #667eea;
    color: #667eea;
    background: transparent;
}

.btn-outline-primary:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* Modern Modal Design */
.modal-content {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-header {
    background: #ffffff;
    border-bottom: 1px solid #e8e8e8;
    padding: 24px;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e8e8e8;
    background: #f8f9fa;
}

/* Modern Form Elements */
.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: block;
}

.form-control, .form-select {
    border: 2px solid #e8e8e8;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    color: #1a1a1a;
    transition: all 0.3s ease;
    background: #ffffff;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.form-control::placeholder {
    color: #adb5bd;
}

/* Avatar Styles */
.avatar-circle {
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #cd701aff 0%, #764ba2 100%);
    color: white;
    font-size: 40px;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-edit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 3px solid white;
    width: 36px;
    height: 36px;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.avatar-edit-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Status Badge Colors */
.badge-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.badge-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: white;
}

.badge-danger {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    color: white;
}

.badge-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .account-dashboard {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px;
        margin-bottom: 24px;
    }
    
    .page-title {
        font-size: 24px;
    }
    
    .accordion-body-content {
        grid-template-columns: 1fr;
    }
    
    .accordion-body-item {
        margin-bottom: 16px;
    }
    
    .row.g-3 {
        margin: 0 -8px;
    }
    
    .row.g-3 > .col-6 {
        padding: 0 8px;
    }
    
    .card-body h3 {
        font-size: 24px;
    }
}

@media (max-width: 576px) {
    .modal-dialog {
        margin: 16px;
    }
    
    .modal-content {
        border-radius: 12px;
    }
    
    .page-header .row {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 16px;
    }
    
    .page-header .col-auto {
        width: 100%;
        justify-content: space-between;
    }
}

/* Loading Animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Toast Notification */
.toast-notification {
    position: fixed;
    top: 24px;
    right: 24px;
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 400px;
    transform: translateX(150%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-notification.success {
    border-left: 4px solid #28a745;
}

.toast-notification.error {
    border-left: 4px solid #dc3545;
}

.toast-notification i {
    font-size: 20px;
}

.toast-notification.success i {
    color: #28a745;
}

.toast-notification.error i {
    color: #dc3545;
}
</style>

<script>
// Dashboard AJAX Handlers
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.profile-form');

    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const response = await fetch('<?php echo app_url('/profile'); ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                // Always capture response text first
                const responseText = await response.text();
                
                let result = null;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', responseText.substring(0, 500));
                    showToast('Server error. Response was not valid JSON. Check browser console.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }

                if (response.ok && result.success) {
                    showToast(result.message || 'Profile updated successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.error || 'Update failed. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showToast('Connection error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    });

    // Profile Picture Upload Handler
    const profilePictureForm = document.getElementById('editProfilePictureForm');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const previewAvatar = document.getElementById('previewAvatar');

    // Image preview
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File size must be less than 5MB', 'error');
                    e.target.value = '';
                    return;
                }

                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showToast('Please select a valid image file', 'error');
                    e.target.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewAvatar.innerHTML = `<img src="${event.target.result}" alt="Preview" class="avatar-img" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Handle profile picture form submission
    if (profilePictureForm) {
        profilePictureForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const file = profilePictureInput.files[0];
            if (!file) {
                showToast('Please select a picture', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('profile_picture', file);
            formData.append('action', 'upload_profile_picture');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            try {
                const response = await fetch('<?php echo app_url('/profile/upload-picture'); ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const responseText = await response.text();
                let result = null;
                
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', responseText.substring(0, 500));
                    showToast('Server error. Check browser console.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }

                if (response.ok && result.success) {
                    showToast(result.message || 'Profile picture updated successfully!', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfilePictureModal'));
                    if (modal) modal.hide();
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.error || 'Upload failed. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showToast('Connection error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
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

// Toast notification function
function showToast(message, type = 'success') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create new toast
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Hide after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}
</script>