<?php
require_once __DIR__ . '/../helpers/cache.php';
require_once __DIR__ . '/../services/SparrowSMSService.php';

class CheckoutController {
    private $db, $customerModel, $orderModel, $cartModel, $otpModel;
    private $pathConfig;
    private $persistentLogin;

    public function __construct($db) {
        $this->db = $db;
        $this->customerModel = new Customer($db);
        $this->orderModel = new Order($db);
        $this->cartModel = new Cart($db);
        $this->otpModel = new OTP($db);
        $this->pathConfig = PathConfig::getInstance();
        $this->persistentLogin = new PersistentLogin($db);
    }

    public function index() {
        try {
            $is_quick = (isset($_GET['quick']) && $_GET['quick'] == '1') || (!empty($_SESSION['from_direct_checkout']));

            $cart = $this->cartModel->getCart();
            if (empty($cart)) {
                header('Location: ' . $this->pathConfig->url('cart'));
                exit;
            }

            // Quick path: avoid heavy calculations and DB queries for fast initial render
            if ($is_quick) {
                // FIXED: Apply bulk pricing and discounts even in quick mode to ensure price is displayed
                $cart_with_discounts = $this->applyBulkPricingAndDiscounts($cart);
                
                // basic subtotal calculation with proper final_price from discounts
                $subtotal = 0;
                foreach ($cart_with_discounts as $item) {
                    $price = isset($item['final_price']) ? $item['final_price'] : ($item['price'] ?? 0);
                    $subtotal += floatval($price) * intval($item['quantity']);
                }

                $delivery_fee = $this->getDefaultDeliveryFee();
                $total = $subtotal + $delivery_fee;

                // clear the session quick flag so subsequent loads are full
                unset($_SESSION['from_direct_checkout']);

                // CRITICAL FIX: For logged-in users, fetch customer data even in quick mode
                // (required to pre-fill form fields with readonly values)
                $customer = null;
                $is_logged_in = false;
                if (isset($_SESSION['customer_id'])) {
                    $is_logged_in = true;
                    $this->customerModel->id = $_SESSION['customer_id'];
                    $customer = $this->customerModel->readOne();
                    if ($customer === false) {
                        unset($_SESSION['customer_id'], $_SESSION['customer_name']);
                        $is_logged_in = false;
                    }
                }

                return [
                    'customer' => $customer,
                    'is_logged_in' => $is_logged_in,
                    'verification_warning' => false,
                    'cart' => $cart_with_discounts,
                    'subtotal' => $subtotal,
                    'total_discount' => 0,
                    'delivery_fee' => $delivery_fee,
                    'total' => $total,
                    'default_delivery_date' => date('Y-m-d', strtotime('+1 day')),
                    'wallet_qrs' => [],
                    'page_title' => 'Checkout - Phool Delivery',
                    'quick_load' => true
                ];
            }

            // Full path: previous heavy logic
            // Apply bulk pricing and calculate discounts
            $cart_with_discounts = $this->applyBulkPricingAndDiscounts($cart);
            
            $customer = null;
            $is_logged_in = false;
            $verification_warning = false;
            
            // Only check verification if user is logged in
            if (isset($_SESSION['customer_id'])) {
                $is_logged_in = true;
                $this->customerModel->id = $_SESSION['customer_id'];
                $customer = $this->customerModel->readOne();
                
                if ($customer === false) {
                    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
                    $is_logged_in = false;
                } else {
                    // Only show warning if phone is not verified
                    $is_phone_verified = $this->isPhoneVerified($customer);
                    $verification_warning = !$is_phone_verified;
                }
            }

            // Use discounted subtotal
            $subtotal = $this->calculateSubtotalWithDiscounts($cart_with_discounts);
            $total_discount = $this->calculateTotalDiscount($cart_with_discounts);
            
            // Get delivery fee based on customer's city or default
            if ($is_logged_in && $customer && !empty($customer['city'])) {
                $delivery_fee = $this->getDeliveryFeeByCity($customer['city']);
            } else {
                $delivery_fee = $this->getDefaultDeliveryFee();
            }
            
            $total = $subtotal + $delivery_fee;

            // Get all active wallet QR codes
            $wallet_qrs = $this->getAllWalletQrs();

            return [
                'customer' => $customer,
                'is_logged_in' => $is_logged_in,
                'verification_warning' => $verification_warning,
                'cart' => $cart_with_discounts,
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'delivery_fee' => $delivery_fee,
                'total' => $total,
                'default_delivery_date' => date('Y-m-d', strtotime('+1 day')),
                'wallet_qrs' => $wallet_qrs,
                'page_title' => 'Checkout - Phool Delivery'
            ];
        } catch (Exception $e) {
            error_log("CheckoutController index error: " . $e->getMessage());
            // Return safe error response without sensitive details
            return [
                'customer' => null,
                'is_logged_in' => false,
                'verification_warning' => false,
                'cart' => [],
                'subtotal' => 0,
                'total_discount' => 0,
                'delivery_fee' => 0,
                'total' => 0,
                'default_delivery_date' => date('Y-m-d', strtotime('+1 day')),
                'wallet_qrs' => [],
                'page_title' => 'Checkout - Phool Delivery'
            ];
        }
    }

