<?php
// controllers/LogOTPController.php
require_once __DIR__ . '/../services/SparrowSMSService.php';

class LogOTPController {
    private $db;
    private $logOTPModel;
    private $customerModel;
    private $smsService;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->logOTPModel = new LogOTPModel($db);
        $this->customerModel = new Customer($db);
        
        // Initialize SMS service with database configuration
        $this->smsService = new SparrowSMSService($db);
        
        // Initialize PathConfig
        $this->pathConfig = PathConfig::getInstance();
    }

    /**
     * Show OTP login form (called from main login page)
     */
    public function showOTPLogin() {
        if (isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url(''));
            exit;
        }
        
        // Check if we're coming from OTP method selection
        $isOtpLogin = $_GET['method'] ?? '';
        
        if ($isOtpLogin === 'otp') {
            // Show phone input form for OTP
            return $this->showPhoneInputForm();
        }
        
        // Default: show method selection (handled by main login page)
        return [
            'page_title' => 'Login - Phool Delivery',
            'form_type' => 'method_selection',
            'show_otp_option' => true
        ];
    }

    /**
     * Show phone input form for OTP login
     */
    private function showPhoneInputForm() {
        $error_message = $_SESSION['otp_error'] ?? '';
        $success_message = $_SESSION['otp_success'] ?? '';
        
        unset($_SESSION['otp_error']);
        unset($_SESSION['otp_success']);
        
        return [
            'page_title' => 'OTP Login - Phool Delivery',
            'form_type' => 'phone_input',
            'error_message' => $error_message,
            'success_message' => $success_message
        ];
    }

    /**
     * Show OTP verification form - PUBLIC METHOD FOR ROUTING
     */
    public function showVerifyOTP() {
        return $this->showOTPVerificationForm();
    }

    /**
     * Show OTP verification form
     */
    public function showOTPVerificationForm() {
        if (!isset($_SESSION['otp_phone']) || !isset($_SESSION['otp_customer_id'])) {
            $_SESSION['otp_error'] = 'Please request an OTP first';
            header('Location: ' . $this->pathConfig->url('login?method=otp'));
            exit;
        }
        
        $error_message = $_SESSION['otp_error'] ?? '';
        $success_message = $_SESSION['otp_success'] ?? '';
        
        unset($_SESSION['otp_error']);
        unset($_SESSION['otp_success']);
        
        return [
            'page_title' => 'Verify OTP - Phool Delivery',
            'form_type' => 'otp_verification',
            'phone' => $_SESSION['otp_phone'],
            'error_message' => $error_message,
            'success_message' => $success_message
        ];
    }

    /**
     * Send OTP for login
     */
    public function sendOTP() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $phone = $_POST['phone'] ?? '';
                
                if (empty($phone)) {
                    $_SESSION['otp_error'] = 'Please enter your phone number';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Validate phone format (Nepali format: 98xxxxxxxx)
                if (!preg_match('/^98[0-9]{8}$/', $phone)) {
                    $_SESSION['otp_error'] = 'Please enter a valid Nepali phone number (98xxxxxxxx)';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Check if SMS service is enabled
                if (!$this->smsService->isEnabled()) {
                    $_SESSION['otp_error'] = 'SMS service is currently unavailable. Please try another login method.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Check if phone is registered - ALLOW BOTH DIRECT AND GUEST USERS
                $customer = $this->customerModel->findByPhone($phone);
                if (!$customer) {
                    $_SESSION['otp_error'] = 'No account found with this phone number. Please sign up first.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Check if customer is active - FOR BOTH DIRECT AND GUEST USERS
                if ($customer['status'] !== 'active') {
                    $_SESSION['otp_error'] = 'Your account is not active. Please contact support.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Generate OTP
                $otp = $this->generateOTP();
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Save OTP to database
                $otpSaved = $this->logOTPModel->saveOTP($customer['id'], $otp, $expires_at);
                
                if (!$otpSaved) {
                    $_SESSION['otp_error'] = 'Failed to generate OTP. Please try again.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Format phone for SMS (add country code)
                $formattedPhone = '977' . $phone;
                
                // Send OTP via SMS
                $smsResult = $this->smsService->sendOTP($formattedPhone, $otp);
                
                if ($smsResult['success']) {
                    $_SESSION['otp_phone'] = $phone;
                    $_SESSION['otp_customer_id'] = $customer['id'];
                    $_SESSION['otp_attempts'] = 0;
                    $_SESSION['otp_success'] = 'OTP sent successfully to ' . $phone;
                    header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                    exit;
                } else {
                    $_SESSION['otp_error'] = 'Failed to send OTP. Please try again.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
            } catch (Exception $e) {
                error_log("OTP Send Error: " . $e->getMessage());
                $_SESSION['otp_error'] = 'An error occurred. Please try again.';
                header('Location: ' . $this->pathConfig->url('login?method=otp'));
                exit;
            }
        }
        
        $_SESSION['otp_error'] = 'Invalid request';
        header('Location: ' . $this->pathConfig->url('login?method=otp'));
        exit;
    }

    /**
     * Verify OTP and login with password change option - MODIFIED TO ALLOW BOTH DIRECT AND GUEST USERS
     */
    public function verifyOTP() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $otp = $_POST['otp'] ?? '';
                $phone = $_SESSION['otp_phone'] ?? '';
                $customer_id = $_SESSION['otp_customer_id'] ?? '';
                
                if (empty($otp) || empty($phone) || empty($customer_id)) {
                    $_SESSION['otp_error'] = 'Invalid OTP verification request';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Validate OTP format
                if (!preg_match('/^[0-9]{6}$/', $otp)) {
                    $_SESSION['otp_error'] = 'Please enter a valid 6-digit OTP';
                    header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                    exit;
                }
                
                // Check OTP attempts
                $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
                if ($_SESSION['otp_attempts'] > 5) {
                    $_SESSION['otp_error'] = 'Too many OTP attempts. Please request a new OTP.';
                    unset($_SESSION['otp_phone'], $_SESSION['otp_customer_id'], $_SESSION['otp_attempts']);
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                // Verify OTP
                $otpRecord = $this->logOTPModel->verifyOTP($customer_id, $otp);
                
                if ($otpRecord) {
                    // OTP verified successfully
                    $customer = $this->customerModel->findById($customer_id);
                    
                    if ($customer && $customer['status'] === 'active') {
                        // Update phone verification status if not already verified
                        $this->updatePhoneVerificationStatus($customer_id);
                        
                        // Check if user needs to change password (guest users or first-time login)
                        // MODIFIED: Show password change option for BOTH guest and direct users
                        $needsPasswordChange = $this->shouldPromptForPasswordChange($customer);
                        
                        if ($needsPasswordChange) {
                            // Store verification success and redirect to password change page
                            $_SESSION['otp_verified'] = true;
                            $_SESSION['otp_verified_customer_id'] = $customer_id;
                            $_SESSION['otp_verified_phone'] = $phone;
                            
                            // Mark OTP as used
                            $this->logOTPModel->markOTPAsUsed($otpRecord['id']);
                            
                            // Clear OTP session data but keep verification data
                            unset($_SESSION['otp_phone'], $_SESSION['otp_customer_id'], $_SESSION['otp_attempts']);
                            
                            // Redirect to password change page
                            header('Location: ' . $this->pathConfig->url('otp/change-password'));
                            exit;
                        } else {
                            // Proceed with normal login
                            $this->completeLogin($customer, $customer_id);
                        }
                    } else {
                        $_SESSION['otp_error'] = 'Account not found or inactive';
                        header('Location: ' . $this->pathConfig->url('login?method=otp'));
                        exit;
                    }
                } else {
                    $_SESSION['otp_error'] = 'Invalid OTP. Please try again.';
                    header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                    exit;
                }
            } catch (Exception $e) {
                error_log("OTP Verification Error: " . $e->getMessage());
                $_SESSION['otp_error'] = 'An error occurred during verification. Please try again.';
                header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                exit;
            }
        }
        
        $_SESSION['otp_error'] = 'Invalid request';
        header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
        exit;
    }

    /**
     * Show password change form after OTP verification
     */
    public function showChangePassword() {
        // Check if OTP was successfully verified
        if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
            $_SESSION['otp_error'] = 'Please verify OTP first';
            header('Location: ' . $this->pathConfig->url('login?method=otp'));
            exit;
        }
        
        $customer_id = $_SESSION['otp_verified_customer_id'] ?? '';
        $phone = $_SESSION['otp_verified_phone'] ?? '';
        
        if (empty($customer_id) || empty($phone)) {
            $_SESSION['otp_error'] = 'Session expired. Please verify OTP again.';
            header('Location: ' . $this->pathConfig->url('login?method=otp'));
            exit;
        }
        
        $error_message = $_SESSION['password_error'] ?? '';
        $success_message = $_SESSION['password_success'] ?? '';
        
        unset($_SESSION['password_error']);
        unset($_SESSION['password_success']);
        
        return [
            'page_title' => 'Set New Password - Phool Delivery',
            'form_type' => 'password_change',
            'phone' => $phone,
            'error_message' => $error_message,
            'success_message' => $success_message
        ];
    }

    /**
     * Process password change after OTP verification
     */
    public function processPasswordChange() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Check if OTP was successfully verified
                if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
                    $_SESSION['password_error'] = 'Please verify OTP first';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                $customer_id = $_SESSION['otp_verified_customer_id'] ?? '';
                $action = $_POST['action'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($customer_id)) {
                    $_SESSION['password_error'] = 'Session expired. Please verify OTP again.';
                    header('Location: ' . $this->pathConfig->url('login?method=otp'));
                    exit;
                }
                
                if ($action === 'skip') {
                    // User chose to skip password change, proceed with login
                    $customer = $this->customerModel->findById($customer_id);
                    if ($customer) {
                        $this->completeLogin($customer, $customer_id);
                    } else {
                        $_SESSION['password_error'] = 'Customer not found';
                        header('Location: ' . $this->pathConfig->url('otp/change-password'));
                        exit;
                    }
                } elseif ($action === 'change') {
                    // User wants to change password
                    if (empty($new_password) || empty($confirm_password)) {
                        $_SESSION['password_error'] = 'Please fill in all password fields';
                        header('Location: ' . $this->pathConfig->url('otp/change-password'));
                        exit;
                    }
                    
                    if ($new_password !== $confirm_password) {
                        $_SESSION['password_error'] = 'Passwords do not match';
                        header('Location: ' . $this->pathConfig->url('otp/change-password'));
                        exit;
                    }
                    
                    if (strlen($new_password) < 6) {
                        $_SESSION['password_error'] = 'Password must be at least 6 characters long';
                        header('Location: ' . $this->pathConfig->url('otp/change-password'));
                        exit;
                    }
                    
                    // Update password
                    if ($this->updateCustomerPassword($customer_id, $new_password)) {
                        // Password updated successfully, proceed with login
                        $customer = $this->customerModel->findById($customer_id);
                        if ($customer) {
                            $_SESSION['password_success'] = 'Password updated successfully!';
                            $this->completeLogin($customer, $customer_id);
                        } else {
                            $_SESSION['password_error'] = 'Customer not found';
                            header('Location: ' . $this->pathConfig->url('otp/change-password'));
                            exit;
                        }
                    } else {
                        $_SESSION['password_error'] = 'Failed to update password. Please try again.';
                        header('Location: ' . $this->pathConfig->url('otp/change-password'));
                        exit;
                    }
                } else {
                    $_SESSION['password_error'] = 'Invalid action';
                    header('Location: ' . $this->pathConfig->url('otp/change-password'));
                    exit;
                }
            } catch (Exception $e) {
                error_log("Password Change Error: " . $e->getMessage());
                $_SESSION['password_error'] = 'An error occurred. Please try again.';
                header('Location: ' . $this->pathConfig->url('otp/change-password'));
                exit;
            }
        }
        
        $_SESSION['password_error'] = 'Invalid request';
        header('Location: ' . $this->pathConfig->url('otp/change-password'));
        exit;
    }

    /**
     * Complete the login process - MODIFIED TO HANDLE BOTH USER TYPES WITH LANGUAGE AUTO-SWITCH
     */
    private function completeLogin($customer, $customer_id) {
        try {
            // Log successful login
            $logModel = new LogModel($this->db);
            $logId = $logModel->logLoginAttempt($customer_id, 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            // Get registration type
            $registrationType = $this->getRegistrationType($customer_id);
            $isGuestUser = ($registrationType === 'guest');
            
            // Start session and login - ALLOW BOTH DIRECT AND GUEST USERS
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_email'] = $customer['email'];
            $_SESSION['customer_phone'] = $customer['phone'];
            $_SESSION['is_guest_user'] = $isGuestUser;
            $_SESSION['registration_type'] = $registrationType;
            $_SESSION['login_log_id'] = $logId;
            $_SESSION['login_method'] = 'otp';
            
            // AUTO-SWITCH LANGUAGE: Load user's preferred language from database
            if (class_exists('LanguageHelper')) {
                LanguageHelper::autoSwitchLanguageAfterLogin($customer_id);
            }
            
            // Clear all OTP session data
            unset(
                $_SESSION['otp_verified'], 
                $_SESSION['otp_verified_customer_id'], 
                $_SESSION['otp_verified_phone'],
                $_SESSION['otp_phone'], 
                $_SESSION['otp_customer_id'], 
                $_SESSION['otp_attempts']
            );
            
            // Show appropriate success message based on user type
            if ($isGuestUser) {
                $_SESSION['auth_success'] = 'Login successful! Welcome back, guest user.';
            } else {
                $_SESSION['auth_success'] = 'Login successful! Welcome back.';
            }
            
            header('Location: ' . $this->pathConfig->url(''));
            exit;
        } catch (Exception $e) {
            error_log("Login Completion Error: " . $e->getMessage());
            $_SESSION['otp_error'] = 'An error occurred during login. Please try again.';
            header('Location: ' . $this->pathConfig->url('login?method=otp'));
            exit;
        }
    }

    /**
     * Check if user should be prompted to change password - MODIFIED FOR BOTH USER TYPES
     */
    private function shouldPromptForPasswordChange($customer) {
        try {
            // Check if it's a guest user (they often use default passwords)
            $registrationType = $this->getRegistrationType($customer['id']);
            
            // Check if password is using default pattern or is weak
            $isDefaultPassword = $this->isUsingDefaultPassword($customer['password']);
            
            // Check if user has never changed password (you might want to add a field for this)
            $hasChangedPassword = $this->hasUserChangedPassword($customer['id']);
            
            // MODIFIED: Prompt for password change for BOTH guest and direct users if:
            // 1. They're using a default password OR  
            // 2. They've never changed their password
            // Remove the restriction that only guest users should be prompted
            return ($isDefaultPassword || !$hasChangedPassword);
        } catch (Exception $e) {
            error_log("Password Change Check Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is using default password
     */
    private function isUsingDefaultPassword($password_hash) {
        try {
            // Check against common default passwords
            $default_passwords = ['DemoGuest', 'password', '123456'];
            
            foreach ($default_passwords as $default) {
                if (password_verify($default, $password_hash)) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Default Password Check Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has changed password (you might want to add a field in customers table)
     */
    private function hasUserChangedPassword($customer_id) {
        try {
            // For now, we'll assume all users have changed password unless we detect default
            // You can add a field like 'password_changed_at' in your customers table
            return true;
        } catch (Exception $e) {
            error_log("Password Change History Error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Update customer password
     */
    private function updateCustomerPassword($customer_id, $new_password) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE customers 
                     SET password = :password, 
                         updated_at = NOW()
                     WHERE id = :customer_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':customer_id', $customer_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Password Update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP() {
        try {
            // Check if this is an AJAX request
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            $phone = $_SESSION['otp_phone'] ?? '';
            $customer_id = $_SESSION['otp_customer_id'] ?? '';
            
            if (empty($phone) || empty($customer_id)) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Cannot resend OTP. Please request a new OTP.']);
                    exit;
                }
                $_SESSION['otp_error'] = 'Cannot resend OTP. Please request a new OTP.';
                header('Location: ' . $this->pathConfig->url('login?method=otp'));
                exit;
            }
            
            // Check if we can resend (limit resends)
            $lastOTP = $this->logOTPModel->getLastOTP($customer_id);
            if ($lastOTP && strtotime($lastOTP['created_at']) > strtotime('-1 minute')) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Please wait 1 minute before requesting a new OTP.']);
                    exit;
                }
                $_SESSION['otp_error'] = 'Please wait 1 minute before requesting a new OTP.';
                header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                exit;
            }
            
            // Generate new OTP
            $otp = $this->generateOTP();
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Save new OTP
            $otpSaved = $this->logOTPModel->saveOTP($customer_id, $otp, $expires_at);
            
            if (!$otpSaved) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP. Please try again.']);
                    exit;
                }
                $_SESSION['otp_error'] = 'Failed to generate OTP. Please try again.';
                header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                exit;
            }
            
            // Format phone for SMS (add country code)
            $formattedPhone = '977' . $phone;
            
            // Send new OTP
            $smsResult = $this->smsService->sendOTP($formattedPhone, $otp);
            
            if ($smsResult['success']) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'New OTP sent successfully']);
                    exit;
                }
                $_SESSION['otp_success'] = 'New OTP sent successfully to ' . $phone;
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Failed to send OTP. Please try again.']);
                    exit;
                }
                $_SESSION['otp_error'] = 'Failed to send OTP. Please try again.';
            }
            
            if (!$isAjax) {
                header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
                exit;
            }
        } catch (Exception $e) {
            error_log("OTP Resend Error: " . $e->getMessage());
            
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to resend OTP. Please try again.']);
                exit;
            }
            
            $_SESSION['otp_error'] = 'Failed to resend OTP. Please try again.';
            header('Location: ' . $this->pathConfig->url('otp/verify-otp'));
            exit;
        }
    }

    /**
     * Update phone verification status
     */
    private function updatePhoneVerificationStatus($customer_id) {
        try {
            $query = "UPDATE customers 
                     SET phone_verified_at = NOW(), 
                         verification_status = 'verified',
                         verified_at = NOW()
                     WHERE id = :customer_id 
                     AND (phone_verified_at IS NULL OR verification_status = 'pending')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Phone Verification Update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOTP() {
        return sprintf("%06d", mt_rand(1, 999999));
    }

    /**
     * Get registration type
     */
    private function getRegistrationType($customer_id) {
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
            error_log("Registration Type Error: " . $e->getMessage());
            return 'direct';
        }
    }
}
?>