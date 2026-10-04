<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Phool Delivery</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .reset-container {
            width: 100%;
            max-width: 400px;
        }

        .reset-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-logo h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .reset-logo p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .reset-form {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .reset-footer {
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }

        .reset-footer p {
            margin-bottom: 10px;
            color: #666;
            font-size: 14px;
        }

        .reset-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .reset-footer a:hover {
            color: #764ba2;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .text-muted {
            color: #999;
            font-size: 12px;
        }

        .reset-info {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .reset-info p {
            color: #333;
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-box">
            <div class="reset-logo">
                <h1>🌸 Phool Delivery</h1>
                <p>Reset Password</p>
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

            <div class="reset-info">
                <p>📧 Enter your email address or phone number associated with your account. We'll send you a link to reset your password.</p>
            </div>

            <form method="POST" class="reset-form" id="resetForm">
                <div class="form-group">
                    <label for="email">Email Address or Phone</label>
                    <input 
                        type="text" 
                        id="email" 
                        name="email" 
                        required 
                        placeholder="Enter your email or phone number"
                        autofocus
                    >
                </div>

                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </form>

            <div class="reset-footer">
                <p>Remember your password? <a href="<?php echo htmlspecialchars(app_url('/login')); ?>">Back to Login</a></p>
                <p>Don't have an account? <a href="<?php echo htmlspecialchars(app_url('/register')); ?>">Register here</a></p>
                <p class="text-muted">For support: support@phooldelivery.example</p>
            </div>
        </div>
    </div>
</body>
</html>