    public function process() {
        try {
            error_log("=== CHECKOUT PROCESS STARTED ===");
            error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
            error_log("SESSION ID: " . (isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : 'NOT SET'));
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // 🔒 Handle WhatsApp phone verification (Step 1: Send OTP)
                if (isset($_POST['action']) && $_POST['action'] === 'verify_phone_whatsapp') {
                    $phone = $_POST['phone'] ?? '';
                    
                    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
                        return;
                    }

                    // Create OTP verification
                    $verification_code = sprintf("%06d", mt_rand(1, 999999));
                    
                    try {
                        $phoneVerificationModel = new PhoneVerification($this->db);
                        if ($phoneVerificationModel->canResendCode($phone) && $phoneVerificationModel->createVerification($phone, $verification_code)) {
                            // Use SMS Service instead of OTP model
                            $smsService = new SparrowSMSService($this->db);
                            $sms_result = $smsService->sendOTP($phone, $verification_code);
                            
                            if (isset($sms_result['success']) && $sms_result['success'] === true) {
                                error_log("✅ WhatsApp verification OTP sent successfully to: " . $phone);
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Verification code sent to your phone',
                                    'phone' => $phone
                                ]);
                            } else {
                                error_log("❌ WhatsApp verification OTP send failed for: " . $phone . " - Error: " . ($sms_result['error'] ?? 'Unknown error'));
                                echo json_encode(['success' => false, 'message' => $sms_result['error'] ?? 'Failed to send verification code']);
                            }
                        } else {
                            error_log("⏳ WhatsApp OTP resend cooldown active for: " . $phone);
                            echo json_encode(['success' => false, 'message' => 'Please wait 60 seconds before requesting a new code']);
                        }
                    } catch (Exception $e) {
                        error_log("❌ WhatsApp OTP send error: " . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                    }
                    return;
                }

