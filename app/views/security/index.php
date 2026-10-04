<?php
// app/views/security/index.php

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get user data to check if they have default password
$user_has_default_password = $security_settings['has_default_password'] ?? false;
?>

<div class="account-container">
    <div class="account-header">
        <h2>Security Settings</h2>
        <p>Manage your account security and privacy</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="security-content">
        <div class="security-sections">
            <!-- Password Change Section -->
            <div class="security-section">
                <h3><?php echo $user_has_default_password ? 'Set Your Password' : 'Change Password'; ?></h3>
                
                <?php if ($user_has_default_password): ?>
                    <div class="alert alert-info">
                        <strong>Default Password Detected:</strong> You are currently using the default password. 
                        Please set a new secure password for your account.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="/phool-delivery-platform/public_html/security/change-password" class="security-form" id="passwordForm">
                    <?php if (!$user_has_default_password): ?>
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <p class="form-help">Minimum 8 characters with letters and numbers</p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary" id="passwordSubmitBtn">
                        <?php echo $user_has_default_password ? 'Set New Password' : 'Change Password'; ?>
                    </button>
                           <a href="<?= $pathConfig->url('account') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid #333;
        border-radius: 6px;
        background-color: transparent;
        color: #333;
        font-size: 14px;
        text-decoration: none;
        transition: 0.2s ease-in-out;
   "
   onmouseover="this.style.backgroundColor='#827b7bff'; this.style.color='#fff';"
   onmouseout="this.style.backgroundColor='transparent'; this.style.color='#887979ff';"
>
    <i class="fas fa-arrow-left"></i> 
    <?= LanguageHelper::t('back_to_dashboard', 'Back') ?>
</a>
                </form>
            </div>

      
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    const submitBtn = document.getElementById('passwordSubmitBtn');
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate password strength
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return;
            }
            
            // Check if password contains both letters and numbers
            if (!/(?=.*[a-zA-Z])(?=.*[0-9])/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain both letters and numbers');
                return;
            }
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match');
                return;
            }
            
            // Disable button to prevent double submission
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
            }
        });
    }
});
</script>