<?php
// app/views/account/verify-email.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

$page_title = LanguageHelper::t('email_verification', 'Email Verification') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="account-container">
    <div class="account-header">
        <h2><?= LanguageHelper::t('email_verification', 'Email Verification') ?></h2>
        <p><?= LanguageHelper::t('verify_email_description', 'Verify your email address to secure your account') ?></p>
        <a href="<?= $pathConfig->url('account/profile') ?>" class="back-to-profile">
            &larr; <?= LanguageHelper::t('back_to_profile', 'Back to Profile') ?>
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="verification-content">
        <div class="verification-info">
            <div class="email-display">
                <strong><?= LanguageHelper::t('email_address', 'Email Address') ?>:</strong>
                <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                <?php if (isset($user['email_verified']) && $user['email_verified']): ?>
                    <span class="verification-badge verified"><?= LanguageHelper::t('verified', 'Verified') ?></span>
                <?php else: ?>
                    <span class="verification-badge not-verified"><?= LanguageHelper::t('not_verified', 'Not Verified') ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (!isset($user['email_verified']) || !$user['email_verified']): ?>
            <div class="verification-steps">
                <h3><?= LanguageHelper::t('verification_steps', 'Verification Steps') ?>:</h3>
                <ol>
                    <li><?= LanguageHelper::t('step_1_send_otp', 'Click "Send OTP" to receive a verification code') ?></li>
                    <li><?= LanguageHelper::t('step_2_check_email', 'Check your email for the 6-digit OTP') ?></li>
                    <li><?= LanguageHelper::t('step_3_enter_otp', 'Enter the OTP and click "Verify"') ?></li>
                </ol>
            </div>

            <div class="otp-verification-section">
                <div class="otp-actions">
                    <button type="button" id="sendOTPBtn" class="btn btn-primary">
                        <?= LanguageHelper::t('send_otp', 'Send OTP') ?>
                    </button>
                </div>

                <div class="otp-input-section" style="display: none;">
                    <div class="form-group">
                        <label for="otpInput"><?= LanguageHelper::t('enter_otp', 'Enter OTP') ?></label>
                        <input type="text" id="otpInput" maxlength="6" placeholder="<?= LanguageHelper::t('enter_6_digit_otp', 'Enter 6-digit OTP') ?>" class="otp-input-field">
                    </div>
                    
                    <div class="otp-actions-group">
                        <button type="button" id="verifyOTPBtn" class="btn btn-success" disabled>
                            <?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>
                        </button>
                        <button type="button" id="resendOTPBtn" class="btn btn-secondary" style="display: none;">
                            <?= LanguageHelper::t('resend_otp', 'Resend OTP') ?>
                        </button>
                    </div>
                    
                    <div class="otp-timer" style="display: none;">
                        <span id="otpTimer">10:00</span> <?= LanguageHelper::t('minutes_remaining', 'minutes remaining') ?>
                    </div>
                </div>

                <div id="otpMessage" class="otp-message" style="margin-top: 15px;"></div>
            </div>
            <?php else: ?>
            <div class="verification-complete">
                <div class="success-icon">✓</div>
                <h3><?= LanguageHelper::t('email_verified', 'Email Verified Successfully!') ?></h3>
                <p><?= LanguageHelper::t('email_verified_message', 'Your email address has been successfully verified.') ?></p>
                <a href="<?= $pathConfig->url('account/profile') ?>" class="btn btn-primary">
                    <?= LanguageHelper::t('back_to_profile', 'Back to Profile') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
 
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sendOTPBtn = document.getElementById('sendOTPBtn');
    const verifyOTPBtn = document.getElementById('verifyOTPBtn');
    const resendOTPBtn = document.getElementById('resendOTPBtn');
    const otpInput = document.getElementById('otpInput');
    const otpTimer = document.getElementById('otpTimer');
    const otpMessage = document.getElementById('otpMessage');
    const otpInputSection = document.querySelector('.otp-input-section');
    const otpTimerDisplay = document.querySelector('.otp-timer');
    
    let countdown;
    let timeLeft = 600;

    // Send OTP
    if (sendOTPBtn) {
        sendOTPBtn.addEventListener('click', function() {
            sendOTPBtn.disabled = true;
            sendOTPBtn.textContent = '<?= LanguageHelper::t('sending', 'Sending...') ?>';
            otpMessage.innerHTML = '';

            fetch('<?= $pathConfig->url('account/profile/send-otp') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    otpInputSection.style.display = 'block';
                    otpTimerDisplay.style.display = 'block';
                    sendOTPBtn.style.display = 'none';
                    resendOTPBtn.style.display = 'inline-block';
                    startTimer();
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message, 'error');
                    sendOTPBtn.disabled = false;
                    sendOTPBtn.textContent = '<?= LanguageHelper::t('send_otp', 'Send OTP') ?>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>', 'error');
                sendOTPBtn.disabled = false;
                sendOTPBtn.textContent = '<?= LanguageHelper::t('send_otp', 'Send OTP') ?>';
            });
        });
    }

    // OTP input validation
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            const otp = this.value.replace(/\D/g, '');
            this.value = otp;
            if (verifyOTPBtn) {
                verifyOTPBtn.disabled = otp.length !== 6;
            }
        });
    }

    // Verify OTP
    if (verifyOTPBtn) {
        verifyOTPBtn.addEventListener('click', function() {
            const otp = otpInput.value;
            
            if (otp.length !== 6) {
                showMessage('<?= LanguageHelper::t('please_enter_6_digit_otp', 'Please enter a 6-digit OTP') ?>', 'error');
                return;
            }
            
            verifyOTPBtn.disabled = true;
            verifyOTPBtn.textContent = '<?= LanguageHelper::t('verifying', 'Verifying...') ?>';
            otpMessage.innerHTML = '';

            fetch('<?= $pathConfig->url('account/profile/verify-otp') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ otp: otp })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    clearInterval(countdown);
                    
                    setTimeout(function() {
                        window.location.href = '<?= $pathConfig->url('account/profile') ?>';
                    }, 2000);
                } else {
                    showMessage(data.message, 'error');
                    verifyOTPBtn.disabled = false;
                    verifyOTPBtn.textContent = '<?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>', 'error');
                verifyOTPBtn.disabled = false;
                verifyOTPBtn.textContent = '<?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>';
            });
        });
    }

    // Resend OTP
    if (resendOTPBtn) {
        resendOTPBtn.addEventListener('click', function() {
            resendOTPBtn.disabled = true;
            resendOTPBtn.textContent = '<?= LanguageHelper::t('sending', 'Sending...') ?>';
            otpMessage.innerHTML = '';

            fetch('<?= $pathConfig->url('account/profile/send-otp') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resetTimer();
                    otpInput.value = '';
                    if (verifyOTPBtn) verifyOTPBtn.disabled = true;
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message, 'error');
                }
                resendOTPBtn.disabled = false;
                resendOTPBtn.textContent = '<?= LanguageHelper::t('resend_otp', 'Resend OTP') ?>';
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>', 'error');
                resendOTPBtn.disabled = false;
                resendOTPBtn.textContent = '<?= LanguageHelper::t('resend_otp', 'Resend OTP') ?>';
            });
        });
    }

    function showMessage(message, type) {
        otpMessage.innerHTML = `<div class="${type}">${message}</div>`;
    }

    function startTimer() {
        timeLeft = 600;
        updateTimerDisplay();
        countdown = setInterval(function() {
            timeLeft--;
            updateTimerDisplay();
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                if (resendOTPBtn) resendOTPBtn.disabled = false;
                if (verifyOTPBtn) verifyOTPBtn.disabled = true;
            }
        }, 1000);
    }

    function resetTimer() {
        clearInterval(countdown);
        startTimer();
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        otpTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            otpTimer.textContent = '00:00';
            otpTimer.style.color = '#dc3545';
        } else if (timeLeft <= 60) {
            otpTimer.style.color = '#dc3545';
        } else {
            otpTimer.style.color = '#666';
        }
    }
});
</script>
<style>
.back-to-profile {
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
    margin-top: 10px;
    display: inline-block;
}

