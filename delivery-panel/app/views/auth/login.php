<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Phool Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            color: #333;
        }

        /* Navigation Bar */
        .navbar-landing {
            background: #ffffff;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 2px solid #f0f0f0;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: 800;
            color: #FF6B35;
            letter-spacing: -0.5px;
        }

        .nav-link {
            color: #333 !important;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 12px;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #FF6B35, #FF8A5E);
            transition: width 0.3s ease;
        }

        .nav-link:hover {
            color: #FF6B35 !important;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-login-nav {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8A5E 100%);
            color: white !important;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-left: 10px;
            display: inline-block;
        }

        .btn-login-nav:hover {
            background: linear-gradient(135deg, #FF8A5E 0%, #FF6B35 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.35);
            color: white !important;
        }

        /* Hero Section */
        .hero-section {
            padding: 60px 20px 80px;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #f0f0f0;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 107, 53, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(0, 78, 137, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-text h1 {
            font-size: 48px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 20px;
            color: #000000;
            letter-spacing: -1px;
        }

        .hero-text h1 .highlight {
            background: linear-gradient(90deg, #FF6B35, #FF8A5E);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-text p {
            font-size: 18px;
            color: #555;
            margin-bottom: 15px;
            line-height: 1.7;
            max-width: 600px;
            font-weight: 500;
        }

        .hero-text .subtext {
            font-size: 16px;
            color: #666;
            margin-bottom: 35px;
            line-height: 1.8;
            max-width: 600px;
        }

        .hero-emoji {
            font-size: 80px;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }

        .hero-badge {
            display: inline-block;
            background: #f5f5f5;
            color: #FF6B35;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        /* Features Section */
        .features-section {
            padding: 70px 20px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 15px;
            color: #000000;
            letter-spacing: -0.5px;
        }

        .section-subtitle {
            text-align: center;
            font-size: 16px;
            color: #666;
            margin-bottom: 50px;
            font-weight: 500;
        }

        .feature-card {
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #f5f5f5;
            margin-bottom: 25px;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            border-color: #FF6B35;
        }

        .feature-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #000000;
        }

        .feature-card p {
            font-size: 14px;
            color: #666;
            line-height: 1.7;
            margin: 0;
        }

        /* Login Section */
        .login-section {
            padding: 70px 20px;
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
        }

        .login-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .login-info {
            padding: 20px 0;
        }

        .login-info h2 {
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 20px;
            color: #000000;
            letter-spacing: -0.5px;
        }

        .login-info p {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.8;
            font-weight: 500;
        }

        .benefit-item {
            display: flex;
            gap: 18px;
            margin-bottom: 25px;
            padding: 18px;
            background: #f9f9f9;
            border-radius: 10px;
            border-left: 4px solid #FF6B35;
            transition: all 0.3s ease;
        }

        .benefit-item:hover {
            background: #f5f5f5;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .benefit-item i {
            font-size: 24px;
            color: #FF6B35;
            flex-shrink: 0;
        }

        .benefit-item div h4 {
            font-weight: 700;
            margin-bottom: 5px;
            color: #000000;
        }

        .benefit-item div p {
            font-size: 13px;
            color: #666;
            margin: 0;
            line-height: 1.5;
        }

        .login-form-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 45px;
            animation: slideUp 0.6s ease-out;
            border: 1px solid #f0f0f0;
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

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h3 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .form-header p {
            color: #666;
            font-size: 14px;
        }

        .form-label {
            font-weight: 700;
            color: #000000;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 12px 14px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
            color: #000000;
        }

        .form-control::placeholder {
            color: #999;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: #fff5f5;
        }

        .form-control.is-valid {
            border-color: #28a745;
            background-color: #f5fff5;
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

        .caps-lock-warning {
            display: none;
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 12px;
            color: #856404;
            animation: fadeIn 0.3s ease;
        }

        .caps-lock-warning.show {
            display: flex;
            align-items: center;
            gap: 8px;
        }
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0;
            cursor: pointer;
            border: 2px solid #ddd;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .form-check-input:checked {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }

        .form-check-label {
            cursor: pointer;
            font-weight: 500;
            color: #666;
            font-size: 13px;
            margin: 0;
        }

        .btn-login {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8A5E 100%);
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            color: white;
        }

        .btn-login:hover:not(:disabled) {
            background: linear-gradient(135deg, #FF8A5E 0%, #FF5722 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.4);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .forgot-password-section {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password-section a {
            color: #FF6B35;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: color 0.2s ease;
        }

        .forgot-password-section a:hover {
            color: #FF8A5E;
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0;
            color: #999;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8e8e8;
        }

        .divider::before {
            margin-right: 15px;
        }

        .divider::after {
            margin-left: 15px;
        }

        .alert {
            border-radius: 10px;
            border: none;
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
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-text h1 {
                font-size: 32px;
            }

            .hero-text p {
                font-size: 16px;
            }

            .section-title {
                font-size: 24px;
            }

            .login-wrapper {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .login-form-container {
                padding: 25px;
            }

            .form-header h3 {
                font-size: 22px;
            }

            .feature-card {
                padding: 20px;
            }

            .feature-icon {
                font-size: 40px;
            }

            .login-info h2 {
                font-size: 24px;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .hero-emoji {
                font-size: 60px;
            }
        }

        @media (max-width: 576px) {
            body {
                font-size: 14px;
            }

            .hero-section {
                padding: 30px 15px 40px;
            }

            .hero-text h1 {
                font-size: 28px;
                margin-bottom: 15px;
            }

            .hero-text p {
                font-size: 14px;
                margin-bottom: 20px;
            }

            .features-section {
                padding: 40px 15px;
            }

            .section-title {
                font-size: 22px;
                margin-bottom: 30px;
            }

            .login-section {
                padding: 40px 15px;
            }

            .login-form-container {
                padding: 20px;
                margin-top: 30px;
            }

            .form-header h3 {
                font-size: 20px;
            }

            .form-control {
                font-size: 16px;
            }

            .login-info {
                padding: 0;
            }

            .login-info h2 {
                font-size: 22px;
            }

            .benefit-item {
                padding: 12px;
                gap: 12px;
            }

            .benefit-item i {
                font-size: 20px;
            }

            .navbar-landing {
                padding: 12px 0;
            }

            .nav-link {
                margin: 0 4px;
            }

            .hero-emoji {
                font-size: 50px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-landing navbar-expand-lg">
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
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#benefits">Benefits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section at Top -->
    <section class="login-section" id="login-section">
        <div class="login-wrapper">
            <!-- Info Column (Mobile: Hidden, Tablet/Desktop: Visible) -->
            <div class="login-info d-none d-lg-block">
                <h2>Rider Dashboard Login</h2>
                <p>Access your personal delivery control panel to manage deliveries and earn rewards.</p>
                
                <div class="benefit-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h4>Track Deliveries</h4>
                        <p>Real-time order tracking and navigation</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h4>Earn Rewards</h4>
                        <p>Daily bonuses and incentive programs</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h4>24/7 Support</h4>
                        <p>Round-the-clock customer support team</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h4>Instant Payouts</h4>
                        <p>Quick and secure payment processing</p>
                    </div>
                </div>
            </div>

            <!-- Login Form -->
            <div class="login-form-container">
                <div class="form-header">
                    <h3>Rider Login</h3>
                    <p>Access Your Dashboard</p>
                </div>

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

                <form method="POST" action="<?php echo htmlspecialchars(app_url('/login')); ?>" id="loginForm">
                    <div class="form-group">
                        <label for="email" class="form-label">Email or Phone</label>
                        <input 
                            type="text" 
                            id="email" 
                            name="email" 
                            required 
                            placeholder="Enter your email or phone number"
                            class="form-control"
                            autofocus
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
                                placeholder="Enter your password"
                                class="form-control password-input"
                            >
                            <button type="button" id="passwordToggle" class="password-toggle-btn" title="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="validation-feedback" id="passwordFeedback"></div>
                        <div class="caps-lock-warning" id="capsLockWarning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Caps Lock is ON</span>
                        </div>
                    </div>

                    <div class="remember-wrapper">
                        <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
                        <label for="remember" class="form-check-label">Remember me for 30 days</label>
                    </div>

                    <button type="submit" class="btn btn-login">Login to Dashboard</button>
                </form>

                <div class="forgot-password-section">
                    <a href="<?php echo htmlspecialchars(app_url('/forgot-password')); ?>">Forgot your password?</a>
                </div>

                <div class="divider"></div>

                <div style="text-align: center;">
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Don't have an account yet?</p>
                    <a href="<?php echo htmlspecialchars(app_url('/register')); ?>" style="display: inline-block; background: linear-gradient(135deg, #FF6B35 0%, #FF8A5E 100%); color: white; padding: 12px 30px; border-radius: 8px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; transition: all 0.3s ease; margin-top: 10px;" onmouseover="this.style.background='linear-gradient(135deg, #FF8A5E 0%, #FF5722 100%)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 25px rgba(255, 107, 53, 0.4)'" onmouseout="this.style.background='linear-gradient(135deg, #FF6B35 0%, #FF8A5E 100%)'; this.style.transform='translateY(0)'; this.style.boxShadow=''">
                        Register Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 col-12">
                    <div class="hero-text">
                        <div class="hero-emoji">🚴</div>
                        <h1>Join Our <span class="highlight">Rider Network</span></h1>
                        <p>Become part of Phool Delivery's fastest growing delivery team and earn competitive rates.</p>
                        <p class="subtext">Deliver flowers, gifts, and essentials across the city. Set your own schedule and work at your pace.</p>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-12 text-center">
                    <div class="hero-emoji" style="font-size: 120px; animation: none;">🌸</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="features-container">
            <h2 class="section-title">Why Deliver With Us?</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Competitive Pay</h3>
                        <p>Earn up to 500-1500 per day with transparent pricing</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">🗓️</div>
                        <h3>Flexible Schedule</h3>
                        <p>Work whenever you want, take breaks as needed</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3>Easy App</h3>
                        <p>Simple-to-use dashboard and mobile app</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">🏆</div>
                        <h3>Rewards Program</h3>
                        <p>Earn bonus points and special incentives</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">🛡️</div>
                        <h3>Insurance Coverage</h3>
                        <p>Accident and delivery protection included</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="feature-card">
                        <div class="feature-icon">⭐</div>
                        <h3>Support Team</h3>
                        <p>24/7 customer support for any issues</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section (Mobile Friendly) -->
    <section class="features-section d-lg-none" id="benefits">
        <div class="features-container">
            <h2 class="section-title">Dashboard Features</h2>
            <div class="benefit-item">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <h4>Real-time Order Tracking</h4>
                    <p>See all available deliveries and track your route efficiently</p>
                </div>
            </div>

            <div class="benefit-item">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <h4>Daily Earnings Report</h4>
                    <p>View detailed breakdowns of your daily income</p>
                </div>
            </div>

            <div class="benefit-item">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <h4>Instant Chat Support</h4>
                    <p>Connect with support team anytime you need help</p>
                </div>
            </div>

            <div class="benefit-item">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <h4>Quick Payouts</h4>
                    <p>Withdraw your earnings directly to your bank account</p>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="#">Disclaimer</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:support@phooldelivery.example">support@phooldelivery.example</a></li>
                        <li><a href="tel:+9779803962360">+977 9803962360</a></li>
                        <li><a href="#">Live Chat</a></li>
                        <li><a href="#">Contact Form</a></li>
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
            REMEMBER_ME_KEY: 'rider_login_remember',
            EMAIL_KEY: 'rider_login_email',
            
            setRememberedEmail(email) {
                if (sessionStorage) {
                    sessionStorage.setItem(this.EMAIL_KEY, email);
                }
            },
            
            getRememberedEmail() {
                if (sessionStorage) {
                    return sessionStorage.getItem(this.EMAIL_KEY) || '';
                }
                return '';
            },
            
            setRememberMeStatus(status) {
                if (sessionStorage) {
                    sessionStorage.setItem(this.REMEMBER_ME_KEY, status ? '1' : '0');
                }
            },
            
            getRememberMeStatus() {
                if (sessionStorage) {
                    return sessionStorage.getItem(this.REMEMBER_ME_KEY) === '1';
                }
                return false;
            },
            
            clearAll() {
                if (sessionStorage) {
                    sessionStorage.removeItem(this.EMAIL_KEY);
                    sessionStorage.removeItem(this.REMEMBER_ME_KEY);
                }
            }
        };

        // Initialize form with remembered data
        function initializeForm() {
            const rememberedEmail = SessionManager.getRememberedEmail();
            const rememberStatus = SessionManager.getRememberMeStatus();
            
            if (rememberedEmail) {
                document.getElementById('email').value = rememberedEmail;
                document.getElementById('remember').checked = rememberStatus;
                // Focus on password if email is already filled
                setTimeout(() => document.getElementById('password').focus(), 100);
            }
        }

        // Show/Hide Password Toggle
        function setupPasswordToggle() {
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            
            if (!passwordToggle) return;
            
            passwordToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        }

        // Caps Lock Detection
        function setupCapsLockDetection() {
            const passwordInput = document.getElementById('password');
            const capsLockWarning = document.getElementById('capsLockWarning');
            
            if (!passwordInput || !capsLockWarning) return;
            
            passwordInput.addEventListener('keypress', function(e) {
                const isCapsLock = e.getModifierState('CapsLock');
                if (isCapsLock) {
                    capsLockWarning.classList.add('show');
                } else {
                    capsLockWarning.classList.remove('show');
                }
            });
            
            passwordInput.addEventListener('keyup', function(e) {
                const isCapsLock = e.getModifierState('CapsLock');
                if (isCapsLock) {
                    capsLockWarning.classList.add('show');
                } else {
                    capsLockWarning.classList.remove('show');
                }
            });
        }

        // Input Validation
        function setupInputValidation() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            emailInput.addEventListener('input', function() {
                validateEmail(this);
            });
            
            passwordInput.addEventListener('input', function() {
                validatePassword(this);
            });
        }

        function validateEmail(input) {
            const value = input.value.trim();
            const feedback = document.getElementById('emailFeedback');
            const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            const isValidPhone = /^\d{10,}$/.test(value.replace(/[^\d]/g, ''));
            
            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                feedback.classList.remove('valid', 'invalid');
                return false;
            }
            
            if (isValidEmail || isValidPhone) {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Valid email or phone number';
                feedback.classList.add('valid');
                feedback.classList.remove('invalid');
                return true;
            } else {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Please enter a valid email or phone number';
                feedback.classList.add('invalid');
                feedback.classList.remove('valid');
                return false;
            }
        }

        function validatePassword(input) {
            const value = input.value;
            const feedback = document.getElementById('passwordFeedback');
            
            if (!value) {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.textContent = '';
                feedback.classList.remove('valid', 'invalid');
                return false;
            }
            
            if (value.length < 6) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                feedback.textContent = '✗ Password must be at least 6 characters';
                feedback.classList.add('invalid');
                feedback.classList.remove('valid');
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                feedback.textContent = '✓ Password looks good';
                feedback.classList.add('valid');
                feedback.classList.remove('invalid');
                return true;
            }
        }

        // Form Submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const rememberCheckbox = document.getElementById('remember');
            const loginBtn = document.querySelector('button[type="submit"]');
            
            // Validate inputs
            const isEmailValid = validateEmail(document.getElementById('email'));
            const isPasswordValid = validatePassword(document.getElementById('password'));
            
            if (!isEmailValid || !isPasswordValid) {
                return;
            }
            
            // Store remember me preference and email in sessionStorage
            if (rememberCheckbox.checked) {
                SessionManager.setRememberedEmail(email);
                SessionManager.setRememberMeStatus(true);
            } else {
                SessionManager.clearAll();
            }
            
            // Disable button and show loading state
            loginBtn.disabled = true;
            const originalHtml = loginBtn.innerHTML;
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            
            try {
                // Form will submit normally via POST
                this.submit();
            } catch (error) {
                console.error('Login error:', error);
                loginBtn.disabled = false;
                loginBtn.innerHTML = originalHtml;
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            setupPasswordToggle();
            setupCapsLockDetection();
            setupInputValidation();
        });
    </script>
