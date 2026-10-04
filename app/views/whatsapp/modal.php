<?php
// WhatsApp Order Modal
?>
<div id="whatsappModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px; width: 95%; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background: #25D366; color: white; padding: 20px; text-align: center;">
            <h2 style="margin: 0; font-size: 24px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <i class="fab fa-whatsapp"></i> Order via WhatsApp
            </h2>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Complete your order quickly via WhatsApp</p>
            <span class="close" onclick="closeWhatsAppModal()" style="position: absolute; right: 20px; top: 20px; font-size: 24px; cursor: pointer;">&times;</span>
        </div>
        
        <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
            <!-- Order Summary -->
            <div class="whatsapp-order-summary" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #25D366;">Order Summary</h4>
                <div id="whatsappCartItems"></div>
                <div class="whatsapp-totals" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Subtotal:</span>
                        <span id="whatsappSubtotal">Rs. 0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Delivery Fee:</span>
                        <span id="whatsappDeliveryFee">Rs. 0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px;">
                        <span>Total:</span>
                        <span id="whatsappTotal">Rs. 0.00</span>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Order Form -->
            <form id="whatsappOrderForm">
                <input type="hidden" name="order_type" value="whatsapp">
                
                <div class="form-section">
                    <h4 style="color: #25D366; margin-bottom: 15px;">Personal Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="wa_name">Full Name *</label>
                            <input type="text" id="wa_name" name="name" class="form-input" required 
                                   value="<?= htmlspecialchars($customer_name ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="wa_phone">Phone Number *</label>
                            <input type="tel" id="wa_phone" name="phone" class="form-input" required 
                                   value="<?= htmlspecialchars($customer_phone ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="wa_email">Email Address</label>
                        <input type="email" id="wa_email" name="email" class="form-input"
                               value="<?= htmlspecialchars($customer_email ?? '') ?>">
                    </div>
                </div>

                <div class="form-section">
                    <h4 style="color: #25D366; margin-bottom: 15px;">Delivery Information</h4>
                    
                    <div class="form-group">
                        <label for="wa_address">Address *</label>
                        <textarea id="wa_address" name="address" class="form-input" rows="2" required><?= htmlspecialchars($customer_address ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="wa_city">City *</label>
                            <select id="wa_city" name="city" class="form-input" required onchange="updateWhatsAppDeliveryFee()">
                                <option value="">-- Select City --</option>
                                <?php foreach ($available_cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city) ?>" 
                                            <?= ($customer_city == $city) ? 'selected' : '' ?>
                                            data-fee="<?= $city_delivery_fees[$city] ?? 0.00 ?>">
                                        <?= htmlspecialchars($city) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display: none;">
                            <label for="wa_street">Street</label>
                            <input type="text" id="wa_street" name="street" class="form-input"
                                   value="<?= htmlspecialchars($customer_street ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4 style="color: #25D366; margin-bottom: 15px;">Delivery Time</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="wa_delivery_date">Delivery Date *</label>
                            <input type="date" id="wa_delivery_date" name="delivery_date" class="form-input" required
                                   value="<?= $default_delivery_date ?>">
                        </div>
                        <div class="form-group">
                            <label for="wa_delivery_time">Delivery Time *</label>
                            <select id="wa_delivery_time" name="delivery_time" class="form-input" required>
                                <option value="morning">Morning (8 AM - 12 PM)</option>
                                <option value="afternoon">Afternoon (12 PM - 4 PM)</option>
                                <option value="evening">Evening (4 PM - 8 PM)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="wa_notes">Special Instructions</label>
                        <textarea id="wa_notes" name="notes" class="form-input" rows="2" placeholder="Any special delivery instructions..."></textarea>
                    </div>
                </div>

                <div class="form-notice" style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
                    <h5 style="margin: 0 0 10px 0; color: #1976d2;">How WhatsApp Ordering Works:</h5>
                    <ol style="margin: 0; padding-left: 20px;">
                        <li>Fill in your details above</li>
                        <li>Click "Place Order via WhatsApp"</li>
                        <li>We'll save your order in our system</li>
                        <li>You'll be redirected to WhatsApp with order details</li>
                        <li>Confirm your order with our team via WhatsApp</li>
                    </ol>
                </div>

                <div class="form-actions" style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeWhatsAppModal()" 
                            style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success" 
                            style="flex: 2; padding: 12px; background: #25D366; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fab fa-whatsapp"></i> Place Order via WhatsApp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#whatsappModal .modal-content {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.whatsapp-order-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.whatsapp-order-item:last-child {
    border-bottom: none;
}

