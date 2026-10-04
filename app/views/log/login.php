<?php
// app/views/log/login.php

// Set page title
$page_title = LanguageHelper::t('login', 'Login') . " - Phool Delivery";

// Use PathConfig for dynamic path handling
$pathConfig = PathConfig::getInstance();
$base_url = $pathConfig->get('base_url');
$assets_path = $pathConfig->get('assets');

$form_type = $form_type ?? 'method_selection';
$phone = $phone ?? '';
$error_message = $error_message ?? '';
$success_message = $success_message ?? '';
$show_otp_option = $show_otp_option ?? true;
$method = $_GET['method'] ?? 'password';

if (!function_exists('base_url')) {
    function base_url($path = '') {
        $pathConfig = PathConfig::getInstance();
        return $pathConfig->url($path);
    }
}

$socialAuthController = new SocialAuthController($GLOBALS['db']);
$isGoogleEnabled = $socialAuthController->isSocialLoginEnabled();

$inputLimits = [
    'login' => 100,
    'password' => 50,
    'phone' => 10,
];

// Include header
include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Main Content Start -->
<main class="main-content" id="main-content">
    <div class="auth-page-wrapper">
        <div class="auth-container-wrapper">
            <section class="auth-section">
                <div class="auth-container">
                    <div class="auth-header" data-aos="fade-up" data-aos-delay="100">
                        <h2><?= LanguageHelper::t('welcome_back', 'Welcome Back!') ?></h2>
                        <p><?= LanguageHelper::t('sign_in_account', 'Sign in to your Phool Delivery account') ?></p>
                    </div>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150">
                            <span class="alert-icon">⚠️</span>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150">
                            <span class="alert-icon">✅</span>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($form_type === 'method_selection'): ?>
                        <div id="methodSelection" class="method-selection" style="<?php echo $method !== 'password' ? 'display: none;' : ''; ?>" data-aos="zoom-in" data-aos-delay="200">
                            <h3><?= LanguageHelper::t('choose_login_method', 'Choose Login Method') ?></h3>
                            <p class="method-subtitle">Select how you'd like to sign in</p>
                            <div class="method-buttons">
                                <button type="button" class="method-btn" onclick="selectLoginMethod('password')" data-aos="zoom-in" data-aos-delay="250">
                                    <span class="method-icon">🔐</span>
                                    <span class="method-title"><?= LanguageHelper::t('password_login', 'Password Login') ?></span>
                                    <span class="method-desc"><?= LanguageHelper::t('use_email_phone_password', 'Use your email/phone and password') ?></span>
                                </button>
                                
                                <?php if ($show_otp_option): ?>
                                <button type="button" class="method-btn" onclick="selectLoginMethod('otp')" data-aos="zoom-in" data-aos-delay="300">
                                    <span class="method-icon">📱</span>
                                    <span class="method-title"><?= LanguageHelper::t('otp_login', 'OTP Login') ?></span>
                                    <span class="method-desc"><?= LanguageHelper::t('get_code_sent_phone', 'Get a code sent to your phone') ?></span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form id="passwordLoginForm" class="auth-form" action="<?= $pathConfig->url('log/login-process') ?>" method="POST" style="<?php echo $method === 'password' ? 'display: block;' : 'display: none;' ?>" onsubmit="return validatePasswordForm()" data-aos="zoom-in" data-aos-delay="200">
                            <div class="form-header">
                                <button type="button" class="back-btn" onclick="showMethodSelection()">
                                    <span class="back-icon">←</span>
                                    <?= LanguageHelper::t('back', 'Back') ?>
                                </button>
                                <h3><?= LanguageHelper::t('password_login', 'Password Login') ?></h3>
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="250">
                                <label for="login" class="form-label">
                                    <span class="label-icon">📧</span>
                                    <?= LanguageHelper::t('email_or_phone', 'Email or Phone Number') ?> *
                                </label>
                                <input type="text" id="login" name="login" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('enter_email_phone', 'Enter your email or phone number') ?>" required 
                                       value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                                       maxlength="<?php echo $inputLimits['login']; ?>"
                                       oninput="validateInput(this, <?php echo $inputLimits['login']; ?>)">
                                <div class="input-feedback" id="loginFeedback"></div>
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                                <label for="password" class="form-label">
                                    <span class="label-icon">🔒</span>
                                    <?= LanguageHelper::t('password', 'Password') ?> *
                                </label>
                                <input type="password" id="password" name="password" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('enter_password', 'Enter your password') ?>" required
                                       maxlength="<?php echo $inputLimits['password']; ?>"
                                       oninput="validateInput(this, <?php echo $inputLimits['password']; ?>)">
                                <div class="input-feedback" id="passwordFeedback"></div>
                                <small class="form-text"><?= LanguageHelper::t('guest_users_password', 'Guest users: Use "DemoGuest" as password') ?></small>
                            </div>
                            
                            <div class="form-options" data-aos="fade-up" data-aos-delay="350">
                                <div class="form-checkbox">
                                    <input type="checkbox" id="remember_me" name="remember_me" checked>
                                    <label for="remember_me"><?= LanguageHelper::t('keep_me_logged_in', 'Keep me logged in') ?></label>
                                </div>
                                <a href="#" class="forgot-password"><?= LanguageHelper::t('forgot_password', 'Forgot Password?') ?></a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-auth" data-aos="zoom-in" data-aos-delay="400">
                                <span class="btn-icon">→</span>
                                <?= LanguageHelper::t('sign_in', 'Sign In') ?>
                            </button>
                            
                            <div class="form-footer" data-aos="fade-up" data-aos-delay="450">
                                <p><?= LanguageHelper::t('prefer_otp_login', 'Prefer OTP login?') ?> <a href="#" onclick="switchToOTP()" class="switch-link"><?= LanguageHelper::t('click_switch', 'Click here to switch') ?></a></p>
                            </div>
                        </form>

                        <form id="otpPhoneForm" class="auth-form" action="<?= $pathConfig->url('otp/send-otp') ?>" method="POST" style="<?php echo $method === 'otp' ? 'display: block;' : 'display: none;' ?>" onsubmit="return validateOTPForm()" data-aos="zoom-in" data-aos-delay="200">
                            <div class="form-header">
                                <button type="button" class="back-btn" onclick="showMethodSelection()">
                                    <span class="back-icon">←</span>
                                    <?= LanguageHelper::t('back', 'Back') ?>
                                </button>
                                <h3><?= LanguageHelper::t('otp_login', 'OTP Login') ?></h3>
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="250">
                                <label for="otp_phone" class="form-label">
                                    <span class="label-icon">📞</span>
                                    <?= LanguageHelper::t('phone_number', 'Phone Number') ?> *
                                </label>
                                <input type="tel" id="otp_phone" name="phone" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('enter_10_digit_phone_placeholder', 'Enter your 10-digit phone number') ?>" 
                                       pattern="\d{10}" maxlength="10" required
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       oninput="validatePhoneInput(this)">
                                <div class="input-feedback" id="phoneFeedback"></div>
                                <small class="form-text"><?= LanguageHelper::t('enter_10_digit_phone', 'Enter your 10-digit phone number') ?></small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-auth" data-aos="zoom-in" data-aos-delay="300">
                                <span class="btn-icon">📲</span>
                                <?= LanguageHelper::t('send_otp', 'Send OTP') ?>
                            </button>
                            
                            <div class="form-footer" data-aos="fade-up" data-aos-delay="350">
                                <p><?= LanguageHelper::t('prefer_password_login', 'Prefer password login?') ?> <a href="#" onclick="switchToPassword()" class="switch-link"><?= LanguageHelper::t('click_switch', 'Click here to switch') ?></a></p>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($form_type === 'phone_input'): ?>
                        <form id="otpPhoneForm" class="auth-form" action="<?= $pathConfig->url('otp/send-otp') ?>" method="POST" onsubmit="return validateOTPForm()" data-aos="zoom-in" data-aos-delay="200">
                            <div class="form-header">
                                <a href="<?= $pathConfig->url('login') ?>" class="back-btn">
                                    <span class="back-icon">←</span>
                                    <?= LanguageHelper::t('back_to_login', 'Back to Login') ?>
                                </a>
                                <h3><?= LanguageHelper::t('otp_login', 'OTP Login') ?></h3>
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="250">
                                <label for="otp_phone" class="form-label">
                                    <span class="label-icon">📞</span>
                                    <?= LanguageHelper::t('phone_number', 'Phone Number') ?> *
                                </label>
                                <input type="tel" id="otp_phone" name="phone" class="form-input" 
                                       placeholder="<?= LanguageHelper::t('enter_10_digit_phone_placeholder', 'Enter your 10-digit phone number') ?>" 
                                       pattern="\d{10}" maxlength="10" required
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       oninput="validatePhoneInput(this)">
                                <div class="input-feedback" id="phoneFeedback"></div>
                                <small class="form-text"><?= LanguageHelper::t('enter_10_digit_phone', 'Enter your 10-digit phone number') ?></small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-auth" data-aos="zoom-in" data-aos-delay="300">
                                <span class="btn-icon">📲</span>
                                <?= LanguageHelper::t('send_otp', 'Send OTP') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($form_type === 'method_selection'): ?>
                    <div class="guest-help" style="<?php echo $method === 'otp' ? 'display: none;' : ''; ?>" data-aos="fade-up" data-aos-delay="500">
                        <h4>🎁 <?= LanguageHelper::t('guest_user', 'Guest User?') ?></h4>
                        <p><?= LanguageHelper::t('guest_order_help', 'If you placed an order as a guest, use your phone number and the default password:') ?></p>
                        <div class="default-password" data-aos="zoom-in" data-aos-delay="550">
                            <code>DemoGuest</code>
                            <button class="copy-btn" onclick="copyPassword()">📋</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($form_type === 'method_selection' && $isGoogleEnabled): ?>
                    <div class="social-login" data-aos="fade-up" data-aos-delay="550">
                        <div class="divider">
                            <span><?= LanguageHelper::t('or_continue_with', 'Or continue with') ?></span>
                        </div>
                        <a href="<?= $pathConfig->url('social/google') ?>" class="btn btn-social google-btn">
                            <img src="<?= $assets_path ?>/img/google-logo.svg" alt="Google" class="social-logo">
                            <?= LanguageHelper::t('sign_in_google', 'Sign in with Google') ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="auth-footer" data-aos="fade-up" data-aos-delay="600">
                        <p><?= LanguageHelper::t('dont_have_account', 'Don\'t have an account?') ?> <a href="<?= $pathConfig->url('signup') ?>" class="signup-link"><?= LanguageHelper::t('sign_up_here', 'Sign up here') ?></a></p>
                       <?php if ($form_type === 'method_selection'): ?>
                        <p><a href="#" onclick="showGuestHelp()" class="help-link"><?= LanguageHelper::t('guest_user_help', 'Guest user help') ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <div id="guestHelpModal" class="modal">
            <div class="modal-content" data-aos="zoom-in">
                <button class="modal-close" onclick="hideGuestHelp()">×</button>
                <h3>🌸 <?= LanguageHelper::t('guest_user_assistance', 'Guest User Assistance') ?></h3>
                <p><?= LanguageHelper::t('guest_order_help_detailed', 'If you placed an order as a guest user:') ?></p>
                <ul>
                    <li data-aos="fade-right" data-aos-delay="100"><?= LanguageHelper::t('use_phone_checkout', 'Use the phone number you provided during checkout') ?></li>
                    <li data-aos="fade-right" data-aos-delay="200"><?= LanguageHelper::t('password_DemoGuest', 'Password:') ?> <strong>DemoGuest</strong></li>
                    <li data-aos="fade-right" data-aos-delay="300"><?= LanguageHelper::t('change_password_after_login', 'After login, you can change your password in account settings') ?></li>
                </ul>
                <p data-aos="fade-up" data-aos-delay="400"><?= LanguageHelper::t('cant_remember_phone', 'Can\'t remember your phone number? Contact customer support.') ?></p>
                <button class="btn btn-secondary" onclick="hideGuestHelp()" data-aos="zoom-in" data-aos-delay="500"><?= LanguageHelper::t('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</main>
<!-- Main Content End -->

<style>
/* Modern Color Palette - Orange, Black, White Theme */
:root {
    --primary-orange: #ff9800;
    --primary-orange-dark: #e68900;
    --primary-orange-light: #ffe0b2;
    --secondary-black: #1a1a1a;
    --secondary-black-light: #333333;
    --accent-orange: #ff7700;
    --accent-orange-light: #ffb84d;
    --success-green: #4caf50;
    --success-green-light: #c8e6c9;
    --error-red: #f44336;
    --error-red-light: #ffcdd2;
    --warning-orange: #ff9800;
    --warning-orange-light: #ffe0b2;
    --text-dark: #1a1a1a;
    --text-medium: #555555;
    --text-light: #888888;
    --border-color: #e8e8e8;
    --background-light: #fafafa;
    --background-white: #ffffff;
    --shadow-light: rgba(0, 0, 0, 0.06);
    --shadow-medium: rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #ff9800 0%, #ff7700 100%);
    --gradient-light: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
}

/* Login Page Specific Styles */
.auth-page-wrapper {
    min-height: calc(100vh - 80px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: var(--background-light);
}

.auth-container-wrapper { 
    display: flex; 
    width: 100%; 
    max-width: 100%;
    border-radius: 0;
    overflow: hidden; 
    box-shadow: none;
    background: var(--background-white); 
    transition: transform 0.3s ease;
    min-height: 100vh;
}

.auth-container-wrapper:hover {
    transform: none;
}

.auth-section { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 40px 20px;
    background: var(--background-white);
    position: relative;
    width: 100%;
    min-height: 100vh;
}

.auth-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-primary);
}

