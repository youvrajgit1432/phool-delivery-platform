<?php
// app/controllers/AccountController.php
class AccountController {
    private $db;
    private $accountModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->accountModel = new Account($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    // Address management
    public function addresses() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                // Redirect to login with return URL
                $redirect = isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '';
                header('Location: ' . $this->pathConfig->url('login' . $redirect));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            $redirect_url = $_GET['redirect'] ?? 'account';
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = [];
                
                if (isset($_POST['delete_address'])) {
                    $result = $this->accountModel->deleteAddress($user_id, $_POST['address_id']);
                } elseif (isset($_POST['set_default'])) {
                    $result = $this->accountModel->setDefaultAddress($user_id, $_POST['address_id']);
                } else {
                    $result = $this->accountModel->saveAddress($user_id, $_POST);
                }
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                    
                    // If this was a redirect from checkout, redirect back
                    if ($redirect_url === 'checkout') {
                        header('Location: ' . $this->pathConfig->url('checkout'));
                        exit;
                    }
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/addresses?redirect=' . $redirect_url));
                exit;
            }
            
            $data = [
                'addresses' => $this->accountModel->getUserAddresses($user_id),
                'redirect_url' => $redirect_url,
                'page_title' => 'My Addresses - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Address management error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while processing your request.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }
    
    // Address change page
    public function addressChange() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            // Get customer data
            $customer = $this->accountModel->getUserProfile($user_id);
            
            if (!$customer) {
                $_SESSION['error_message'] = 'Customer not found';
                header('Location: ' . $this->pathConfig->url('account'));
                exit;
            }
            
            $data = [
                'customer' => $customer,
                'page_title' => 'Change Address - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Address change error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while loading the page.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }
    
    // Update address
    public function updateAddress() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You must be logged in to update your address'
                ]);
                return;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_address') {
                // Validate required fields
                $required = ['name', 'phone', 'address', 'city'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Please fill in all required fields'
                        ]);
                        return;
                    }
                }
                
                // Validate phone format
                $phonePattern = '/^[0-9]{10}$/';
                if (!preg_match($phonePattern, $_POST['phone'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Please enter a valid 10-digit phone number'
                    ]);
                    return;
                }
                
                // Validate email if provided
                if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Please enter a valid email address'
                    ]);
                    return;
                }
                
                // Update customer details in the customers table
                $success = $this->updateCustomerDetails($user_id, $_POST);
                
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Address updated successfully'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to update address. Please try again.'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request'
                ]);
            }
        } catch (Exception $e) {
            error_log("Update address error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'An error occurred while updating your address.'
            ]);
        }
    }
    
    // Update customer details in the customers table
    private function updateCustomerDetails($customer_id, $data) {
        try {
            $query = "UPDATE customers SET 
                     name = :name, 
                     email = :email, 
                     phone = :phone, 
                     address = :address, 
                     city = :city, 
                     updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(":name", $data['name']);
            $stmt->bindParam(":email", $data['email'] ?? null);
            $stmt->bindParam(":phone", $data['phone']);
            $stmt->bindParam(":address", $data['address']);
            $stmt->bindParam(":city", $data['city']);
            $stmt->bindParam(":id", $customer_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update customer details error: " . $e->getMessage());
            return false;
        }
    }
    
    // Dashboard
    public function dashboard() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            // Get all user data needed for the dashboard
            $user = $this->accountModel->getUserProfile($user_id);
            $recent_orders = $this->accountModel->getUserOrders($user_id, 5);
            $addresses = $this->accountModel->getUserAddresses($user_id);
            $wishlist_count = $this->accountModel->getWishlistCount($user_id);
            $payment_methods = $this->accountModel->getPaymentMethods($user_id);
            $preferences = $this->accountModel->getPreferences($user_id);
            
            $data = [
                'user' => $user,
                'recent_orders' => $recent_orders,
                'addresses' => $addresses,
                'wishlist_count' => $wishlist_count,
                'payment_methods' => $payment_methods,
                'preferences' => $preferences,
                'notification_link' => $this->pathConfig->url('account/notifications'),
                'page_title' => 'Account Dashboard - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Dashboard error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while loading your dashboard.';
            header('Location: ' . $this->pathConfig->url(''));
            exit;
        }
    }
    
    // Wishlist management
    public function wishlist() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['product_id'])) {
                $result = $this->accountModel->removeFromWishlist($user_id, $_GET['product_id']);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/wishlist'));
                exit;
            }
            
            $data = [
                'wishlist' => $this->accountModel->getWishlist($user_id),
                'page_title' => 'My Wishlist - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Wishlist error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while accessing your wishlist.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }
    
    // Payment methods management
    public function paymentMethods() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['delete_method'])) {
                    $result = $this->accountModel->deletePaymentMethod($user_id, $_POST['method_id']);
                } else {
                    $result = $this->accountModel->savePaymentMethod($user_id, $_POST);
                }
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/payment-methods'));
                exit;
            }
            
            $data = [
                'payment_methods' => $this->accountModel->getPaymentMethods($user_id),
                'page_title' => 'Payment Methods - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Payment methods error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while accessing payment methods.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }
    
    // Preferences management - UPDATED
    public function preferences() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = $this->accountModel->savePreferences($user_id, $_POST);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                    
                    // Update session language if changed
                    if (isset($_POST['language']) && !empty($_POST['language'])) {
                        $newLanguage = $_POST['language'];
                        $_SESSION['user_language'] = $newLanguage;
                        
                        // Reload LanguageHelper with new language
                        LanguageHelper::setLanguage($newLanguage, $user_id, true);
                        
                        // Add language change flag to trigger page reload
                        $_SESSION['language_changed'] = true;
                    }
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                
                header('Location: ' . $this->pathConfig->url('account/preferences'));
                exit;
            }
            
            $data = [
                'preferences' => $this->accountModel->getPreferences($user_id),
                'page_title' => 'My Preferences - Phool Delivery'
            ];
            
            return $data;
        } catch (Exception $e) {
            error_log("Preferences error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while accessing your preferences.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }

    // Support page
    public function support() {
        try {
            // Check if user is logged in
            if (!isset($_SESSION['customer_id'])) {
                header('Location: ' . $this->pathConfig->url('login'));
                exit;
            }

            $customer = new Customer($this->db);
            $customer->id = $_SESSION['customer_id'];
            $customer->readOne();

            return [
                'user' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone
                ],
                'page_title' => 'Customer Support - Phool Delivery'
            ];
        } catch (Exception $e) {
            error_log("Support page error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while loading the support page.';
            header('Location: ' . $this->pathConfig->url('account'));
            exit;
        }
    }

    // Support request processing
    public function supportProcess() {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Check if user is logged in
                if (!isset($_SESSION['customer_id'])) {
                    $_SESSION['error_message'] = "Please log in to submit your request.";
                    header('Location: ' . $this->pathConfig->url('login'));
                    exit;
                }

                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $subject = $_POST['subject'] ?? '';
                $message = $_POST['message'] ?? '';
                $priority = $_POST['priority'] ?? 'medium';

                // Basic validation
                if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                    $_SESSION['error_message'] = "Please fill in all required fields.";
                    header('Location: ' . $this->pathConfig->url('account/support'));
                    exit;
                }

                // Here you would typically save the support request to database
                // For now, we'll just show a success message
                $_SESSION['success_message'] = "Thank you for contacting us! We'll get back to you within 24 hours.";
                header('Location: ' . $this->pathConfig->url('account/support'));
                exit;
            }
        } catch (Exception $e) {
            error_log("Support process error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while submitting your support request.';
            header('Location: ' . $this->pathConfig->url('account/support'));
            exit;
        }
    }
    // Add this method to your AccountController class

