<?php
/**
 * Auth Controller
 * Handles authentication for delivery riders
 */

namespace Phool\DeliveryPanel\Controllers;
use Phool\DeliveryPanel\Models\Rider;

class AuthController {
    /**
     * Show login form
     */
    public function login() {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['rider_id'])) {
            require_once dirname(dirname(__FILE__)) . '/helpers/url.php';
            header('Location: ' . app_url('/dashboard'));
            exit;
        }
        
        // Load login view
        require_once dirname(dirname(__FILE__)) . '/views/auth/login.php';
    }
    
    /**
     * Show registration form
     */
    public function register() {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['rider_id'])) {
            require_once dirname(dirname(__FILE__)) . '/helpers/url.php';
            header('Location: ' . app_url('/dashboard'));
            exit;
        }
        
        // Load registration view
        require_once dirname(dirname(__FILE__)) . '/views/auth/register.php';
    }
    
    /**
     * Handle login form submission
     */
    public function authenticate() {
        require_once dirname(dirname(__FILE__)) . '/helpers/url.php';
        
        $logFile = dirname(dirname(dirname(__FILE__))) . '/storage/logs/auth-errors.log';
        
        // Log immediately that authenticate() was called
        @file_put_contents($logFile, "\n=== AUTHENTICATE METHOD CALLED ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        @file_put_contents($logFile, "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n", FILE_APPEND);
        @file_put_contents($logFile, "POST data received: " . json_encode($_POST) . "\n", FILE_APPEND);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        
        try {
            // Log request
            @file_put_contents($logFile, "\n=== LOGIN ATTEMPT ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            
            // Get request data
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            @file_put_contents($logFile, "Email/Phone: " . htmlspecialchars($email) . "\n", FILE_APPEND);

            if (empty($email) || empty($password)) {
                @file_put_contents($logFile, "ERROR: Empty email or password\n", FILE_APPEND);
                $_SESSION['error'] = 'Please provide email/phone and password.';
                header('Location: ' . app_url('/login'));
                exit;
            }

            // Include Rider model with dependency
            require_once dirname(dirname(__FILE__)) . '/models/BaseModel.php';
            require_once dirname(dirname(__FILE__)) . '/models/Rider.php';
            
            // Find rider by email or phone
            @file_put_contents($logFile, "Looking up rider...\n", FILE_APPEND);
            $rider = \Phool\DeliveryPanel\Models\Rider::findByEmailOrPhone($email);
            
            if (!$rider) {
                @file_put_contents($logFile, "ERROR: Rider not found\n", FILE_APPEND);
                $_SESSION['error'] = 'Invalid credentials.';
                header('Location: ' . app_url('/login'));
                exit;
            }
            
            @file_put_contents($logFile, "Rider found: " . $rider->email . "\n", FILE_APPEND);

            // Verify password against stored hash
            @file_put_contents($logFile, "Verifying password...\n", FILE_APPEND);
            if (!isset($rider->password) || empty($rider->password)) {
                @file_put_contents($logFile, "ERROR: No password hash found\n", FILE_APPEND);
                $_SESSION['error'] = 'Invalid credentials.';
                header('Location: ' . app_url('/login'));
                exit;
            }
            
            if (!\Phool\DeliveryPanel\Models\Rider::verifyPassword($password, $rider->password)) {
                @file_put_contents($logFile, "ERROR: Password verification failed\n", FILE_APPEND);
                $_SESSION['error'] = 'Invalid credentials.';
                header('Location: ' . app_url('/login'));
                exit;
            }
            
            @file_put_contents($logFile, "Password verified successfully\n", FILE_APPEND);

            // Successful authentication: populate session
            $_SESSION['rider_id'] = $rider->id;
            $_SESSION['rider_name'] = trim($rider->first_name . ' ' . $rider->last_name);
            $_SESSION['rider_email'] = $rider->email;
            $_SESSION['rider_phone'] = $rider->phone;
            $_SESSION['rider_type'] = $rider->rider_type ?? 'gig';
            $_SESSION['rider_status'] = $rider->status ?? 'active';

            @file_put_contents($logFile, "Session created for rider: " . $rider->id . "\n", FILE_APPEND);
            @file_put_contents($logFile, "Redirecting to dashboard...\n", FILE_APPEND);
            
            // Redirect to dashboard
            header('Location: ' . app_url('/dashboard'));
            exit;
        } catch (\Exception $e) {
            // Log the error
            $logFile = dirname(dirname(dirname(__FILE__))) . '/storage/logs/auth-errors.log';
            @file_put_contents($logFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            @file_put_contents($logFile, "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n", FILE_APPEND);
            @file_put_contents($logFile, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            error_log('AUTH EXCEPTION: ' . $e->getMessage());
            
            // Display generic error
            $_SESSION['error'] = 'An error occurred during login. Please try again.';
            header('Location: ' . app_url('/login'));
            exit;
        }
    }
    
    /**
     * Handle logout
     */
    public function logout() {
        require_once dirname(dirname(__FILE__)) . '/helpers/url.php';
        session_destroy();
        header('Location: ' . app_url('/login'));
        exit;
    }
    
    /**
     * Handle registration form submission
     */
    public function store() {
        require_once dirname(dirname(__FILE__)) . '/helpers/url.php';
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        
        // Get form data
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $rider_type = trim($_POST['rider_type'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($first_name)) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($last_name)) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (!preg_match('/^\d{10}$/', preg_replace('/\D/', '', $phone))) {
            $errors[] = 'Phone number must be 10 digits.';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (empty($rider_type) || !in_array($rider_type, ['gig', 'in_house', 'partner'])) {
            $errors[] = 'Valid rider type is required.';
        }
        
        // Return validation errors
        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => implode(' ', $errors)
                ]);
            } else {
                header('Location: ' . app_url('/register'));
            }
            return;
        }
        
        // Check if email or phone already exists
        $existing_rider = Rider::findByEmailOrPhone($email);
        if ($existing_rider) {
            $_SESSION['error'] = 'Email or phone number already registered.';
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email or phone number already registered.'
                ]);
            } else {
                header('Location: ' . app_url('/register'));
            }
            return;
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Prepare rider data
        $rider_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password_hash,
            'rider_type' => $rider_type,
            'city' => 'Kathmandu',  // Default city
            'country' => 'Nepal',
            'status' => 'pending',  // Set to pending for verification
            'email_verified' => 0,
            'phone_verified' => 0,
            'documents_verified' => 0,
            'bank_verified' => 0,
            'is_available' => 0,
        ];
        
        try {
            // Create the rider
            $new_rider = Rider::create($rider_data);
            
            if ($new_rider) {
                $_SESSION['success'] = 'Registration successful! Please log in.';
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Registration successful! Redirecting to login...',
                        'redirect' => app_url('/login')
                    ]);
                } else {
                    header('Location: ' . app_url('/login'));
                }
            } else {
                throw new \Exception('Failed to create rider account');
            }
        } catch (\Exception $e) {
            $log_msg = date('Y-m-d H:i:s') . ' - Registration error: ' . $e->getMessage() . "\n";
            @error_log($log_msg, 3, dirname(__DIR__, 2) . '/storage/logs/registration.log');
            
            $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Registration failed. Please try again.'
                ]);
            } else {
                header('Location: ' . app_url('/register'));
            }
        }
    }
}

