<?php
// app/views/account/addresses.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$page_title = "My Addresses - Phool Delivery";

// Check if this is a redirect from checkout
$is_checkout_redirect = ($redirect_url === 'checkout');
?>

<section class="account-addresses">
    <div class="container">
        <div class="account-header">
            <h1>My Addresses</h1>
            
            <?php if ($is_checkout_redirect): ?>
            <div class="checkout-notice">
                <p>Please update your delivery address to continue with your order.</p>
                <a href="<?php echo $pathConfig->url('checkout'); ?>" class="btn btn-outline">Back to Checkout</a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="account-content">
            <!-- Display success/error messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
            <?php endif; ?>
            
            <div class="addresses-container">
                <!-- Add New Address Form -->
                <div class="address-form-card">
                    <h3>Add New Address</h3>
                    <form method="POST" action="<?php echo $pathConfig->url('account/addresses'); ?><?php echo $is_checkout_redirect ? '?redirect=checkout' : ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="address_name">Address Name (e.g., Home, Office)</label>
                                <input type="text" id="address_name" name="address_name" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_address">Full Address *</label>
                            <textarea id="full_address" name="full_address" class="form-input" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" name="city" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="street">Street</label>
                                <input type="text" id="street" name="street" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="landmark">Landmark</label>
                                <input type="text" id="landmark" name="landmark" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_default" value="1">
                                <span>Set as default address</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Address</button>
                    </form>
                </div>
                
                <!-- Existing Addresses -->
                <div class="addresses-list">
                    <h3>Your Addresses</h3>
                    
                    <?php if (empty($addresses)): ?>
                    <div class="empty-state">
                        <p>You haven't added any addresses yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="address-grid">
                        <?php foreach ($addresses as $address): ?>
                        <div class="address-card <?php echo $address['is_default'] ? 'default-address' : ''; ?>">
                            <div class="address-header">
                                <h4><?php echo htmlspecialchars($address['address_name']); ?></h4>
                                <?php if ($address['is_default']): ?>
                                <span class="default-badge">Default</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="address-details">
                                <p><?php echo htmlspecialchars($address['full_address']); ?></p>
                                <p><?php echo htmlspecialchars($address['city']); ?>, 
                                   <?php echo !empty($address['street']) ? htmlspecialchars($address['street']) . ', ' : ''; ?>
                                   <?php echo !empty($address['postal_code']) ? htmlspecialchars($address['postal_code']) : ''; ?></p>
                                <?php if (!empty($address['landmark'])): ?>
                                <p class="landmark">Landmark: <?php echo htmlspecialchars($address['landmark']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="address-actions">
                                <?php if (!$address['is_default']): ?>
                                <form method="POST" action="<?php echo $pathConfig->url('account/addresses'); ?><?php echo $is_checkout_redirect ? '?redirect=checkout' : ''; ?>" style="display: inline;">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <input type="hidden" name="set_default" value="1">
                                    <button type="submit" class="btn btn-outline btn-small">Set as Default</button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" action="<?php echo $pathConfig->url('account/addresses'); ?><?php echo $is_checkout_redirect ? '?redirect=checkout' : ''; ?>" style="display: inline;">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <input type="hidden" name="delete_address" value="1">
                                    <button type="submit" class="btn btn-outline btn-small btn-danger" 
                                            onclick="return confirm('Are you sure you want to delete this address?')">Delete</button>
                                </form>
                                
                                <?php if ($is_checkout_redirect): ?>
                                <a href="<?php echo $pathConfig->url('checkout'); ?>" class="btn btn-primary btn-small">Use This Address</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.account-addresses {
    padding: 2rem 0;
}

.account-header {
    margin-bottom: 2rem;
}

.checkout-notice {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.addresses-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .addresses-container {
        grid-template-columns: 1fr 1fr;
    }
}

.address-form-card, .addresses-list {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.address-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.address-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    position: relative;
}

.address-card.default-address {
    border-color: #007bff;
    background-color: #f8f9ff;
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.default-badge {
    background-color: #007bff;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.address-details p {
    margin: 0.25rem 0;
    color: #6c757d;
}

.landmark {
    color: #495057;
    font-weight: 500;
}

.address-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}
</style>
<style>
.account-addresses {
    padding: 2rem 0;
}

.account-header {
    margin-bottom: 2rem;
}

.checkout-notice {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.addresses-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .addresses-container {
        grid-template-columns: 1fr 1fr;
    }
}

.address-form-card, .addresses-list {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.address-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.address-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    position: relative;
}

.address-card.default-address {
    border-color: #007bff;
    background-color: #f8f9ff;
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.default-badge {
    background-color: #007bff;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.address-details p {
    margin: 0.25rem 0;
    color: #6c757d;
}

.landmark {
    color: #495057;
    font-weight: 500;
}

.address-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}
</style>