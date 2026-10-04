<?php
// app/views/account/profile.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('my_profile', 'My Profile') . " - Phool Delivery";
$base_url = $pathConfig->get('base_url');
$assets_path = $pathConfig->get('assets');

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get available cities for dropdown
$available_cities = [];
try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT id, city_name FROM delivery_cities WHERE standard_delivery_fee >= 0 ORDER BY city_name");
    $stmt->execute();
    $available_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
    // Fallback cities
    $available_cities = [
        ['id' => 1, 'city_name' => 'Banepa'],
        ['id' => 2, 'city_name' => 'Kathmandu'],
        ['id' => 3, 'city_name' => 'Bhaktapur'],
        ['id' => 4, 'city_name' => 'Pokhara']
    ];
}
?>

<div class="account-container">
    <div class="account-header" data-aos="fade-up" data-aos-delay="100">
        <h2><?= LanguageHelper::t('my_profile', 'My Profile') ?></h2>
        <p><?= LanguageHelper::t('manage_profile_info', 'Manage your personal information and account settings') ?></p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success auto-hide" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error auto-hide" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="profile-content">
        <form method="POST" class="profile-form" id="profileForm" data-aos="zoom-in" data-aos-delay="200">
            <div class="form-section" data-aos="fade-up" data-aos-delay="250">
                <h3><?= LanguageHelper::t('personal_information', 'Personal Information') ?></h3>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                    <label for="name"><?= LanguageHelper::t('full_name', 'Full Name') ?> *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group" data-aos="fade-up" data-aos-delay="350">
                    <label for="email"><?= LanguageHelper::t('email_address', 'Email Address') ?> *</label>
                    <div class="email-verification-container">
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        <div class="verification-status">
                            <?php if (isset($user['email_verified']) && $user['email_verified']): ?>
                                <span class="verification-badge verified"><?= LanguageHelper::t('verified', 'Verified') ?></span>
                            <?php else: ?>
                                <span class="verification-badge not-verified"><?= LanguageHelper::t('not_verified', 'Not Verified') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                    <label for="phone"><?= LanguageHelper::t('phone_number', 'Phone Number') ?> *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    <?php if (isset($user['phone_verified']) && !$user['phone_verified']): ?>
                        <span class="verification-badge not-verified"><?= LanguageHelper::t('not_verified', 'Not Verified') ?></span>
                    <?php else: ?>
                        <span class="verification-badge verified"><?= LanguageHelper::t('verified', 'Verified') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Address Information Section - NEW -->
            <div class="form-section" data-aos="fade-up" data-aos-delay="450">
                <h3><?= LanguageHelper::t('address_information', 'Address Information') ?></h3>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="500">
                    <label for="city"><?= LanguageHelper::t('city', 'City') ?> *</label>
                    <select id="city" name="city" required>
                        <option value=""><?= LanguageHelper::t('select_city', 'Select your city') ?></option>
                        <?php foreach ($available_cities as $city): ?>
                            <option value="<?= $city['id'] ?>" 
                                <?= (isset($user['city_id']) && $user['city_id'] == $city['id']) || 
                                    (!isset($user['city_id']) && $city['city_name'] == 'Kathmandu') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city['city_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" data-aos="fade-up" data-aos-delay="600">
                    <label for="address"><?= LanguageHelper::t('address', 'Address') ?></label>
                    <textarea id="address" name="address" rows="3" placeholder="<?= LanguageHelper::t('enter_address', 'Enter your complete address') ?>"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    <small class="form-help"><?= LanguageHelper::t('address_help', 'Include house number, street name, ward, area, and any landmarks') ?></small>
                </div>
            </div>

            <!-- Email Verification Section -->
            <?php if (!isset($user['email_verified']) || !$user['email_verified']): ?>
            <div class="verification-section" data-aos="fade-up" data-aos-delay="650">
                <h3><?= LanguageHelper::t('email_verification', 'Email Verification') ?></h3>
                <p class="verification-description"><?= LanguageHelper::t('verify_email_description', 'Verify your email address to secure your account') ?></p>
            
                <div class="otp-verification-section">
                    <div class="otp-actions">
                        <button type="button" id="sendOTPBtn" class="btn btn-primary" data-aos="zoom-in" data-aos-delay="700">
                            <?= LanguageHelper::t('send_otp', 'Send OTP') ?>
                        </button>
                    </div>

                    <div class="otp-input-section" style="display: none;" data-aos="fade-up" data-aos-delay="750">
                        <div class="form-group">
                            <label for="otpInput"><?= LanguageHelper::t('enter_otp', 'Enter OTP') ?></label>
                            <input type="text" id="otpInput" maxlength="6" placeholder="<?= LanguageHelper::t('enter_6_digit_otp', 'Enter 6-digit OTP') ?>" class="otp-input-field">
                        </div>
                        
                        <div class="otp-actions-group">
                            <button type="button" id="verifyOTPBtn" class="btn btn-success" disabled data-aos="zoom-in" data-aos-delay="800">
                                <?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>
                            </button>
                            <button type="button" id="resendOTPBtn" class="btn btn-secondary" style="display: none;" data-aos="zoom-in" data-aos-delay="850">
                                <?= LanguageHelper::t('resend_otp', 'Resend OTP') ?>
                            </button>
                        </div>
                        
                        <div class="otp-timer" style="display: none;" data-aos="fade-up" data-aos-delay="900">
                            <span id="otpTimer">10:00</span> <?= LanguageHelper::t('minutes_remaining', 'minutes remaining') ?>
                        </div>
                    </div>

                    <div id="otpMessage" class="otp-message" style="margin-top: 15px;"></div>
                </div>
            </div>
            <?php else: ?>
            <!-- Modified: Added auto-hide class and made it hidden by default, will show only when needed -->
            <div class="verification-complete auto-hide" style="display: none;" data-aos="zoom-in" data-aos-delay="650">
                <div class="success-icon">✓</div>
                <h3><?= LanguageHelper::t('email_verified', 'Email Verified Successfully!') ?></h3>
                <p><?= LanguageHelper::t('email_verified_message', 'Your email address has been successfully verified.') ?></p>
            </div>
            <?php endif; ?>

            <div class="form-actions" >
                <button type="submit" class="btn btn-primary" id="saveChangesBtn">
                    <?= LanguageHelper::t('save_changes', 'Save Changes') ?>
                </button>
                <a href="<?= $pathConfig->url('account') ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> 
                    <?= LanguageHelper::t('back_to_dashboard', 'Back to Dashboard') ?>
                </a>
            </div>
        </form>
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

    // Auto-hide success/error messages after 5 seconds
    const autoHideMessages = document.querySelectorAll('.auto-hide');
    autoHideMessages.forEach(message => {
        if (message.textContent.trim() !== '') {
            message.style.display = 'block';
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            }, 5000);
        } else {
            message.style.display = 'none';
        }
    });

    // Auto-submit OTP when 6 digits are entered
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            const otp = this.value.replace(/\D/g, '');
            this.value = otp;
            
            if (verifyOTPBtn) {
                verifyOTPBtn.disabled = otp.length !== 6;
            }
            
            // Auto-submit when 6 digits are entered
            if (otp.length === 6 && verifyOTPBtn && !verifyOTPBtn.disabled) {
                verifyOTP();
            }
        });
    }

    // Send OTP
    if (sendOTPBtn) {
        sendOTPBtn.addEventListener('click', function() {
            sendOTPBtn.disabled = true;
            sendOTPBtn.textContent = '<?= LanguageHelper::t('sending', 'Sending...') ?>';
            otpMessage.innerHTML = '';

            // Use PathConfig for dynamic URL
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
                    
                    // Focus on OTP input field
                    if (otpInput) {
                        setTimeout(() => {
                            otpInput.focus();
                        }, 100);
                    }
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

    // Verify OTP
    if (verifyOTPBtn) {
        verifyOTPBtn.addEventListener('click', function() {
            verifyOTP();
        });
    }

    // Verify OTP function
    function verifyOTP() {
        const otp = otpInput.value;
        
        if (otp.length !== 6) {
            showMessage('<?= LanguageHelper::t('please_enter_6_digit_otp', 'Please enter a 6-digit OTP') ?>', 'error');
            return;
        }
        
        if (verifyOTPBtn) {
            verifyOTPBtn.disabled = true;
            verifyOTPBtn.textContent = '<?= LanguageHelper::t('verifying', 'Verifying...') ?>';
        }
        otpMessage.innerHTML = '';

        // Use PathConfig for dynamic URL
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
                
                // Show the verification complete message temporarily
                const verificationComplete = document.querySelector('.verification-complete');
                if (verificationComplete) {
                    verificationComplete.style.display = 'block';
                    setTimeout(() => {
                        verificationComplete.style.opacity = '0';
                        verificationComplete.style.transition = 'opacity 0.5s ease';
                        setTimeout(() => {
                            verificationComplete.style.display = 'none';
                        }, 500);
                    }, 3000);
                }
                
                // Reload page to show verified status after a delay
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage(data.message, 'error');
                if (verifyOTPBtn) {
                    verifyOTPBtn.disabled = false;
                    verifyOTPBtn.textContent = '<?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>';
                }
                // Clear OTP input on failure
                if (otpInput) {
                    otpInput.value = '';
                    otpInput.focus();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>', 'error');
            if (verifyOTPBtn) {
                verifyOTPBtn.disabled = false;
                verifyOTPBtn.textContent = '<?= LanguageHelper::t('verify_otp', 'Verify OTP') ?>';
            }
        });
    }

    // Resend OTP
    if (resendOTPBtn) {
        resendOTPBtn.addEventListener('click', function() {
            resendOTPBtn.disabled = true;
            resendOTPBtn.textContent = '<?= LanguageHelper::t('sending', 'Sending...') ?>';
            otpMessage.innerHTML = '';

            // Use PathConfig for dynamic URL
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
                    if (otpInput) {
                        otpInput.value = '';
                        otpInput.focus();
                    }
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
        otpMessage.innerHTML = `<div class="alert alert-${type} auto-hide">${message}</div>`;
        
        // Auto-hide OTP messages after 5 seconds
        const otpAlert = otpMessage.querySelector('.alert');
        if (otpAlert) {
            setTimeout(() => {
                otpAlert.style.opacity = '0';
                otpAlert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    otpAlert.remove();
                }, 500);
            }, 5000);
        }
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

    // Form submission handling
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const saveBtn = document.getElementById('saveChangesBtn');
            const originalText = saveBtn.innerHTML;
            
            // Show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<?= LanguageHelper::t('saving', 'Saving...') ?>';
            
            // Submit form normally (will redirect with messages)
            this.submit();
        });
    }
});
</script>
 
<style>
.verification-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 10px;
}

.verification-badge.verified {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.verification-badge.not-verified {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.email-verification-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.verification-status {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.verification-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    border-left: 4px solid #007bff;
}

.verification-description {
    color: #666;
    margin-bottom: 15px;
}

.verification-steps {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
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
    margin-top: 20px;
}

.otp-actions {
    margin-bottom: 15px;
}

.otp-input-section {
    background: white;
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
}

.verification-complete {
    text-align: center;
    padding: 20px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    margin: 20px 0;
    transition: opacity 0.5s ease;
}

.success-icon {
    font-size: 32px;
    color: #28a745;
    margin-bottom: 10px;
}

.verification-complete h3 {
    color: #155724;
    margin-bottom: 10px;
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

.alert {
    padding: 12px;
    border-radius: 6px;
    margin: 10px 0;
    transition: opacity 0.5s ease;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.alert-error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.auto-hide {
    transition: opacity 0.5s ease;
}

@media (max-width: 768px) {
    .email-verification-container {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .otp-actions-group {
        flex-direction: column;
    }
    
    .otp-input-field {
        width: 100%;
    }
    
    .verification-status {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>