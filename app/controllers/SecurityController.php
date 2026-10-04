<?php
// app/controllers/SecurityController.php
class SecurityController {
    private $db;
    private $securityModel;
    private $accountModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->securityModel = new Security($db);
        $this->accountModel = new Account($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    // Security settings page
    public function index() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        try {
            $data = [
                'login_history' => $this->securityModel->getLoginHistory($user_id),
                'security_settings' => $this->securityModel->getSecuritySettings($user_id),
                'page_title' => 'Security Settings - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("SecurityController index error: " . $e->getMessage());
            return [
                'login_history' => [],
                'security_settings' => [],
                'page_title' => 'Security Settings - Phool Delivery',
                'error' => 'Unable to load security settings'
            ];
        }
    }
    
    // Change password
    public function changePassword() {
        if (!isset($_SESSION['customer_id'])) {
            $this->sendErrorResponse('You must be logged in to change your password');
            return;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $result = $this->securityModel->changePassword($user_id, $_POST);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/security'));
                exit;
            } catch (Exception $e) {
                error_log("Change password error for user $user_id: " . $e->getMessage());
                $_SESSION['error_message'] = 'Unable to change password at this time';
                header('Location: ' . $this->pathConfig->url('account/security'));
                exit;
            }
        } else {
            $this->sendErrorResponse('Invalid request method');
        }
    }
    
    // Toggle 2FA
    public function toggle2FA() {
        if (!isset($_SESSION['customer_id'])) {
            $this->sendErrorResponse('You must be logged in to modify 2FA settings');
            return;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $enable = isset($_POST['enable_2fa']);
                $result = $this->securityModel->toggle2FA($user_id, $enable);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/security'));
                exit;
            } catch (Exception $e) {
                error_log("Toggle 2FA error for user $user_id: " . $e->getMessage());
                $_SESSION['error_message'] = 'Unable to update 2FA settings at this time';
                header('Location: ' . $this->pathConfig->url('account/security'));
                exit;
            }
        } else {
            $this->sendErrorResponse('Invalid request method');
        }
    }
    
    // Get login history (API endpoint)
    public function getLoginHistory() {
        if (!isset($_SESSION['customer_id'])) {
            $this->sendJsonError('Authentication required');
            return;
        }
        
        $user_id = $_SESSION['customer_id'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        try {
            $history = $this->securityModel->getLoginHistory($user_id, $limit);
            $this->sendJsonResponse($history);
        } catch (Exception $e) {
            error_log("Get login history error for user $user_id: " . $e->getMessage());
            $this->sendJsonError('Unable to retrieve login history');
        }
    }
    
    /**
     * Send standardized JSON error response
     */
    private function sendJsonError($message) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
    
    /**
     * Send standardized JSON success response
     */
    private function sendJsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    /**
     * Send standardized error response
     */
    private function sendErrorResponse($message) {
        if ($this->isAjaxRequest()) {
            $this->sendJsonError($message);
        } else {
            $_SESSION['error_message'] = $message;
            header('Location: ' . $this->pathConfig->url('account/security'));
            exit;
        }
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}

// Additional security configuration (add to your main config or bootstrap file)
// Disable displaying errors in production
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Set error log path using PathConfig
$pathConfig = PathConfig::getInstance();
ini_set('error_log', $pathConfig->filePath() . '/storage/logs/php_errors.log');

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
});

// Set custom exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    http_response_code(500);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    // Don't expose sensitive error details to client
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred: ' . $exception->getMessage()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]);
    }
    exit;
});
?>