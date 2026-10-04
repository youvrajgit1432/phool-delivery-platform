<?php
// app/views/account/payment-methods.php

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<?php echo CSRF::getTokenField(); ?>
<div class="account-container">
    <div class="account-header">
        <h2>Payment Methods</h2>
        <p>Manage your saved payment methods</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="payment-methods-content">
        <div class="payment-methods-list">
            <?php foreach ($payment_methods as $method): ?>
                <div class="payment-method-card <?php echo $method['is_default'] ? 'default-method' : ''; ?>">
                    <div class="method-header">
                        <h4><?php echo ucfirst($method['payment_type']); ?></h4>
                        <?php if ($method['is_default']): ?>
                            <span class="default-badge">Default</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="method-details">
                        <?php if ($method['payment_type'] === 'card'): ?>
                            <p>Card ending in •••• <?php echo htmlspecialchars($method['last_four']); ?></p>
                            <?php if (!empty($method['expiry_month']) && !empty($method['expiry_year'])): ?>
                                <p>Expires: <?php echo htmlspecialchars($method['expiry_month']); ?>/<?php echo htmlspecialchars($method['expiry_year']); ?></p>
                            <?php endif; ?>
                        <?php elseif ($method['payment_type'] === 'esewa'): ?>
                            <p>eSewa Wallet</p>
                        <?php elseif ($method['payment_type'] === 'khalti'): ?>
                            <p>Khalti Wallet</p>
                        <?php else: ?>
                            <p><?php echo ucfirst($method['payment_type']); ?> Account</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($method['provider'])): ?>
                            <p>Provider: <?php echo htmlspecialchars($method['provider']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="method-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                            <input type="hidden" name="delete_method" value="1">
                            <button type="submit" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this payment method?')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="payment-method-card add-new-method">
                <div class="add-method-placeholder" onclick="showPaymentForm()">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <h4>Add Payment Method</h4>
                </div>
            </div>
        </div>

        <!-- Payment Form Modal -->
        <div id="paymentFormModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add Payment Method</h3>
                    <span class="close" onclick="closePaymentForm()">&times;</span>
                </div>
                <form method="POST" id="paymentForm">
                    <div class="form-group">
                        <label for="payment_type">Payment Type *</label>
                        <select id="payment_type" name="payment_type" required onchange="togglePaymentFields()">
                            <option value="">Select Payment Type</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="esewa">eSewa</option>
                            <option value="khalti">Khalti</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>

                    <div id="cardFields" style="display:none;">
                        <div class="form-group">
                            <label for="card_number">Card Number *</label>
                            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiry_month">Expiry Month *</label>
                                <select id="expiry_month" name="expiry_month">
                                    <option value="">MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                            <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="expiry_year">Expiry Year *</label>
                                <select id="expiry_year" name="expiry_year">
                                    <option value="">YYYY</option>
                                    <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="card_holder">Card Holder Name *</label>
                            <input type="text" id="card_holder" name="card_holder">
                        </div>
                    </div>

                    <div id="walletFields" style="display:none;">
                        <div class="form-group">
                            <label for="wallet_id">Wallet ID/Phone Number *</label>
                            <input type="text" id="wallet_id" name="wallet_id">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="provider">Provider/Bank Name</label>
                        <input type="text" id="provider" name="provider" placeholder="e.g., Nepal Bank, Nabil Bank, etc.">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_default" id="is_default">
                            <span>Set as default payment method</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closePaymentForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showPaymentForm() {
    document.getElementById('paymentFormModal').style.display = 'block';
}

function closePaymentForm() {
    document.getElementById('paymentFormModal').style.display = 'none';
    document.getElementById('paymentForm').reset();
    document.getElementById('cardFields').style.display = 'none';
    document.getElementById('walletFields').style.display = 'none';
}

function togglePaymentFields() {
    const paymentType = document.getElementById('payment_type').value;
    const cardFields = document.getElementById('cardFields');
    const walletFields = document.getElementById('walletFields');
    
    cardFields.style.display = (paymentType === 'card') ? 'block' : 'none';
    walletFields.style.display = (paymentType === 'esewa' || paymentType === 'khalti') ? 'block' : 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('paymentFormModal');
    if (event.target === modal) {
        closePaymentForm();
    }
}

// Format card number
document.getElementById('card_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 16) value = value.slice(0, 16);
    
    // Add spaces every 4 characters
    value = value.replace(/(\d{4})/g, '$1 ').trim();
    e.target.value = value;
});
</script>