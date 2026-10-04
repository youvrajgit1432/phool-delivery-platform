<?php
/**
 * Edit Rider
 * Admin panel page to edit rider information
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'Edit Rider';
$current_page = 'riders';

$db = getDBConnection();

// Get rider ID
$rider_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($rider_id <= 0) {
    header('Location: ../riders.php');
    exit;
}

// Fetch rider
$stmt = $db->prepare("SELECT * FROM riders WHERE id = ?");
$stmt->execute([$rider_id]);
$rider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rider) {
    $_SESSION['error_message'] = 'Rider not found';
    header('Location: ../riders.php');
    exit;
}

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $vehicle_type = $_POST['vehicle_type'] ?? $rider['vehicle_type'];
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
        $status = $_POST['status'] ?? $rider['status'];
        $home_address = trim($_POST['home_address'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($phone)) {
            throw new Exception('First name, last name, and phone are required');
        }

        // Check if phone changed and if new phone is unique
        if ($phone !== $rider['phone']) {
            $check = $db->prepare("SELECT id FROM riders WHERE phone = ? AND id != ?");
            $check->execute([$phone, $rider_id]);
            if ($check->fetch()) {
                throw new Exception('Phone number already registered');
            }
        }

        $sql = "UPDATE riders SET
            first_name = ?, last_name = ?, phone = ?, vehicle_type = ?,
            vehicle_number = ?, city = ?, date_of_birth = ?,
            identification_type = ?, identification_number = ?,
            license_number = ?, license_expiry = ?, bank_name = ?,
            account_number = ?, account_holder_name = ?, ifsc_code = ?,
            base_salary = ?, status = ?, home_address = ?, postal_code = ?,
            updated_at = NOW()";

        $params = [
            $first_name, $last_name, $phone, $vehicle_type,
            $vehicle_number, $city, $date_of_birth ?: null,
            $identification_type ?: null, $identification_number,
            $license_number, $license_expiry ?: null, $bank_name,
            $account_number, $account_holder_name, $ifsc_code,
            $base_salary, $status, $home_address, $postal_code
        ];

        // Add password update if provided
        if (!empty($password)) {
            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }
            $sql = "UPDATE riders SET
                first_name = ?, last_name = ?, phone = ?, password = ?, vehicle_type = ?,
                vehicle_number = ?, city = ?, date_of_birth = ?,
                identification_type = ?, identification_number = ?,
                license_number = ?, license_expiry = ?, bank_name = ?,
                account_number = ?, account_holder_name = ?, ifsc_code = ?,
                base_salary = ?, status = ?, home_address = ?, postal_code = ?,
                updated_at = NOW()";
            
            $params = [
                $first_name, $last_name, $phone, password_hash($password, PASSWORD_BCRYPT),
                $vehicle_type, $vehicle_number, $city, $date_of_birth ?: null,
                $identification_type ?: null, $identification_number,
                $license_number, $license_expiry ?: null, $bank_name,
                $account_number, $account_holder_name, $ifsc_code,
                $base_salary, $status, $home_address, $postal_code
            ];
        }

        $sql .= " WHERE id = ?";
        $params[] = $rider_id;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_message'] = 'Rider updated successfully!';
        header('Location: ../riders.php?view=' . $rider['status']);
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
            <h1 class="h3">Edit Rider: <?php echo htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']); ?></h1>
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
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($rider['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($rider['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email (Read-only)</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($rider['email']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($rider['phone']); ?>" required pattern="[0-9]{10,}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password (Leave blank to keep current)</label>
                                <input type="password" class="form-control" name="password" minlength="8">
                                <small class="text-muted">Only fill this if you want to change the password</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo $rider['date_of_birth'] ?? ''; ?>">
                            </div>
                        </div>

                        <!-- Rider Type & Vehicle -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-truck me-2"></i>Rider Type & Vehicle</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Rider Type (Read-only)</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $rider['rider_type'])); ?>" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="vehicle_type" required>
                                    <option value="motorcycle" <?php echo $rider['vehicle_type'] === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                    <option value="bicycle" <?php echo $rider['vehicle_type'] === 'bicycle' ? 'selected' : ''; ?>>Bicycle</option>
                                    <option value="scooter" <?php echo $rider['vehicle_type'] === 'scooter' ? 'selected' : ''; ?>>Scooter</option>
                                    <option value="car" <?php echo $rider['vehicle_type'] === 'car' ? 'selected' : ''; ?>>Car</option>
                                    <option value="van" <?php echo $rider['vehicle_type'] === 'van' ? 'selected' : ''; ?>>Van</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vehicle Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="vehicle_number" value="<?php echo htmlspecialchars($rider['vehicle_number'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- Location -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($rider['city']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($rider['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Home Address</label>
                                <textarea class="form-control" name="home_address" rows="2"><?php echo htmlspecialchars($rider['home_address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Identification -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-id-card me-2"></i>Identification</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">ID Type</label>
                                <select class="form-select" name="identification_type">
                                    <option value="">Select...</option>
                                    <option value="License" <?php echo ($rider['identification_type'] === 'License') ? 'selected' : ''; ?>>License</option>
                                    <option value="Aadhar" <?php echo ($rider['identification_type'] === 'Aadhar') ? 'selected' : ''; ?>>Aadhar</option>
                                    <option value="Passport" <?php echo ($rider['identification_type'] === 'Passport') ? 'selected' : ''; ?>>Passport</option>
                                    <option value="National ID" <?php echo ($rider['identification_type'] === 'National ID') ? 'selected' : ''; ?>>National ID</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="identification_number" value="<?php echo htmlspecialchars($rider['identification_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" name="license_number" value="<?php echo htmlspecialchars($rider['license_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">License Expiry</label>
                                <input type="date" class="form-control" name="license_expiry" value="<?php echo $rider['license_expiry'] ?? ''; ?>">
                            </div>
                        </div>

                        <!-- Bank Information -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-university me-2"></i>Bank Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($rider['bank_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" name="account_holder_name" value="<?php echo htmlspecialchars($rider['account_holder_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($rider['account_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" name="ifsc_code" value="<?php echo htmlspecialchars($rider['ifsc_code'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Salary -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-money-bill me-2"></i>Salary</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Base Salary (Optional)</label>
                                <input type="number" step="0.01" class="form-control" name="base_salary" value="<?php echo $rider['base_salary'] ?? ''; ?>" placeholder="For in-house riders">
                            </div>
                        </div>

                        <!-- Status -->
                        <h6 class="mb-3 text-primary mt-4"><i class="fas fa-check-circle me-2"></i>Status</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="pending" <?php echo $rider['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo $rider['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $rider['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="inactive" <?php echo $rider['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Rider
                            </button>
                            <a href="../riders.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Performance Stats -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted">Average Rating</label>
                        <div class="h5"><?php echo number_format($rider['average_rating'], 1); ?> ⭐</div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Total Deliveries</label>
                        <div class="h5"><?php echo $rider['total_deliveries']; ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Total Reviews</label>
                        <div class="h5"><?php echo $rider['total_reviews']; ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Total Earnings</label>
                        <div class="h5">Rs. <?php echo number_format($rider['total_earnings'], 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Total Penalties</label>
                        <div class="h5 text-danger">Rs. <?php echo number_format($rider['total_penalties'], 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">On-Time Delivery Rate</label>
                        <div class="h5"><?php echo number_format($rider['on_time_delivery_rate'], 1); ?>%</div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Cancellation Rate</label>
                        <div class="h5 text-warning"><?php echo number_format($rider['cancellation_rate'], 1); ?>%</div>
                    </div>
                </div>
            </div>

            <!-- Verification Status -->
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-check-double me-2"></i>Verification Status</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $rider['email_verified'] ? 'success' : 'danger'; ?>">
                            <i class="fas fa-envelope"></i> Email <?php echo $rider['email_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $rider['documents_verified'] ? 'success' : 'danger'; ?>">
                            <i class="fas fa-file"></i> Documents <?php echo $rider['documents_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-<?php echo $rider['bank_verified'] ? 'success' : 'danger'; ?>">
                            <i class="fas fa-bank"></i> Bank <?php echo $rider['bank_verified'] ? 'Verified' : 'Unverified'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Account Info</h6>
                    <small class="text-muted">
                        <p><strong>Registered:</strong> <?php echo date('M d, Y H:i', strtotime($rider['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($rider['updated_at'])); ?></p>
                        <p><strong>Last Login:</strong> <?php echo $rider['last_login'] ? date('M d, Y H:i', strtotime($rider['last_login'])) : 'Never'; ?></p>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../app/views/layouts/footer.php';
?>