.auth-container { 
    width: 100%; 
    max-width: 500px; 
}

.auth-header { 
    text-align: center; 
    margin-bottom: 35px; 
}

.auth-header h2 { 
    font-size: 28px; 
    color: var(--secondary-black); 
    margin-bottom: 8px; 
    font-weight: 700;
    letter-spacing: -0.5px;
} 

.auth-header p { 
    color: var(--text-medium); 
    font-size: 15px; 
    line-height: 1.5;
}

.alert { 
    padding: 14px 16px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    font-size: 14px; 
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border-left: 4px solid transparent;
}

.alert-error { 
    background-color: var(--error-red-light); 
    color: var(--error-red); 
    border-left-color: var(--error-red);
}

.alert-success { 
    background-color: var(--success-green-light); 
    color: var(--success-green); 
    border-left-color: var(--success-green);
}

.alert-icon {
    font-size: 16px;
    flex-shrink: 0;
}

.auth-form { 
    margin-bottom: 20px; 
    background: var(--background-white);
    padding: 0;
    border-radius: 0;
    border: none;
    box-shadow: none;
}

.form-header { 
    display: flex; 
    align-items: center; 
    margin-bottom: 25px; 
    border-bottom: none;
    padding-bottom: 0;
    position: relative;
}

.back-btn { 
    background: transparent; 
    border: none;
    border-radius: 0;
    color: var(--text-medium); 
    cursor: pointer; 
    font-size: 14px; 
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.back-btn:hover { 
    background: transparent; 
    color: var(--primary-orange);
    border-color: transparent;
}

.back-icon {
    font-size: 16px;
}

.form-header h3 { 
    margin: 0 auto; 
    color: var(--text-dark); 
    font-size: 22px;
    font-weight: 600;
}

.form-group { 
    margin-bottom: 20px; 
    position: relative;
}

.form-label { 
    display: flex; 
    align-items: center;
    gap: 8px;
    margin-bottom: 8px; 
    font-weight: 500; 
    color: var(--text-dark); 
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.label-icon {
    font-size: 16px;
    color: var(--primary-orange);
}

.form-input {
    width: 100%; 
    padding: 14px 16px; 
    border: 1px solid var(--border-color); 
    border-radius: 6px;
    font-size: 15px; 
    transition: all 0.3s ease;
    background: var(--background-white);
    color: var(--text-dark);
}

.form-input::placeholder {
    color: var(--text-light);
}

.form-input:focus {
    outline: none; 
    border-color: var(--primary-orange); 
    box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
    background: var(--background-white);
}

.form-text { 
    display: block; 
    margin-top: 6px; 
    font-size: 12px; 
    color: var(--text-light); 
    font-style: normal;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid var(--border-color);
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-checkbox input[type="checkbox"]:checked {
    background-color: var(--primary-orange);
    border-color: var(--primary-orange);
}

.form-checkbox label {
    cursor: pointer;
    font-size: 13px;
    color: var(--text-medium);
    user-select: none;
}

.forgot-password {
    color: var(--primary-orange);
    font-size: 13px;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.forgot-password:hover {
    color: var(--primary-orange-dark);
    text-decoration: underline;
}

.btn {
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    padding: 14px 24px;
    border: none; 
    border-radius: 6px; 
    font-size: 15px; 
    font-weight: 600; 
    cursor: pointer;
    transition: all 0.3s ease; 
    width: 100%; 
    gap: 12px;
    position: relative;
    overflow: hidden;
}

.btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn:focus:not(:active)::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(40, 40);
        opacity: 0;
    }
}

.btn-primary { 
    background: var(--gradient-primary); 
    color: white; 
}

.btn-primary:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(255, 152, 0, 0.25);
}

.btn-secondary {
    background: var(--background-light);
    color: var(--text-dark);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--border-color);
    transform: translateY(-2px);
}

