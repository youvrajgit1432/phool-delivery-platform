/**
 * Vendor Panel JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if using Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

/**
 * Make AJAX request
 */
function makeRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    return fetch(url, options)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'error');
                return null;
            }
            return data;
        })
        .catch(error => {
            showAlert('An error occurred: ' + error.message, 'error');
            return null;
        });
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of page
    document.body.insertBefore(alertDiv, document.body.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR'
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-IN', options);
}

/**
 * Delete item confirmation
 */
function deleteItem(id, itemName = 'item') {
    if (confirm(`Are you sure you want to delete this ${itemName}?`)) {
        makeRequest(`/phool-delivery-platform/vendor-panel/public/ajax/product-delete.php`, 'POST', { id })
            .then(result => {
                if (result && result.success) {
                    showAlert('Item deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            });
    }
}

/**
 * Handle form submission via AJAX
 */
function handleFormSubmit(formId, endpoint) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        makeRequest(endpoint, 'POST', data)
            .then(result => {
                if (result && result.success) {
                    showAlert('Successfully saved', 'success');
                    setTimeout(() => {
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            location.reload();
                        }
                    }, 1500);
                }
            });
    });
}
