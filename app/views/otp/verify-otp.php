<?php
// app/views/otp/verify-otp.php
// This page is designed to work within the main layout (header/footer)

// Get data from controller
$phone = $phone ?? '';
$error_message = $error_message ?? '';
$success_message = $success_message ?? '';

// Use PathConfig for URLs
$pathConfig = PathConfig::getInstance();

// Set page title for header
$page_title = 'Verify OTP - Phool Delivery';
$meta_description = 'Verify your phone number with OTP to complete authentication';
?>

<!-- OTP Verification Page -->
<div class="otp-verification-container">
    <div class="otp-wrapper">
        <!-- Left Section - Visual (Hidden on Mobile) -->
        <div class="otp-visual-section">
            <div class="otp-visual-content" data-aos="fade-right" data-aos-delay="100">
                <h2 data-aos="fade-up" data-aos-delay="200">Secure OTP Verification</h2>
                <p data-aos="fade-up" data-aos-delay="300">Enter the verification code sent to your phone</p>
                
                <div class="otp-visual-graphic" data-aos="zoom-in" data-aos-delay="400">
                    <i class="fas fa-shield-alt"></i>
                </div>
                
                <ul class="otp-features-list">
                    <li data-aos="fade-right" data-aos-delay="500"><i class="fas fa-check"></i> Secure one-time password verification</li>
                    <li data-aos="fade-right" data-aos-delay="600"><i class="fas fa-check"></i> Valid for 10 minutes only</li>
                    <li data-aos="fade-right" data-aos-delay="700"><i class="fas fa-check"></i> Instant access to your account</li>
                    <li data-aos="fade-right" data-aos-delay="800"><i class="fas fa-check"></i> Enhanced security protection</li>
                </ul>
            </div>
        </div>

        <!-- Right Section - Form -->
        <div class="otp-form-section">
            <div class="otp-form-container">
                <div class="otp-form-header" data-aos="fade-up" data-aos-delay="100">
                    <h2><i class="fas fa-shield-alt"></i> Verify Your Identity</h2>
                    <p>Enter the 6-digit code sent to your phone</p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error_message): ?>
                    <div class="otp-alert otp-alert-error" data-aos="zoom-in" data-aos-delay="150">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Success Message -->
                <?php if ($success_message): ?>
                    <div class="otp-alert otp-alert-success" data-aos="zoom-in" data-aos-delay="150">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- OTP Verification Form -->
                <form id="otpVerificationForm" class="otp-form" action="<?php echo $pathConfig->url('otp/verify-otp-process'); ?>" method="POST" data-aos="zoom-in" data-aos-delay="200">
                    
                    <!-- Phone Number Display -->
                    <div class="otp-info-box" data-aos="fade-up" data-aos-delay="300">
                        <p>We sent a 6-digit code to <strong><?php echo htmlspecialchars($phone); ?></strong></p>
                        <p class="otp-timer-display">Code expires in: <span id="otpTimer" class="otp-timer-value">10:00</span></p>
                        <div class="otp-progress-bar">
                            <div class="otp-progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                    
                    <!-- OTP Input Field -->
                    <div class="otp-form-group" data-aos="fade-up" data-aos-delay="350">
                        <label for="otp_code" class="otp-form-label">Verification Code <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="otp_code" 
                            name="otp" 
                            class="otp-input" 
                            placeholder="• • • • • •" 
                            pattern="\d{6}" 
                            maxlength="6" 
                            required
                            autocomplete="one-time-code" 
                            inputmode="numeric"
                        >
                        <small class="otp-form-help">Enter the 6-digit code sent via SMS</small>
                    </div>
                    
                    <!-- Resend Section -->
                    <div class="otp-resend-section" data-aos="fade-up" data-aos-delay="400">
                        <p>Didn't receive the code? 
                            <a href="<?php echo $pathConfig->url('otp/resend-otp'); ?>" class="otp-resend-link" id="resendLink">Resend OTP</a>
                            <span id="resendTimer" class="otp-resend-timer" style="display: none;">Resend available in <span id="resendCountdown">60</span>s</span>
                        </p>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="otp-btn otp-btn-primary" id="verifyBtn" data-aos="zoom-in" data-aos-delay="450">
                        Verify & Continue <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <!-- Help Section -->
                    <div class="otp-help-section" data-aos="fade-up" data-aos-delay="500">
                        <h4><i class="fas fa-question-circle"></i> Having trouble?</h4>
                        <ul>
                            <li data-aos="fade-right" data-aos-delay="550">Check your SMS messages</li>
                            <li data-aos="fade-right" data-aos-delay="600">Ensure you have network connectivity</li>
                            <li data-aos="fade-right" data-aos-delay="650">Wait for the code to arrive (may take up to 1 minute)</li>
                            <li data-aos="fade-right" data-aos-delay="700">Contact support if issues persist</li>
                        </ul>
                    </div>
                </form>
                
                <!-- Footer Links -->
                <div class="otp-form-footer" data-aos="fade-up" data-aos-delay="550">
                    <p>Remember your password? <a href="<?php echo $pathConfig->url('login'); ?>">Try password login</a></p>
                    <p><a href="<?php echo $pathConfig->url('signup'); ?>">Don't have an account? Sign up</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OTP Page Styles -->
