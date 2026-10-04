<?php
/**
 * Vendor Registration Page - Multi-Step Form
 */
require_once dirname(__FILE__, 3) . '/helpers/url.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Phool Delivery Vendor Panel</title>
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
            color: #000000;
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
            background: radial-gradient(circle, rgba(255, 107, 107, 0.08) 0%, transparent 70%);
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
            background: linear-gradient(90deg, #000000 0%, #FF6B6B 100%);
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
            border-left: 4px solid #FF6B6B;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar-benefit:hover {
            background: #f5f5f5;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .sidebar-benefit-icon {
            font-size: 24px;
            flex-shrink: 0;
            line-height: 1;
            color: #FF6B6B;
        }

        .sidebar-benefit:nth-child(2) .sidebar-benefit-icon {
            animation-delay: 0.2s;
        }

        .sidebar-benefit:nth-child(3) .sidebar-benefit-icon {
            animation-delay: 0.4s;
        }

        .sidebar-benefit:nth-child(4) .sidebar-benefit-icon {
            animation-delay: 0.6s;
        }

        .sidebar-benefit:nth-child(5) .sidebar-benefit-icon {
            animation-delay: 0.8s;
        }

        .sidebar-benefit:nth-child(6) .sidebar-benefit-icon {
            animation-delay: 1s;
        }

        .sidebar-benefit:nth-child(7) .sidebar-benefit-icon {
            animation-delay: 1.2s;
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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutLeft {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-50px);
            }
        }

        @keyframes rotateIn {
            from {
                opacity: 0;
                transform: perspective(1000px) rotateY(-90deg);
            }
            to {
                opacity: 1;
                transform: perspective(1000px) rotateY(0deg);
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

        /* Progress Bar */
        .progress-container {
            padding: 15px 25px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .progress {
            height: 6px;
            background: #e8e8e8;
            border-radius: 3px;
            margin-bottom: 10px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #FF6B6B 0%, #FF8A9B 100%);
            border-radius: 3px;
            transition: width 0.4s ease;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .register-body {
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
        }

        .form-step {
            display: none;
            animation: slideInRight 0.4s ease-out;
        }

        .form-step.active {
            display: block;
        }

        .form-step.exit {
            animation: slideOutLeft 0.3s ease-out;
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
            border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
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
            color: #FF6B6B;
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
            color: #EE5A6F;
        }

        .form-control.password-input {
            padding-right: 45px;
        }

        .step-title {
            font-size: 18px;
            font-weight: 900;
            color: #000000;
            margin-bottom: 5px;
        }

        .step-subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .form-footer {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8A9B 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #FF8A9B 0%, #EE5A6F 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #000000;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #e8e8e8;
            border-color: #d0d0d0;
            transform: translateY(-2px);
        }

        .btn .spinner-border {
            width: 16px;
            height: 16px;
            border-width: 2px;
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
            color: #FF6B6B;
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

        .alert-info {
            background-color: #e3f2fd;
            color: #0d47a1;
            border-color: #0d47a1;
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

        .terms-checkbox {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            cursor: pointer;
            border: 2px solid #ddd;
            border-radius: 4px;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .form-check-input:checked {
            background-color: #FF6B6B;
            border-color: #FF6B6B;
        }

        .terms-text {
            font-size: 12px;
            color: #666;
        }

        .terms-text a {
            color: #FF6B6B;
            text-decoration: none;
            font-weight: 700;
        }

        .terms-text a:hover {
            text-decoration: underline;
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
            color: #FF6B6B;
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

            .progress-container {
                padding: 12px 15px;
            }

            .form-control {
                font-size: 16px;
            }

            .form-footer {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .step-title {
                font-size: 16px;
            }

            .step-subtitle {
                font-size: 12px;
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
                padding-bottom: 18px;
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

        /* Large screens */
        @media (min-width: 993px) {
            .register-wrapper {
                margin: 30px auto;
            }

            .register-card {
                max-width: 1000px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-register navbar-expand-lg">
        <div class="container-lg">
            <a class="navbar-brand" href="<?php echo htmlspecialchars(vendor_url('/')); ?>">
                🌼 Phool Delivery
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/login')); ?>">Login</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="register-wrapper">
        <div class="register-card">
            <!-- Left Sidebar (Desktop) / Below Form (Mobile) -->
            <div class="register-sidebar">
                <div class="sidebar-title">Start Your Journey</div>
                <div class="sidebar-subtitle">Join thousands of successful vendors on Phool Delivery and grow your flower and gift business.</div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">💼</div>
                    <div class="sidebar-benefit-text">
                        <h4>Professional Store</h4>
                        <p>Get your own branded storefront with complete control over products and pricing</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">👥</div>
                    <div class="sidebar-benefit-text">
                        <h4>Thousands of Customers</h4>
                        <p>Access our growing customer base actively searching for quality flowers and gifts</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">📊</div>
                    <div class="sidebar-benefit-text">
                        <h4>Real-Time Analytics</h4>
                        <p>Track sales, orders, and customer behavior with detailed reports and insights</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">💰</div>
                    <div class="sidebar-benefit-text">
                        <h4>Fast Payments</h4>
                        <p>Earn more with competitive commissions and quick payment settlements</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">🚚</div>
                    <div class="sidebar-benefit-text">
                        <h4>Reliable Delivery</h4>
                        <p>Our delivery network ensures orders reach customers on time, every time</p>
                    </div>
                </div>

                <div class="sidebar-benefit">
                    <div class="sidebar-benefit-icon">🎧</div>
                    <div class="sidebar-benefit-text">
                        <h4>24/7 Support</h4>
                        <p>Dedicated support team ready to help you succeed on our platform</p>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div style="display: flex; flex-direction: column;">
                <div class="register-header">
                    <h1>🌼 Vendor Registration</h1>
                    <p>Join Phool Delivery Platform</p>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" style="width: 25%;"></div>
                    </div>
                    <div class="step-indicator">
                        <span>Account</span>
                        <span>Business</span>
                        <span>Details</span>
                        <span>Complete</span>
                    </div>
                </div>

                <div class="register-body">
                <div id="alertContainer"></div>

                <form id="registerForm">
                    <!-- Step 1: Account Information -->
                    <div class="form-step active" data-step="1">
                        <div class="step-title">Account Information</div>
                        <div class="step-subtitle">Create your vendor account</div>

                        <div class="form-group">
                            <label for="owner_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="owner_name" name="owner_name" placeholder="Your full name" required>
                            <div class="validation-feedback" id="ownerFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="your@email.com" required>
                            <div class="validation-feedback" id="emailFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
                            <div class="validation-feedback" id="phoneFeedback"></div>
                        </div>

                        <div class="form-footer">
                            <button type="button" class="btn btn-primary" onclick="nextStep(1)">
                                Next <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Business Information -->
                    <div class="form-step" data-step="2">
                        <div class="step-title">Business Information</div>
                        <div class="step-subtitle">Tell us about your business</div>

                        <div class="form-group">
                            <label for="business_name" class="form-label">Business Name *</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" placeholder="Your shop/business name" required>
                            <div class="validation-feedback" id="businessFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="business_category" class="form-label">Business Category *</label>
                            <select class="form-control" id="business_category" name="business_category" required>
                                <option value="">Select Category</option>
                                <option value="flowers">Flowers & Plants</option>
                                <option value="gifts">Gifts & Accessories</option>
                                <option value="mixed">Flowers & Gifts</option>
                                <option value="decorations">Decorations</option>
                                <option value="other">Other</option>
                            </select>
                            <div class="validation-feedback" id="categoryFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="business_address" class="form-label">Business Address *</label>
                            <input type="text" class="form-control" id="business_address" name="business_address" placeholder="Street address" required>
                            <div class="validation-feedback" id="addressFeedback"></div>
                        </div>

                        <div class="form-footer">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                                Next <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Security Details -->
                    <div class="form-step" data-step="3">
                        <div class="step-title">Set Your Password</div>
                        <div class="step-subtitle">Secure your account</div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control password-input" id="password" name="password" placeholder="At least 8 characters" required>
                                <button type="button" class="password-toggle-btn" id="passwordToggle" title="Show/Hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="validation-feedback" id="passwordFeedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control password-input" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                                <button type="button" class="password-toggle-btn" id="confirmPasswordToggle" title="Show/Hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="validation-feedback" id="confirmPasswordFeedback"></div>
                        </div>

                        <div class="form-footer">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(3)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                                Next <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Review & Confirm -->
                    <div class="form-step" data-step="4">
                        <div class="step-title">Review & Confirm</div>
                        <div class="step-subtitle">Check your information before submitting</div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Your account will be <strong>pending approval</strong> after submission. Our team will verify your details within 24-48 hours.
                        </div>

                        <div style="background: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #f0f0f0;">
                            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
                                <small style="color: #666; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Account Details</small>
                                <div id="reviewAccount" style="margin-top: 8px; color: #000; font-size: 14px; font-weight: 500;"></div>
                            </div>
                            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
                                <small style="color: #666; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Business Details</small>
                                <div id="reviewBusiness" style="margin-top: 8px; color: #000; font-size: 14px; font-weight: 500;"></div>
                            </div>
                            <div>
                                <small style="color: #666; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Status</small>
                                <div style="margin-top: 8px; color: #FF6B6B; font-size: 14px; font-weight: 700;">
                                    <i class="bi bi-clock-history me-2"></i>Pending Approval
                                </div>
                            </div>
                        </div>

                        <div class="terms-checkbox">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="terms-text" for="terms">
                                I agree to the Terms & Conditions and understand my account will be pending approval
                            </label>
                        </div>

                        <div class="form-footer">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(4)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="btn btn-primary" id="registerBtn">
                                <i class="bi bi-check-circle me-2"></i>Complete Registration
                            </button>
                        </div>
                    </div>
                </form>

                <div class="register-footer">
                    <p>Already have an account? <a href="<?php echo htmlspecialchars(vendor_url('/login')); ?>">Login here</a></p>
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
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>For Vendors</h4>
                    <ul>
                        <li><a href="#">Why Join</a></li>
                        <li><a href="#">Requirements</a></li>
                        <li><a href="#">Dashboard Guide</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Report Issue</a></li>
                        <li><a href="#">Feedback</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Vendor Agreement</a></li>
                        <li><a href="#">Refund Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Phool Delivery. All rights reserved. | Built with 💚 for flower and gift vendors</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const FORM_DATA_KEY = 'vendor_registration_form_data';

        // Form Data Persistence
        const FormDataManager = {
            saveField(fieldName, value) {
                let formData = JSON.parse(sessionStorage.getItem(FORM_DATA_KEY) || '{}');
                formData[fieldName] = value;
                sessionStorage.setItem(FORM_DATA_KEY, JSON.stringify(formData));
            },

            saveAllFields(form) {
                const formData = new FormData(form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                sessionStorage.setItem(FORM_DATA_KEY, JSON.stringify(data));
            },

            getField(fieldName) {
                const formData = JSON.parse(sessionStorage.getItem(FORM_DATA_KEY) || '{}');
                return formData[fieldName] || '';
            },

            getAllFields() {
                return JSON.parse(sessionStorage.getItem(FORM_DATA_KEY) || '{}');
            },

            clearAll() {
                sessionStorage.removeItem(FORM_DATA_KEY);
            }
        };

        // Restore form data on page load
        function restoreFormData() {
            const savedData = FormDataManager.getAllFields();
            
            Object.keys(savedData).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = savedData[key] === 'on' || savedData[key] === true;
                    } else {
                        field.value = savedData[key];
                    }
                }
            });
        }

        // Save form data as user types
        function setupAutoSave() {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    FormDataManager.saveField(this.name, this.value);
                });

                input.addEventListener('input', function() {
                    FormDataManager.saveField(this.name, this.value);
                });
            });
        }

        // Password Toggle Setup
        function setupPasswordToggle() {
            const toggles = [
                { btn: '#passwordToggle', input: '#password' },
                { btn: '#confirmPasswordToggle', input: '#confirm_password' }
            ];

            toggles.forEach(({ btn, input }) => {
                const btnEl = document.querySelector(btn);
                const inputEl = document.querySelector(input);
                if (btnEl && inputEl) {
                    btnEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        const isPassword = inputEl.type === 'password';
                        inputEl.type = isPassword ? 'text' : 'password';
                        const icon = this.querySelector('i');
                        icon.classList.toggle('bi-eye');
                        icon.classList.toggle('bi-eye-slash');
                    });
                }
            });
        }

        // Validation Functions
        function validateOwnerName(input) {
            const value = input.value.trim();
            const feedback = document.getElementById('ownerFeedback');
            if (value.length < 3) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Name must be at least 3 characters';
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
            if (value.length !== 10) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Phone must be 10 digits';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid phone';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validateBusinessName(input) {
            const value = input.value.trim();
            const feedback = document.getElementById('businessFeedback');
            if (value.length < 3) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Business name must be at least 3 characters';
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid business name';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validatePassword(input) {
            const value = input.value;
            const feedback = document.getElementById('passwordFeedback');
            if (value.length < 8) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = `✗ Password must be at least 8 characters (${value.length}/8)`;
                feedback.className = 'validation-feedback invalid';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Strong password';
                feedback.className = 'validation-feedback valid';
                return true;
            }
        }

        function validateConfirmPassword(input) {
            const password = document.getElementById('password').value;
            const value = input.value;
            const feedback = document.getElementById('confirmPasswordFeedback');
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

        // Setup validation listeners
        function setupValidation() {
            document.getElementById('owner_name').addEventListener('input', function() { validateOwnerName(this); });
            document.getElementById('email').addEventListener('input', function() { validateEmail(this); });
            document.getElementById('phone').addEventListener('input', function() { validatePhone(this); });
            document.getElementById('business_name').addEventListener('input', function() { validateBusinessName(this); });
            document.getElementById('password').addEventListener('input', function() { validatePassword(this); });
            document.getElementById('confirm_password').addEventListener('input', function() { validateConfirmPassword(this); });
        }

        // Step Navigation
        function nextStep(step) {
            let isValid = true;

            if (step === 1) {
                isValid = validateOwnerName(document.getElementById('owner_name')) &&
                         validateEmail(document.getElementById('email')) &&
                         validatePhone(document.getElementById('phone'));
            } else if (step === 2) {
                isValid = validateBusinessName(document.getElementById('business_name')) &&
                         document.getElementById('business_category').value !== '';
            } else if (step === 3) {
                isValid = validatePassword(document.getElementById('password')) &&
                         validateConfirmPassword(document.getElementById('confirm_password'));
                if (isValid) {
                    updateReview();
                }
            }

            if (isValid && currentStep < 4) {
                currentStep++;
                updateStep();
            }
        }

        function prevStep(step) {
            if (currentStep > 1) {
                currentStep--;
                updateStep();
            }
        }

        function updateStep() {
            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('active');
            
            const progress = (currentStep / 4) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateReview() {
            const ownerName = document.getElementById('owner_name').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const businessName = document.getElementById('business_name').value;
            const category = document.getElementById('business_category').options[document.getElementById('business_category').selectedIndex].text;
            const address = document.getElementById('business_address').value;

            document.getElementById('reviewAccount').innerHTML = `
                <div><strong>${ownerName}</strong></div>
                <div>${email}</div>
                <div>${phone}</div>
            `;

            document.getElementById('reviewBusiness').innerHTML = `
                <div><strong>${businessName}</strong></div>
                <div>${category}</div>
                <div>${address}</div>
            `;
        }

        // Form Submission
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const registerBtn = document.getElementById('registerBtn');
            registerBtn.disabled = true;
            const originalHtml = registerBtn.innerHTML;
            registerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';

            try {
                const response = await fetch('<?php echo htmlspecialchars(vendor_url('/ajax/register')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const text = await response.text();
                let data;

                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', text);
                    throw new Error('Invalid response from server');
                }

                if (!response.ok) {
                    throw new Error(data.error || 'Registration failed');
                }

                if (data.success) {
                    showAlert('Registration successful! Redirecting to login...', 'success');
                    FormDataManager.clearAll();
                    setTimeout(() => {
                        window.location.href = '<?php echo htmlspecialchars(vendor_url('/login?registered=1')); ?>';
                    }, 1500);
                } else {
                    showAlert(data.error || 'Registration failed', 'danger');
                    registerBtn.disabled = false;
                    registerBtn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Registration error:', error);
                showAlert(error.message || 'An error occurred. Please try again.', 'danger');
                registerBtn.disabled = false;
                registerBtn.innerHTML = originalHtml;
            }
        });

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle';
            const alertHtml = `
                <div class="alert alert-${type}" role="alert">
                    <i class="bi bi-${icon} me-2"></i>
                    <strong>${type === 'success' ? 'Success!' : type === 'danger' ? 'Error!' : 'Info'}</strong> ${message}
                </div>
            `;
            alertContainer.innerHTML = alertHtml;
            alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

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