.btn-auth {
    margin-top: 8px;
}

.btn-icon {
    font-size: 18px;
}

.btn-social { 
    background-color: var(--background-white); 
    color: var(--text-dark); 
    border: 1px solid var(--border-color); 
    text-decoration: none; 
    transition: all 0.3s ease;
}

.btn-social:hover { 
    background-color: var(--background-light); 
    border-color: var(--text-light);
    transform: translateY(-2px);
    text-decoration: none; 
    box-shadow: 0 4px 15px var(--shadow-light);
}

.google-btn {
    background: white;
    color: var(--text-dark);
}

.social-logo { 
    width: 20px; 
    height: 20px; 
}

.social-login {
    margin: 25px 0;
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 25px 0;
    color: var(--text-light);
    font-size: 13px;
}

.divider::before, .divider::after {
    content: ''; 
    flex: 1; 
    border-bottom: 1px solid var(--border-color);
}

.divider span { 
    padding: 0 12px; 
}

.auth-footer { 
    text-align: center; 
    margin-top: 25px; 
    padding-top: 25px;
    border-top: 1px solid var(--border-color);
}

.auth-footer p { 
    margin-bottom: 12px; 
    color: var(--text-medium); 
    font-size: 13px; 
}

.signup-link { 
    color: var(--primary-orange); 
    text-decoration: none; 
    font-weight: 600; 
    transition: all 0.3s ease; 
    position: relative;
}

