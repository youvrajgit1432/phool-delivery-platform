<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Rider - Phool Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            color: #333;
        }

        body {
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
            min-height: 100vh;
            padding: 20px;
        }

        /* Navigation Bar */
        .navbar-register {
            background: #ffffff;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 12px 0;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
        }

        .navbar-register .navbar-brand {
            font-size: 24px;
            font-weight: 800;
            color: #FF6B35;
            letter-spacing: -0.5px;
        }

        .navbar-register .nav-link {
            color: #333 !important;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .register-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
        }

        .register-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 100%;
            max-width: 1200px;
            border: 1px solid #f0f0f0;
            animation: slideUp 0.6s ease-out;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .register-sidebar {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            padding: 50px 40px;
            border-right: 2px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .register-sidebar::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 107, 53, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .register-sidebar > * {
            position: relative;
            z-index: 1;
        }

        .sidebar-title {
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 12px;
            color: #000000;
            letter-spacing: -0.5px;
            background: linear-gradient(90deg, #000000 0%, #FF6B35 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 35px;
            line-height: 1.7;
            font-weight: 500;
        }

        .sidebar-benefit {
            display: flex;
            gap: 18px;
            margin-bottom: 25px;
            padding: 18px;
            background: #f9f9f9;
            border-radius: 10px;
            border-left: 4px solid #FF6B35;
            transition: all 0.3s ease;
        }

        .sidebar-benefit:hover {
            background: #f5f5f5;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .sidebar-benefit-icon {
            font-size: 24px;
            flex-shrink: 0;
            line-height: 1;
            color: #FF6B35;
        }

        .sidebar-benefit-text h4 {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 5px;
            letter-spacing: -0.3px;
        }

        .sidebar-benefit-text p {
            font-size: 13px;
            color: #666;
            margin: 0;
            line-height: 1.5;
            font-weight: 400;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            color: #000000;
            padding: 30px 25px;
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
        }

        .register-header h1 {
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 5px;
            color: #000000;
        }

        .register-header p {
            font-size: 13px;
            color: #666;
            margin: 0;
            font-weight: 500;
        }

        .register-body {
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
            color: #000000;
        }

        .form-control::placeholder {
            color: #999;
        }

        .form-control:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
            background-color: #ffffff;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: #fff5f5;
        }

        .form-control.is-valid {
            border-color: #28a745;
            background-color: #f5fff5;
        }

        .validation-feedback {
            font-size: 12px;
            margin-top: 6px;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .validation-feedback.invalid {
            color: #dc3545;
            display: block;
        }

        .validation-feedback.valid {
            color: #28a745;
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-toggle-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #FF6B35;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: color 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #FF8A5E;
        }

        .form-control.password-input {
            padding-right: 45px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8A5E 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #FF8A5E 0%, #FF5722 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .register-footer {
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            margin-top: 20px;
        }

        .register-footer p {
            margin-bottom: 8px;
            color: #666;
            font-size: 13px;
        }

        .register-footer a {
            color: #FF6B35;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s ease;
        }

        .register-footer a:hover {
            color: #000000;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        /* Footer */
        .footer-section {
            background: #f5f5f5;
            color: #333;
            padding: 50px 20px 30px;
            margin-top: 60px;
            border-top: 1px solid #e0e0e0;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 30px;
        }

        .footer-col h4 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-col ul {
            list-style: none;
            padding: 0;
        }

        .footer-col ul li {
            margin-bottom: 12px;
        }

        .footer-col ul li a {
            color: #666;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .footer-col ul li a:hover {
            color: #FF6B35;
            text-decoration: underline;
        }

        .footer-bottom {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .register-card {
                grid-template-columns: 1fr;
            }

            .register-sidebar {
                border-right: none;
                border-bottom: 2px solid #f0f0f0;
                padding: 35px 25px;
                order: 2;
            }

            .register-body {
                order: 1;
            }

            .sidebar-title {
                font-size: 22px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 15px;
            }

            .navbar-register {
                margin-bottom: 20px;
            }

            .register-header {
                padding: 20px 15px;
            }

            .register-header h1 {
                font-size: 20px;
            }

            .register-body {
                padding: 20px 15px;
            }

            .form-control {
                font-size: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .sidebar-title {
                font-size: 18px;
                margin-bottom: 12px;
            }

            .sidebar-subtitle {
                font-size: 12px;
                margin-bottom: 20px;
            }

            .sidebar-benefit {
                margin-bottom: 18px;
                padding: 15px;
            }

            .sidebar-benefit-icon {
                font-size: 24px;
            }

            .sidebar-benefit-text h4 {
                font-size: 13px;
            }

            .sidebar-benefit-text p {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-register navbar-expand-lg">
        <div class="container-lg">
            <a class="navbar-brand" href="<?php echo htmlspecialchars(app_url('/')); ?>">
                🚴 Phool Delivery
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(app_url('/login')); ?>">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="register-wrapper">
        <div class="register-card">
            <!-- Left Sidebar -->
            <div class="register-sidebar">
                <div class="sidebar-title">Join Our Rider Network</div>
                <div class="sidebar-subtitle">Start earning today and become part of Phool Delivery's fastest growing delivery community.</div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">💰</div>
                    <div class="sidebar-benefit-text">
                        <h4>Competitive Pay</h4>
                        <p>Earn 500-1500 per day with transparent pricing</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">🗓️</div>
                    <div class="sidebar-benefit-text">
                        <h4>Flexible Schedule</h4>
                        <p>Work whenever you want, take breaks as needed</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">📱</div>
                    <div class="sidebar-benefit-text">
                        <h4>Easy App</h4>
                        <p>Simple dashboard for order management</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">🏆</div>
                    <div class="sidebar-benefit-text">
                        <h4>Rewards Program</h4>
                        <p>Earn bonus points and incentives daily</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">🛡️</div>
                    <div class="sidebar-benefit-text">
                        <h4>Insurance Coverage</h4>
                        <p>Accident and delivery protection included</p>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div style="display: flex; flex-direction: column;">
                <div class="register-header">
                    <h1>Create Your Account</h1>
                    <p>Start Your Delivery Journey</p>
                </div>

                <div class="register-body">
                    <div id="alertContainer"></div>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars(app_url('/register')); ?>" id="registerForm">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                required 
                                placeholder="First name"
                                class="form-control"
                            >
                            <div class="validation-feedback" id="firstNameFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                required 
                                placeholder="10-digit phone number"
                                class="form-control"
                            >
                            <div class="validation-feedback" id="phoneFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input 
                                type="text" 
                                id="last_name" 
                                name="last_name" 
                                required 
                                placeholder="Last name"
                                class="form-control"
                            >
                            <div class="validation-feedback" id="lastNameFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input 
                                type="text" 
                                id="email" 
                                name="email" 
                                required 
                                placeholder="your@email.com"
                                class="form-control"
                            >
                            <div class="validation-feedback" id="emailFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required 
                                    placeholder="Create a strong password"
                                    class="form-control password-input"
                                >
                                <button type="button" id="passwordToggle" class="password-toggle-btn" title="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="validation-feedback" id="passwordFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm" class="form-label">Confirm Password</label>
                            <div class="password-wrapper">
                                <input 
                                    type="password" 
                                    id="password_confirm" 
                                    name="password_confirm" 
                                    required 
                                    placeholder="Confirm password"
                                    class="form-control password-input"
                                >
                                <button type="button" id="confirmPasswordToggle" class="password-toggle-btn" title="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="validation-feedback" id="confirmPasswordFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="rider_type" class="form-label">Rider Type</label>
                            <select id="rider_type" name="rider_type" required class="form-control">
                                <option value="">Select rider type</option>
                                <option value="gig">Gig Worker</option>
                                <option value="in_house">In-House</option>
                                <option value="partner">Partner</option>
                            </select>
                            <div class="validation-feedback" id="riderTypeFeedback"></div>
                        </div>

                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </form>

                    <div class="register-footer">
                        <p>Already have an account? <a href="<?php echo htmlspecialchars(app_url('/login')); ?>">Login here</a></p>
                        <p style="color: #999; font-size: 12px; margin-bottom: 0;">For support: support@phooldelivery.example</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-section" id="contact">
        <div class="footer-content">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>About Phool Delivery</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Our Story</a></li>
                        <li><a href="#">Careers</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>For Riders</h4>
                    <ul>
                        <li><a href="#">Join Us</a></li>
                        <li><a href="#">Guidelines</a></li>
                        <li><a href="#">Support</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:support@phooldelivery.example">support@phooldelivery.example</a></li>
                        <li><a href="tel:+9779803962360">+977 9803962360</a></li>
                        <li><a href="#">Live Chat</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Phool Delivery. All rights reserved. | Building better delivery for everyone</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Session Storage Management
        const SessionManager = {
            FORM_DATA_KEY: 'rider_registration_form_data',
            
            saveField(fieldName, value) {
                let formData = JSON.parse(sessionStorage.getItem(this.FORM_DATA_KEY) || '{}');
                formData[fieldName] = value;
                sessionStorage.setItem(this.FORM_DATA_KEY, JSON.stringify(formData));
            },

            getAllFields() {
                return JSON.parse(sessionStorage.getItem(this.FORM_DATA_KEY) || '{}');
            },

            clearAll() {
                sessionStorage.removeItem(this.FORM_DATA_KEY);
            }
        };

        // Restore form data on page load
        function restoreFormData() {
            const savedData = SessionManager.getAllFields();
            
            Object.keys(savedData).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = savedData[key];
                }
            });
        }

        // Setup auto-save
        function setupAutoSave() {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    SessionManager.saveField(this.name, this.value);
                });

                input.addEventListener('input', function() {
                    SessionManager.saveField(this.name, this.value);
                });
            });
        }

        // Show/Hide Password Toggle
        function setupPasswordToggle() {
            const toggles = [
                { btn: '#passwordToggle', input: '#password' },
                { btn: '#confirmPasswordToggle', input: '#password_confirm' }
            ];

            toggles.forEach(({ btn, input }) => {
                const btnEl = document.querySelector(btn);
                const inputEl = document.querySelector(input);
                if (btnEl && inputEl) {
                    btnEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        const isPassword = inputEl.type === 'password';
                        inputEl.type = isPassword ? 'text' : 'password';
                        this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
                    });
                }
            });
        }

        // Validation Functions
        function validateName(input, feedbackId) {
            const value = input.value.trim();
            const feedback = document.getElementById(feedbackId);
            
            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            }

            if (value.length < 2) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Name must be at least 2 characters';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid name';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validateEmail(input) {
            const value = input.value.trim();
            const feedback = document.getElementById('emailFeedback');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            }

            if (!emailRegex.test(value)) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Invalid email address';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid email';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validatePhone(input) {
            const value = input.value.trim().replace(/[^\d]/g, '');
            const feedback = document.getElementById('phoneFeedback');

            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            }

            if (value.length !== 10) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Phone must be 10 digits';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid phone number';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validatePassword(input) {
            const value = input.value;
            const feedback = document.getElementById('passwordFeedback');

            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            }

            if (value.length < 6) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Password must be at least 6 characters';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Password looks good';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validateConfirmPassword(input) {
            const password = document.getElementById('password').value;
            const value = input.value;
            const feedback = document.getElementById('confirmPasswordFeedback');

            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            }

            if (value !== password) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Passwords do not match';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Passwords match';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validateRiderType(input) {
            const value = input.value;
            const feedback = document.getElementById('riderTypeFeedback');

            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '';
                return true;
            }
        }

        // Setup validation listeners
        function setupValidation() {
            const firstNameInput = document.getElementById('first_name');
            const lastNameInput = document.getElementById('last_name');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('password_confirm');
            const riderTypeInput = document.getElementById('rider_type');

            if (firstNameInput) firstNameInput.addEventListener('input', function() { validateName(this, 'firstNameFeedback'); });
            if (lastNameInput) lastNameInput.addEventListener('input', function() { validateName(this, 'lastNameFeedback'); });
            if (emailInput) emailInput.addEventListener('input', function() { validateEmail(this); });
            if (phoneInput) phoneInput.addEventListener('input', function() { validatePhone(this); });
            if (passwordInput) passwordInput.addEventListener('input', function() { validatePassword(this); });
            if (confirmPasswordInput) confirmPasswordInput.addEventListener('input', function() { validateConfirmPassword(this); });
            if (riderTypeInput) riderTypeInput.addEventListener('change', function() { validateRiderType(this); });
        }

        // Form Submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate all fields
            const isFirstNameValid = validateName(document.getElementById('first_name'), 'firstNameFeedback');
            const isLastNameValid = validateName(document.getElementById('last_name'), 'lastNameFeedback');
            const isEmailValid = validateEmail(document.getElementById('email'));
            const isPhoneValid = validatePhone(document.getElementById('phone'));
            const isPasswordValid = validatePassword(document.getElementById('password'));
            const isConfirmPasswordValid = validateConfirmPassword(document.getElementById('password_confirm'));
            const isRiderTypeValid = validateRiderType(document.getElementById('rider_type'));

            if (!isFirstNameValid || !isLastNameValid || !isEmailValid || !isPhoneValid || !isPasswordValid || !isConfirmPasswordValid || !isRiderTypeValid) {
                return;
            }

            // Form will submit normally via POST
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Account...';
            
            this.submit();
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupPasswordToggle();
            setupValidation();
            restoreFormData();
            setupAutoSave();
        });
    </script>
</body>
</html>
