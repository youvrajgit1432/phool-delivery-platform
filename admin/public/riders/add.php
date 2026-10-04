<?php
/**
 * Add New Rider
 * Admin panel page to register and add a new delivery rider
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'Add New Rider';
$current_page = 'riders';

$db = getDBConnection();

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $rider_type = $_POST['rider_type'] ?? 'in_house';
        $vehicle_type = $_POST['vehicle_type'] ?? 'motorcycle';
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $identification_type = $_POST['identification_type'] ?? null;
        $identification_number = trim($_POST['identification_number'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? null;
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $account_holder_name = trim($_POST['account_holder_name'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $base_salary = !empty($_POST['base_salary']) ? floatval($_POST['base_salary']) : null;
        $status = $_POST['status'] ?? 'pending';
        $home_address = trim($_POST['home_address'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
            throw new Exception('First name, last name, email, phone, and password are required');
        }

        // Check if email exists
        $check = $db->prepare("SELECT id FROM riders WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new Exception('Email already registered');
        }

        // Check if phone exists
        $check = $db->prepare("SELECT id FROM riders WHERE phone = ?");
        $check->execute([$phone]);
        if ($check->fetch()) {
            throw new Exception('Phone number already registered');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert rider
        $sql = "INSERT INTO riders (
            first_name, last_name, email, phone, password, rider_type, vehicle_type,
            vehicle_number, city, date_of_birth, identification_type, identification_number,
            license_number, license_expiry, bank_name, account_number, account_holder_name,
            ifsc_code, base_salary, status, home_address, postal_code, email_verified,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $first_name, $last_name, $email, $phone, $hashed_password, $rider_type, $vehicle_type,
            $vehicle_number, $city, $date_of_birth, $identification_type, $identification_number,
            $license_number, $license_expiry, $bank_name, $account_number, $account_holder_name,
            $ifsc_code, $base_salary, $status, $home_address, $postal_code
        ]);

        $_SESSION['success_message'] = 'Rider added successfully! Status: ' . ucfirst($status);
        header('Location: ../riders.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="../riders.php" class="btn btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Riders
            </a>
            <h1 class="h3">Add New Delivery Rider</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Rider Information</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- Personal Information -->
                        <h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>Personal Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="phone" required pattern="[0-9]{10,}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth">
                            </div>
                        </div>

                        <!-- Rider Type & Vehicle -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-truck me-2"></i>Rider Type & Vehicle</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Rider Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="rider_type" required>
                                    <option value="in_house">In-House (Salaried)</option>
                                    <option value="gig">Gig (Freelance)</option>
                                    <option value="partner">Partner (Delivery Company)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="vehicle_type" required>
                                    <option value="motorcycle">Motorcycle</option>
                                    <option value="bicycle">Bicycle</option>
                                    <option value="scooter">Scooter</option>
                                    <option value="car">Car</option>
                                    <option value="van">Van</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vehicle Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="vehicle_number" required>
                            </div>
                        </div>

                        <!-- Location -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="city" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Home Address</label>
                                <textarea class="form-control" name="home_address" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Identification -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-id-card me-2"></i>Identification</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">ID Type</label>
                                <select class="form-select" name="identification_type">
                                    <option value="">Select...</option>
                                    <option value="License">License</option>
                                    <option value="Aadhar">Aadhar</option>
                                    <option value="Passport">Passport</option>
                                    <option value="National ID">National ID</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="identification_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" name="license_number">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">License Expiry</label>
                                <input type="date" class="form-control" name="license_expiry">
                            </div>
                        </div>

                        <!-- Bank Information -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-university me-2"></i>Bank Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" name="account_holder_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" name="ifsc_code">
                            </div>
                        </div>

                        <!-- Salary (for in-house riders) -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-money-bill me-2"></i>Salary</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Base Salary (Optional)</label>
                                <input type="number" step="0.01" class="form-control" name="base_salary" placeholder="For in-house riders">
                            </div>
                        </div>

                        <!-- Status -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-check-circle me-2"></i>Status</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Initial Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="pending">Pending (Requires Approval)</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Add Rider
                            </button>
                            <a href="../riders.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Information</h6>
                    <ul class="small">
                        <li>Password must be at least 8 characters</li>
                        <li>Email and phone must be unique</li>
                        <li>Pending riders require admin approval before they can accept orders</li>
                        <li>In-house riders can have an assigned base salary</li>
                        <li>All required fields marked with <span class="text-danger">*</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../app/views/layouts/footer.php';
?>
