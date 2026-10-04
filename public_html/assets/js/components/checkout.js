// Checkout form handling
document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkout-form');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const digitalPaymentNotice = document.getElementById('digital-payment-notice');
    
    if (checkoutForm) {
        // Show/hide digital payment notice
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                if (this.value === 'cod') {
                    digitalPaymentNotice.style.display = 'none';
                } else {
                    digitalPaymentNotice.style.display = 'block';
                }
            });
        });
        
        // Form submission
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('address').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            if (!name || !phone || !address) {
                utils.showNotification('Please fill in all required fields', 'error');
                return;
            }
            
            // Validate phone number (Nepali format)
            const phoneRegex = /^(98|97|96)\d{8}$/;
            if (!phoneRegex.test(phone)) {
                utils.showNotification('Please enter a valid Nepali phone number', 'error');
                return;
            }
            
            // If digital payment, redirect to verification
            if (paymentMethod !== 'cod') {
                // Store form data temporarily
                sessionStorage.setItem('checkoutData', JSON.stringify({
                    name,
                    phone,
                    email: document.getElementById('email').value.trim(),
                    address,
                    landmark: document.getElementById('landmark').value.trim(),
                    instructions: document.getElementById('instructions').value.trim(),
                    paymentMethod
                }));
                
                // Redirect to verification
                window.location.href = '/verify';
                return;
            }
            
            // For COD, submit the form directly
            this.submit();
        });
    }
});