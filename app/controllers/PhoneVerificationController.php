<?php
// app/controllers/PhoneVerificationController.php

class PhoneVerificationController {
    private $db;
    private $phoneVerificationModel;
    private $otpModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->phoneVerificationModel = new PhoneVerification($db);
        $this->otpModel = new OTP($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    public function sendVerificationCode() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                    return;
                }

                // Validate phone format
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number']);
                    return;
                }

                // Check if we can resend code
                if (!$this->phoneVerificationModel->canResendCode($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Please wait before requesting a new code']);
                    return;
                }

                // Generate verification code
                $verification_code = sprintf("%06d", mt_rand(1, 999999));

                // Create verification record
                if ($this->phoneVerificationModel->createVerification($phone, $verification_code)) {
                    // Send SMS via OTP model (reuse existing functionality)
                    $sms_sent = $this->otpModel->sendOTP($phone, $verification_code);
                    
                    if ($sms_sent) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Verification code sent to your phone'
                        ]);
                    } else {
                        // Don't expose the actual code in production
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Verification code generated. Please check your phone.'
                        ]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to process verification request']);
                }
            }
        } catch (Exception $e) {
            error_log("Send verification error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
    }

    public function verifyCode() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';
                $code = $input['code'] ?? '';

                if (empty($phone) || empty($code)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number and verification code are required']);
                    return;
                }

                // Check attempts limit
                $attempts = $this->phoneVerificationModel->getAttemptsCount($phone);
                if ($attempts >= 5) {
                    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please request a new code']);
                    return;
                }

                if ($this->phoneVerificationModel->verifyCode($phone, $code)) {
                    // Update customer verification status
                    $this->updateCustomerVerificationStatus($phone);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Phone number verified successfully'
                    ]);
                } else {
                    $remaining_attempts = 5 - ($attempts + 1);
                    $message = $remaining_attempts > 0 
                        ? "Invalid verification code. {$remaining_attempts} attempts remaining."
                        : "Too many failed attempts. Please request a new code.";

                    echo json_encode(['success' => false, 'message' => $message]);
                }
            }
        } catch (Exception $e) {
            error_log("Verify code error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
        }
    }

    public function resendCode() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                    return;
                }

                // Check if we can resend code
                if (!$this->phoneVerificationModel->canResendCode($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Please wait before requesting a new code']);
                    return;
                }

                // Generate new verification code
                $verification_code = sprintf("%06d", mt_rand(1, 999999));

                // Create new verification record
                if ($this->phoneVerificationModel->createVerification($phone, $verification_code)) {
                    // Send SMS via OTP model
                    $sms_sent = $this->otpModel->sendOTP($phone, $verification_code);
                    
                    if ($sms_sent) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'New verification code sent to your phone'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'New verification code sent. Please check your phone.'
                        ]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send verification code']);
                }
            }
        } catch (Exception $e) {
            error_log("Resend code error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to resend code. Please try again.']);
        }
    }

    /**
     * Store a guest user's verified phone number in the database
     * Called after phone verification is complete
     */
    public function storeGuestVerifiedPhone() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                    return;
                }

                // Validate phone format
                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid phone format']);
                    return;
                }

                // Check if guest user already exists with this phone
                $customerModel = new Customer($this->db);
                $existingCustomer = $customerModel->findByPhone($phone);

                if ($existingCustomer) {
                    // Update existing guest user's verification status
                    $updateQuery = "UPDATE customers 
                                   SET verification_status = 'verified', 
                                       phone_verified_at = NOW(),
                                       updated_at = NOW()
                                   WHERE phone = :phone";
                    
                    $stmt = $this->db->prepare($updateQuery);
                    $stmt->bindParam(":phone", $phone);
                    
                    if ($stmt->execute()) {
                        error_log("Guest user verification status updated for phone: " . $phone);
                        
                        // Also store in session
                        $_SESSION['verified_phone'] = $phone;
                        $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Guest phone stored in database',
                            'customer_id' => $existingCustomer['id']
                        ]);
                    } else {
                        error_log("Failed to update guest user verification");
                        echo json_encode(['success' => false, 'message' => 'Failed to update verification status']);
                    }
                } else {
                    // No existing customer yet - will be created during registration
                    // Just store in session for now
                    $_SESSION['verified_phone'] = $phone;
                    $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
                    
                    error_log("Guest phone stored in session for phone: " . $phone);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Guest phone stored in session',
                        'customer_id' => null
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            error_log("Store guest verified phone error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error storing phone']);
        }
    }

    /**
     * Check if a phone number is already verified in the system
     * Used for guest users to skip OTP if phone is already verified
     */
    public function checkPhoneVerified() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'verified' => false, 'exists' => false, 'message' => 'Phone number is required']);
                    return;
                }

                // Check in customers table if this phone is verified
                $customerModel = new Customer($this->db);
                $customer = $customerModel->findByPhone($phone);

                if ($customer && $customer['verification_status'] === 'verified') {
                    // Phone is verified - store in session and return success
                    $_SESSION['verified_phone'] = $phone;
                    $_SESSION['phone_verified_at'] = $customer['phone_verified_at'] ?? date('Y-m-d H:i:s');
                    
                    error_log("Phone already verified: " . $phone);
                    
                    echo json_encode([
                        'success' => true, 
                        'verified' => true,
                        'exists' => true,
                        'message' => 'Phone number is already verified',
                        'phone_verified_at' => $customer['phone_verified_at'] ?? null
                    ]);
                } elseif ($customer) {
                    // Phone EXISTS in database but NOT VERIFIED - SECURITY RISK
                    // User must verify this phone with OTP before accessing account
                    error_log("Phone exists but not verified (SECURITY): " . $phone);
                    
                    echo json_encode([
                        'success' => true, 
                        'verified' => false,
                        'exists' => true,
                        'message' => 'Phone number exists in database but needs verification for security',
                        'security_notice' => 'This phone number is already registered. Please verify it with OTP to proceed.'
                    ]);
                } else {
                    // Phone doesn't exist - new guest registration
                    echo json_encode([
                        'success' => true, 
                        'verified' => false,
                        'exists' => false,
                        'message' => 'Phone number is new, proceed with verification'
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'verified' => false, 'exists' => false, 'message' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            error_log("Check phone verified error: " . $e->getMessage());
            echo json_encode(['success' => false, 'verified' => false, 'exists' => false, 'message' => 'Error checking phone verification']);
        }
    }

    public function getVerificationStatus($phone) {
        try {
            $verification = $this->phoneVerificationModel->getPendingVerification($phone);
            
            if ($verification) {
                return [
                    'has_pending_verification' => true,
                    'attempts' => $verification['attempts'],
                    'expires_at' => $verification['expires_at']
                ];
            }

            return ['has_pending_verification' => false];
        } catch (Exception $e) {
            error_log("Get verification status error: " . $e->getMessage());
            return ['has_pending_verification' => false];
        }
    }

    /**
     * FIXED: Properly update customer verification status after phone verification
     */
    private function updateCustomerVerificationStatus($phone) {
        try {
            // First, get the customer details
            $customerModel = new Customer($this->db);
            $customer = $customerModel->findByPhone($phone);
            
            if ($customer && $customer['verification_status'] !== 'verified') {
                // Update all verification-related fields
                $updateQuery = "UPDATE customers 
                               SET verification_status = 'verified', 
                                   phone_verified_at = NOW(),
                                   updated_at = NOW(),
                                   sms_verification_code = NULL,
                                   sms_verification_expires = NULL
                               WHERE phone = :phone";
                
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bindParam(":phone", $phone);
                
                if ($stmt->execute()) {
                    error_log("Customer verification status updated for phone: " . $phone);
                    
                    // ✅ NEW: Store verified phone in session for all users (logged in or guest)
                    $_SESSION['verified_phone'] = $phone;
                    $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
                    
                    // Also update session if customer is logged in
                    if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == $customer['id']) {
                        $_SESSION['verification_status'] = 'verified';
                    }
                } else {
                    error_log("Failed to update customer verification status for phone: " . $phone);
                }
            } else if ($customer) {
                // ✅ NEW: Even if already verified, store in session for guest users
                $_SESSION['verified_phone'] = $phone;
                $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            error_log("Error updating customer verification status: " . $e->getMessage());
        }
    }
}
?>