.signup-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary-orange);
    transition: width 0.3s ease;
}

.signup-link:hover { 
    color: var(--primary-orange-dark); 
}

.signup-link:hover::after {
    width: 100%;
}

.guest-help {
    background: linear-gradient(135deg, var(--primary-orange-light) 0%, #fff8f0 100%); 
    border-left: 4px solid var(--primary-orange); 
    padding: 18px;
    margin: 20px 0; 
    border-radius: 8px; 
    animation: fadeIn 0.5s ease forwards;
    animation-delay: 0.4s; 
    opacity: 0;
    border: 1px solid rgba(255, 152, 0, 0.1);
}

.guest-help h4 { 
    margin-top: 0; 
    color: var(--primary-orange); 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    font-size: 16px;
}

.guest-help p {
    margin: 8px 0;
    font-size: 13px;
}

.default-password { 
    background: white; 
    border: 1px solid var(--border-color); 
    padding: 12px 16px; 
    border-radius: 6px; 
    font-family: 'Courier New', monospace; 
    font-weight: 600; 
    margin: 12px 0; 
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 15px;
}

.default-password code {
    color: var(--primary-orange);
    font-size: 15px;
}

.copy-btn {
    background: var(--primary-orange-light);
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 13px;
    color: var(--primary-orange);
    transition: all 0.3s ease;
}

.copy-btn:hover {
    background: var(--primary-orange);
    color: white;
}

.help-link { 
    color: var(--primary-orange); 
    text-decoration: none; 
    font-weight: 500; 
    transition: all 0.3s ease;
}

.help-link:hover { 
    color: var(--primary-orange-dark); 
    text-decoration: underline; 
}

.switch-link {
    color: var(--primary-orange);
    font-weight: 500;
    text-decoration: none;
    position: relative;
}

.switch-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background: var(--primary-orange);
    transition: width 0.3s ease;
}