.whatsapp-item-name {
    flex: 2;
    font-weight: 500;
}

.whatsapp-item-details {
    flex: 1;
    text-align: right;
    font-size: 14px;
    color: #666;
}

.form-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.form-section:last-child {
    border-bottom: none;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #25D366;
    box-shadow: 0 0 0 2px rgba(37, 211, 102, 0.2);
}

.btn-success:hover {
    background: #1ea952 !important;
    transform: translateY(-1px);
}

.btn-secondary:hover {
    background: #5a6268 !important;
}
</style>

<script>
// WhatsApp modal functionality
function openWhatsAppModal() {
    document.getElementById('whatsappModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    loadWhatsAppOrderSummary();
}

function closeWhatsAppModal() {
    document.getElementById('whatsappModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function loadWhatsAppOrderSummary() {
    // Fetch current cart data and populate the WhatsApp order summary
    fetch('<?= $base_url ?>/whatsapp/order-summary')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateWhatsAppOrderDisplay(data);
            }
        })
        .catch(error => {
            console.error('Error loading WhatsApp order summary:', error);
        });
}

function updateWhatsAppOrderDisplay(data) {
    const cartItems = document.getElementById('whatsappCartItems');
    const subtotal = document.getElementById('whatsappSubtotal');
    const deliveryFee = document.getElementById('whatsappDeliveryFee');
    const total = document.getElementById('whatsappTotal');
    
    // Update cart items
    cartItems.innerHTML = '';
    data.cart.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.className = 'whatsapp-order-item';
        itemElement.innerHTML = `
            <div class="whatsapp-item-name">${item.name}</div>
            <div class="whatsapp-item-details">
                ${item.quantity} ${item.unit} × Rs. ${item.final_price.toFixed(2)}<br>
                <strong>Rs. ${item.item_total.toFixed(2)}</strong>
            </div>
        `;
        cartItems.appendChild(itemElement);
    });
    
    // Update totals
    subtotal.textContent = `Rs. ${data.subtotal.toFixed(2)}`;
    deliveryFee.textContent = data.delivery_fee === 0 ? 'FREE' : `Rs. ${data.delivery_fee.toFixed(2)}`;
    total.textContent = `Rs. ${data.total.toFixed(2)}`;
}

function updateWhatsAppDeliveryFee() {
    const citySelect = document.getElementById('wa_city');
    const selectedCity = citySelect.value;
    const deliveryFee = document.getElementById('whatsappDeliveryFee');
    
    if (!selectedCity) {
        deliveryFee.textContent = 'Rs. 0.00';
        return;
    }
    
    const selectedOption = citySelect.options[citySelect.selectedIndex];
    const deliveryFeeAmount = parseFloat(selectedOption.getAttribute('data-fee')) || 0.00;
    
    deliveryFee.textContent = deliveryFeeAmount === 0 ? 'FREE' : `Rs. ${deliveryFeeAmount.toFixed(2)}`;
    
    // Update total
    updateWhatsAppTotal(deliveryFeeAmount);
}

function updateWhatsAppTotal(deliveryFee) {
    const subtotalElement = document.getElementById('whatsappSubtotal');
    const totalElement = document.getElementById('whatsappTotal');
    
    const subtotal = parseFloat(subtotalElement.textContent.replace('Rs. ', '')) || 0;
    const total = subtotal + deliveryFee;
    
    totalElement.textContent = `Rs. ${total.toFixed(2)}`;
}

// WhatsApp form submission
document.getElementById('whatsappOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    const formData = new FormData(this);
    
    fetch('<?= $base_url ?>/whatsapp/process-order', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to WhatsApp with the generated message
            window.open(data.whatsapp_url, '_blank');
            closeWhatsAppModal();
            
            // Show success message
            showMessage('Order placed successfully! Redirecting to WhatsApp...', 'success', 'addressUpdateMessage');
            
            // Clear cart and redirect to success page after a delay
            setTimeout(() => {
                window.location.href = '<?= $base_url ?>/success?order_id=' + data.order_id + '&source=whatsapp';
            }, 2000);
        } else {
            showMessage(data.message, 'error', 'addressUpdateMessage');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error processing WhatsApp order:', error);
        showMessage('Error processing order. Please try again.', 'error', 'addressUpdateMessage');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('whatsappModal')) {
        closeWhatsAppModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('whatsappModal').style.display === 'flex') {
        closeWhatsAppModal();
    }
});

// Auto-fill street from address
document.getElementById('wa_address').addEventListener('input', function() {
    document.getElementById('wa_street').value = this.value;
});
</script>