<?php
/**
 * Vendor Panel Landing Page
 * For non-authenticated users
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Only require url.php if not already loaded
if (!function_exists('vendor_url')) {
    require_once dirname(__FILE__, 3) . '/helpers/url.php';
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['vendor_id'])) {
    header('Location: ' . vendor_url('/'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Phool Delivery - Vendor Management Platform">
    <meta name="theme-color" content="#667eea">
    <title>Phool Delivery - Vendor Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(vendor_asset_url('/css/vendor.css')); ?>">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .landing-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }

        .landing-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .landing-hero {
            padding: 3rem 2rem;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .landing-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }

        .landing-hero p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
        }

        .landing-logo {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .landing-features {
            padding: 3rem 2rem;
        }

        .landing-features h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #1f2937;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 12px;
            background: #f9fafb;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: #eff6ff;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .feature-card h4 {
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .feature-card p {
            color: #6b7280;
            font-size: 0.95rem;
            margin: 0;
        }

        .landing-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding: 2rem;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .btn-landing {
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-register {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-register:hover {
            background: #667eea;
            color: white;
        }

        .landing-footer {
            text-align: center;
            padding: 1.5rem;
            background: #f9fafb;
            color: #6b7280;
            font-size: 0.9rem;
            border-top: 1px solid #e5e7eb;
        }

        .landing-footer a {
            color: #667eea;
            text-decoration: none;
        }

        .landing-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .landing-hero h1 {
                font-size: 2rem;
            }

            .landing-hero p {
                font-size: 1rem;
            }

            .landing-features {
                padding: 2rem 1rem;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .landing-actions {
                flex-direction: column;
            }

            .btn-landing {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="landing-content">
            <!-- Hero Section -->
            <div class="landing-hero">
                <div class="landing-logo">🌸</div>
                <h1>Phool Delivery</h1>
                <p>Vendor Management & Sales Platform</p>
                <p style="font-size: 0.95rem; margin: 0;">Manage your products, orders, and payouts from one unified dashboard</p>
            </div>

            <!-- Features Section -->
            <div class="landing-features">
                <h2>Why Choose Phool Delivery?</h2>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <h4>Product Management</h4>
                        <p>Easily add, edit, and manage your flower products with beautiful gallery uploads</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h4>Order Tracking</h4>
                        <p>Real-time order updates and customer notifications for seamless fulfillment</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Sales Analytics</h4>
                        <p>Detailed insights into your sales performance and revenue trends</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <h4>Easy Payouts</h4>
                        <p>Quick and secure payout processing with transparent commission structure</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>Availability Settings</h4>
                        <p>Set your business hours and product availability with flexible scheduling</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure & Safe</h4>
                        <p>Enterprise-grade security with encrypted transactions and data protection</p>
                    </div>
                </div>
            </div>

            <!-- Actions Section -->
            <div class="landing-actions">
                <a href="<?php echo htmlspecialchars(vendor_url('/login')); ?>" class="btn btn-landing btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                </a>
                <a href="<?php echo htmlspecialchars(vendor_url('/register')); ?>" class="btn btn-landing btn-register">
                    <i class="fas fa-user-plus me-2"></i>Register as Vendor
                </a>
            </div>

            <!-- Footer -->
            <div class="landing-footer">
                <p class="mb-2">Need help? <a href="#" class="fw-bold">Contact Support</a></p>
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Phool Delivery. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