.switch-link:hover::after {
    width: 100%;
}

.modal { 
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.5); 
    backdrop-filter: blur(5px);
    z-index: 10000; 
    animation: fadeIn 0.3s ease forwards; 
}

.modal-content {
    position: absolute; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%); 
    background: white;
    padding: 35px; 
    border-radius: 12px; 
    width: 90%; 
    max-width: 480px; 
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-light);
    cursor: pointer;
    padding: 5px;
    line-height: 1;
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: var(--error-red);
}

.modal-content h3 { 
    color: var(--primary-orange); 
    margin-bottom: 18px; 
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-content p {
    font-size: 14px;
    color: var(--text-medium);
    line-height: 1.5;
}

.modal-content ul { 
    margin: 18px 0; 
    padding-left: 20px; 
    list-style: none;
}

.modal-content li { 
    margin-bottom: 12px; 
    padding-left: 28px;
    position: relative;
    font-size: 14px;
}

.modal-content li::before {
    content: "✓";
    position: absolute;
    left: 0;
    color: var(--success-green);
    font-weight: bold;
}

.method-selection { 
    text-align: center; 
    margin-bottom: 30px; 
    padding: 25px;
    background: var(--background-light);
    border-radius: 8px;
    border: 1px dashed var(--border-color);
}

.method-selection h3 { 
    margin-bottom: 8px; 
    color: var(--text-dark); 
    font-size: 20px;
    font-weight: 600;
}

.method-subtitle {
    color: var(--text-light);
    margin-bottom: 25px;
    font-size: 13px;
}

.method-buttons { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 15px; 
    justify-content: center; 
    max-width: 100%;
}

.method-btn {
    flex: 1; 
    max-width: none; 
    padding: 30px 20px; 
    border: 2px solid var(--border-color); 
    border-radius: 8px;
    background: var(--background-white); 
    cursor: pointer; 
    transition: all 0.3s ease; 
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 180px;
    justify-content: center;
}

.method-btn:hover { 
    border-color: var(--primary-orange); 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(255, 152, 0, 0.15); 
}

.method-icon { 
    font-size: 28px; 
    display: block; 
    margin-bottom: 12px; 
    color: var(--primary-orange);
}

.method-title { 
    display: block; 
    font-weight: 600; 
    color: var(--text-dark); 
    margin-bottom: 6px; 
    font-size: 16px;
}

.method-desc { 
    display: block; 
    font-size: 12px; 
    color: var(--text-light); 
    line-height: 1.4;
}

.form-footer { 
    text-align: center; 
    margin-top: 20px; 
    padding-top: 20px; 
    border-top: 1px solid var(--border-color); 
    font-size: 13px;
}

.input-feedback { 
    font-size: 12px; 
    margin-top: 6px; 
    min-height: 18px; 
    padding: 0 4px;
}

.input-feedback.error { 
    color: var(--error-red); 
}

.input-feedback.success { 
    color: var(--success-green); 
}

.input-feedback.warning { 
    color: var(--warning-orange); 
}

.form-input.error { 
    border-color: var(--error-red); 
    background-color: var(--error-red-light);
}

.form-input.success { 
    border-color: var(--success-green); 
    background-color: var(--success-green-light);
}

.char-counter { 
    font-size: 11px; 
    text-align: right; 
    margin-top: 4px; 
    color: var(--text-light); 
}

.char-counter.warning { 
    color: var(--warning-orange); 
}

.char-counter.error { 
    color: var(--error-red); 
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.auth-form .form-group { animation: fadeIn 0.5s ease forwards; }
.auth-form .form-group:nth-child(1) { animation-delay: 0.1s; }
.auth-form .form-group:nth-child(2) { animation-delay: 0.2s; }
.btn-auth { animation: fadeIn 0.5s ease 0.3s forwards; opacity: 0; }

.form-input:focus, .btn:focus, .method-btn:focus { 
    outline: 2px solid var(--primary-orange); 
    outline-offset: 2px; 
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .auth-page-wrapper {
        padding: 0;
        min-height: 100vh;
    }
    
    .auth-container-wrapper { 
        border-radius: 0;
        margin: 0;
        min-height: 100vh;
    }
    
    .auth-section { 
        padding: 30px 16px;
        min-height: 100vh;
    }
    
    .method-buttons { 
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .method-btn { 
        max-width: 100%; 
        width: 100%;
        padding: 25px 16px;
        min-height: 160px;
    }
    
    .auth-container { 
        max-width: 100%; 
    }
    
    .auth-header h2 {
        font-size: 24px;
        margin-bottom: 6px;
    }
    
    .auth-header p {
        font-size: 14px;
    }
    
    .auth-form {
        padding: 0;
        margin-bottom: 15px;
    }
    
    .form-header {
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 18px;
    }
    
    .form-input {
        padding: 13px 14px;
        font-size: 16px;
    }
    
    .btn {
        padding: 13px 20px;
        font-size: 14px;
    }
    
    .method-icon {
        font-size: 24px;
    }
    
    .method-title {
        font-size: 14px;
    }
    
    .method-desc {
        font-size: 11px;
    }
    
    .guest-help {
        padding: 16px;
        margin: 18px 0;
        border-radius: 6px;
    }
    
    .guest-help h4 {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .form-label {
        font-size: 12px;
        gap: 6px;
    }
    
    .form-text {
        font-size: 11px;
        margin-top: 5px;
    }
    
    .auth-footer {
        margin-top: 20px;
        padding-top: 20px;
    }
    
    .auth-footer p {
        font-size: 12px;
        margin-bottom: 10px;
    }
    
    .modal-content {
        padding: 28px 20px;
        width: 85%;
        max-width: 420px;
    }
    
    .modal-content h3 {
        font-size: 20px;
        margin-bottom: 16px;
    }
    
    .modal-content p {
        font-size: 13px;
    }
    
    .modal-content li {
        font-size: 13px;
        margin-bottom: 10px;
        padding-left: 24px;
    }
}

@media (max-width: 480px) {
    .auth-section {
        padding: 20px 12px;
    }
    
    .auth-header {
        margin-bottom: 25px;
    }
    
    .auth-header h2 {
        font-size: 22px;
    }
    
    .auth-header p {
        font-size: 13px;
    }
    
    .form-input {
        padding: 12px 12px;
        font-size: 16px;
        border-radius: 4px;
    }
    
    .btn {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .method-btn {
        padding: 20px 12px;
        min-height: 150px;
    }
    
    .method-icon {
        font-size: 22px;
    }
    
    .method-title {
        font-size: 13px;
    }
    
    .method-desc {
        font-size: 10px;
    }
    
    .method-selection {
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .method-selection h3 {
        font-size: 18px;
    }
    
    .method-subtitle {
        font-size: 12px;
        margin-bottom: 20px;
    }
    
    .form-label {
        font-size: 11px;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .default-password {
        padding: 10px 12px;
        font-size: 13px;
    }
    
    .copy-btn {
        padding: 4px 10px;
        font-size: 11px;
    }
    
    .guest-help {
        padding: 14px;
        margin: 16px 0;
    }
    
    .guest-help h4 {
        font-size: 13px;
    }
    
    .guest-help p {
        font-size: 12px;
    }
    
    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .forgot-password {
        font-size: 12px;
    }
    
    .modal-content {
        padding: 24px 16px;
        width: 90%;
    }
    
    .modal-content h3 {
        font-size: 18px;
    }
    
    .form-text {
        font-size: 10px;
    }
    
    .divider {
        margin: 20px 0;
        font-size: 12px;
    }
    
    .divider span {
        padding: 0 8px;
    }
}

@media (max-height: 600px) and (max-width: 768px) {
    .auth-section {
        padding: 20px;
        min-height: auto;
    }
    
    .auth-header {
        margin-bottom: 15px;
    }
    
    .auth-header h2 {
        font-size: 20px;
    }
    
    .form-group {
        margin-bottom: 12px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const method = urlParams.get('method');
    
    if (method === 'otp') showOTPForm();
    else if (method === 'password') showPasswordForm();
    else showMethodSelection();
    
    // Initialize form inputs
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() { 
            this.parentElement.classList.add('focused'); 
        });
        input.addEventListener('blur', function() { 
            if (this.value === '') this.parentElement.classList.remove('focused'); 
        });
        if (input.value !== '') input.parentElement.classList.add('focused');
        if (input.type !== 'password' && input.id !== 'otp_phone') addCharacterCounter(input);
    });
    
    const phoneInput = document.getElementById('otp_phone');
    if (phoneInput) phoneInput.addEventListener('input', function(e) { validatePhoneInput(this); });

    // Copy password functionality
    const copyBtn = document.querySelector('.copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyPassword);
    }

    // Initialize AOS animations
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 600,
            once: true,
            offset: 100,
            delay: 100
        });
    }
});

function validateInput(input, maxLength) {
    const value = input.value;
    const feedback = document.getElementById(input.id + 'Feedback');
    const sanitizedValue = value.replace(/[<>]/g, '');
    if (sanitizedValue !== value) input.value = sanitizedValue;
    if (value.length > maxLength) {
        input.value = value.substring(0, maxLength);
        showFeedback(feedback, `Maximum ${maxLength} characters allowed`, 'error');
        return false;
    }
    if (input.id === 'login') return validateLoginInput(input, feedback);
    if (input.id === 'password') return validatePasswordInput(input, feedback);
    showFeedback(feedback, '', 'success');
    return true;
}

function validateLoginInput(input, feedback) {
    const value = input.value.trim();
    if (value === '') { showFeedback(feedback, '', 'success'); return false; }
    if (value.includes('@')) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) { showFeedback(feedback, 'Please enter a valid email address', 'error'); return false; }
    } else {
        const phoneRegex = /^\d{10}$/;
        if (value.length === 10 && !phoneRegex.test(value)) { showFeedback(feedback, 'Please enter a valid 10-digit phone number', 'error'); return false; }
    }
    showFeedback(feedback, 'Valid input', 'success');
    return true;
}