/**
 * Notification settings management
 */
public function notifications() {
    try {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        // Get notification preferences
        $notificationModel = new Notification($this->db);
        $preferences = $notificationModel->getPreferences($user_id);
        
        $success_message = $_SESSION['success_message'] ?? '';
        $error_message = $_SESSION['error_message'] ?? '';
        
        unset($_SESSION['success_message'], $_SESSION['error_message']);
        
        $data = [
            'preferences' => $preferences,
            'success_message' => $success_message,
            'error_message' => $error_message,
            'page_title' => LanguageHelper::t('notification_settings', 'Notification Settings') . ' - Phool Delivery'
        ];
        
        return $data;
    } catch (Exception $e) {
        error_log("Notifications error: " . $e->getMessage());
        $_SESSION['error_message'] = 'An error occurred while accessing notification settings.';
        header('Location: ' . $this->pathConfig->url('account'));
        exit;
    }
}

    // Submit interest form (AJAX)
    public function submitInterest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Check if user is logged in
                if (!isset($_SESSION['customer_id'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Please log in to submit your interest.']);
                    exit;
                }

                $interest = new Interest($this->db);
                $interest->customer_id = $_SESSION['customer_id'];
                $interest->product_type = $_POST['product_type'] ?? '';
                $interest->delivery_address = $_POST['delivery_address'] ?? '';
                $interest->quantity_needed = $_POST['quantity_needed'] ?? '';
                $interest->event_type = $_POST['event_type'] ?? '';
                $interest->event_date = $_POST['event_date'] ?? '';
                $interest->special_requirements = $_POST['special_requirements'] ?? '';

                // Basic validation
                if (empty($interest->product_type) || empty($interest->delivery_address)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
                    exit;
                }

                if ($interest->create()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Thank you for your interest! We\'ll notify you when we start service in your area.']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Sorry, there was an error submitting your interest. Please try again.']);
                }
                exit;
            }
        } catch (Exception $e) {
            error_log("Submit interest error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your interest.']);
            exit;
        }
    }

    // Helper method to check if user is logged in
    private function isLoggedIn() {
        return isset($_SESSION['customer_id']);
    }
}
?>