<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Edit City";

// Get city ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid city ID!";
    header("Location: ../address_fee.php");
    exit;
}

$city_id = $_GET['id'];

// Fetch city data
try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_cities WHERE id = ?");
    $stmt->execute([$city_id]);
    $city = $stmt->fetch();
    
    if (!$city) {
        $_SESSION['error_message'] = "City not found!";
        header("Location: ../address_fee.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching city: " . $e->getMessage();
    header("Location: ../address_fee.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $city_name = trim($_POST['city_name']);
    $standard_delivery_fee = $_POST['standard_delivery_fee'];
    $nighttime_urgent_fee = $_POST['nighttime_urgent_fee'];
    $min_order_amount = $_POST['min_order_amount'];
    
    // Validate inputs
    if (empty($city_name)) {
        $_SESSION['error_message'] = "City name is required!";
    } elseif ($standard_delivery_fee < 0 || $nighttime_urgent_fee < 0 || $min_order_amount < 0) {
        $_SESSION['error_message'] = "Fees and amounts cannot be negative!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE delivery_cities SET city_name = ?, standard_delivery_fee = ?, nighttime_urgent_fee = ?, min_order_amount = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$city_name, $standard_delivery_fee, $nighttime_urgent_fee, $min_order_amount, $city_id])) {
                $_SESSION['success_message'] = "City '$city_name' updated successfully!";
                header("Location: ../address_fee.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to update city.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error_message'] = "City '$city_name' already exists!";
            } else {
                $_SESSION['error_message'] = "Error updating city: " . $e->getMessage();
            }
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit City: <?php echo htmlspecialchars($city['city_name']); ?></h1>
    <a href="../address_fee.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Cities
    </a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit City Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editCityForm">
                    <div class="mb-3">
                        <label for="city_name" class="form-label">City Name *</label>
                        <input type="text" class="form-control" id="city_name" name="city_name" 
                               placeholder="e.g., Banepa, Bhaktapur, Kathmandu" 
                               value="<?php echo isset($_POST['city_name']) ? htmlspecialchars($_POST['city_name']) : htmlspecialchars($city['city_name']); ?>" 
                               required maxlength="100">
                        <div class="form-text">Enter the name of the city where you deliver.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="standard_delivery_fee" class="form-label">Standard Delivery Fee (Rs.) *</label>
                                <input type="number" class="form-control" id="standard_delivery_fee" name="standard_delivery_fee" 
                                       min="0" step="0.01" 
                                       value="<?php echo isset($_POST['standard_delivery_fee']) ? $_POST['standard_delivery_fee'] : $city['standard_delivery_fee']; ?>" 
                                       required>
                                <div class="form-text">Regular delivery fee for this city.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="nighttime_urgent_fee" class="form-label">Nighttime Urgent Fee (Rs.) *</label>
                                <input type="number" class="form-control" id="nighttime_urgent_fee" name="nighttime_urgent_fee" 
                                       min="0" step="0.01" 
                                       value="<?php echo isset($_POST['nighttime_urgent_fee']) ? $_POST['nighttime_urgent_fee'] : $city['nighttime_urgent_fee']; ?>" 
                                       required>
                                <div class="form-text">Fee for urgent deliveries during nighttime.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_order_amount" class="form-label">Minimum Order Amount (Rs.) *</label>
                                <input type="number" class="form-control" id="min_order_amount" name="min_order_amount" 
                                       min="0" step="0.01" 
                                       value="<?php echo isset($_POST['min_order_amount']) ? $_POST['min_order_amount'] : $city['min_order_amount']; ?>" 
                                       required>
                                <div class="form-text">Minimum order value for delivery in this city.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../address_fee.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update City
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- City Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">City Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($city['created_at'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($city['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editCityForm');
    
    form.addEventListener('submit', function(e) {
        const cityName = document.getElementById('city_name').value.trim();
        const standardFee = parseFloat(document.getElementById('standard_delivery_fee').value);
        const nighttimeFee = parseFloat(document.getElementById('nighttime_urgent_fee').value);
        const minOrderAmount = parseFloat(document.getElementById('min_order_amount').value);
        
        if (!cityName) {
            e.preventDefault();
            alert('Please enter a city name.');
            return false;
        }
        
        if (standardFee < 0 || nighttimeFee < 0 || minOrderAmount < 0) {
            e.preventDefault();
            alert('Fees and amounts cannot be negative.');
            return false;
        }
    });
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>