function validatePasswordInput(input, feedback) {
    const value = input.value;
    if (value.length === 0) { showFeedback(feedback, '', 'success'); return false; }
    if (value.length < 4) { showFeedback(feedback, 'Password must be at least 4 characters', 'warning'); return false; }
    showFeedback(feedback, 'Valid password', 'success');
    return true;
}

function validatePhoneInput(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 10) value = value.slice(0, 10);
    input.value = value;
    const feedback = document.getElementById('phoneFeedback');
    if (value.length === 0) { showFeedback(feedback, '', 'success'); return false; }
    if (value.length < 10) { showFeedback(feedback, `${10 - value.length} digits remaining`, 'warning'); return false; }
    if (value.length === 10) { showFeedback(feedback, 'Valid phone number', 'success'); return true; }
    return false;
}

function validatePasswordForm() {
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    const loginValid = validateLoginInput(loginInput, document.getElementById('loginFeedback'));
    const passwordValid = validatePasswordInput(passwordInput, document.getElementById('passwordFeedback'));
    if (!loginValid) { loginInput.focus(); return false; }
    if (!passwordValid) { passwordInput.focus(); return false; }
    loginInput.value = loginInput.value.trim();
    passwordInput.value = passwordInput.value.trim();
    return true;
}

function validateOTPForm() {
    const phoneInput = document.getElementById('otp_phone');
    const phoneValue = phoneInput.value.replace(/\D/g, '');
    const feedback = document.getElementById('phoneFeedback');
    
    if (phoneValue.length !== 10) {
        showFeedback(feedback, 'Please enter a valid 10-digit phone number', 'error');
        phoneInput.focus();
        return false;
    }
    
    showFeedback(feedback, 'Valid phone number', 'success');
    return true;
}

