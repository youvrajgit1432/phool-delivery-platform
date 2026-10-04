<?php
// app/views/account/address-change.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = "Change Address - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

// Get redirect URL if provided
$redirect_url = $_GET['redirect'] ?? 'account';
?>

<section class="address-change-page">
    <div class="container">
        <div class="page-header">
            <h1>Change Delivery Address</h1>
            <p>Update your delivery information</p>
        </div>
        
        <div class="address-form-container">
            <form id="addressChangeForm" class="address-form">
                <input type="hidden" name="action" value="update_address">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                
                <div class="form-section">
                    <h2>Contact Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Delivery Address</h2>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" class="form-input" rows="3" required><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="street">Street</label>
                            <input type="text" id="street" name="street" class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['street'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="window.location.href='<?php echo $pathConfig->url($redirect_url === 'checkout' ? 'checkout' : 'account'); ?>'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addressChangeForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        // Form validation
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
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }
        
        // Submit form
        const formData = new FormData(this);
        
        fetch('<?php echo $pathConfig->url("account/update-address"); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                
                // Redirect based on the redirect parameter
                const redirect = new URLSearchParams(formData).get('redirect');
                if (redirect === 'checkout') {
                    window.location.href = '<?php echo $pathConfig->url("checkout"); ?>';
                } else {
                    window.location.href = '<?php echo $pathConfig->url("account"); ?>';
                }
            } else {
                alert(data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    
    // Remove error class when user starts typing
    form.querySelectorAll('input, textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
});
</script>