<style>
:root {
    --otp-primary: #29b027ff;
    --otp-primary-dark: #a2991fff;
    --otp-primary-light: #e1f7e1;
    --otp-secondary: #ff9800;
    --otp-text-primary: #212121;
    --otp-text-secondary: #757575;
    --otp-light-bg: #f9f9f9;
    --otp-white: #ffffff;
    --otp-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    --otp-transition: all 0.3s ease;
    --otp-border-radius: 12px;
}

/* OTP Page Styles */
:root {
    --otp-primary: #29b027ff;
    --otp-primary-dark: #a2991fff;
    --otp-primary-light: #e1f7e1;
    --otp-secondary: #ff9800;
    --otp-text-primary: #212121;
    --otp-text-secondary: #757575;
    --otp-light-bg: #f9f9f9;
    --otp-white: #ffffff;
    --otp-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    --otp-transition: all 0.3s ease;
    --otp-border-radius: 12px;
}

.otp-verification-container {
    width: 100%;
    min-height: calc(100vh - 160px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    margin-top: 0;
}

.otp-wrapper {
    display: flex;
    width: 100%;
    max-width: 1000px;
    background: var(--otp-white);
    border-radius: var(--otp-border-radius);
    overflow: hidden;
    box-shadow: var(--otp-shadow);
}

/* Visual Section */
.otp-visual-section {
    flex: 1;
    background: linear-gradient(135deg, var(--otp-primary) 0%, var(--otp-primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: white;
    position: relative;
    overflow: hidden;
}

.otp-visual-section::before {
    content: "";
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='0.1' d='M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,218.7C672,224,768,160,864,138.7C960,117,1056,139,1152,149.3C1248,160,1344,160,1392,160L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E");
    background-size: cover;
    background-position: center;
    opacity: 0.5;
}

.otp-visual-content {
    position: relative;
    z-index: 1;
    text-align: center;
    width: 100%;
}

.otp-visual-content h2 {
    font-size: 28px;
    margin-bottom: 15px;
    font-weight: 700;
}

.otp-visual-content p {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 30px;
}

.otp-visual-graphic {
    width: 120px;
    height: 120px;
    margin: 30px auto;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    animation: otp-float 6s ease-in-out infinite;
}

@keyframes otp-float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
}

.otp-features-list {
    text-align: left;
    margin-top: 30px;
    list-style: none;
    padding: 0;
}

.otp-features-list li {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    font-size: 14px;
}

.otp-features-list i {
    width: 20px;
    height: 20px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-size: 12px;
}

/* Form Section */
.otp-form-section {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    overflow-y: auto;
}

.otp-form-container {
    width: 100%;
    max-width: 400px;
}

.otp-form-header {
    text-align: center;
    margin-bottom: 30px;
}

.otp-form-header h2 {
    font-size: 24px;
    color: var(--otp-primary-dark);
    margin-bottom: 10px;
    font-weight: 700;
}

.otp-form-header p {
    color: var(--otp-text-secondary);
    font-size: 14px;
}

/* Alerts */
.otp-alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.5s ease forwards;
}

.otp-alert-error {
    background-color: #ffebee;
    color: #d32f2f;
    border-left: 4px solid #d32f2f;
}

.otp-alert-success {
    background-color: #e8f5e9;
    color: var(--otp-primary-dark);
    border-left: 4px solid var(--otp-primary-dark);
}

.otp-alert i {
    flex-shrink: 0;
}

/* Info Box */
.otp-info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: var(--otp-border-radius);
    margin-bottom: 20px;
    text-align: center;
    border-left: 4px solid var(--otp-primary);
}

