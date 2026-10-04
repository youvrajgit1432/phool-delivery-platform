<?php
// app/views/log/verify-otp.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

$error_message = $error_message ?? '';
$success_message = $success_message ?? '';
$phone = $phone ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Verify OTP'); ?></title>
    <link rel="stylesheet" href="<?php echo $pathConfig->url('assets/css/auth.css'); ?>">
</head>
<body>
    <div class="auth-container-wrapper">
        <section class="auth-section">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Verify OTP 🔒</h2>
                    <p>Enter the 6-digit code sent to <?php echo htmlspecialchars($phone); ?></p>
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

                <form class="auth-form" action="<?php echo $pathConfig->url('otp/verify-otp-process'); ?>" method="POST">
                    <div class="form-group">
                        <label for="otp" class="form-label">Enter OTP *</label>
                        <input type="text" id="otp" name="otp" class="form-input" 
                               placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}" required>
                        <small class="form-text">Check your SMS messages for the OTP</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-auth">Verify & Login</button>
                    
                    <div class="otp-actions">
                        <a href="<?php echo $pathConfig->url('otp/resend-otp'); ?>" class="resend-link">Resend OTP</a>
                        <a href="<?php echo $pathConfig->url('otp/login'); ?>" class="back-link">← Back to login</a>
                    </div>
                </form>
                
                <div class="auth-footer">
                    <p>Didn't receive the OTP? <a href="<?php echo $pathConfig->url('otp/resend-otp'); ?>">Resend OTP</a></p>
                    <p>Having trouble? <a href="<?php echo $pathConfig->url('login'); ?>">Try password login</a></p>
                </div>
            </div>
        </section>
    </div>

    <style>
    .otp-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
    }
    
    .resend-link, .back-link {
        color: #9c27b0;
        text-decoration: none;
        font-size: 14px;
    }
    
    .resend-link:hover, .back-link:hover {
        text-decoration: underline;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.focus();
            
            otpInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                e.target.value = value;
            });
        }
        
        // Auto-submit when 6 digits are entered
        otpInput.addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                e.target.form.submit();
            }
        });
    });
    </script>
</body>
</html>