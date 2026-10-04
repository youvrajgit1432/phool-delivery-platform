<?php
// app/controllers/ProfileController.php
class ProfileController {
    private $db;
    private $profileModel;
    private $emailService;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->profileModel = new Profile($db);
        $this->emailService = new EmailService($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    // Profile management
    public function profile() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->profileModel->updateProfile($user_id, $_POST);
            
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
            
            header('Location: ' . $this->pathConfig->url('account/profile'));
            exit;
        }
        
        $data = [
            'user' => $this->profileModel->getUserProfile($user_id),
            'page_title' => 'My Profile - Phool Delivery'
        ];
        
        return $data;
    }

    // Show email verification page
    public function verifyEmail() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $user = $this->profileModel->getUserProfile($user_id);
        
        $data = [
            'user' => $user,
            'page_title' => 'Verify Email - Phool Delivery'
        ];
        
        return $data;
    }

    // Send OTP for email verification
    public function sendVerificationOTP() {
        header('Content-Type: application/json');
        
        // Log minimal information for debugging
        error_log("Send OTP API called for user");
        
        try {
            // Check session
            if (!isset($_SESSION['customer_id'])) {
                throw new Exception('Authentication required');
            }
            
            $user_id = $_SESSION['customer_id'];
            
            $user = $this->profileModel->getUserProfile($user_id);
            
            if (!$user) {
                throw new Exception('User profile not found');
            }
            
            if (empty($user['email'])) {
                throw new Exception('Email address not found');
            }
            
            $email = $user['email'];
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Send OTP using EmailService
            $result = $this->emailService->sendOTP($email, $user_id);
            
            if ($result['success']) {
                error_log("OTP sent successfully to user ID: $user_id");
                echo json_encode([
                    'success' => true, 
                    'message' => 'OTP sent successfully to your email.'
                ]);
            } else {
                error_log("OTP send failed for user ID: $user_id");
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to send OTP. Please try again.'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Send OTP Error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Unable to process your request. Please try again.'
            ]);
        }
        exit;
    }

    // Verify OTP
    public function verifyEmailOTP() {
        header('Content-Type: application/json');
        
        error_log("Verify OTP API called");
        
        try {
            // Check session
            if (!isset($_SESSION['customer_id'])) {
                throw new Exception('Authentication required');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid request data');
            }
            
            $otp = $input['otp'] ?? '';
            $user_id = $_SESSION['customer_id'];
            
            if (empty($otp)) {
                throw new Exception('OTP is required');
            }
            
            // Validate OTP format
            if (!preg_match('/^\d{6}$/', $otp)) {
                throw new Exception('Invalid OTP format');
            }
            
            // Verify OTP using EmailService
            $result = $this->emailService->verifyOTP($user_id, $otp);
            
            if ($result['success']) {
                error_log("OTP verified successfully for user ID: $user_id");
            } else {
                error_log("OTP verification failed for user ID: $user_id");
            }
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            error_log("Verify OTP Error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Verification failed. Please try again.'
            ]);
        }
        exit;
    }

    // Check verification status
    public function checkVerificationStatus() {
        header('Content-Type: application/json');
        
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Authentication required'
                ]);
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            $user = $this->profileModel->getUserProfile($user_id);
            
            if (!$user) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'User profile not found'
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'email_verified' => !empty($user['email_verified_at']),
                'phone_verified' => !empty($user['phone_verified_at']),
                'has_pending_otp' => $this->profileModel->hasPendingOTP($user_id)
            ]);
            
        } catch (Exception $e) {
            error_log("Check Verification Status Error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Unable to fetch verification status'
            ]);
        }
        exit;
    }

    // Generic error handler for API responses
    private function handleApiError(Exception $e, $context = '') {
        error_log("API Error" . ($context ? " in $context: " : ": ") . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again.'
        ];
    }

    // Validate and sanitize input
    private function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
?>