.otp-info-box p {
    margin-bottom: 8px;
}

.otp-timer-display {
    font-weight: bold;
    color: #e74c3c;
    margin-top: 10px;
    font-size: 14px;
}

.otp-timer-value {
    font-weight: 700;
}

.otp-progress-bar {
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}

.otp-progress-fill {
    height: 100%;
    background: var(--otp-primary);
    width: 100%;
    transition: width 1s linear;
}

/* Form Group */
.otp-form-group {
    margin-bottom: 20px;
}

.otp-form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--otp-text-primary);
}

.required {
    color: #e74c3c;
}

.otp-input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e0e0e0;
    border-radius: var(--otp-border-radius);
    font-size: 24px;
    text-align: center;
    letter-spacing: 10px;
    font-weight: bold;
    transition: var(--otp-transition);
    font-family: 'Courier New', monospace;
}

.otp-input:focus {
    outline: none;
    border-color: var(--otp-primary);
    box-shadow: 0 0 0 3px rgba(41, 176, 39, 0.2);
}

.otp-form-help {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--otp-text-secondary);
}

/* Resend Section */
.otp-resend-section {
    text-align: center;
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: var(--otp-border-radius);
}

.otp-resend-section p {
    margin: 0;
    font-size: 14px;
}

.otp-resend-link {
    color: var(--otp-primary);
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: var(--otp-transition);
}

.otp-resend-link:hover {
    color: var(--otp-primary-dark);
    text-decoration: underline;
}

.otp-resend-timer {
    color: #666;
    font-size: 0.9em;
}

/* Buttons */
.otp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 20px;
    border: none;
    border-radius: var(--otp-border-radius);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--otp-transition);
    width: 100%;
    margin-bottom: 10px;
}

.otp-btn-primary {
    background-color: var(--otp-primary);
    color: white;
}

.otp-btn-primary:hover:not(:disabled) {
    background-color: var(--otp-primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(41, 176, 39, 0.3);
}

.otp-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Help Section */
.otp-help-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: var(--otp-border-radius);
    margin-top: 20px;
    border-left: 4px solid var(--otp-primary);
}

.otp-help-section h4 {
    margin: 0 0 10px 0;
    color: var(--otp-text-primary);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.otp-help-section ul {
    margin: 0;
    padding-left: 20px;
    list-style: disc;
}

.otp-help-section li {
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--otp-text-secondary);
}

/* Form Footer */
.otp-form-footer {
    text-align: center;
    margin-top: 25px;
}

.otp-form-footer p {
    margin-bottom: 10px;
    color: var(--otp-text-secondary);
    font-size: 14px;
}

.otp-form-footer a {
    color: var(--otp-primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--otp-transition);
}

.otp-form-footer a:hover {
    color: var(--otp-primary-dark);
    text-decoration: underline;
}

/* Animations */
@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(10px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .otp-wrapper {
        flex-direction: column;
        max-width: 100%;
    }
    
    .otp-visual-section {
        display: none;
    }
    
    .otp-form-section {
        padding: 30px 20px;
        min-height: auto;
    }
    
    .otp-form-container {
        max-width: 100%;
    }
    
    .otp-form-header h2 {
        font-size: 20px;
    }
    
    .otp-input {
        font-size: 20px;
        letter-spacing: 8px;
    }
    
    .otp-verification-container {
        min-height: calc(100vh - 200px);
        padding: 20px;
        padding-bottom: 100px;
    }
}

/* Extra mobile adjustments */
@media (max-width: 480px) {
    .otp-verification-container {
        min-height: calc(100vh - 180px);
        padding: 15px;
        padding-bottom: 120px;
    }
    
    .otp-form-section {
        padding: 20px 15px;
    }
    
    .otp-form-container {
        padding: 0;
    }
    
    .otp-input {
        font-size: 18px;
        letter-spacing: 6px;
    }
}

/* Desktop - increase min height since no mobile nav */
@media (min-width: 769px) {
    .otp-verification-container {
        min-height: calc(100vh - 80px);
    }
}

/* Focus states for accessibility */
.otp-input:focus, .otp-btn:focus {
    outline: 2px solid var(--otp-primary);
    outline-offset: 2px;
}
</style>

