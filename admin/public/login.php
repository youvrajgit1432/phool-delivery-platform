<?php
// public/login.php
 

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../bootstrap/app.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    // Check if user needs to change password
    if (isset($_SESSION['default_password_used']) && $_SESSION['default_password_used'] === true) {
        header("Location: change_password.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// Initialize variables
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("Login attempt for username: " . $username);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $pdo = getDBConnection();
            
            if (!$pdo) {
                $error = 'Database connection failed';
                error_log("Database connection error");
            } else {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    error_log("User found: " . $user['username']);
                    error_log("User status: " . $user['status']);
                    
                    // Check if user is active
                    if ($user['status'] !== 'active') {
                        $error = 'Your account is not active. Please contact administrator.';
                        error_log("User account is not active");
                    } else if (password_verify($password, $user['password'])) {
                        error_log("Password verification successful");
                        
                        // Check if default password is being used
                        $default_password = "Adphool1432@@";
                        $default_password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                        $is_default_password = password_verify($default_password, $user['password']);
                        
                        // Login successful
                        $_SESSION['admin_id'] = $user['id'];
                        $_SESSION['admin_name'] = $user['full_name'];
                        $_SESSION['admin_role'] = $user['role'];
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['default_password_used'] = $is_default_password;
                        
                        // Update last login
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        error_log("Login successful, default password: " . ($is_default_password ? 'yes' : 'no'));
                        
                        // Redirect based on password status
                        if ($is_default_password) {
                            header("Location: change_password.php");
                        } else {
                            header("Location: index.php");
                        }
                        exit;
                    } else {
                        $error = 'Invalid username or password';
                        error_log("Password verification failed");
                    }
                } else {
                    $error = 'Invalid username or password';
                    error_log("User not found in database");
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again.';
            error_log("Database error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            error_log("General error: " . $e->getMessage());
        }
    }
}

$page_title = "Login - Phool Delivery Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/log.css">
</head>
<body class="login-page-body">
    <div class="login-container">
        <div class="card login-card">
            <div class="card-body">
                <div class="brand-logo">
                    <h2>Phool Delivery</h2>
                    <p>Admin Panel</p>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" placeholder="Username" required autofocus>
                        <label for="username">Username</label>
                    </div>
                    <div class="form-floating mb-3 position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                        <button type="button" class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn login-btn">Login</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
            </div>
        </div>
    </div>

    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>