                // 🔒 Handle WhatsApp phone verification confirmation (Step 2: Verify OTP)
                if (isset($_POST['action']) && $_POST['action'] === 'confirm_verification_whatsapp') {
                    $phone = $_POST['phone'] ?? '';
                    $code = $_POST['code'] ?? '';
                    
                    if (empty($phone) || empty($code)) {
                        echo json_encode(['success' => false, 'message' => 'Phone and code are required']);
                        return;
                    }

                    try {
                        $phoneVerificationModel = new PhoneVerification($this->db);
                        
                        // Check attempts
                        if ($phoneVerificationModel->getAttemptsCount($phone) >= 5) {
                            echo json_encode(['success' => false, 'message' => 'Too many attempts. Please request a new code']);
                            return;
                        }

                        if ($phoneVerificationModel->verifyCode($phone, $code)) {
                            // Update customer verification status
                            $customerModel = new Customer($this->db);
                            $customer = $customerModel->findByPhone($phone);
                            
                            if ($customer) {
                                $updateQuery = "UPDATE customers 
                                               SET verification_status = 'verified',
                                                   phone_verified_at = NOW(),
                                                   verified_at = NOW()
                                               WHERE phone = :phone";
                                
                                $stmt = $this->db->prepare($updateQuery);
                                $stmt->bindParam(":phone", $phone);
                                $stmt->execute();
                                
                                error_log("WhatsApp phone verified: " . $phone);
                            }

                            $_SESSION['whatsapp_verified_phone'] = $phone;
                            $_SESSION['whatsapp_phone_verified_at'] = date('Y-m-d H:i:s');

                            echo json_encode([
                                'success' => true,
                                'verified' => true,
                                'message' => 'Phone verified successfully'
                            ]);
                        } else {
                            $remaining_attempts = 5 - ($phoneVerificationModel->getAttemptsCount($phone) + 1);
                            echo json_encode([
                                'success' => false,
                                'message' => "Invalid code. {$remaining_attempts} attempts remaining"
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log("WhatsApp verification error: " . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Verification failed']);
                    }
                    return;
                }
                error_log("POST data received. Keys: " . implode(', ', array_keys($_POST)));
                
                // Get customer ID (logged in or guest)
                $customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
                error_log("Initial customer_id: " . ($customer_id ? $customer_id : 'NULL'));
                
                // Note: Phone verification check removed to allow logged-in users to place orders
                // Phone verification can be enforced later in fulfillment/delivery stage if needed

                // FIX: For logged-in users, name and phone are readonly so they come from session
                // For guests, we need them from POST
                $is_logged_in_user = !empty($_SESSION['customer_id']);
                error_log("Is logged-in user: " . ($is_logged_in_user ? 'YES' : 'NO'));
                
                if ($is_logged_in_user) {
                    // For logged-in users, use session data for name and phone
                    $_POST['name'] = $_SESSION['customer_name'] ?? $_POST['name'] ?? '';
                    $_POST['phone'] = $_SESSION['customer_phone'] ?? $_POST['phone'] ?? '';
                    $_POST['email'] = $_SESSION['customer_email'] ?? $_POST['email'] ?? '';
                    
                    error_log("Logged-in user data - Name: " . $_POST['name'] . ", Phone: " . $_POST['phone']);
                    
                    // Always auto-check terms for logged-in users (they have hidden terms input)
                    $_POST['terms'] = 1;
                    error_log("Terms auto-set to 1 for logged-in user");
                }

                // Terms checkbox required for all users (both logged-in and guests)
                $required = ['name', 'phone', 'address', 'city', 'payment_method', 'delivery_date', 'delivery_time', 'terms'];
                
                error_log("Checking required fields: " . implode(', ', $required));
                foreach ($required as $field) {
                    $value = $_POST[$field] ?? '';
                    error_log("  Field '{$field}': " . (empty($value) ? "EMPTY ❌" : "OK ✓ (value: {$value})"));
                    
                    if (empty($_POST[$field])) {
                        $error_msg = match($field) {
                            'name' => 'Name is required',
                            'phone' => 'Phone number is required',
                            'address' => 'Address is required',
                            'city' => 'City is required',
                            'payment_method' => 'Payment method is required',
                            'delivery_date' => 'Delivery date is required',
                            'delivery_time' => 'Delivery time is required',
                            'terms' => 'You must agree to terms and conditions',
                            default => 'Field is required: ' . $field
                        };
                        error_log("❌ VALIDATION ERROR: {$error_msg}");
                        error_log("POST dump: " . json_encode($_POST));
                        echo json_encode(['success' => false, 'message' => $error_msg]);
                        return;
                    }
                }
                
                error_log("✓ All required fields validated successfully");

                // ⚠️ SECURITY CHECK: For guest/logged-out users, REQUIRE OTP verification if phone already exists
                // This protects existing accounts from unauthorized access
                // ANY guest trying to use an existing phone number MUST verify ownership via OTP first
                // ✅ BUT: Skip this check if security_verified flag is set (OTP already verified in Step 4.5)
                if (!$is_logged_in_user) {
                    $phone = $_POST['phone'] ?? '';
                    $security_verified = isset($_POST['security_verified']) && $_POST['security_verified'] === 'true';
                    
                    error_log("========== SECURITY CHECK START ==========");
                    error_log("Phone: {$phone}");
                    error_log("Security_verified POST data: " . (isset($_POST['security_verified']) ? $_POST['security_verified'] : 'NOT SET'));
                    error_log("Is security_verified flag true? " . ($security_verified ? 'YES ✓' : 'NO ❌'));
                    
                    // ✅ CRITICAL FIX: Check flag FIRST before doing phone validation
                    if ($security_verified) {
                        // OTP verification already completed in Step 4.5 - skip phone check entirely
                        error_log("✓✓✓ SECURITY VERIFIED: OTP already verified in Step 4.5 - SKIPPING phone check ✓✓✓");
                        error_log("========== SECURITY CHECK SKIPPED (FLAG SET) ==========");
                    } else {
                        // NO security verification flag - must check if phone exists
                        error_log("✗ No security_verified flag - checking if phone exists in database...");
                        
                        // Check if this phone already exists in database
                        $existingCustomer = $this->customerModel->findByPhone($phone);
                        
                        if ($existingCustomer) {
                            // ⚠️ SECURITY: Phone belongs to an EXISTING account
                            // Require OTP verification to prove ownership
                            error_log("⚠️ SECURITY BLOCK: Phone {$phone} already exists in database (Customer ID: {$existingCustomer['id']})");
                            error_log("SECURITY: Existing account detected - REQUIRING OTP verification for security");
                            error_log("========== SECURITY CHECK FAILED (PHONE EXISTS) ==========");
                            
                            echo json_encode([
                                'success' => false,
                                'message' => 'This phone number is already registered in our system. For security protection, you must verify it with OTP to prove you own this account. Please enter the OTP we\'ll send to your phone.',
                                'security_block' => true,
                                'phone' => $phone,
                                'existing_account' => true,
                                'requires_otp_verification' => true
                            ]);
                            return;
                        } else {
                            // ✅ New phone (not in database) - safe to proceed
                            error_log("✓ SECURITY: Phone is new, safe to proceed with guest registration");
                            error_log("========== SECURITY CHECK PASSED (NEW PHONE) ==========");
                        }
                    }
                }

                $payment_method = $_POST['payment_method'];
                $payment_screenshot = '';
                $order_type = $_POST['order_type'] ?? 'website'; // Default to website
                
                error_log("Payment method: {$payment_method}, Order type: {$order_type}");
                
                if ($payment_method !== 'cod') {
                    error_log("Non-COD payment - checking for screenshot");
                    
                    if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_NO_FILE) {
                        error_log("❌ Payment screenshot missing for non-COD payment");
                        echo json_encode(['success' => false, 'message' => 'Payment screenshot is required for digital payments']);
                        return;
                    }
                    
                    $upload_result = $this->uploadPaymentScreenshot($_FILES['payment_screenshot']);
                    if (!$upload_result['success']) {
                        error_log("❌ Screenshot upload failed: " . $upload_result['message']);
                        echo json_encode(['success' => false, 'message' => $upload_result['message']]);
                        return;
                    }
                    
                    $payment_screenshot = $upload_result['file_name'];
                    error_log("✓ Screenshot uploaded: {$payment_screenshot}");
                }

                $cart = $this->cartModel->getCart();
                error_log("Cart items: " . count($cart));
                
                if (empty($cart)) {
                    error_log("❌ Cart is empty");
                    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
                    return;
                }
                
                error_log("✓ Cart is not empty");

                // Use submitted discount values from form (calculated on checkout page)
                // These values were pre-calculated and sent as hidden form fields for accuracy
                $submitted_applied_discount = floatval($_POST['applied_discount'] ?? 0);
                $submitted_final_amount = floatval($_POST['final_amount'] ?? 0);
                $submitted_subtotal = floatval($_POST['subtotal'] ?? 0);
                $submitted_bulk_savings = floatval($_POST['bulk_savings'] ?? 0);
                $submitted_product_offers_discount = floatval($_POST['product_offers_discount'] ?? 0);
                $submitted_event_discount = floatval($_POST['event_discount'] ?? 0);
                
                error_log("Submitted discount values - Applied: {$submitted_applied_discount}, Final: {$submitted_final_amount}, Subtotal: {$submitted_subtotal}");

                // Apply bulk pricing and discounts for final calculation (for cart totals validation)
                $cart_with_discounts = $this->applyBulkPricingAndDiscounts($cart);
                $subtotal = $this->calculateSubtotalWithDiscounts($cart_with_discounts);
                $total_discount = $this->calculateTotalDiscount($cart_with_discounts);
                
                error_log("Recalculated values - Subtotal: {$subtotal}, Discount: {$total_discount}");
                
                // Use submitted values if available (from checkout page), otherwise use recalculated
                $applied_discount = ($submitted_applied_discount > 0) ? $submitted_applied_discount : $total_discount;
                $final_amount_calculated = ($submitted_final_amount > 0) ? $submitted_final_amount : $subtotal - $total_discount;


                // FIX: For logged-in users, customer_id is already set; for guests, handle registration
                if (!$is_logged_in_user) {
                    error_log("Handling guest customer...");
                    $customer_id = $this->handleGuestCustomer($_POST);
                    
                    if (!$customer_id) {
                        error_log("❌ Failed to create guest customer");
                        echo json_encode(['success' => false, 'message' => 'Failed to process customer information']);
                        return;
                    }
                    
                    error_log("✓ Guest customer created with ID: {$customer_id}");
                    
                    $_SESSION['customer_id'] = $customer_id;
                    $_SESSION['customer_name'] = $_POST['name'];
                    $_SESSION['customer_email'] = $_POST['email'] ?? '';
                    $_SESSION['customer_phone'] = $_POST['phone'];
                    
                    // AUTO-INSERT: For guest checkout, transfer any pending device registration
                    if (class_exists('NotificationController')) {
                        $notificationController = new NotificationController($this->db);
                        $notificationController->transferSessionPreferencesToUser($customer_id, session_id());
                    }
                } else {
                    error_log("✓ Using existing logged-in customer ID: {$customer_id}");
                    
                    // IMPORTANT: Do NOT update customer table with checkout form changes
                    // Any changes made in checkout should only apply to the specific order
                    // Logged-in users should manage their profile separately in account settings
                }

                // Get delivery fee based on selected city
                $delivery_fee = $this->getDeliveryFeeByCity($_POST['city']);
                error_log("Delivery fee: {$delivery_fee}");
                
                // Calculate final total with delivery fee using submitted values
                $total = $final_amount_calculated + $delivery_fee;
                error_log("Final total with delivery fee: {$total}");

                // IMPORTANT: Collect order-specific details from form
                // These will be stored in the order but won't modify customer table
                $order_delivery_address = $_POST['address'] ?? '';
                $order_delivery_phone = $_POST['phone'] ?? '';
                $order_secondary_phone = $_POST['contact'] ?? '';
                
                error_log("Order-specific details: Address={$order_delivery_address}, Phone={$order_delivery_phone}, Secondary={$order_secondary_phone}");

                $order_data = [
                    'customer_id' => $customer_id,
                    'quantity' => array_sum(array_column($cart, 'quantity')),
                    'rate' => $subtotal,
                    'total_discount' => $applied_discount,
                    'total_amount' => $total,
                    'payment_method' => $payment_method,
                    'payment_screenshot' => $payment_screenshot,
                    'order_type' => $order_type, // Add order type
                    'delivery_date' => $this->calculateDeliveryDateTime($_POST['delivery_date'], $_POST['delivery_time']),
                    'notes' => $_POST['notes'] ?? '',
                    // Order-specific delivery details (not saved to customer table)
                    'delivery_address' => $order_delivery_address,
                    'delivery_phone' => $order_delivery_phone,
                    'secondary_phone' => $order_secondary_phone,
                    'items' => $cart_with_discounts
                ];
                
                error_log("Attempting to create order with data: " . json_encode([
                    'customer_id' => $order_data['customer_id'],
                    'quantity' => $order_data['quantity'],
                    'rate' => $order_data['rate'],
                    'total_discount' => $order_data['total_discount'],
                    'total_amount' => $order_data['total_amount'],
                    'payment_method' => $order_data['payment_method'],
                    'order_type' => $order_data['order_type'],
                    'delivery_date' => $order_data['delivery_date'],
                    'delivery_address' => $order_data['delivery_address'],
                    'delivery_phone' => $order_data['delivery_phone'],
                    'secondary_phone' => $order_data['secondary_phone'],
                    'items_count' => count($order_data['items'])
                ]));

                $order_id = $this->orderModel->create($order_data);
                
                if ($order_id) {
                    error_log("✓ Order created successfully with ID: {$order_id}");
                    
                    $this->cartModel->clearCart();
                    error_log("✓ Cart cleared");
                    
                    $messageModel = new Message($this->db);
                    $discount_message = $total_discount > 0 ? " You saved Rs. " . number_format($total_discount, 2) . " with discounts." : "";
                    
                    $order_source = $order_type === 'whatsapp' ? 'WhatsApp' : 'Website';
                    
                    $messageModel->create(
                        $customer_id,
                        'Order Confirmed #' . $order_id,
                        'Your order has been placed successfully via ' . $order_source . '. Order total: Rs. ' . number_format($total, 2) . '.' . $discount_message . 
                        ($payment_method == 'cod' ? ' Payment will be collected on delivery.' : ' Your payment is pending verification.'),
                        'order',
                        $order_id
                    );
                    
                    if (isset($_SESSION['customer_id'])) {
                        $_SESSION['message_count'] = (new Message($this->db))->getUnreadCount($_SESSION['customer_id']);
                    }
                    
                    $message = $order_type === 'whatsapp' 
                        ? "Order placed successfully via WhatsApp! Please complete your conversation on WhatsApp." 
                        : "Order placed successfully";
                        
                    if ($payment_method == 'cod') {
                        $message .= $order_type === 'whatsapp' ? '' : ". Payment will be collected on delivery.";
                    } else {
                        $message .= $order_type === 'whatsapp' ? '' : ". Your payment is pending verification.";
                    }
                    
                    if ($total_discount > 0) {
                        $message .= " You saved Rs. " . number_format($total_discount, 2) . "!";
                    }
                    
                    error_log("✓ Order success response ready. Message: {$message}");
                        
                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'order_id' => $order_id,
                        'total_discount' => $total_discount,
                        'order_type' => $order_type,
                        'redirect' => $order_type === 'whatsapp' ? false : $this->pathConfig->url('success?order_id=' . $order_id)
                    ]);
                } else {
                    error_log("❌ Failed to create order. Order model returned false");
                    error_log("Order data dump: " . json_encode($order_data));
                    echo json_encode(['success' => false, 'message' => 'Failed to create order. Please contact support.']);
                }
            }
        } catch (Exception $e) {
            error_log("Error processing checkout: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to process your order']);
        }
    }

    /**
     * Apply bulk pricing and discounts to cart items
     */
    private function applyBulkPricingAndDiscounts($cart) {
        try {
            $updated_cart = [];
            
            foreach ($cart as $item) {
                $product_id = $item['id'];
                $quantity = $item['quantity'];
                $unit = $item['unit'];
                
                // Get product details with bulk price and offers
                $product_details = $this->getProductWithOffers($product_id);
                
                if (!$product_details) {
                    $updated_cart[] = $item;
                    continue;
                }
                
                $original_price = $product_details['price'];
                $bulk_price = $product_details['bulk_price'];
                $bulk_threshold = $this->getBulkThreshold($unit);
                
                $item['original_price'] = $original_price;
                $item['bulk_price'] = $bulk_price;
                $item['bulk_threshold'] = $bulk_threshold;
                $item['is_bulk'] = false;
                $item['bulk_discount'] = 0;
                $item['offer_discount'] = 0;
                $item['discount_reason'] = '';
                
                // Check if quantity qualifies for bulk pricing
                if ($quantity >= $bulk_threshold && $bulk_price !== null && $bulk_price < $original_price) {
                    $item['is_bulk'] = true;
                    $item['final_price'] = $bulk_price;
                    $bulk_saving = ($original_price - $bulk_price) * $quantity;
                    $item['bulk_discount'] = $bulk_saving;
                    $item['discount_reason'] = "Bulk discount applied for {$quantity} {$unit}";
                } else {
                    $item['final_price'] = $original_price;
                }
                
                // Apply product offers if available
                $offer_discount = $this->applyProductOffers($product_id, $quantity, $item['final_price'], $unit);
                if ($offer_discount > 0) {
                    $item['offer_discount'] = $offer_discount;
                    if (!empty($item['discount_reason'])) {
                        $item['discount_reason'] .= " + Offer discount";
                    } else {
                        $item['discount_reason'] = "Special offer discount applied";
                    }
                }
                
                // Calculate final item total
                $item['item_total'] = ($item['final_price'] * $quantity) - $item['offer_discount'];
                $item['total_saving'] = $item['bulk_discount'] + $item['offer_discount'];
                
                $updated_cart[] = $item;
            }
            
            return $updated_cart;
        } catch (Exception $e) {
            error_log("Error applying bulk pricing: " . $e->getMessage());
            return $cart; // Return original cart on error
        }
    }

    /**
     * Get bulk threshold based on unit type
     */
    private function getBulkThreshold($unit) {
        return 10;
    }

    /**
     * Get product details with offers
     */
    private function getProductWithOffers($product_id) {
        try {
            $cacheKey = 'product_offers_' . $product_id;
            $cached = Cache::get($cacheKey);
            if ($cached !== false) return $cached;

            $query = "SELECT p.*, 
                             po.offer_type, po.buy_quantity, po.get_quantity, 
                             po.discount_percentage, po.fixed_discount,
                             se.discount_percentage as event_discount
                      FROM products p
                      LEFT JOIN product_offers po ON p.id = po.product_id 
                          AND po.is_active = 1 
                          AND (po.start_date IS NULL OR po.start_date <= CURDATE())
                          AND (po.end_date IS NULL OR po.end_date >= CURDATE())
                      LEFT JOIN special_events se ON se.is_active = 1 
                          AND CURDATE() BETWEEN se.start_date AND se.end_date
                      WHERE p.id = :product_id AND p.status = 'active'";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            Cache::set($cacheKey, $result, 60);
            return $result;
        } catch (Exception $e) {
            error_log("Error fetching product with offers: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply product offers and calculate discount
     */
    private function applyProductOffers($product_id, $quantity, $current_price, $unit) {
        try {
            $cacheKey = 'product_offer_latest_' . $product_id;
            $offer = Cache::get($cacheKey);
            if ($offer === false) {
                $query = "SELECT * FROM product_offers 
                      WHERE product_id = :product_id 
                      AND is_active = 1 
                      AND (start_date IS NULL OR start_date <= CURDATE())
                      AND (end_date IS NULL OR end_date >= CURDATE())
                      ORDER BY id DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->execute();
                $offer = $stmt->fetch(PDO::FETCH_ASSOC);
                Cache::set($cacheKey, $offer, 60);
            }
            
            if (!$offer) {
                return 0;
            }
            
            $discount = 0;
            
            switch ($offer['offer_type']) {
                case 'buy_x_get_y':
                    if ($quantity >= $offer['buy_quantity'] && $offer['get_quantity'] > 0) {
                        $free_units = floor($quantity / $offer['buy_quantity']) * $offer['get_quantity'];
                        $discount = $free_units * $current_price;
                    }
                    break;
                    
                case 'percentage_discount':
                    if ($quantity >= $offer['buy_quantity']) {
                        $discount = ($current_price * $quantity * $offer['discount_percentage']) / 100;
                    }
                    break;
                    
                case 'fixed_discount':
                    if ($quantity >= $offer['buy_quantity']) {
                        $discount = $offer['fixed_discount'] * $quantity;
                    }
                    break;
            }
            
            return $discount;
            
        } catch (Exception $e) {
            error_log("Error applying product offers: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate subtotal with discounts applied
     */
    private function calculateSubtotalWithDiscounts($cart) {
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['item_total'];
        }
        return $subtotal;
    }

    /**
     * Calculate total discount
     */
    private function calculateTotalDiscount($cart) {
        $total_discount = 0;
        foreach ($cart as $item) {
            $total_discount += $item['total_saving'];
        }
        return $total_discount;
    }

    /**
     * Get delivery fee by city from delivery_cities table
     */
    private function getDeliveryFeeByCity($city) {
        try {
            $cacheKey = 'delivery_fee_city_' . md5($city);
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return (float)$cached;
            }

            $query = "SELECT standard_delivery_fee FROM delivery_cities WHERE city_name = :city_name LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':city_name', $city);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $fee = (float)$row['standard_delivery_fee'];
                Cache::set($cacheKey, $fee, 600);
                return $fee;
            }

            return $this->getDefaultDeliveryFee();
        } catch (Exception $e) {
            error_log("Error fetching delivery fee for city {$city}: " . $e->getMessage());
            return $this->getDefaultDeliveryFee();
        }
    }

    /**
     * Get default delivery fee (fallback)
     */
    private function getDefaultDeliveryFee() {
        try {
            $cacheKey = 'delivery_fee_default';
            $cached = Cache::get($cacheKey);
            if ($cached !== false) return (float)$cached;

            $query = "SELECT standard_delivery_fee FROM delivery_cities ORDER BY id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $fee = (float)$row['standard_delivery_fee'];
                Cache::set($cacheKey, $fee, 600);
                return $fee;
            }

            return 50.00;
        } catch (Exception $e) {
            error_log("Error fetching default delivery fee: " . $e->getMessage());
            return 50.00;
        }
    }

    /**
     * Check if phone is verified using multiple indicators
     */
    private function isPhoneVerified($customer) {
        try {
            // Check multiple verification indicators
            if (isset($customer['verification_status']) && $customer['verification_status'] === 'verified') {
                return true;
            }
            
            if (isset($customer['phone_verified_at']) && !empty($customer['phone_verified_at'])) {
                return true;
            }
            
            if (isset($customer['verified_at']) && !empty($customer['verified_at'])) {
                return true;
            }
            
            // Additional check: if OTP was used for login recently
            if (isset($_SESSION['login_method']) && $_SESSION['login_method'] === 'otp') {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error checking phone verification: " . $e->getMessage());
            return false;
        }
    }

    private function getAllWalletQrs() {
        try {
            $cacheKey = 'wallet_qrs_active';
            $cached = Cache::get($cacheKey);
            if ($cached !== false) return $cached;

            $query = "SELECT wallet_type, qr_image FROM wallet_qrs WHERE status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $wallet_qrs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $wallet_qrs[$row['wallet_type']] = $row['qr_image'];
            }
            Cache::set($cacheKey, $wallet_qrs, 3600);
            return $wallet_qrs;
        } catch (Exception $e) {
            error_log("Error fetching wallet QR codes: " . $e->getMessage());
            return [];
        }
    }

    private function handleGuestCustomer($data) {
        try {
            $existing_customer = $this->customerModel->findByPhone($data['phone']);
            if ($existing_customer) {
                // Check registration type to confirm it's a guest user
                $registrationType = $this->getRegistrationType($existing_customer['id']);
                if ($registrationType === 'guest') {
                    $_SESSION['guest_user_phone'] = $data['phone'];
                    // ✅ Mark phone as verified in session for existing guest user
                    $_SESSION['verified_phone'] = $data['phone'];
                    $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
                    
                    // AUTO-LOGIN: Automatically log in the guest user
                    $this->autoLoginGuestUser($existing_customer);
                }
                return $existing_customer['id'];
            }
            
            $this->customerModel->name = $data['name'];
            $this->customerModel->email = $data['email'] ?? null;
            $this->customerModel->phone = $data['phone'];
            $this->customerModel->address = $data['address'];
            $this->customerModel->city = $data['city'];
            $this->customerModel->contact = $data['contact'] ?? '';
            
            // Use the default password for guest users
            $default_password = 'DemoGuest';
            $this->customerModel->password = password_hash($default_password, PASSWORD_DEFAULT);
            
            // Set registration type as 'guest' for checkout registrations
            $this->customerModel->registration_type = 'guest';
            
            // ✅ NEW: Mark phone as verified since guest users verify their phone before registration
            $this->customerModel->phone_verified_at = date('Y-m-d H:i:s');
            $this->customerModel->verification_status = 'verified';
            
            $customer_id = $this->customerModel->create() ? $this->customerModel->id : false;
            
            if ($customer_id) {
                $_SESSION['guest_user_phone'] = $data['phone'];
                $_SESSION['registration_type'] = 'guest';
                // ✅ Mark phone as verified in session for new guest user
                $_SESSION['verified_phone'] = $data['phone'];
                $_SESSION['phone_verified_at'] = date('Y-m-d H:i:s');
                
                // AUTO-LOGIN: Automatically log in the newly created guest user
                $customer_data = [
                    'id' => $customer_id,
                    'name' => $data['name'],
                    'email' => $data['email'] ?? '',
                    'phone' => $data['phone'],
                    'registration_type' => 'guest'
                ];
                $this->autoLoginGuestUser($customer_data);
            }
            
            return $customer_id;
        } catch (Exception $e) {
            error_log("Error handling guest customer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-login guest user after checkout registration - ENHANCED WITH PREFERENCE FLAGS
     */
    private function autoLoginGuestUser($customer) {
        try {
            // Set session variables
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_email'] = $customer['email'] ?? '';
            $_SESSION['customer_phone'] = $customer['phone'];
            $_SESSION['is_guest_user'] = true;
            $_SESSION['registration_type'] = $customer['registration_type'];
            $_SESSION['login_method'] = 'guest_checkout';
            
            // CRITICAL FIX: Set preference flags for guest users to prevent modal display
            $this->setGuestUserPreferenceFlags($customer);
            
            // Create persistent login for guest users (1 month instead of 1 year)
            $sessionData = [
                'customer_id' => $customer['id'],
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'] ?? '',
                'customer_phone' => $customer['phone'],
                'is_guest_user' => true,
                'registration_type' => $customer['registration_type'],
                // Include preference flags in persistent session
                'language_preference_set' => $_SESSION['language_preference_set'] ?? true,
                'city_preference_set' => $_SESSION['city_preference_set'] ?? true,
                'notification_preference_set' => $_SESSION['notification_preference_set'] ?? true,
                'user_city_id' => $_SESSION['user_city_id'] ?? 2,
                'user_city_name' => $_SESSION['user_city_name'] ?? 'Kathmandu',
                'user_language' => $_SESSION['user_language'] ?? 'en'
            ];
            
            // For guest users, use shorter persistent session (30 days)
            $this->createGuestPersistentLogin($customer['id'], $sessionData);
            
            // AUTO-SWITCH LANGUAGE: Load user's preferred language from database
            if (class_exists('LanguageHelper')) {
                LanguageHelper::autoSwitchLanguageAfterLogin($customer['id']);
            }
            
            // AUTO-INSERT: Transfer notification preferences after successful guest login
            if (class_exists('NotificationController')) {
                $notificationController = new NotificationController($this->db);
                $notificationController->transferSessionPreferencesToUser($customer['id'], session_id());
            }
            
            // Log the auto-login
            $logModel = new LogModel($this->db);
            $logModel->logLoginAttempt($customer['id'], 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            error_log("Guest user auto-login successful: " . $customer['id'] . " with preference flags set");
            
        } catch (Exception $e) {
            error_log("Guest user auto-login error: " . $e->getMessage());
        }
    }

    /**
     * Set preference flags for guest users to prevent modal display
     */
    private function setGuestUserPreferenceFlags($customer) {
        try {
            // Set city preference based on checkout selection
            if (isset($_POST['city']) && !empty($_POST['city'])) {
                $_SESSION['user_city_id'] = $this->getCityIdByName($_POST['city']);
                $_SESSION['user_city_name'] = $_POST['city'];
                $_SESSION['city_preference_set'] = true;
                
                error_log("Guest user city preference set: " . $_POST['city']);
            } else {
                // Default to Kathmandu if no city selected
                $_SESSION['user_city_id'] = 2;
                $_SESSION['user_city_name'] = 'Kathmandu';
                $_SESSION['city_preference_set'] = true;
            }
            
            // Set language preference to default (English)
            $_SESSION['user_language'] = 'en';
            $_SESSION['language_preference_set'] = true;
            
            // Set notification preference to true (assume they want notifications)
            $_SESSION['notification_preference_set'] = true;
            
            // Initialize other preference-related session variables
            $_SESSION['user_notification_preferences'] = [
                'push_notifications_enabled' => true,
                'main_notification_enabled' => true,
                'offers_notifications' => true,
                'delivery_tracking_notifications' => true,
                'system_notifications' => true
            ];
            
            error_log("Guest user preference flags initialized for customer: " . $customer['id']);
            
        } catch (Exception $e) {
            error_log("Error setting guest user preference flags: " . $e->getMessage());
        }
    }

    /**
     * Get city ID by city name
     */
    private function getCityIdByName($cityName) {
        try {
            $query = "SELECT id FROM delivery_cities WHERE city_name = :city_name LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':city_name', $cityName);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$row['id'];
            }
            
            return 2; // Default to Kathmandu ID
        } catch (Exception $e) {
            error_log("Error getting city ID by name: " . $e->getMessage());
            return 2; // Default to Kathmandu ID
        }
    }

    /**
     * Create persistent login for guest users (shorter duration)
     */
    private function createGuestPersistentLogin($customerId, $sessionData) {
        try {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
            
            // Serialize session data
            $serializedData = serialize($sessionData);
            
            // Extract preference flags for database storage
            $preferenceFlags = [
                'language_preference_set' => $sessionData['language_preference_set'] ?? true,
                'city_preference_set' => $sessionData['city_preference_set'] ?? true,
                'notification_preference_set' => $sessionData['notification_preference_set'] ?? true,
                'user_city_id' => $sessionData['user_city_id'] ?? 2,
                'user_city_name' => $sessionData['user_city_name'] ?? 'Kathmandu',
                'user_language' => $sessionData['user_language'] ?? 'en'
            ];
            
            $query = "INSERT INTO persistent_sessions 
                     (session_id, customer_id, session_data, expires_at, created_at, preference_flags) 
                     VALUES (:session_id, :customer_id, :session_data, :expires_at, NOW(), :preference_flags)";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $token);
            $stmt->bindParam(':customer_id', $customerId);
            $stmt->bindParam(':session_data', $serializedData);
            $stmt->bindParam(':expires_at', $expiresAt);
            $stmt->bindParam(':preference_flags', json_encode($preferenceFlags));
            
            if ($stmt->execute()) {
                // Set remember token cookie for 30 days for guest users
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Guest persistent login creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Utility method to get registration type
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
            error_log("Error getting registration type: " . $e->getMessage());
            return 'direct';
        }
    }

    private function uploadPaymentScreenshot($file) {
        try {
            // Use PathConfig to get upload directory
            $upload_dir = $this->pathConfig->filePath('admin_storage') . '/uploads/payment_screenshots/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
            }
            
            if ($file['size'] > 2097152) {
                return ['success' => false, 'message' => 'File size too large. Maximum size is 2MB.'];
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = 'payment_' . time() . '_' . uniqid() . '.' . $file_extension;
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $file_name)) {
                return ['success' => true, 'file_name' => $file_name];
            }
            
            return ['success' => false, 'message' => 'Failed to upload file.'];
        } catch (Exception $e) {
            error_log("Error uploading payment screenshot: " . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to upload payment screenshot'];
        }
    }

    private function updateCustomerDetails($customer_id, $data) {
        try {
            $stmt = $this->db->prepare("UPDATE customers SET name = :name, email = :email, phone = :phone, address = :address, city = :city, contact = :contact, updated_at = NOW() WHERE id = :id");
            
            $stmt->bindParam(":name", $data['name']);
            $stmt->bindParam(":email", $data['email'] ?? null);
            $stmt->bindParam(":phone", $data['phone']);
            $stmt->bindParam(":address", $data['address']);
            $stmt->bindParam(":city", $data['city']);
            $stmt->bindParam(":contact", $data['contact'] ?? '');
            $stmt->bindParam(":id", $customer_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error updating customer details: " . $e->getMessage());
            return false;
        }
    }

    private function calculateDeliveryDateTime($delivery_date, $flower_time) {
        try {
            $delivery_datetime = new DateTime($delivery_date);
            
            switch ($flower_time) {
                case 'morning':
                    $delivery_datetime->modify('-1 day')->setTime(18, 0);
                    break;
                case 'afternoon':
                    $delivery_datetime->setTime(9, 0);
                    break;
                case 'evening':
                    $delivery_datetime->setTime(14, 0);
                    break;
                default:
                    $delivery_datetime->setTime(12, 0);
            }
            
            return $delivery_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log("Error calculating delivery date: " . $e->getMessage());
            // Return default delivery time
            return date('Y-m-d H:i:s', strtotime('+1 day 12:00:00'));
        }
    }

    public function success() {
        try {
            $order_id = $_GET['order_id'] ?? null;
            if (!$order_id) {
                header('Location: ' . $this->pathConfig->url('home'));
                exit;
            }
            
            return [
                'order_id' => $order_id,
                'order' => $this->orderModel->getOrder($order_id),
                'page_title' => 'Order Success - Phool Delivery'
            ];
        } catch (Exception $e) {
            error_log("Error loading success page: " . $e->getMessage());
            header('Location: ' . $this->pathConfig->url('home'));
            exit;
        }
    }

    /**
     * API endpoint to get delivery fee for a specific city
     */
    public function getDeliveryFeeApi() {
        try {
            $city = $_GET['city'] ?? '';
            
            if (empty($city)) {
                echo json_encode(['success' => false, 'message' => 'City is required']);
                return;
            }
            
            $delivery_fee = $this->getDeliveryFeeByCity($city);
            echo json_encode(['success' => true, 'delivery_fee' => $delivery_fee]);
        } catch (Exception $e) {
            error_log("Error in getDeliveryFeeApi: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to fetch delivery fee']);
        }
    }

    /**
     * Additional method to help guest users retrieve their default password
     */
    
    public function getGuestPasswordInfo() {
        try {
            // For guest users, the password is always 'DemoGuest'
            return [
                'success' => true,
                'default_password' => 'DemoGuest',
                'message' => 'Your default password is: DemoGuest'
            ];
        } catch (Exception $e) {
            error_log("Error getting guest password info: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to retrieve password information'
            ];
        }
    }

    /**
     * 🔒 Check if phone is already verified for WhatsApp orders
     * NEW: Handle phone verification check endpoint
     */
    public function checkPhoneVerified() {
        try {
            header('Content-Type: application/json');
            
            $phone = $_POST['phone'] ?? '';
            
            if (empty($phone)) {
                echo json_encode(['success' => false, 'verified' => false, 'message' => 'Phone number is required']);
                return;
            }

            error_log("Checking WhatsApp phone verification for: " . $phone);

            // Check in session first (faster)
            if (isset($_SESSION['whatsapp_verified_phone']) && $_SESSION['whatsapp_verified_phone'] === $phone) {
                error_log("Phone verified in session: " . $phone);
                echo json_encode([
                    'success' => true,
                    'verified' => true,
                    'message' => 'Phone already verified in session'
                ]);
                return;
            }

            // Check in database
            try {
                $customerModel = new Customer($this->db);
                $customer = $customerModel->findByPhone($phone);

                if ($customer) {
                    if ($customer['verification_status'] === 'verified') {
                        error_log("Phone verified in database: " . $phone);
                        $_SESSION['whatsapp_verified_phone'] = $phone;
                        $_SESSION['whatsapp_phone_verified_at'] = $customer['phone_verified_at'] ?? date('Y-m-d H:i:s');
                        
                        echo json_encode([
                            'success' => true,
                            'verified' => true,
                            'message' => 'Phone already verified'
                        ]);
                    } else {
                        error_log("Phone exists but not verified: " . $phone);
                        echo json_encode([
                            'success' => true,
                            'verified' => false,
                            'exists' => true,
                            'message' => 'Phone exists but not verified'
                        ]);
                    }
                } else {
                    error_log("Phone not found in database: " . $phone);
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'exists' => false,
                        'message' => 'Phone not registered'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error checking phone in database: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'verified' => false,
                    'message' => 'Error checking phone verification status'
                ]);
            }
        } catch (Exception $e) {
            error_log("Error in checkPhoneVerified: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'verified' => false, 'message' => 'An error occurred']);
        }
    }

    /**
     * Use proper password verification that works with hashed passwords
     */
    public static function verifyGuestPassword($input_password, $stored_password) {
        return password_verify($input_password, $stored_password);
    }
}
?>