<!-- OTP Verification JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpInput = document.getElementById('otp_code');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendLink = document.getElementById('resendLink');
    const resendTimer = document.getElementById('resendTimer');
    const resendCountdown = document.getElementById('resendCountdown');
    const otpTimer = document.getElementById('otpTimer');
    const progressFill = document.getElementById('progressFill');
    const otpForm = document.getElementById('otpVerificationForm');
    
    let totalSeconds = 600; // 10 minutes
    let resendSeconds = 60; // 1 minute
    let autoSubmitEnabled = true;

    // Enhanced OTP input handling
    const setupOTPInput = () => {
        // Auto-submit when 6 digits entered
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^\d]/g, '');
            if (this.value.length === 6 && autoSubmitEnabled) {
                autoSubmitOTP();
            }
        });

        // Handle paste event for auto-fill
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pastedData.replace(/[^\d]/g, '').slice(0, 6);
            this.value = digits;
            
            if (digits.length === 6 && autoSubmitEnabled) {
                autoSubmitOTP();
            }
        });

        // Handle keydown for manual control
        otpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                this.value = '';
            }
        });
    };

    // Auto-submit function
    const autoSubmitOTP = () => {
        const otpValue = otpInput.value.trim();
        
        if (otpValue.length === 6 && /^\d+$/.test(otpValue)) {
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = 'Verifying... <i class="fas fa-spinner fa-spin"></i>';
            otpForm.submit();
        }
    };

    // Manual validation and submit
    const validateAndSubmit = () => {
        const otpValue = otpInput.value.trim();
        
        if (otpValue.length !== 6 || !/^\d+$/.test(otpValue)) {
            showError('Please enter a valid 6-digit code');
            return false;
        }
        
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = 'Verifying... <i class="fas fa-spinner fa-spin"></i>';
        return true;
    };

    // Start OTP expiration timer
    const startOTPTimer = () => {
        const timerInterval = setInterval(() => {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            otpTimer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            const progressPercent = (totalSeconds / 600) * 100;
            progressFill.style.width = progressPercent + '%';
            
            if (totalSeconds <= 0) {
                clearInterval(timerInterval);
                otpInput.disabled = true;
                verifyBtn.disabled = true;
                showError('OTP has expired. Please request a new one.');
                autoSubmitEnabled = false;
            }
            
            totalSeconds--;
        }, 1000);
    };
    
    // Start resend countdown
    const startResendCountdown = () => {
        resendLink.style.display = 'none';
        resendTimer.style.display = 'inline';
        
        const countdownInterval = setInterval(() => {
            if (resendSeconds <= 0) {
                clearInterval(countdownInterval);
                resendLink.style.display = 'inline';
                resendTimer.style.display = 'none';
                resendSeconds = 60;
            } else {
                resendCountdown.textContent = resendSeconds;
                resendSeconds--;
            }
        }, 1000);
    };
    
    // Form submission handler
    otpForm.addEventListener('submit', function(e) {
        if (!validateAndSubmit()) {
            e.preventDefault();
        }
    });
    
    // Resend link handler
    resendLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (resendSeconds > 0 && resendSeconds < 60) {
            showError('Please wait before requesting another code');
            return;
        }
        
        startResendCountdown();
        
        // Show loading state
        const originalText = resendLink.innerHTML;
        resendLink.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        resendLink.style.pointerEvents = 'none';
        
        fetch('<?php echo $pathConfig->url('otp/resend-otp'); ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Code sent successfully! Check your SMS');
            } else {
                showError(data.message || 'Failed to resend code');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Network error. Please try again.');
        })
        .finally(() => {
            resendLink.innerHTML = originalText;
            resendLink.style.pointerEvents = 'auto';
        });
    });
    
    // Helper functions for messages
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'otp-alert otp-alert-error';
        alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${message}</span>`;
        
        const existingAlert = document.querySelector('.otp-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        document.querySelector('.otp-form-header').insertAdjacentElement('afterend', alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    function showSuccess(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'otp-alert otp-alert-success';
        alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> <span>${message}</span>`;
        
        const existingAlert = document.querySelector('.otp-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        document.querySelector('.otp-form-header').insertadjacentElement('afterend', alertDiv);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
    
    // Initialize everything
    setupOTPInput();
    startOTPTimer();
    startResendCountdown();
    
    // Focus on OTP input
    otpInput.focus();
});
</script>
