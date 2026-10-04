<?php
class LogController {
    private $db;
    private $logModel;
    private $customerModel;
    private $pathConfig;
    private $persistentLogin;

    public function __construct($db) {
        $this->db = $db;
        $this->logModel = new LogModel($db);
        $this->customerModel = new Customer($db);
        $this->pathConfig = PathConfig::getInstance();
        $this->persistentLogin = new PersistentLogin($db);
    }

    /**
     * Show login page with method selection
     */
    public function login() {
        // Ensure session is started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // If already logged in, redirect to home
        if (isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url(''));
            exit;
        }
        
        $method = $_GET['method'] ?? '';
        
        // If OTP method is selected, redirect to OTP controller
        if ($method === 'otp') {
            $logOTPController = new LogOTPController($this->db);
            return $logOTPController->showOTPLogin();
        }
        
        $error_message = $_SESSION['auth_error'] ?? $_SESSION['login_error'] ?? '';
        $success_message = $_SESSION['auth_success'] ?? '';
        
        unset($_SESSION['auth_error']);
        unset($_SESSION['login_error']);
        unset($_SESSION['auth_success']);
        
        return [
            'page_title' => 'Login - Phool Delivery',
            'error_message' => $error_message,
            'success_message' => $success_message,
            'show_otp_option' => true
        ];
    }

    /**
     * Process password login - WITH PERSISTENT LOGIN
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $login = $_POST['login'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember_me = isset($_POST['remember_me']);
            
            if (empty($login) || empty($password)) {
                $_SESSION['auth_error'] = 'Please fill in all required fields';
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            try {
                $customer = $this->findCustomerByIdentifier($login);
                
                if ($customer) {
                    // Get registration type first
                    $registrationType = $this->getRegistrationType($customer['id']);
                    $isGuestUser = ($registrationType === 'guest');
                    
                    // Handle password verification
                    $isValidPassword = $this->verifyPassword($password, $customer['password'], $isGuestUser);
                    
                    if ($isValidPassword) {
                        if ($customer['status'] !== 'active') {
                            $_SESSION['auth_error'] = 'Your account is not active. Please contact support.';
                            header('Location: ' . $this->pathConfig->url('login'));
                            exit;
                        }
                        
                        // Login successful - update log
                        $logId = $this->logModel->logLoginAttempt($customer['id'], 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
                        
                        // Set session variables
                        $_SESSION['customer_id'] = $customer['id'];
                        $_SESSION['customer_name'] = $customer['name'];
                        $_SESSION['customer_email'] = $customer['email'];
                        $_SESSION['customer_phone'] = $customer['phone'];
                        $_SESSION['is_guest_user'] = $isGuestUser;
                        $_SESSION['registration_type'] = $registrationType;
                        $_SESSION['login_log_id'] = $logId;
                        $_SESSION['login_method'] = 'password';
                        
                        // Create persistent login if remember me is checked
                        if ($remember_me) {
                            $sessionData = [
                                'customer_id' => $customer['id'],
                                'customer_name' => $customer['name'],
                                'customer_email' => $customer['email'],
                                'customer_phone' => $customer['phone'],
                                'is_guest_user' => $isGuestUser,
                                'registration_type' => $registrationType
                            ];
                            $this->persistentLogin->createPersistentLogin($customer['id'], $sessionData);
                        }
                        
                        // AUTO-SWITCH LANGUAGE: Load user's preferred language from database
                        LanguageHelper::autoSwitchLanguageAfterLogin($customer['id']);
                        
                        // AUTO-INSERT: Transfer notification preferences after successful login
                        if (class_exists('NotificationController')) {
                            $notificationController = new NotificationController($this->db);
                            $notificationController->transferSessionPreferencesToUser($customer['id'], session_id());
                        }
                        
                        // If it's a guest user with default password, show reminder
                        if ($isGuestUser && $this->isUsingDefaultPassword($customer['password'])) {
                            $_SESSION['auth_success'] = 'Welcome back! Your default password is: phool1234 (You can change it in your profile)';
                        } else {
                            $_SESSION['auth_success'] = 'Login successful! Welcome back.';
                        }
                        
                        header('Location: ' . $this->pathConfig->url(''));
                        exit;
                    } else {
                        // Log failed attempt
                        $this->logModel->logLoginAttempt($customer['id'], 'failed', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
                        
                        // Provide generic error message for security
                        $_SESSION['auth_error'] = 'Invalid login credentials. Please check your details and try again.';
                        header('Location: ' . $this->pathConfig->url('login'));
                        exit;
                    }
                } else {
                    // Generic error message to prevent user enumeration
                    $_SESSION['auth_error'] = 'Invalid login credentials. Please check your details and try again.';
                    header('Location: ' . $this->pathConfig->url('login'));
                    exit;
                }
            } catch (Exception $e) {
                // Log the actual error but show generic message to user
                error_log("Login process error: " . $e->getMessage());
                $_SESSION['auth_error'] = 'An error occurred during login. Please try again.';
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
        }
        
        $_SESSION['auth_error'] = 'Invalid request method';
        header('Location: ' . $this->pathConfig->url('login'));
        exit;
    }

    /**
     * Enhanced Logout user - WITH PERSISTENT LOGIN CLEANUP
     */
    public function logout() {
        try {
            // Ensure session is started and active
            if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            
            // Store customer ID for logging before destroying session
            $customer_id = $_SESSION['customer_id'] ?? null;
            
            // Update logout time if login log exists
            if (isset($_SESSION['login_log_id'])) {
                try {
                    $this->logModel->updateLogoutTime($_SESSION['login_log_id']);
                } catch (Exception $e) {
                    error_log("Logout time update failed: " . $e->getMessage());
                }
            }
            
            // Log the logout action
            if ($customer_id) {
                try {
                    $this->logModel->logLoginAttempt($customer_id, 'logout', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
                } catch (Exception $e) {
                    error_log("Logout logging failed: " . $e->getMessage());
                }
            }
            
            // Destroy persistent login
            $this->persistentLogin->destroyPersistentLogin();
            
            // Clear all session variables
            $_SESSION = [];
            
            // Destroy the session completely
            if (session_status() !== PHP_SESSION_NONE) {
                session_destroy();
            }
            
            // Clear session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Start a fresh minimal session for success message
            if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            
            // Set minimal session data
            $_SESSION = [];
            $_SESSION['auth_success'] = 'You have been logged out successfully.';
            
            // FIXED: Use JavaScript-based redirection as fallback
            $home_url = $this->pathConfig->url('');
            
            // Additional security headers
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            error_log("Logout successful for customer: " . $customer_id . ". Redirecting to: " . $home_url);
            
            // Use JavaScript fallback redirection
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Logout Successful</title>
                <meta http-equiv="refresh" content="0;url=' . $home_url . '">
                <script>
                    window.location.href = "' . $home_url . '";
                </script>
            </head>
            <body>
                <p>Logout successful. <a href="' . $home_url . '">Click here if not redirected</a></p>
            </body>
            </html>';
            exit;
            
        } catch (Exception $e) {
            // Log error but don't show to user
            error_log("Logout error: " . $e->getMessage());
            
            // Fallback redirect even if error occurs
            $home_url = $this->pathConfig->url('');
            header('Location: ' . $home_url);
            exit;
        }
    }

    // Helper method for guest users to reset their password
    public function resetGuestPassword() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $phone = $_POST['phone'] ?? '';
            
            if (empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                return;
            }
            
            try {
                $customer = $this->customerModel->findByPhone($phone);
                if (!$customer) {
                    // Generic message to prevent user enumeration
                    echo json_encode(['success' => false, 'message' => 'If an account exists with this phone number, instructions will be sent.']);
                    return;
                }
                
                // Check if it's a guest user
                $registrationType = $this->getRegistrationType($customer['id']);
                if ($registrationType !== 'guest') {
                    echo json_encode(['success' => false, 'message' => 'Password reset instructions have been sent if an account exists.']);
                    return;
                }
                
                // Check if they're still using default password
                if ($this->isUsingDefaultPassword($customer['password'])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Your default password is: phool1234',
                        'default_password' => 'phool1234'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Password reset instructions have been sent to your registered contact.',
                        'password_changed' => true
                    ]);
                }
            } catch (Exception $e) {
                error_log("Reset guest password error: " . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'message' => 'An error occurred. Please try again later.'
                ]);
            }
        }
    }

    /**
     * Utility method to check registration type
     */
    public function getRegistrationType($customer_id) {
        try {
            $query = "SELECT registration_type FROM customers WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $customer_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['registration_type'] ?? 'direct';
            }
            return 'direct';
        } catch (Exception $e) {
            error_log("Error getting registration type: " . $e->getMessage());
            return 'direct';
        }
    }

    private function findCustomerByIdentifier($identifier) {
        try {
            // Try email first
            $query = "SELECT * FROM customers WHERE email = :identifier AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Try phone
            $query = "SELECT * FROM customers WHERE phone = :identifier AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error finding customer: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Unified password verification
     */
    private function verifyPassword($input_password, $stored_password_hash, $isGuestUser = false) {
        // First, try normal password verification (works for all users)
        if (password_verify($input_password, $stored_password_hash)) {
            return true;
        }
        
        // If it's a guest user, also check the default password
        if ($isGuestUser && $input_password === 'phool1234') {
            return true;
        }
        
        return false;
    }

    /**
     * Check if a guest user is still using the default password
     */
    private function isUsingDefaultPassword($stored_password_hash) {
        return password_verify('phool1234', $stored_password_hash);
    }

    private function identifierExists($field, $value) {
        try {
            $query = "SELECT id FROM customers WHERE $field = :value";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error checking identifier exists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get login history for a customer
     */
    public function getLoginHistory($customer_id, $limit = 10) {
        try {
            return $this->logModel->getLoginHistory($customer_id, $limit);
        } catch (Exception $e) {
            error_log("Error getting login history: " . $e->getMessage());
            return [];
        }
    }
}
?>