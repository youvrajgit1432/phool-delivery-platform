<?php
class OTPController {
    private $db;
    private $smsService;
    private $otpModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->otpModel = new OTP($db);
        $this->pathConfig = PathConfig::getInstance();
        
        // Initialize SMS service with database configuration
        $this->smsService = new SparrowSMSService($db);
    }

    /**
     * Generate and send OTP to phone number
     * Daily limit: 5 OTP requests per day
     * Resend cooldown: 60 seconds between requests
     */
    public function sendOTP($phone) {
        try {
            // Clean phone number (remove spaces, dashes, etc.)
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Validate phone number (Nepal format)
            if (strlen($phone) !== 10 || !preg_match('/^9[0-9]{9}$/', $phone)) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format. Please provide a 10-digit Nepal number starting with 9.'
                ];
            }
            
            // ✅ CHECK DAILY LIMIT: Max 5 OTP per day
            $dailyCount = $this->otpModel->countDailyAttempts($phone);
            if ($dailyCount >= 5) {
                return [
                    'success' => false,
                    'message' => 'You have reached the maximum OTP requests (5) for today. Please try again tomorrow.'
                ];
            }
            
            // ✅ CHECK RESEND COOLDOWN: Min 60 seconds between requests
            $cooldownStatus = $this->otpModel->checkResendCooldown($phone);
            if (!$cooldownStatus['allowed']) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting a new OTP.',
                    'retry_after' => $cooldownStatus['seconds_remaining'],
                    'can_resend_at' => $cooldownStatus['can_resend_at']
                ];
            }
            
            // Check if SMS is enabled
            if (!$this->smsService->isEnabled()) {
                return [
                    'success' => false,
                    'message' => 'SMS service is currently unavailable. Please try again later.'
                ];
            }
            
            // Generate OTP (6 digits)
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP in database with expiration (10 minutes)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $otpId = $this->otpModel->create($phone, $otpCode, $expiresAt);
            
            if (!$otpId) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate OTP. Please try again.'
                ];
            }
            
            // Prepare SMS message
            $message = "Your Phool Delivery verification code is: $otpCode. Valid for 10 minutes.";
            
            // Format phone for SMS (add country code)
            $formattedPhone = '977' . $phone;
            
            // Send SMS via SMS Sparrow
            $smsResult = $this->smsService->sendSMS($formattedPhone, $message);
            
            if ($smsResult['success']) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'otp_id' => $otpId,
                    'remaining_today' => 5 - $dailyCount - 1
                ];
            } else {
                // Delete the OTP record if SMS failed
                $this->otpModel->delete($otpId);
                
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP. Please try again.'
                ];
            }
        } catch (Exception $e) {
            // Log the error for server-side debugging
            error_log("OTP Send Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ];
        }
    }

    /**
     * Verify OTP code for a phone number
     */
    public function verifyOTP($phone, $otpCode) {
        try {
            // Clean phone number
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Validate inputs
            if (empty($phone) || empty($otpCode)) {
                return [
                    'success' => false,
                    'message' => 'Phone number and OTP code are required.'
                ];
            }
            
            // Find valid OTP for this phone
            $otpRecord = $this->otpModel->findValidOTP($phone, $otpCode);
            
            if (!$otpRecord) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP code.'
                ];
            }
            
            // Mark OTP as used
            $this->otpModel->markAsUsed($otpRecord['id']);
            
            // Update customer verification status
            $this->updateCustomerPhoneVerification($phone);
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully.'
            ];
        } catch (Exception $e) {
            // Log the error for server-side debugging
            error_log("OTP Verification Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ];
        }
    }

    /**
     * Update customer phone verification status after successful OTP verification
     */
    private function updateCustomerPhoneVerification($phone) {
        try {
            $query = "UPDATE customers 
                     SET verification_status = 'verified', 
                         phone_verified_at = NOW(),
                         updated_at = NOW()
                     WHERE phone = :phone";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":phone", $phone);
            
            if ($stmt->execute()) {
                error_log("Phone verification updated for: " . $phone);
                
                // Also update session if customer is logged in
                if (isset($_SESSION['customer_id'])) {
                    // Get customer ID from phone to verify it matches session
                    $customerQuery = "SELECT id FROM customers WHERE phone = :phone";
                    $customerStmt = $this->db->prepare($customerQuery);
                    $customerStmt->bindParam(":phone", $phone);
                    $customerStmt->execute();
                    
                    if ($customerStmt->rowCount() > 0) {
                        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                        if ($customer['id'] == $_SESSION['customer_id']) {
                            $_SESSION['verification_status'] = 'verified';
                        }
                    }
                }
                
                return true;
            } else {
                error_log("Failed to update phone verification for: " . $phone);
                return false;
            }
        } catch (Exception $e) {
            error_log("Error updating customer phone verification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resend OTP to phone number
     */
    public function resendOTP($phone) {
        try {
            // Clean phone number
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Check if there's a recent OTP request (prevent abuse)
            $recentAttempts = $this->otpModel->countRecentAttempts($phone, 10);
            
            if ($recentAttempts >= 3) {
                return [
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again later.'
                ];
            }
            
            // Send new OTP
            return $this->sendOTP($phone);
        } catch (Exception $e) {
            // Log the error for server-side debugging
            error_log("OTP Resend Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.'
            ];
        }
    }

    /**
     * API endpoint for sending OTP
     */
    public function apiSendOTP() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Debug: Log received phone
            error_log("OTP Send API - Received phone: " . ($input['phone'] ?? 'empty'));
            
            if (!isset($input['phone']) || empty($input['phone'])) {
                error_log("OTP Send API - Phone number missing");
                echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                return;
            }
            
            error_log("OTP Send API - Calling sendOTP for phone: " . $input['phone']);
            $result = $this->sendOTP($input['phone']);
            
            // Debug: Log the result
            error_log("OTP Send API - Result: " . json_encode($result));
            
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("API Send OTP Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Request failed. Please try again.']);
        }
    }

    /**
     * API endpoint for verifying OTP
     */
    public function apiVerifyOTP() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['phone']) || !isset($input['otp_code']) || 
                empty($input['phone']) || empty($input['otp_code'])) {
                echo json_encode(['success' => false, 'message' => 'Phone and OTP code are required']);
                return;
            }
            
            $result = $this->verifyOTP($input['phone'], $input['otp_code']);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("API Verify OTP Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
        }
    }

    /**
     * API endpoint for resending OTP
     */
    public function apiResendOTP() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['phone']) || empty($input['phone'])) {
                echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                return;
            }
            
            $result = $this->resendOTP($input['phone']);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("API Resend OTP Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Resend failed. Please try again.']);
        }
    }

    /**
     * Generic error handler for API responses
     */
    private function handleApiError($message = 'An error occurred') {
        error_log("API Error: " . $message);
        return [
            'success' => false,
            'message' => 'Service temporarily unavailable. Please try again later.'
        ];
    }

    /**
     * Render OTP verification page
     */
    public function showVerifyOTP() {
        try {
            // Check if phone is provided
            $phone = $_GET['phone'] ?? '';
            
            if (empty($phone)) {
                // Redirect to appropriate error page based on environment
                $errorUrl = $this->pathConfig->url('error?code=invalid_phone');
                header('Location: ' . $errorUrl);
                exit;
            }
            
            // Render the OTP verification page
            $viewPath = $this->pathConfig->filePath('admin_storage') . '/../app/views/otp/verify-otp.php';
            
            if (file_exists($viewPath)) {
                include $viewPath;
            } else {
                // Fallback to simple HTML form
                $this->renderOTPForm($phone);
            }
        } catch (Exception $e) {
            error_log("Show OTP Error: " . $e->getMessage());
            $this->renderOTPForm($phone ?? '');
        }
    }

    /**
     * Render OTP form as fallback
     */
    private function renderOTPForm($phone) {
        $baseUrl = $this->pathConfig->get('base_url');
        $assetsUrl = $this->pathConfig->get('assets');
        
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verify OTP - Phool Delivery</title>
            <link rel="stylesheet" href="' . $assetsUrl . '/css/main.css">
            <style>
                .otp-container { max-width: 400px; margin: 50px auto; padding: 20px; }
                .otp-input { width: 100%; padding: 10px; margin: 10px 0; }
                .btn { background: #28a745; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="otp-container">
                <h2>Verify Your Phone</h2>
                <p>Enter the OTP sent to ' . htmlspecialchars($phone) . '</p>
                <form id="otpForm" action="' . $baseUrl . '/otp/verify" method="POST">
                    <input type="hidden" name="phone" value="' . htmlspecialchars($phone) . '">
                    <input type="text" name="otp_code" class="otp-input" placeholder="Enter OTP" required maxlength="6">
                    <button type="submit" class="btn">Verify OTP</button>
                </form>
                <p><a href="#" onclick="resendOTP()">Resend OTP</a></p>
            </div>
            <script>
                function resendOTP() {
                    fetch("' . $baseUrl . '/api/otp/resend", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ phone: "' . $phone . '" })
                    }).then(r => r.json()).then(data => {
                        alert(data.message);
                    });
                }
            </script>
        </body>
        </html>';
    }

    /**
     * Process OTP verification form submission
     */
    public function processVerifyOTP() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $phone = $_POST['phone'] ?? '';
            $otpCode = $_POST['otp_code'] ?? '';
            
            if (empty($phone) || empty($otpCode)) {
                throw new Exception('Phone and OTP code are required');
            }
            
            $result = $this->verifyOTP($phone, $otpCode);
            
            if ($result['success']) {
                // Redirect to success page or dashboard
                $redirectUrl = $this->pathConfig->url('account/dashboard');
                $_SESSION['success_message'] = 'Phone verified successfully!';
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            error_log("Process OTP Error: " . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            
            // Redirect back to OTP page with phone
            $redirectUrl = $this->pathConfig->url('otp/verify?phone=' . urlencode($_POST['phone'] ?? ''));
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Get OTP status for monitoring
     */
    public function getOTPStatus() {
        try {
            $stats = $this->otpModel->getStats();
            
            return [
                'success' => true,
                'data' => [
                    'total_otps' => $stats['total'] ?? 0,
                    'used_otps' => $stats['used'] ?? 0,
                    'expired_otps' => $stats['expired'] ?? 0,
                    'pending_otps' => $stats['pending'] ?? 0,
                    'sms_service' => $this->smsService->isEnabled() ? 'active' : 'inactive',
                    'environment' => $this->pathConfig->isOnline() ? 'online' : 'local'
                ]
            ];
        } catch (Exception $e) {
            error_log("OTP Status Error: " . $e->getMessage());
            return $this->handleApiError();
        }
    }
}
?>