function showFeedback(feedbackElement, message, type) {
    if (!feedbackElement) return;
    feedbackElement.textContent = message;
    feedbackElement.className = 'input-feedback';
    if (type && message) feedbackElement.classList.add(type);
}

function addCharacterCounter(input) {
    const counter = document.createElement('div');
    counter.className = 'char-counter';
    counter.textContent = `0/${input.maxLength}`;
    input.parentNode.appendChild(counter);
    input.addEventListener('input', function() {
        const length = this.value.length;
        const maxLength = parseInt(this.maxLength);
        counter.textContent = `${length}/${maxLength}`;
        if (length > maxLength * 0.8) { 
            counter.classList.add('warning'); 
            counter.classList.remove('error'); 
        }
        else if (length > maxLength) { 
            counter.classList.add('error'); 
            counter.classList.remove('warning'); 
        }
        else { 
            counter.className = 'char-counter'; 
        }
    });
    const event = new Event('input');
    input.dispatchEvent(event);
}

function showMethodSelection() {
    document.getElementById('methodSelection').style.display = 'block';
    document.getElementById('passwordLoginForm').style.display = 'none';
    document.getElementById('otpPhoneForm').style.display = 'none';
    const guestHelp = document.querySelector('.guest-help');
    if (guestHelp) guestHelp.style.display = 'block';
    window.history.replaceState({}, '', '<?= $pathConfig->url("login") ?>');
}

