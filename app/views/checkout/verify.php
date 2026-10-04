<?php
// app/views/checkout/verify.php
$page_title = "Verify Your Phone - Phool Delivery";
?>
<section class="verify-page">
    <div class="container">
        <div class="verify-container">
            <h2>Verify Your Phone Number</h2>
            <p>We've sent a 6-digit verification code to your phone. Please enter it below to continue.</p>
            
            <form id="verify-form" class="verify-form">
                <div class="form-group">
                    <label for="otp-code" class="form-label">Verification Code</label>
                    <input type="text" id="otp-code" name="otp_code" class="form-input" required maxlength="6" placeholder="Enter 6-digit code" pattern="[0-9]{6}">
                </div>
                
                <button type="submit" class="btn btn-verify">Verify & Continue</button>
            </form>
            
            <div class="resend-otp">
                <p>Didn't receive the code? <a href="#" id="resend-otp">Resend OTP</a></p>
                <p id="countdown" style="display: none; color: #666; margin-top: 5px;"></p>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const verifyForm = document.getElementById('verify-form');
    const resendLink = document.getElementById('resend-otp');
    const countdownElement = document.getElementById('countdown');
    let resendTimer = null;
    let resendTime = 60; // 60 seconds cooldown
    
    // Start resend countdown
    function startResendCountdown() {
        resendLink.style.pointerEvents = 'none';
        resendLink.style.opacity = '0.6';
        countdownElement.style.display = 'block';
        countdownElement.textContent = `Resend available in ${resendTime} seconds`;
        
        resendTimer = setInterval(() => {
            resendTime--;
            countdownElement.textContent = `Resend available in ${resendTime} seconds`;
            
            if (resendTime <= 0) {
                clearInterval(resendTimer);
                resendLink.style.pointerEvents = 'auto';
                resendLink.style.opacity = '1';
                countdownElement.style.display = 'none';
                resendTime = 60;
            }
        }, 1000);
    }
    
    // Start countdown on page load
    startResendCountdown();
    
    if (verifyForm) {
        verifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const otpCode = document.getElementById('otp-code').value.trim();
            
            if (otpCode.length !== 6 || !/^\d{6}$/.test(otpCode)) {
                alert('Please enter a valid 6-digit code');
                return;
            }
            
            // Get phone from session
            const phone = '<?php echo $_SESSION['checkout_phone'] ?? ''; ?>';
            
            if (!phone) {
                alert('Session expired. Please restart checkout.');
                window.location.href = '/phool-delivery-platform/public_html/checkout';
                return;
            }
            
            const verifyBtn = this.querySelector('.btn-verify');
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';
            
            // Verify OTP
            fetch('/phool-delivery-platform/public_html/api/verify-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: phone,
                    otp_code: otpCode
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Phone verified successfully!');
                    // Clear session phone
                    fetch('/phool-delivery-platform/public_html/checkout/clear-verification-phone', {
                        method: 'POST'
                    });
                    // Redirect to success page
                    setTimeout(() => {
                        window.location.href = '/phool-delivery-platform/public_html/success';
                    }, 1000);
                } else {
                    alert(data.message || 'Error verifying OTP. Please try again.');
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify & Continue';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error verifying OTP. Please try again.');
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify & Continue';
            });
        });
    }
    
    if (resendLink) {
        resendLink.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if cooldown is active
            if (resendTime < 60) {
                return;
            }
            
            // Resend OTP logic
            const phone = '<?php echo $_SESSION['checkout_phone'] ?? ''; ?>';
            
            if (!phone) {
                alert('Session expired. Please restart checkout.');
                window.location.href = '/phool-delivery-platform/public_html/checkout';
                return;
            }
            
            this.style.pointerEvents = 'none';
            this.style.opacity = '0.6';
            
            fetch('/phool-delivery-platform/public_html/api/resend-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('New OTP sent to your phone.');
                    // Restart countdown
                    if (resendTimer) {
                        clearInterval(resendTimer);
                    }
                    startResendCountdown();
                } else {
                    alert(data.message || 'Error resending OTP. Please try again.');
                    this.style.pointerEvents = 'auto';
                    this.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error resending OTP. Please try again.');
                this.style.pointerEvents = 'auto';
                this.style.opacity = '1';
            });
        });
    }
});
</script>