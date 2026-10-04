// public/assets/js/admin.js

// Toggle the side navigation
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('main');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            sidebar.classList.toggle('collapsed');
            
            // Store sidebar state in localStorage
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
        
        // Check saved state
        if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
            document.body.classList.add('sb-sidenav-toggled');
            sidebar.classList.add('collapsed');
        }
    }
    
    // Enable Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Enable Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Confirmations for delete actions removed to avoid browser alert/confirm popups
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Auto-hide success messages after 5 seconds
    setTimeout(function() {
        const successAlerts = document.querySelectorAll('.alert-success');
        successAlerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Chart initialization function
function initCharts() {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Sales (NPR)',
                    data: [15000, 18000, 22000, 19000, 25000, 30000, 35000, 32000, 28000, 30000, 32000, 40000],
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    pointBackgroundColor: '#4e73df',
                    pointBorderColor: '#4e73df',
                    pointHoverBackgroundColor: '#4e73df',
                    pointHoverBorderColor: '#4e73df',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'NPR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Order Status Chart
    const statusCtx = document.getElementById('orderStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Delivered', 'Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Cancelled'],
                datasets: [{
                    data: [45, 15, 12, 10, 8, 10],
                    backgroundColor: [
                        '#1cc88a', '#f6c23e', '#36b9cc', '#4e73df', '#6f42c1', '#e74a3b'
                    ],
                    hoverBackgroundColor: [
                        '#17a673', '#dda20a', '#2c9faf', '#2e59d9', '#59319d', '#be2617'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },
                cutout: '70%'
            }
        });
    }
}

// Initialize charts when document is ready
document.addEventListener('DOMContentLoaded', initCharts);

// AJAX helper functions
const AdminAPI = {
    // GET request
    get: function(url, callback) {
        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => callback(null, data))
        .catch(error => callback(error, null));
    },
    
    // POST request
    post: function(url, data, callback) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => callback(null, data))
        .catch(error => callback(error, null));
    },
    
    // Update order status
    updateOrderStatus: function(orderId, status, callback) {
        this.post('/admin/api/orders/update-status', {orderId, status}, callback);
    },
    
    // Get order details
    getOrderDetails: function(orderId, callback) {
        this.get('/admin/api/orders/' + orderId, callback);
    }
};

// Utility functions
const AdminUtils = {
    // Format currency
    formatCurrency: function(amount, currency = 'NPR') {
        return currency + ' ' + parseFloat(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    // Format date
    formatDate: function(dateString, format = 'medium') {
        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: format === 'short' ? 'short' : 'long',
            day: 'numeric'
        };
        return date.toLocaleDateString(undefined, options);
    },
    
    // Format datetime
    formatDateTime: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    // Debounce function for search inputs
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Show notification
    showNotification: function(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Add to body
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token to all forms
    const forms = document.querySelectorAll('form');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
    if (csrfToken) {
        forms.forEach(form => {
            const existingToken = form.querySelector('input[name="_token"]');
            if (!existingToken) {
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = csrfToken;
                form.appendChild(tokenInput);
            }
        });
    }
    
    // Add loading state to buttons on form submit
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            }
        });
    });
    
    // Initialize search functionality with debounce
    const searchInputs = document.querySelectorAll('input[type="search"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', AdminUtils.debounce(function(e) {
            const searchValue = e.target.value;
            // Implement search functionality here
            console.log('Searching for:', searchValue);
        }, 300));
    });
});