// public_html/assets/js/components/cart.js

let cart = JSON.parse(localStorage.getItem('phool_cart')) || [];

// Production environment detection - use global if available, otherwise detect
var isProduction = window.isProduction !== undefined ? window.isProduction : (window.location.hostname.includes('phooldelivery.com') || window.location.protocol === 'https:');
// DEBUG LOGS COMPLETELY DISABLED - no console output in any environment
var debugLog = function() {};
// Expose globally to prevent redeclaration errors in other scripts
window.isProduction = isProduction;
window.debugLog = function() {};

function initCart() {
    debugLog('Cart initialization started');
    updateCartCount();
    
    // Add to cart buttons
    document.addEventListener('click', function(e) {
        // Handle "Add to Cart" buttons
        if (e.target.classList.contains('add-to-cart')) {
            const productId = e.target.getAttribute('data-id');
            const productName = e.target.getAttribute('data-name');
            const productPrice = parseFloat(e.target.getAttribute('data-price'));
            
            addToCart(productId, productName, productPrice);
            return;
        }
        
        // Handle quick add buttons
        if (e.target.classList.contains('quick-add-btn')) {
            const productId = e.target.getAttribute('data-id');
            const productName = e.target.getAttribute('data-name');
            const productPrice = parseFloat(e.target.getAttribute('data-price'));
            
            quickAddToCart(e.target, productId, productName, productPrice);
            return;
        }
        
        // Handle carousel order buttons
        if (e.target.classList.contains('carousel-order')) {
            const productName = e.target.getAttribute('data-product');
            const weight = e.target.getAttribute('data-weight');
            
            // For demo purposes, we'll add a generic product
            addToCart('carousel-' + Date.now(), productName, 300, parseInt(weight));
            return;
        }
    });
    
    debugLog('Cart initialized');
}

function addToCart(id, name, price, quantity = 1) {
    debugLog("Adding to cart:", id, name, price, quantity);
    
    // Determine the correct cart endpoint based on environment
    const cartEndpoint = window.isOnline ? '/cart/add' : '/phool-delivery/public_html/cart/add';
    
    fetch(cartEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${id}&product_name=${encodeURIComponent(name)}&product_price=${price}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update local cart from server response if needed
            if (data.cart_items) {
                cart = data.cart_items;
                localStorage.setItem('phool_cart', JSON.stringify(cart));
            }
            
            updateCartCount(data.cart_count);
            utils.showNotification(`Added ${quantity}kg ${name} to cart!`);
            
            // Animation effect
            const addButton = document.querySelector(`[data-id="${id}"]`);
            if (addButton) {
                addButton.classList.add('adding');
                setTimeout(() => {
                    addButton.classList.remove('adding');
                }, 1000);
            }
        } else {
            utils.showNotification(data.message, 'error');
        }
    })
    .catch(error => {
    
    });
}

function quickAddToCart(button, id, name, price) {
    // Animation for quick add
    button.textContent = "✓";
    button.classList.add('added');
    
    // Add to cart
    addToCart(id, name, price, 1);
    
    // Reset button after animation
    setTimeout(() => {
        button.textContent = "+";
        button.classList.remove('added');
    }, 1000);
}

function updateCartCount(count = null) {
    const cartCountElements = document.querySelectorAll('.cart-count, .mobile-cart-count');
    const totalItems = count !== null ? count : cart.reduce((total, item) => total + item.quantity, 0);
    
    cartCountElements.forEach(element => {
        element.textContent = totalItems;
        
        // Show/hide based on count
        if (totalItems > 0) {
            element.style.display = 'flex';
        } else {
            element.style.display = 'none';
        }
    });
    
    // Update localStorage
    localStorage.setItem('phool_cart', JSON.stringify(cart));
}

function getProductImage(productName) {
    const imageMap = {
        'Marigold': 'f9.jpg',
        'Rose': 'f11.jpg',
        'Jasmine': 'f3.jpg',
        'Seasonal Bouquet': 'f6.jpg',
        'Mixed Flowers': 'f12.jpg'
    };
    
    return imageMap[productName] || 'default.jpg';
}

function getCart() {
    return cart;
}

function clearCart() {
    // Determine the correct cart endpoint based on environment
    const cartEndpoint = window.isOnline ? '/cart/clear' : '/phool-delivery/public_html/cart/clear';
    
    fetch(cartEndpoint, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            cart = [];
            localStorage.removeItem('phool_cart');
            updateCartCount(0);
            utils.showNotification('Cart cleared!');
        } else {
            utils.showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        utils.showNotification('Failed to clear cart', 'error');
    });
}

function removeFromCart(productId) {
    // Determine the correct cart endpoint based on environment
    const cartEndpoint = window.isOnline ? '/cart/remove' : '/phool-delivery/public_html/cart/remove';
    
    fetch(cartEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update local cart from server response if needed
            if (data.cart_items) {
                cart = data.cart_items;
                localStorage.setItem('phool_cart', JSON.stringify(cart));
            }
            
            updateCartCount(data.cart_count);
            utils.showNotification('Item removed from cart!');
        } else {
            utils.showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        utils.showNotification('Failed to remove item', 'error');
    });
}

// Initialize environment detection
function detectEnvironment() {
    const host = window.location.host;
    const path = window.location.pathname;
    
    // Check if we're on online hosting
    window.isOnline = (
        host.includes('phooldelivery.com') ||
        host.includes('admin.phooldelivery.com') ||
        path.includes('/home2/phooldel')
    );
    
    // Environment detection complete (logging suppressed)
}

// Make functions available globally
window.initCart = initCart;
window.addToCart = addToCart;
window.getCart = getCart;
window.clearCart = clearCart;
window.removeFromCart = removeFromCart;
window.updateCartCount = updateCartCount;
window.detectEnvironment = detectEnvironment;

// Auto-initialize environment detection
detectEnvironment();