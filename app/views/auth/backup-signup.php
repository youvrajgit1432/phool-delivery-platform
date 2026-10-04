<?php
// app/views/auth/signup.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('create_account', 'Create Account') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$error_message = $error_message ?? '';
$success_message = $success_message ?? '';
$form_data = $form_data ?? [];

$limits = [
    'name' => 100, 'email' => 255, 'phone' => 20, 
    'address' => 500, 'city' => 50, 'street' => 100, 'password' => 128
];
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-header" data-aos="fade-up" data-aos-delay="100">
                <h2><?= LanguageHelper::t('create_your_account', 'Create Your Account') ?> 🌼</h2>
                <p><?= LanguageHelper::t('join_phool_delivery', 'Join Phool Delivery and get fresh flowers delivered') ?></p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <!-- FIXED: Use traditional form submission without AJAX -->
            <form class="auth-form multi-step-form" action="<?= $pathConfig->url('auth/signup-process') ?>" method="POST" id="signupForm" data-aos="zoom-in" data-aos-delay="200">
                <input type="hidden" name="is_traditional_submit" value="1">
                
                <div class="form-progress" data-aos="fade-up" data-aos-delay="250">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="step-indicators">
                        <?php foreach([1 => 'account', 2 => 'contact', 3 => 'address', 4 => 'security'] as $step => $text): ?>
                        <div class="step-indicator <?= $step === 1 ? 'active' : '' ?>" data-step="<?= $step ?>" data-aos="zoom-in" data-aos-delay="<?= ($step * 100) + 200 ?>">
                            <span><?= $step ?></span>
                            <p><?= LanguageHelper::t($text, ucfirst($text)) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Step 1: Account Information -->
                <div class="form-step active" data-step="1">
                    <h3 class="step-title"><?= LanguageHelper::t('account_information', 'Account Information') ?></h3>
                    <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                        <label for="name" class="form-label"><?= LanguageHelper::t('full_name', 'Full Name') ?> *</label>
                        <input type="text" id="name" name="name" class="form-input" 
                               value="<?php echo htmlspecialchars(substr($form_data['name'] ?? '', 0, $limits['name'])); ?>" 
                               placeholder="<?= LanguageHelper::t('enter_full_name', 'Enter your full name') ?>" 
                               maxlength="<?= $limits['name'] ?>" data-limit="<?= $limits['name'] ?>" required>
                        <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['name'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                        <div class="validation-message" id="name-validation"></div>
                    </div>
                    <div class="form-actions" data-aos="fade-up" data-aos-delay="350">
                        <button type="button" class="btn btn-next"><?= LanguageHelper::t('continue', 'Continue') ?> 🌸</button>
                    </div>
                </div>
                
                <!-- Step 2: Contact Information -->
                <div class="form-step" data-step="2">
                    <h3 class="step-title"><?= LanguageHelper::t('contact_information', 'Contact Information') ?></h3>
                    <div class="form-row">
                        <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                            <label for="email" class="form-label"><?= LanguageHelper::t('email_address', 'Email Address') ?></label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars(substr($form_data['email'] ?? '', 0, $limits['email'])); ?>" 
                                   placeholder="<?= LanguageHelper::t('enter_email_optional', 'Enter your email (optional)') ?>"
                                   maxlength="<?= $limits['email'] ?>" data-limit="<?= $limits['email'] ?>">
                            <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['email'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                            <div class="validation-message" id="email-validation"></div>
                        </div>
                        <div class="form-group" data-aos="fade-up" data-aos-delay="350">
                            <label for="phone" class="form-label"><?= LanguageHelper::t('phone_number', 'Phone Number') ?></label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars(substr($form_data['phone'] ?? '', 0, $limits['phone'])); ?>" 
                                   placeholder="<?= LanguageHelper::t('enter_phone_optional', 'Enter your phone (optional)') ?>"
                                   maxlength="<?= $limits['phone'] ?>" data-limit="<?= $limits['phone'] ?>">
                            <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['phone'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                            <div class="validation-message" id="phone-validation"></div>
                        </div>
                    </div>
                    <div class="form-actions" data-aos="fade-up" data-aos-delay="400">
                        <button type="button" class="btn btn-prev"><?= LanguageHelper::t('back', 'Back') ?></button>
                        <button type="button" class="btn btn-next"><?= LanguageHelper::t('continue', 'Continue') ?> 🌺</button>
                    </div>
                </div>
                
                <!-- Step 3: Address Information -->
                <div class="form-step" data-step="3">
                    <h3 class="step-title"><?= LanguageHelper::t('delivery_address', 'Delivery Address') ?></h3>
                    <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                        <label for="address" class="form-label"><?= LanguageHelper::t('delivery_address', 'Delivery Address') ?></label>
                        <textarea id="address" name="address" class="form-input" 
                                  placeholder="<?= LanguageHelper::t('enter_address_optional', 'Enter your delivery address (optional)') ?>"
                                  maxlength="<?= $limits['address'] ?>" data-limit="<?= $limits['address'] ?>"><?php echo htmlspecialchars(substr($form_data['address'] ?? '', 0, $limits['address'])); ?></textarea>
                        <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['address'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                        <div class="validation-message" id="address-validation"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" data-aos="fade-up" data-aos-delay="350">
                            <label for="city" class="form-label"><?= LanguageHelper::t('city', 'City') ?></label>
                            <select id="city" name="city" class="form-input">
                                <option value="">-- <?= LanguageHelper::t('select_city', 'Select City') ?> --</option>
                                <?php foreach(['Banepa', 'Kathmandu', 'Bhaktapur', 'Dhulikhel'] as $city): ?>
                                <option value="<?= $city ?>" <?= (isset($form_data['city']) && $form_data['city'] == $city) ? 'selected' : '' ?>><?= $city ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                            <label for="street" class="form-label"><?= LanguageHelper::t('street_name', 'Street Name') ?></label>
                            <input type="text" id="street" name="street" class="form-input" 
                                   value="<?php echo htmlspecialchars(substr($form_data['street'] ?? '', 0, $limits['street'])); ?>" 
                                   placeholder="<?= LanguageHelper::t('enter_street_optional', 'Enter street name (optional)') ?>"
                                   maxlength="<?= $limits['street'] ?>" data-limit="<?= $limits['street'] ?>">
                            <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['street'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                            <div class="validation-message" id="street-validation"></div>
                        </div>
                    </div>
                    <div class="form-actions" data-aos="fade-up" data-aos-delay="450">
                        <button type="button" class="btn btn-prev"><?= LanguageHelper::t('back', 'Back') ?></button>
                        <button type="button" class="btn btn-next"><?= LanguageHelper::t('continue', 'Continue') ?> 🌷</button>
                    </div>
                </div>
                
                <!-- Step 4: Security Information -->
                <div class="form-step" data-step="4">
                    <h3 class="step-title"><?= LanguageHelper::t('security_information', 'Security Information') ?></h3>
                    <div class="form-row">
                        <div class="form-group password-group" data-aos="fade-up" data-aos-delay="300">
                            <label for="password" class="form-label"><?= LanguageHelper::t('password', 'Password') ?> *</label>
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('create_password', 'Create a password') ?>" 
                                       maxlength="<?= $limits['password'] ?>" data-limit="<?= $limits['password'] ?>" required>
                                <button type="button" class="toggle-password" aria-label="<?= LanguageHelper::t('show_password', 'Show password') ?>">
                                    <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['password'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                            <div class="password-strength"><div class="strength-meter"></div></div>
                            <div class="password-requirements">
                                <p><?= LanguageHelper::t('password_must_include', 'Password must include:') ?></p>
                                <ul>
                                    <li class="requirement-length" data-aos="fade-right" data-aos-delay="350"><?= LanguageHelper::t('at_least_8_chars', 'At least 8 characters') ?></li>
                                    <li class="requirement-uppercase" data-aos="fade-right" data-aos-delay="400"><?= LanguageHelper::t('one_uppercase_letter', 'One uppercase letter') ?></li>
                                    <li class="requirement-number" data-aos="fade-right" data-aos-delay="450"><?= LanguageHelper::t('one_number', 'One number') ?></li>
                                </ul>
                            </div>
                            <div class="validation-message" id="password-validation"></div>
                        </div>
                        <div class="form-group password-group" data-aos="fade-up" data-aos-delay="350">
                            <label for="confirm_password" class="form-label"><?= LanguageHelper::t('confirm_password', 'Confirm Password') ?> *</label>
                            <div class="password-input-container">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('confirm_your_password', 'Confirm your password') ?>" 
                                       maxlength="<?= $limits['password'] ?>" data-limit="<?= $limits['password'] ?>" required>
                                <button type="button" class="toggle-password" aria-label="<?= LanguageHelper::t('show_password', 'Show password') ?>">
                                    <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <div class="char-counter"><span class="char-count">0</span>/<span class="char-limit"><?= $limits['password'] ?></span> <?= LanguageHelper::t('characters', 'characters') ?></div>
                            <div class="validation-message" id="confirm-password-validation"></div>
                        </div>
                    </div>
                    
                    <!-- ADDED: Remember Me Checkbox -->
                    <div class="form-checkbox" data-aos="fade-up" data-aos-delay="400">
                        <input type="checkbox" id="remember_me" name="remember_me" checked>
                        <label for="remember_me"><?= LanguageHelper::t('remember_me', 'Keep me logged in') ?></label>
                    </div>
                    
                    <div class="form-checkbox" data-aos="fade-up" data-aos-delay="450">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms"><?= LanguageHelper::t('agree_to_terms', 'I agree to the') ?> <a href="<?= $pathConfig->url('terms') ?>"><?= LanguageHelper::t('terms_of_service', 'Terms of Service') ?></a> <?= LanguageHelper::t('and', 'and') ?> <a href="<?= $pathConfig->url('privacy') ?>"><?= LanguageHelper::t('privacy_policy', 'Privacy Policy') ?></a></label>
                    </div>
                    <div class="form-actions" data-aos="fade-up" data-aos-delay="500">
                        <button type="button" class="btn btn-prev"><?= LanguageHelper::t('back', 'Back') ?></button>
                        <button type="submit" class="btn btn-primary btn-auth" id="submitBtn"><?= LanguageHelper::t('create_account', 'Create Account') ?> 🌻</button>
                    </div>
                </div>
            </form>
            
            <div class="auth-footer" data-aos="fade-up" data-aos-delay="550">
                <p><?= LanguageHelper::t('already_have_account', 'Already have an account?') ?> <a href="<?= $pathConfig->url('login') ?>"><?= LanguageHelper::t('sign_in_here', 'Sign in here') ?></a></p>
            </div>
        </div>
    </div>
</section>

<style>
.char-counter { font-size: 0.8rem; color: #666; text-align: right; margin-top: 0.25rem; }
.char-counter.warning { color: #ff9800; }
.char-counter.error { color: #f44336; font-weight: bold; }
.validation-message { font-size: 0.8rem; margin-top: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; display: none; }
.validation-message.error { background-color: #ffebee; color: #f44336; display: block; }
.validation-message.success { background-color: #e8f5e8; color: #4caf50; display: block; }
.form-input:invalid { border-color: #f44336; }
.form-input:valid { border-color: #4caf50; }

/* Loading state for submit button */
.btn-loading {
    position: relative;
    color: transparent !important;
}
.btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes blinkPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3); }
    50% { transform: scale(1.05); box-shadow: 0 6px 20px rgba(139, 69, 19, 0.5); }
}
.order-now-btn, .contact-now-btn { animation: blinkPulse 2s infinite; }
.contact-now-btn { animation-delay: 1s; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('signupForm');
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-indicator');
    const progressFill = document.getElementById('progressFill');
    const submitBtn = document.getElementById('submitBtn');
    let currentStep = 0;

    document.querySelectorAll('.btn-next').forEach(button => {
        button.addEventListener('click', () => validateStep(currentStep) && goToStep(currentStep + 1));
    });

    document.querySelectorAll('.btn-prev').forEach(button => {
        button.addEventListener('click', () => goToStep(currentStep - 1));
    });

    function goToStep(step) {
        steps[currentStep].classList.remove('active');
        indicators[currentStep].classList.remove('active');
        steps[step].classList.add('active');
        indicators[step].classList.add('active');
        progressFill.style.width = ((step + 1) / steps.length) * 100 + '%';
        currentStep = step;
    }

    function validateStep(step) {
        const inputs = steps[step].querySelectorAll('input[required], textarea[required]');
        let isValid = true;
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                showValidationMessage(document.getElementById(input.id + '-validation'), '<?= LanguageHelper::t('field_required', 'This field is required') ?>', 'error');
            }
        });
        return isValid;
    }

    const limits = <?= json_encode($limits) ?>;
    
    function initializeFieldValidation() {
        ['name', 'email', 'phone', 'address', 'street', 'password', 'confirm_password'].forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                const counter = field.parentElement.querySelector('.char-counter');
                const charCount = counter.querySelector('.char-count');
                const validationMsg = document.getElementById(fieldName + '-validation');
                
                updateCharCounter(field, charCount);
                field.addEventListener('input', () => {
                    updateCharCounter(field, charCount);
                    validateField(field, validationMsg);
                });
                field.addEventListener('blur', () => validateField(field, validationMsg));
                field.addEventListener('keydown', e => {
                    if (field.value.length >= limits[fieldName] && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
                        e.preventDefault();
                        showValidationMessage(validationMsg, '<?= LanguageHelper::t('max_characters_allowed', 'Maximum {limit} characters allowed') ?>'.replace('{limit}', limits[fieldName]), 'error');
                    }
                });
                field.addEventListener('paste', e => {
                    setTimeout(() => {
                        if (field.value.length > limits[fieldName]) {
                            field.value = field.value.substring(0, limits[fieldName]);
                            showValidationMessage(validationMsg, '<?= LanguageHelper::t('text_truncated', 'Text truncated to {limit} characters') ?>'.replace('{limit}', limits[fieldName]), 'warning');
                        }
                        updateCharCounter(field, charCount);
                    }, 0);
                });
            }
        });
    }
    
    function updateCharCounter(field, charCount) {
        const currentLength = field.value.length;
        const maxLength = parseInt(field.getAttribute('maxlength'));
        charCount.textContent = currentLength;
        const percentage = (currentLength / maxLength) * 100;
        charCount.parentElement.className = percentage >= 90 ? 'char-counter error' : percentage >= 75 ? 'char-counter warning' : 'char-counter';
    }
    
    function validateField(field, validationMsg) {
        const fieldName = field.id;
        const value = field.value.trim();
        const maxLength = parseInt(field.getAttribute('maxlength'));
        
        validationMsg.textContent = '';
        validationMsg.className = 'validation-message';
        
        if (value.length > maxLength) {
            field.value = value.substring(0, maxLength);
            showValidationMessage(validationMsg, '<?= LanguageHelper::t('max_characters_allowed', 'Maximum {limit} characters allowed') ?>'.replace('{limit}', maxLength), 'error');
            return false;
        }
        
        const validations = {
            name: value && (!/^[a-zA-Z\s\.\-']+$/.test(value) ? '<?= LanguageHelper::t('name_validation', 'Name can only contain letters, spaces, hyphens, and apostrophes') ?>' : value.length < 2 ? '<?= LanguageHelper::t('name_min_length', 'Name must be at least 2 characters long') ?>' : ''),
            email: value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '<?= LanguageHelper::t('email_validation', 'Please enter a valid email address') ?>' : '',
            phone: value && !/^[\d\s\-\+\(\)]+$/.test(value) ? '<?= LanguageHelper::t('phone_validation', 'Phone number can only contain digits, spaces, hyphens, and plus sign') ?>' : '',
            password: value ? validatePassword(value).message : '',
            confirm_password: value && document.getElementById('password').value !== value ? '<?= LanguageHelper::t('passwords_dont_match', 'Passwords do not match') ?>' : ''
        };
        
        if (validations[fieldName]) {
            showValidationMessage(validationMsg, validations[fieldName], 'error');
            return false;
        }
        
        if (field.hasAttribute('required') && value) {
            showValidationMessage(validationMsg, '<?= LanguageHelper::t('looks_good', 'Looks good!') ?>', 'success');
        }
        
        return true;
    }
    
    function validatePassword(password) {
        if (password.length < 8) return { isValid: false, message: '<?= LanguageHelper::t('password_min_length', 'Password must be at least 8 characters long') ?>' };
        if (!/[A-Z]/.test(password)) return { isValid: false, message: '<?= LanguageHelper::t('password_uppercase', 'Password must contain at least one uppercase letter') ?>' };
        if (!/\d/.test(password)) return { isValid: false, message: '<?= LanguageHelper::t('password_number', 'Password must contain at least one number') ?>' };
        return { isValid: true, message: '<?= LanguageHelper::t('strong_password', 'Strong password!') ?>' };
    }
    
    function showValidationMessage(element, message, type) {
        element.textContent = message;
        element.className = `validation-message ${type}`;
    }

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('.eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.style.stroke = '#8b4513';
            } else {
                input.type = 'password';
                icon.style.stroke = 'currentColor';
            }
        });
    });

    // FIXED: Traditional form submission only
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate all required fields
        document.querySelectorAll('input[required], textarea[required]').forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                showValidationMessage(document.getElementById(field.id + '-validation'), '<?= LanguageHelper::t('field_required', 'This field is required') ?>', 'error');
            }
        });
        
        // Validate password match
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        if (password !== confirmPassword) {
            isValid = false;
            showValidationMessage(document.getElementById('confirm-password-validation'), '<?= LanguageHelper::t('passwords_dont_match', 'Passwords do not match') ?>', 'error');
        }
        
        // Validate terms acceptance
        if (!document.getElementById('terms').checked) {
            isValid = false;
            alert('<?= LanguageHelper::t('agree_terms_required', 'Please agree to the terms and conditions') ?>');
            e.preventDefault();
            return;
        }
        
        if (!isValid) {
            e.preventDefault();
            document.querySelector('.validation-message.error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        
        // If valid, show loading state and let form submit traditionally
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        
        // The form will submit traditionally - no AJAX
        // The server will handle the redirect
    });
    
    initializeFieldValidation();
});
</script>