function selectLoginMethod(method) {
    if (method === 'password') showPasswordForm();
    else if (method === 'otp') showOTPForm();
}

function showPasswordForm() {
    document.getElementById('methodSelection').style.display = 'none';
    document.getElementById('passwordLoginForm').style.display = 'block';
    document.getElementById('otpPhoneForm').style.display = 'none';
    const guestHelp = document.querySelector('.guest-help');
    if (guestHelp) guestHelp.style.display = 'block';
    document.getElementById('login').focus();
    window.history.replaceState({}, '', '<?= $pathConfig->url("login") ?>?method=password');
}

function showOTPForm() {
    document.getElementById('methodSelection').style.display = 'none';
    document.getElementById('passwordLoginForm').style.display = 'none';
    document.getElementById('otpPhoneForm').style.display = 'block';
    const guestHelp = document.querySelector('.guest-help');
    if (guestHelp) guestHelp.style.display = 'none';
    const phoneInput = document.getElementById('otp_phone');
    if (phoneInput) phoneInput.focus();
    window.history.replaceState({}, '', '<?= $pathConfig->url("login") ?>?method=otp');
}

function switchToOTP() { 
    showOTPForm(); 
    return false;
}

function switchToPassword() { 
    showPasswordForm(); 
    return false;
}

function showGuestHelp() { 
    document.getElementById('guestHelpModal').style.display = 'block'; 
    return false;
}

function hideGuestHelp() { 
    document.getElementById('guestHelpModal').style.display = 'none'; 
}

function copyPassword() {
    const password = 'DemoGuest';
    navigator.clipboard.writeText(password).then(() => {
        const btn = document.querySelector('.copy-btn');
        if (btn) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '✅';
            btn.style.background = '#4caf50';
            btn.style.color = 'white';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
                btn.style.color = '';
            }, 2000);
        }
    });
}

// Close modal when clicking outside
document.getElementById('guestHelpModal').addEventListener('click', function(e) {
    if (e.target === this) hideGuestHelp();
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideGuestHelp();
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../layouts/footer.php';
?>