.back-to-profile:hover {
    text-decoration: underline;
}

.verification-info {
    max-width: 500px;
    margin: 0 auto;
}

.email-display {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.verification-steps {
    background: #e7f3ff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.verification-steps ol {
    margin: 10px 0 0 0;
    padding-left: 20px;
}

.verification-steps li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.otp-verification-section {
    margin-top: 30px;
}

.otp-actions {
    margin-bottom: 20px;
}

.otp-input-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.otp-input-field {
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    width: 200px;
    text-align: center;
    font-size: 18px;
    letter-spacing: 3px;
    font-weight: bold;
}

.otp-input-field:focus {
    border-color: #007bff;
    outline: none;
}

.otp-actions-group {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.otp-timer {
    font-size: 14px;
    color: #dc3545;
    font-weight: bold;
    text-align: center;
}

.otp-message {
    font-size: 14px;
    padding: 12px;
    border-radius: 6px;
}

.otp-message .success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.otp-message .error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.verification-complete {
    text-align: center;
    padding: 40px 20px;
}

.success-icon {
    font-size: 48px;
    color: #28a745;
    margin-bottom: 20px;
}

.verification-complete h3 {
    color: #28a745;
    margin-bottom: 15px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #1e7e34;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .email-display {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .otp-actions-group {
        flex-direction: column;
    }
    
    .otp-input-field {
        width: 100%;
    }
}
</style>