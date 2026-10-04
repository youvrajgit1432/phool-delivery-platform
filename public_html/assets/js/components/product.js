// public/assets/js/components/product.js

function initProducts() {
    // Product detail page quantity controls
    const quantityInput = document.getElementById('quantity');
    const decreaseBtn = document.querySelector('.quantity-btn.decrease');
    const increaseBtn = document.querySelector('.quantity-btn.increase');
    const detailTotal = document.getElementById('detail-total');
    const addToCartBtn = document.querySelector('.add-to-cart-detail');
    
    if (quantityInput && decreaseBtn && increaseBtn && detailTotal && addToCartBtn) {
        const price = parseFloat(addToCartBtn.dataset.price);
        const minQuantity = parseFloat(quantityInput.min);
        
        // Update total when quantity changes
        function updateTotal() {
            const quantity = parseFloat(quantityInput.value);
            const total = price * quantity;
            detailTotal.textContent = total.toFixed(2);
        }
        
        // Decrease quantity
        decreaseBtn.addEventListener('click', () => {
            let quantity = parseFloat(quantityInput.value);
            if (quantity > minQuantity) {
                quantity -= 0.5;
                quantityInput.value = quantity;
                updateTotal();
            }
        });
        
        // Increase quantity
        increaseBtn.addEventListener('click', () => {
            let quantity = parseFloat(quantityInput.value);
            quantity += 0.5;
            quantityInput.value = quantity;
            updateTotal();
        });
        
        // Input change
        quantityInput.addEventListener('change', () => {
            let quantity = parseFloat(quantityInput.value);
            if (quantity < minQuantity) {
                quantityInput.value = minQuantity;
            }
            updateTotal();
        });
        
        // Add to cart with custom quantity
        addToCartBtn.addEventListener('click', () => {
            const quantity = parseFloat(quantityInput.value);
            const productId = addToCartBtn.dataset.id;
            const productName = addToCartBtn.dataset.name;
            const productPrice = parseFloat(addToCartBtn.dataset.price);
            
            // Add to cart with custom quantity
            for (let i = 0; i < quantity; i += 0.5) {
                addToCart(productId, productName, productPrice);
            }
            
            utils.showNotification(`${quantity} kg of ${productName} added to cart`);
        });
    }
    
    // Products page filtering
    const categoryFilter = document.getElementById('category-filter');
    const sortFilter = document.getElementById('sort-filter');
    const productCards = document.querySelectorAll('.product-card');
    
    if (categoryFilter && sortFilter && productCards.length > 0) {
        categoryFilter.addEventListener('change', filterProducts);
        sortFilter.addEventListener('change', filterProducts);
        
        function filterProducts() {
            const category = categoryFilter.value;
            const sortBy = sortFilter.value;
            
            // Filter by category
            productCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Sort products
            const visibleProducts = Array.from(productCards).filter(card => 
                card.style.display !== 'none'
            );
            
            visibleProducts.sort((a, b) => {
                switch(sortBy) {
                    case 'price-low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price-high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'name':
                        return a.querySelector('.product-title').textContent.localeCompare(
                            b.querySelector('.product-title').textContent
                        );
                    default: // popular
                        return 0; // Keep original order
                }
            });
            
            // Reorder products in DOM
            const productsGrid = document.querySelector('.products-grid');
            visibleProducts.forEach(product => {
                productsGrid.appendChild(product);
            });
        }
    }
}




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