<?php
// controllers/SocialAuthController.php
class SocialAuthController {
    private $db;
    private $customerModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->customerModel = new Customer($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    // Use PathConfig for base URL generation
    private function base_url($path = '') {
        return $this->pathConfig->url($path);
    }
    
    // Initialize Google login
    public function googleLogin() {
        try {
            // Load social config
            $socialConfig = $this->getSocialConfig();
            
            if (!isset($socialConfig['google']['client_id'])) {
                $this->redirectWithError('social_login_unavailable');
                return;
            }
            
            $clientID = $socialConfig['google']['client_id'];
            $redirectURI = $this->base_url('auth/google-callback');
            
            // Create Google client URL
            $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                'client_id' => $clientID,
                'redirect_uri' => $redirectURI,
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'offline',
                'prompt' => 'consent'
            ]);
            
            header('Location: ' . $googleAuthUrl);
            exit;
            
        } catch (Exception $e) {
            $this->logError('Google login initialization failed: ' . $e->getMessage());
            $this->redirectWithError('social_login_failed');
        }
    }
    
    // Google callback
    public function googleCallback() {
        try {
            if (!isset($_GET['code'])) {
                $this->redirectWithError('authentication_failed');
                return;
            }
            
            $socialConfig = $this->getSocialConfig();
            
            if (!isset($socialConfig['google']['client_id']) || !isset($socialConfig['google']['client_secret'])) {
                $this->redirectWithError('social_login_unavailable');
                return;
            }
            
            $code = $_GET['code'];
            $clientID = $socialConfig['google']['client_id'];
            $clientSecret = $socialConfig['google']['client_secret'];
            $redirectURI = $this->base_url('auth/google-callback');
            
            // Exchange code for access token
            $tokenResponse = $this->exchangeGoogleCode($code, $clientID, $clientSecret, $redirectURI);
            
            if (!$tokenResponse || isset($tokenResponse['error'])) {
                $this->logError('Google token exchange failed');
                $this->redirectWithError('authentication_failed');
                return;
            }
            
            // Get user info from Google
            $userInfo = $this->getGoogleUserInfo($tokenResponse['access_token']);
            
            if (!$userInfo || isset($userInfo['error'])) {
                $this->logError('Google user info failed');
                $this->redirectWithError('authentication_failed');
                return;
            }
            
            // Validate required user data
            if (empty($userInfo['id']) || empty($userInfo['email'])) {
                $this->logError('Google user data incomplete');
                $this->redirectWithError('authentication_failed');
                return;
            }
            
            // Process social login
            $socialData = [
                'id' => $userInfo['id'],
                'email' => $userInfo['email'],
                'name' => $userInfo['name'] ?? '',
                'picture' => $userInfo['picture'] ?? null,
                'access_token' => $tokenResponse['access_token'],
                'refresh_token' => $tokenResponse['refresh_token'] ?? null
            ];
            
            $customer = $this->customerModel->findOrCreateFromSocial('google', $socialData);
            
            if ($customer) {
                // Login successful
                session_start();
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['social_login'] = true;
                $_SESSION['social_provider'] = 'google';
                
                // Use PathConfig for redirect URL
                header('Location: ' . $this->pathConfig->url(''));
                exit;
            } else {
                $this->redirectWithError('social_login_failed');
            }
            
        } catch (Exception $e) {
            $this->logError('Google callback failed: ' . $e->getMessage());
            $this->redirectWithError('authentication_failed');
        }
    }
    
    // Exchange Google code for token
    private function exchangeGoogleCode($code, $clientID, $clientSecret, $redirectURI) {
        try {
            $url = 'https://oauth2.googleapis.com/token';
            
            $data = [
                'code' => $code,
                'client_id' => $clientID,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectURI,
                'grant_type' => 'authorization_code'
            ];
            
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                    'timeout' => 30
                ]
            ];
            
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === FALSE) {
                $this->logError('Google token exchange HTTP request failed');
                return false;
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            $this->logError('Google token exchange exception: ' . $e->getMessage());
            return false;
        }
    }
    
    // Get Google user info
    private function getGoogleUserInfo($accessToken) {
        try {
            $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
            
            $options = [
                'http' => [
                    'header' => "Authorization: Bearer $accessToken\r\n",
                    'ignore_errors' => true,
                    'timeout' => 30
                ]
            ];
            
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === FALSE) {
                $this->logError('Google user info HTTP request failed');
                return false;
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            $this->logError('Google user info exception: ' . $e->getMessage());
            return false;
        }
    }
    
    // Get social configuration
    private function getSocialConfig() {
        try {
            // Use PathConfig to get the correct path for config file
            $configFile = $this->pathConfig->filePath('admin_storage') . '/../config/social.php';
            
            if (file_exists($configFile)) {
                return include $configFile;
            }
            
            // Fallback to environment variables
            return [
                'google' => [
                    'client_id' => getenv('GOOGLE_CLIENT_ID'),
                    'client_secret' => getenv('GOOGLE_CLIENT_SECRET')
                ]
            ];
            
        } catch (Exception $e) {
            $this->logError('Social config loading failed: ' . $e->getMessage());
            return ['google' => ['client_id' => '', 'client_secret' => '']];
        }
    }
    
    // Check if social login is configured
    public function isSocialLoginEnabled() {
        try {
            $config = $this->getSocialConfig();
            $googleEnabled = !empty($config['google']['client_id']) && !empty($config['google']['client_secret']);
            
            return $googleEnabled;
            
        } catch (Exception $e) {
            $this->logError('Social login check failed: ' . $e->getMessage());
            return false;
        }
    }
    
    // Secure error logging - don't expose sensitive data
    private function logError($message) {
        // Log only generic error messages without sensitive data
        $safeMessage = preg_replace('/client_secret=[^&]*/', 'client_secret=***', $message);
        $safeMessage = preg_replace('/access_token=[^&]*/', 'access_token=***', $safeMessage);
        $safeMessage = preg_replace('/refresh_token=[^&]*/', 'refresh_token=***', $safeMessage);
        $safeMessage = preg_replace('/code=[^&]*/', 'code=***', $safeMessage);
        
        error_log('SocialAuth Error: ' . $safeMessage);
    }
    
    // Secure redirect with generic error messages
    private function redirectWithError($errorType) {
        $errorMessages = [
            'authentication_failed' => 'Authentication failed. Please try again.',
            'social_login_failed' => 'Login failed. Please try again.',
            'social_login_unavailable' => 'Social login is currently unavailable.',
            'invalid_request' => 'Invalid request. Please try again.'
        ];
        
        $message = $errorMessages[$errorType] ?? 'An error occurred. Please try again.';
        
        // Use PathConfig for redirect URL
        $url = $this->pathConfig->url('login?error=' . urlencode($errorType));
        
        header('Location: ' . $url);
        exit;
    }
    
    // Handle exceptions globally
    public function handleException($exception) {
        $this->logError('Unhandled exception: ' . $exception->getMessage());
        $this->redirectWithError('authentication_failed');
    }
}

// Set exception handler at the bottom of the file
// Note: This should be called after the controller is instantiated in your application
// set_exception_handler([new SocialAuthController($db), 'handleException']);
?>