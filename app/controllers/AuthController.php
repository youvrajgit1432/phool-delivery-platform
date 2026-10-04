<?php
class AuthController {
    private $db;
    private $customerModel;
    private $logModel;
    private $pathConfig;
    private $persistentLogin;

    public function __construct($db) {
        $this->db = $db;
        $this->customerModel = new Customer($db);
        $this->logModel = new LogModel($db);
        $this->pathConfig = PathConfig::getInstance();
        $this->persistentLogin = new PersistentLogin($db);
    }

    public function signup() {
        if (isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url(''));
            exit;
        }
        
        $error_message = $_SESSION['auth_error'] ?? '';
        $success_message = $_SESSION['auth_success'] ?? '';
        $form_data = $_SESSION['form_data'] ?? [];
        
        unset($_SESSION['auth_error']);
        unset($_SESSION['auth_success']);
        unset($_SESSION['form_data']);
        
        return [
            'page_title' => 'Sign Up - Phool Delivery',
            'error_message' => $error_message,
            'success_message' => $success_message,
            'form_data' => $form_data
        ];
    }

    public function processSignup() {
        // FIXED: Always treat signup-process as traditional form submission
        $isAjax = false; // Force traditional form submission for signup
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $remember_me = isset($_POST['remember_me']);
            
            $_SESSION['form_data'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'street' => $street
            ];
            
            // Validation
            if (empty($name) || empty($password) || empty($confirm_password)) {
                $errorMsg = 'Please fill in all required fields';
                $_SESSION['auth_error'] = $errorMsg;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
            
            if (empty($email) && empty($phone)) {
                $errorMsg = 'Please provide either email or phone number';
                $_SESSION['auth_error'] = $errorMsg;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
            
            if ($password !== $confirm_password) {
                $errorMsg = 'Passwords do not match';
                $_SESSION['auth_error'] = $errorMsg;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
            
            if (strlen($password) < 6) {
                $errorMsg = 'Password must be at least 6 characters long';
                $_SESSION['auth_error'] = $errorMsg;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
            
            // Check if identifier exists
            $identifierCheck = $this->checkIdentifierExists($email, $phone);
            if ($identifierCheck !== true) {
                $_SESSION['auth_error'] = $identifierCheck;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                // Set registration type as 'direct' for signup form registrations
                $registration_type = 'direct';
                
                $query = "INSERT INTO customers (name, email, phone, password, registration_type, address, city, street, status, created_at, updated_at) 
                         VALUES (:name, :email, :phone, :password, :registration_type, :address, :city, :street, 'active', NOW(), NOW())";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':registration_type', $registration_type);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':city', $city);
                $stmt->bindParam(':street', $street);
                
                if ($stmt->execute()) {
                    $customer_id = $this->db->lastInsertId();
                    
                    // Log the signup as a login event
                    $this->logModel->logLoginAttempt($customer_id, 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
                    
                    // ENHANCED: Set session variables with preference flags
                    $this->setManualSignupSession($customer_id, $name, $email, $phone, $registration_type, $city);
                    
                    // Create persistent login if remember me is checked
                    if ($remember_me) {
                        $sessionData = [
                            'customer_id' => $customer_id,
                            'customer_name' => $name,
                            'customer_email' => $email,
                            'customer_phone' => $phone,
                            'is_guest_user' => false,
                            'registration_type' => $registration_type,
                            // Include preference flags in persistent session
                            'language_preference_set' => $_SESSION['language_preference_set'] ?? true,
                            'city_preference_set' => $_SESSION['city_preference_set'] ?? true,
                            'notification_preference_set' => $_SESSION['notification_preference_set'] ?? true,
                            'user_city_id' => $_SESSION['user_city_id'] ?? 2,
                            'user_city_name' => $_SESSION['user_city_name'] ?? 'Kathmandu',
                            'user_language' => $_SESSION['user_language'] ?? 'en'
                        ];
                        $this->persistentLogin->createPersistentLogin($customer_id, $sessionData);
                    }
                    
                    // AUTO-SWITCH LANGUAGE: Initialize language preferences for new user
                    if (class_exists('LanguageHelper')) {
                        LanguageHelper::autoSwitchLanguageAfterLogin($customer_id);
                    }
                    
                    // AUTO-INSERT AFTER LOGIN/REGISTRATION: Transfer notification preferences
                    if (class_exists('NotificationController')) {
                        $notificationController = new NotificationController($this->db);
                        $notificationController->transferSessionPreferencesToUser($customer_id, session_id());
                    }
                    
                    // Transfer user preferences after registration
                    $this->transferUserPreferencesAfterRegistration($customer_id);
                    
                    $messageModel = new Message($this->db);
                    $messageModel->create(
                        $customer_id,
                        'Welcome to Phool Delivery!',
                        'Thank you for creating an account with Phool Delivery.',
                        'system'
                    );
                    
                    $_SESSION['message_count'] = 1;
                    unset($_SESSION['form_data']);
                    
                    // FIXED: Set success message and redirect appropriately
                    $_SESSION['auth_success'] = 'Account created successfully! Welcome to Phool Delivery.';
                    
                    // FIXED: Always redirect for traditional form submission
                    header('Location: ' . $this->pathConfig->url(''));
                    exit;
                } else {
                    $errorMsg = 'Registration failed. Please try again.';
                    $_SESSION['auth_error'] = $errorMsg;
                    header('Location: ' . $this->pathConfig->url('signup'));
                    exit;
                }
            } catch (Exception $e) {
                // Log detailed error for server-side debugging
                error_log("Registration error for email: " . $email . " - " . $e->getMessage());
                
                // Generic error message for client
                $errorMsg = 'Registration failed. Please try again.';
                $_SESSION['auth_error'] = $errorMsg;
                header('Location: ' . $this->pathConfig->url('signup'));
                exit;
            }
        }
        
        // If not POST request
        $errorMsg = 'Invalid request method';
        $_SESSION['auth_error'] = $errorMsg;
        header('Location: ' . $this->pathConfig->url('signup'));
        exit;
    }

    /**
     * Enhanced session setup for manual signup with preference flags
     */
    private function setManualSignupSession($customer_id, $name, $email, $phone, $registration_type, $selected_city = '') {
        try {
            // Set basic session variables
            $_SESSION['customer_id'] = $customer_id;
            $_SESSION['customer_name'] = $name;
            $_SESSION['customer_email'] = $email;
            $_SESSION['customer_phone'] = $phone;
            $_SESSION['is_guest_user'] = false;
            $_SESSION['registration_type'] = $registration_type;
            $_SESSION['login_method'] = 'manual_signup';
            
            // CRITICAL: Set preference flags for manual signup users
            $this->setManualSignupPreferenceFlags($customer_id, $selected_city);
            
            error_log("Manual signup session set for customer: " . $customer_id . " with preference flags");
            
        } catch (Exception $e) {
            error_log("Error setting manual signup session: " . $e->getMessage());
        }
    }

    /**
     * Set preference flags for manual signup users
     */
    private function setManualSignupPreferenceFlags($customer_id, $selected_city = '') {
        try {
            // Set city preference based on signup selection
            if (!empty($selected_city)) {
                $city_id = $this->getCityIdByName($selected_city);
                $_SESSION['user_city_id'] = $city_id;
                $_SESSION['user_city_name'] = $selected_city;
                $_SESSION['city_preference_set'] = true;
                
                error_log("Manual signup city preference set: " . $selected_city);
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
            
            // Save city preference to database for persistence
            $this->saveCityPreferenceToDatabase($customer_id, $_SESSION['user_city_id'], $_SESSION['user_city_name']);
            
            error_log("Manual signup preference flags initialized for customer: " . $customer_id);
            
        } catch (Exception $e) {
            error_log("Error setting manual signup preference flags: " . $e->getMessage());
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
     * Save city preference to database
     */
    private function saveCityPreferenceToDatabase($customer_id, $city_id, $city_name) {
        try {
            // First check if preference already exists
            $check_query = "SELECT id FROM user_city_preferences WHERE user_id = :user_id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $customer_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing preference
                $update_query = "UPDATE user_city_preferences SET city_id = :city_id, updated_at = NOW() WHERE user_id = :user_id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':city_id', $city_id);
                $update_stmt->bindParam(':user_id', $customer_id);
                $update_stmt->execute();
            } else {
                // Insert new preference
                $insert_query = "INSERT INTO user_city_preferences (user_id, city_id, is_default, created_at, updated_at) 
                               VALUES (:user_id, :city_id, 1, NOW(), NOW())";
                $insert_stmt = $this->db->prepare($insert_query);
                $insert_stmt->bindParam(':user_id', $customer_id);
                $insert_stmt->bindParam(':city_id', $city_id);
                $insert_stmt->execute();
            }
            
            // Also update customer's city in main table
            $update_customer_query = "UPDATE customers SET city = :city_name, updated_at = NOW() WHERE id = :id";
            $update_customer_stmt = $this->db->prepare($update_customer_query);
            $update_customer_stmt->bindParam(':city_name', $city_name);
            $update_customer_stmt->bindParam(':id', $customer_id);
            $update_customer_stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Error saving city preference to database: " . $e->getMessage());
            return false;
        }
    }

    private function transferUserPreferencesAfterRegistration($customer_id) {
        try {
            // Transfer notification preferences from session to user
            if (class_exists('NotificationController')) {
                $notificationController = new NotificationController($this->db);
                $sessionId = session_id();
                $notificationController->transferSessionPreferencesToUser($customer_id, $sessionId);
            }
            
            // Transfer city preferences from session to user if exists
            if (isset($_SESSION['guest_city_preference'])) {
                $cityController = new CityController($this->db);
                $cityPref = $_SESSION['guest_city_preference'];
                $cityController->saveCityToDatabase($customer_id, $cityPref['city_id'], $cityPref['city_name'], $cityPref['delivery_fee']);
                unset($_SESSION['guest_city_preference']);
            }
            
        } catch (Exception $e) {
            error_log("Error transferring user preferences: " . $e->getMessage());
            // Don't fail registration if preference transfer fails
        }
    }

    private function checkIdentifierExists($email, $phone) {
        try {
            // Check email if provided
            if (!empty($email)) {
                $query = "SELECT id FROM customers WHERE email = :email";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    return 'Email already registered';
                }
            }
            
            // Check phone if provided
            if (!empty($phone)) {
                $query = "SELECT id FROM customers WHERE phone = :phone";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':phone', $phone);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    return 'Phone number already registered';
                }
            }
            
            return true;
        } catch (Exception $e) {
            // Log detailed error for server-side debugging
            error_log("Error checking identifier exists: " . $e->getMessage());
            
            // Generic error for client
            return 'Registration validation failed. Please try again.';
        }
    }
}
?>