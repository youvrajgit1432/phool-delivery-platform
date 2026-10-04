<?php
// app/views/otp/change-password.php

$phone = $phone ?? '';
$error_message = $error_message ?? '';
$success_message = $success_message ?? '';

if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $script_name = $_SERVER['SCRIPT_NAME'];
        $base_path = str_replace('/index.php', '', $script_name);
        $base_url = $protocol . '://' . $host . $base_path;
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }
}
?>

<div class="auth-container-wrapper">
    <div class="auth-visual">
        <div class="auth-visual-content">
            <h2>Secure Your Account 🔒</h2>
            <p>Set a new password for enhanced security</p>
            
            <div class="visual-graphic">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="100%" height="100%">
                    <path fill="#fff" d="M48 24h-4v-6c0-6.6-5.4-12-12-12s-12 5.4-12 12v6h-4c-2.2 0-4 1.8-4 4v24c0 2.2 1.8 4 4 4h32c2.2 0 4-1.8 4-4V28c0-2.2-1.8-4-4-4zm-18-6c0-3.3 2.7-6 6-6s6 2.7 6 6v6H30v-6zm12 24c0 1.1-.9 2-2 2h-8c-1.1 0-2-.9-2-2v-6c0-1.1.9-2 2-2h8c1.1 0 2 .9 2 2v6z"/>
                    <circle cx="32" cy="38" r="2" fill="#9c27b0"/>
                </svg>
            </div>
            
            <ul class="features-list">
                <li>Enhanced account security</li>
                <li>Protect your personal information</li>
                <li>Quick access to your account</li>
                <li>Peace of mind with secure login</li>
            </ul>
        </div>
    </div>
    
    <section class="auth-section">
        <div class="auth-container">
            <div class="auth-header">
                <h2>Set New Password</h2>
                <p>Choose a strong password to secure your account</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form id="passwordChangeForm" class="auth-form" action="/phool-delivery-platform/public_html/otp/process-password-change" method="POST">
                <div class="form-header">
                    <h3>Security Recommendation</h3>
                    <p>For your security, we recommend setting a new password.</p>
                </div>
                
                <div class="security-info">
                    <div class="security-alert">
                        <i class="icon-shield"></i>
                        <div>
                            <strong>Security Notice</strong>
                            <p>We recommend setting a strong password to protect your account. This applies to all users for enhanced security.</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" 
                           placeholder="Enter new password" minlength="6" required>
                    <small class="form-text">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                           placeholder="Confirm new password" minlength="6" required>
                    <small class="form-text">Re-enter your new password</small>
                </div>
                
                <div class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <small id="strengthText">Password strength</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="action" value="change" class="btn btn-primary btn-auth">
                        Set New Password & Login
                    </button>
                    
                    <button type="submit" name="action" value="skip" class="btn btn-secondary btn-auth">
                        Skip for Now
                    </button>
                </div>
                
                <div class="security-tips">
                    <h4>Password Tips:</h4>
                    <ul>
                        <li>Use at least 6 characters</li>
                        <li>Include numbers and letters</li>
                        <li>Avoid common words or phrases</li>
                        <li>Don't reuse passwords from other sites</li>
                    </ul>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>You can always change your password later in account settings.</p>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const form = document.getElementById('passwordChangeForm');
    
    // Password strength indicator
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 6) strength += 25;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
        if (password.match(/\d/)) strength += 25;
        if (password.match(/[^a-zA-Z\d]/)) strength += 25;
        
        strengthBar.style.width = strength + '%';
        
        if (strength < 50) {
            strengthBar.style.backgroundColor = '#e74c3c';
            strengthText.textContent = 'Weak password';
        } else if (strength < 75) {
            strengthBar.style.backgroundColor = '#f39c12';
            strengthText.textContent = 'Medium strength';
        } else {
            strengthBar.style.backgroundColor = '#27ae60';
            strengthText.textContent = 'Strong password';
        }
    });
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const action = document.activeElement.value || e.submitter.value;
        
        if (action === 'change') {
            if (newPassword.value.length < 6) {
                e.preventDefault();
                showError('Password must be at least 6 characters long');
                newPassword.focus();
                return;
            }
            
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                showError('Passwords do not match');
                confirmPassword.focus();
                return;
            }
        }
    });
    
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.textContent = message;
        
        const existingAlert = document.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        document.querySelector('.auth-header').after(alertDiv);
    }
    
    // Focus on first password field
    newPassword.focus();
});
</script>

<style>
.password-strength {
    margin: 1rem 0;
}

.strength-meter {
    width: 100%;
    height: 4px;
    background: #eee;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.security-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.security-alert i {
    font-size: 1.5rem;
    color: #f39c12;
}

.security-alert strong {
    display: block;
    margin-bottom: 0.5rem;
    color: #856404;
}

.security-alert p {
    margin: 0;
    color: #856404;
    font-size: 0.9rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin: 1.5rem 0;
}

.form-actions .btn {
    flex: 1;
}

.security-tips {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1.5rem;
}

.security-tips h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.security-tips ul {
    margin: 0;
    padding-left: 1.2rem;
    color: #7f8c8d;
}

.security-tips li {
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}
</style>