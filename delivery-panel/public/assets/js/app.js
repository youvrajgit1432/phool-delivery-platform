/**
 * Phool Delivery - Main JavaScript
 * Delivery Rider Panel
 */

// Show notification
function showNotification(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.content-wrapper');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// AJAX request helper
function makeRequest(url, method = 'GET', data = null) {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: data ? JSON.stringify(data) : null
    }).then(response => response.json());
}

// Format currency (NPR)
function formatCurrency(amount) {
    return 'NPR ' + new Intl.NumberFormat('en-NP', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-NP', options);
}

// Calculate distance
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return (R * c).toFixed(2);
}

// Accept Order
document.addEventListener('DOMContentLoaded', function() {
    // Accept order buttons
    const acceptButtons = document.querySelectorAll('.accept-order');
    acceptButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (confirm('Accept this order?')) {
                makeRequest(window.APP_PUBLIC + '/ajax/accept-order.php', 'POST', {
                    order_id: orderId
                }).then(response => {
                    if (response.success) {
                        showNotification('Order accepted! Proceeding to pickup...', 'success');
                        setTimeout(() => {
                            window.location.href = window.APP_URL + '/orders/active';
                        }, 1500);
                    } else {
                        showNotification(response.message || 'Failed to accept order', 'danger');
                    }
                });
            }
        });
    });

    // Availability toggle
    const availabilityToggle = document.getElementById('availabilityToggle');
    if (availabilityToggle) {
        availabilityToggle.addEventListener('change', function() {
            const status = this.checked ? 'online' : 'offline';
            makeRequest(window.APP_PUBLIC + '/ajax/toggle-availability.php', 'POST', {
                status: status
            }).then(response => {
                if (response.success) {
                    showNotification('Status updated to ' + status, 'success');
                } else {
                    showNotification('Failed to update status', 'danger');
                    this.checked = !this.checked;
                }
            });
        });
    }

    // Real-time order updates (polling)
    function checkForNewOrders() {
        if (window.location.pathname.includes('/orders/assigned')) {
            makeRequest(window.APP_PUBLIC + '/ajax/check-new-orders.php', 'GET')
                .then(response => {
                    if (response.has_new_orders) {
                        showNotification('New orders available! Refreshing...', 'info');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                });
        }
    }

    // Poll every 30 seconds
    setInterval(checkForNewOrders, 30000);
});

// Track location
function startLocationTracking() {
    if ('geolocation' in navigator) {
        navigator.geolocation.watchPosition(
            function(position) {
                const { latitude, longitude } = position.coords;
                
                // Send location to server
                makeRequest(window.APP_PUBLIC + '/ajax/update-location.php', 'POST', {
                    latitude: latitude,
                    longitude: longitude,
                    timestamp: new Date().toISOString()
                });
            },
            function(error) {
                console.error('Location tracking error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});

// Responsive menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.querySelector('.navbar-toggler');
    if (toggler) {
        toggler.addEventListener('click', function() {
            const menu = document.querySelector('#navbarNav');
            if (menu) {
                menu.classList.toggle('show');
            }
        });
    }
});

// Initialize tooltips (Bootstrap)
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Log current status
console.log('Phool Delivery Rider Panel - JavaScript loaded');
