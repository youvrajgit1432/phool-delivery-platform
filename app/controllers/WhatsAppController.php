<?php
require_once __DIR__ . '/../services/SparrowSMSService.php';

class WhatsAppController {
    private $db;
    private $orderModel;
    private $cartModel;
    private $customerModel;
    private $otpModel;
    private $phoneVerificationModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->orderModel = new Order($db);
        $this->cartModel = new Cart($db);
        $this->customerModel = new Customer($db);
        $this->otpModel = new OTP($db);
        $this->phoneVerificationModel = new PhoneVerification($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    public function index() {
        try {
            $cart = $this->cartModel->getCart();
            if (empty($cart)) {
                return ['success' => false, 'message' => 'Your cart is empty'];
            }

            // Apply bulk pricing and calculate discounts (same as checkout)
            $cart_with_discounts = $this->applyBulkPricingAndDiscounts($cart);
            
            $customer = null;
            $is_logged_in = false;
            
            if (isset($_SESSION['customer_id'])) {
                $is_logged_in = true;
                $this->customerModel->id = $_SESSION['customer_id'];
                $customer = $this->customerModel->readOne();
            }

            $subtotal = $this->calculateSubtotalWithDiscounts($cart_with_discounts);
            $total_discount = $this->calculateTotalDiscount($cart_with_discounts);
            
            // Get delivery fee based on customer's city or default
            if ($is_logged_in && $customer && !empty($customer['city'])) {
                $delivery_fee = $this->getDeliveryFeeByCity($customer['city']);
            } else {
                $delivery_fee = $this->getDefaultDeliveryFee();
            }
            
            $total = $subtotal + $delivery_fee;

            return [
                'success' => true,
                'customer' => $customer,
                'is_logged_in' => $is_logged_in,
                'cart' => $cart_with_discounts,
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'delivery_fee' => $delivery_fee,
                'total' => $total,
                'default_delivery_date' => date('Y-m-d', strtotime('+1 day'))
            ];
        } catch (Exception $e) {
            error_log("WhatsAppController index error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to load order summary'];
        }
    }

    public function process() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // Check if this is a verification request (Step 1: Verify phone with OTP)
                if (isset($_POST['action']) && $_POST['action'] === 'verify_phone_whatsapp') {
                    return $this->handlePhoneVerificationForWhatsApp();
                }

                // Check if this is a confirm verification request (Step 2: Confirm OTP code)
                if (isset($_POST['action']) && $_POST['action'] === 'confirm_verification_whatsapp') {
                    return $this->handleVerificationConfirmationForWhatsApp();
                }

                // Original flow: Check if verification is required
                $phone = $_POST['phone'] ?? '';
                if (empty($phone)) {
                    return ['success' => false, 'message' => 'Please fill in all required fields'];
                }

                // Check if phone is verified before proceeding with order
                if (!$this->isPhoneVerifiedForWhatsApp($phone)) {
                    return ['success' => false, 'verified' => false, 'message' => 'Phone verification required'];
                }

                $required = ['name', 'phone', 'address', 'city', 'delivery_date', 'delivery_time'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        return ['success' => false, 'message' => 'Please fill in all required fields'];
                    }
                }

                $cart = $this->cartModel->getCart();
                if (empty($cart)) {
                    return ['success' => false, 'message' => 'Your cart is empty'];
                }

                // Apply bulk pricing and discounts
                $cart_with_discounts = $this->applyBulkPricingAndDiscounts($cart);
                $subtotal = $this->calculateSubtotalWithDiscounts($cart_with_discounts);
                $total_discount = $this->calculateTotalDiscount($cart_with_discounts);

                $customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : $this->handleGuestCustomer($_POST);
                if (!$customer_id) {
                    return ['success' => false, 'message' => 'Failed to process customer information'];
                }

                // Get delivery fee based on selected city
                $delivery_fee = $this->getDeliveryFeeByCity($_POST['city']);
                $total = $subtotal + $delivery_fee;

                $order_data = [
                    'customer_id' => $customer_id,
                    'quantity' => array_sum(array_column($cart, 'quantity')),
                    'rate' => $subtotal,
                    'total_discount' => $total_discount,
                    'total_amount' => $total,
                    'payment_method' => 'cod', // WhatsApp orders default to COD
                    'payment_screenshot' => '',
                    'delivery_date' => $this->calculateDeliveryDateTime($_POST['delivery_date'], $_POST['delivery_time']),
                    'notes' => $_POST['notes'] ?? '',
                    'items' => $cart_with_discounts,
                    'order_type' => 'whatsapp' // Mark as WhatsApp order
                ];

                $order_id = $this->orderModel->createWhatsAppOrder($order_data);
                if ($order_id) {
                    $this->cartModel->clearCart();
                    
                    // Create order confirmation message
                    $messageModel = new Message($this->db);
                    $discount_message = $total_discount > 0 ? " You saved Rs. " . number_format($total_discount, 2) . " with discounts." : "";
                    
                    $messageModel->create(
                        $customer_id,
                        'WhatsApp Order Confirmed #' . $order_id,
                        'Your WhatsApp order has been placed successfully. Order total: Rs. ' . number_format($total, 2) . '.' . $discount_message . ' Payment will be collected on delivery.',
                        'order',
                        $order_id
                    );

                    // Generate WhatsApp message
                    $whatsapp_message = $this->generateWhatsAppMessage($order_id, $_POST, $cart_with_discounts, $total);
                    
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'whatsapp_message' => $whatsapp_message,
                        'whatsapp_url' => $this->generateWhatsAppUrl($whatsapp_message),
                        'message' => 'Order placed successfully via WhatsApp!'
                    ];
                } else {
                    return ['success' => false, 'message' => 'Failed to create order'];
                }
            }
        } catch (Exception $e) {
            error_log("Error processing WhatsApp order: " . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to process your order'];
        }
    }

    /**
     * 🔒 Handle phone verification for WhatsApp orders
     * Step 1: Send OTP to phone number
     */
    private function handlePhoneVerificationForWhatsApp() {
        try {
            $phone = $_POST['phone'] ?? '';

            if (empty($phone)) {
                return ['success' => false, 'message' => 'Phone number is required'];
            }

            // Validate phone format
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                return ['success' => false, 'message' => 'Please enter a valid 10-digit phone number'];
            }

            // Check if we can resend code
            if (!$this->phoneVerificationModel->canResendCode($phone)) {
                return ['success' => false, 'message' => 'Please wait 60 seconds before requesting a new code'];
            }

            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(1, 999999));

            // Create verification record
            if ($this->phoneVerificationModel->createVerification($phone, $verification_code)) {
                // Use SMS Service instead of OTP model
                $smsService = new SparrowSMSService($this->db);
                $sms_result = $smsService->sendOTP($phone, $verification_code);
                
                if (isset($sms_result['success']) && $sms_result['success'] === true) {
                    error_log("✅ WhatsApp verification OTP sent successfully to: " . $phone);
                    return [
                        'success' => true,
                        'message' => 'Verification code sent to your phone',
                        'phone' => $phone
                    ];
                } else {
                    error_log("❌ WhatsApp verification OTP send failed: " . ($sms_result['error'] ?? 'Unknown error'));
                    return ['success' => false, 'message' => $sms_result['error'] ?? 'Failed to send verification code'];
                }
            } else {
                error_log("❌ Failed to create verification record for: " . $phone);
                return ['success' => false, 'message' => 'Failed to process verification request'];
            }
        } catch (Exception $e) {
            error_log("❌ WhatsApp verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    /**
     * 🔒 Handle verification confirmation for WhatsApp orders
     * Step 2: Verify OTP code and mark phone as verified
     */
    private function handleVerificationConfirmationForWhatsApp() {
        try {
            $phone = $_POST['phone'] ?? '';
            $code = $_POST['code'] ?? '';

            if (empty($phone) || empty($code)) {
                return ['success' => false, 'message' => 'Phone number and verification code are required'];
            }

            // Check attempts limit
            $attempts = $this->phoneVerificationModel->getAttemptsCount($phone);
            if ($attempts >= 5) {
                return ['success' => false, 'message' => 'Too many attempts. Please request a new code'];
            }

            // Verify the code
            if ($this->phoneVerificationModel->verifyCode($phone, $code)) {
                // Update customer verification status
                $this->updateCustomerVerificationStatusForWhatsApp($phone);
                
                // Store in session for this page load
                $_SESSION['whatsapp_verified_phone'] = $phone;
                $_SESSION['whatsapp_phone_verified_at'] = date('Y-m-d H:i:s');

                error_log("WhatsApp phone verified successfully: " . $phone);
                
                return [
                    'success' => true,
                    'verified' => true,
                    'message' => 'Phone verified successfully! You can now place your order.',
                    'phone' => $phone
                ];
            } else {
                $remaining_attempts = 5 - ($attempts + 1);
                $message = $remaining_attempts > 0 
                    ? "Invalid code. {$remaining_attempts} attempts remaining" 
                    : "Too many failed attempts. Please request a new code";

                return ['success' => false, 'message' => $message];
            }
        } catch (Exception $e) {
            error_log("WhatsApp verify code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed. Please try again.'];
        }
    }

    /**
     * 🔒 Check if phone is verified for WhatsApp orders
     */
    private function isPhoneVerifiedForWhatsApp($phone) {
        try {
            // Check if recently verified in this session for WhatsApp
            if (isset($_SESSION['whatsapp_verified_phone']) && $_SESSION['whatsapp_verified_phone'] === $phone) {
                return true;
            }

            // Check if phone is verified in database
            $customerModel = new Customer($this->db);
            $customer = $customerModel->findByPhone($phone);

            if ($customer && $customer['verification_status'] === 'verified') {
                $_SESSION['whatsapp_verified_phone'] = $phone;
                $_SESSION['whatsapp_phone_verified_at'] = $customer['phone_verified_at'] ?? date('Y-m-d H:i:s');
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("WhatsApp phone verification check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔒 Update customer verification status for WhatsApp orders
     */
    private function updateCustomerVerificationStatusForWhatsApp($phone) {
        try {
            // First, check if customer exists
            $customerModel = new Customer($this->db);
            $customer = $customerModel->findByPhone($phone);
            
            if ($customer && $customer['verification_status'] !== 'verified') {
                // Update verification-related fields
                $updateQuery = "UPDATE customers 
                               SET verification_status = 'verified',
                                   phone_verified_at = NOW(),
                                   verified_at = NOW()
                               WHERE phone = :phone";
                
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bindParam(":phone", $phone);
                
                if ($stmt->execute()) {
                    error_log("Customer verification status updated for WhatsApp: " . $phone);
                    return true;
                }
            } else if ($customer) {
                // Already verified - just store in session
                $_SESSION['whatsapp_verified_phone'] = $phone;
                $_SESSION['whatsapp_phone_verified_at'] = date('Y-m-d H:i:s');
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("WhatsApp update customer verification error: " . $e->getMessage());
            return false;
        }
    }

    private function generateWhatsAppMessage($order_id, $customer_data, $cart, $total) {
        $message = "🛍️ *New Order Request* 🛍️\n\n";
        $message .= "*Order ID:* #" . $order_id . "\n";
        $message .= "*Customer Details:*\n";
        $message .= "👤 Name: " . $customer_data['name'] . "\n";
        $message .= "📞 Phone: " . $customer_data['phone'] . "\n";
        
        if (!empty($customer_data['email'])) {
            $message .= "📧 Email: " . $customer_data['email'] . "\n";
        }
        
        $message .= "📍 Address: " . $customer_data['address'] . ", " . $customer_data['city'] . "\n";
        $message .= "🗓️ Delivery Date: " . $customer_data['delivery_date'] . " (" . $customer_data['delivery_time'] . ")\n\n";
        
        $message .= "*Order Items:*\n";
        foreach ($cart as $item) {
            $message .= "• " . $item['name'] . " - " . $item['quantity'] . " " . $item['unit'] . " × Rs. " . number_format($item['final_price'], 2) . " = Rs. " . number_format($item['item_total'], 2) . "\n";
        }
        
        $message .= "\n*Order Summary:*\n";
        $message .= "Subtotal: Rs. " . number_format($this->calculateSubtotalWithDiscounts($cart), 2) . "\n";
        $message .= "Delivery Fee: Rs. " . number_format($this->getDeliveryFeeByCity($customer_data['city']), 2) . "\n";
        $message .= "💵 *Total Amount: Rs. " . number_format($total, 2) . "*\n\n";
        
        if (!empty($customer_data['notes'])) {
            $message .= "*Special Instructions:*\n" . $customer_data['notes'] . "\n\n";
        }
        
        $message .= "Payment Method: 💰 Cash on Delivery\n";
        $message .= "Order Type: 📱 WhatsApp Order\n\n";
        $message .= "Please confirm this order and provide delivery timing.";

        return urlencode($message);
    }

    private function generateWhatsAppUrl($message) {
        $phone = "9803962360"; // Your WhatsApp business number
        return "https://wa.me/{$phone}?text={$message}";
    }

    // Reuse existing methods from CheckoutController
    private function applyBulkPricingAndDiscounts($cart) {
        // Same implementation as in CheckoutController
        $checkoutController = new CheckoutController($this->db);
        return $checkoutController->applyBulkPricingAndDiscounts($cart);
    }

    private function calculateSubtotalWithDiscounts($cart) {
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['item_total'];
        }
        return $subtotal;
    }

    private function calculateTotalDiscount($cart) {
        $total_discount = 0;
        foreach ($cart as $item) {
            $total_discount += $item['total_saving'];
        }
        return $total_discount;
    }

    private function getDeliveryFeeByCity($city) {
        // Same implementation as in CheckoutController
        $checkoutController = new CheckoutController($this->db);
        return $checkoutController->getDeliveryFeeByCity($city);
    }

    private function getDefaultDeliveryFee() {
        // Same implementation as in CheckoutController
        $checkoutController = new CheckoutController($this->db);
        return $checkoutController->getDefaultDeliveryFee();
    }

    private function handleGuestCustomer($data) {
        // Same implementation as in CheckoutController
        $checkoutController = new CheckoutController($this->db);
        return $checkoutController->handleGuestCustomer($data);
    }

    private function calculateDeliveryDateTime($delivery_date, $flower_time) {
        // Same implementation as in CheckoutController
        $checkoutController = new CheckoutController($this->db);
        return $checkoutController->calculateDeliveryDateTime($delivery_date, $flower_time);
    }
}
?>