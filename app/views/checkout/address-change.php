<?php
// app/views/checkout/address-change.php
$page_title = "Change Delivery Address - Phool Delivery";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$assets_path = $base_url . '/phool-delivery-platform/public_html/assets';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: /phool-delivery-platform/public_html/login?redirect=address-change');
    exit;
}

// Get customer data
$customer_id = $_SESSION['customer_id'];
$customerModel = new Customer($db);
$customerModel->id = $customer_id;
$customer = $customerModel->readOne();

if (!$customer) {
    // Customer not found, clear session and redirect
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    header('Location: /phool-delivery-platform/public_html/login');
    exit;
}

// Set form values
$customer_name = $customer['name'] ?? '';
$customer_phone = $customer['phone'] ?? '';
$customer_email = $customer['email'] ?? '';
$customer_address = $customer['address'] ?? '';
$customer_city = $customer['city'] ?? '';
$customer_street = $customer['street'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['name', 'phone', 'address', 'city'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    
    // Validate phone format
    $phonePattern = '/^[0-9]{10}$/';
    if (!empty($_POST['phone']) && !preg_match($phonePattern, $_POST['phone'])) {
        $errors[] = 'Please enter a valid 10-digit phone number';
    }
    
    // Validate email if provided
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        // Update customer details
        $query = "UPDATE customers SET 
                 name = :name, 
                 email = :email, 
                 phone = :phone, 
                 address = :address, 
                 city = :city, 
                 street = :street,
                 updated_at = NOW()
                 WHERE id = :id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":name", $_POST['name']);
        $stmt->bindParam(":email", $_POST['email']);
        $stmt->bindParam(":phone", $_POST['phone']);
        $stmt->bindParam(":address", $_POST['address']);
        $stmt->bindParam(":city", $_POST['city']);
        $stmt->bindParam(":street", $_POST['street']);
        $stmt->bindParam(":id", $customer_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Address updated successfully!';
            
            // Redirect back to checkout page
            $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : '/phool-delivery-platform/public_html/checkout';
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $errors[] = 'Failed to update address. Please try again.';
        }
    }
}
?>

<section class="address-change-page">
    <div class="container">
        <div class="page-header">
            <h1>Change Delivery Address</h1>
            <p>Update your delivery information</p>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="alert-message error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="address-form-container">
            <form method="POST" id="addressForm">
                <div class="form-section">
                    <h2>Contact Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer_phone); ?>" required
                                   pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($customer_email); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Delivery Address</h2>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" class="form-input" rows="2" required><?php echo htmlspecialchars($customer_address); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer_city); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="street">Street</label>
                            <input type="text" id="street" name="street" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer_street); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Address</button>
                    <a href="<?php echo isset($_GET['redirect']) ? urldecode($_GET['redirect']) : '/phool-delivery-platform/public_html/checkout'; ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addressForm = document.getElementById('addressForm');
    
    addressForm.addEventListener('submit', function(e) {
        // Basic validation
        const requiredFields = this.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                valid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        // Phone validation
        const phoneInput = document.getElementById('phone');
        const phonePattern = /^[0-9]{10}$/;
        if (phoneInput.value && !phonePattern.test(phoneInput.value)) {
            phoneInput.classList.add('error');
            valid = false;
            alert('Please enter a valid 10-digit phone number');
        }
        
        if (!valid) {
            e.preventDefault();
        }
    });
    
    // Remove error class when user starts typing
    addressForm.querySelectorAll('input, textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
});
</script>