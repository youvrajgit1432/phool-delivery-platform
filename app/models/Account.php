<?php
// app/models/Account.php

class Account {
    private $conn;
    private $table_users = "customers";
    private $table_addresses = "customer_addresses";
    private $table_payments = "user_payment_methods";
    private $table_preferences = "user_preferences";
    private $table_wishlist = "wishlist";
    private $table_orders = "orders";
    private $table_order_items = "order_items";
    private $table_products = "products";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Security: Input validation helper
    private function validateInput($data, $rules) {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if (isset($data[$field])) {
                if ($rule === 'required' && empty(trim($data[$field]))) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                } elseif ($rule === 'email' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format';
                } elseif ($rule === 'int' && !filter_var($data[$field], FILTER_VALIDATE_INT)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be an integer';
                } elseif ($rule === 'float' && !filter_var($data[$field], FILTER_VALIDATE_FLOAT)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                }
            } elseif ($rule === 'required') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return $errors;
    }
    
    // Security: Sanitization helper
    private function sanitizeData($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    // Security: CSRF token verification (to be used in controllers)
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    // Security: Regenerate session ID after login
    public static function secureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Set secure session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']), // Only over HTTPS
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Strict' // CSRF protection
        ]);
    }
    
    // Get user profile (basic info only - for dashboard)
    public function getUserProfile($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return false;
        }
        
        $query = "SELECT id, name, email, phone, registration_type, address, city, 
                         email_verified_at, phone_verified_at, created_at, updated_at 
                  FROM " . $this->table_users . " 
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add verification status flags
        if ($user) {
            $user['email_verified'] = !empty($user['email_verified_at']);
            $user['phone_verified'] = !empty($user['phone_verified_at']);
        }
        
        return $user;
    }
    
    // Get user addresses
    public function getUserAddresses($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return [];
        }
        
        $query = "SELECT * FROM " . $this->table_addresses . " 
                  WHERE customer_id = :user_id 
                  ORDER BY is_default DESC, created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Save address (add or update)
    public function saveAddress($user_id, $data) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Security: Input validation
        $validationRules = [
            'address_line1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'zip_code' => 'required'
        ];
        
        $validationErrors = $this->validateInput($data, $validationRules);
        if (!empty($validationErrors)) {
            return ['success' => false, 'message' => implode(', ', $validationErrors)];
        }
        
        // Security: Sanitize input data
        $data = $this->sanitizeData($data);
        
        // Security: Validate address_type against allowed values
        $allowedTypes = ['home', 'work', 'billing', 'shipping'];
        $addressType = in_array($data['address_type'] ?? 'home', $allowedTypes) ? $data['address_type'] : 'home';

        try {
            $this->conn->beginTransaction();

            // If this is set as default, remove default from other addresses
            if (isset($data['is_default']) && $data['is_default']) {
                $this->removeDefaultAddress($user_id);
            }

            if (isset($data['address_id']) && !empty($data['address_id'])) {
                // Security: Verify address belongs to user before update
                if (!$this->verifyAddressOwnership($user_id, $data['address_id'])) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'Address not found'];
                }
                
                // Update existing address
                $query = "UPDATE " . $this->table_addresses . " 
                         SET address_type = :address_type, 
                             address_line1 = :address_line1, 
                             address_line2 = :address_line2, 
                             city = :city, 
                             state = :state, 
                             zip_code = :zip_code, 
                             country = :country, 
                             is_default = :is_default,
                             updated_at = NOW()
                         WHERE id = :address_id AND customer_id = :user_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(":address_id", $data['address_id'], PDO::PARAM_INT);
                $message = 'Address updated successfully';
            } else {
                // Insert new address
                $query = "INSERT INTO " . $this->table_addresses . " 
                         (customer_id, address_type, address_line1, address_line2, 
                          city, state, zip_code, country, is_default)
                         VALUES (:user_id, :address_type, :address_line1, :address_line2, 
                                 :city, :state, :zip_code, :country, :is_default)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
                $message = 'Address added successfully';
            }
            
            // Bind common parameters
            $stmt->bindValue(":address_type", $addressType);
            $stmt->bindValue(":address_line1", $data['address_line1']);
            $stmt->bindValue(":address_line2", $data['address_line2'] ?? '');
            $stmt->bindValue(":city", $data['city']);
            $stmt->bindValue(":state", $data['state']);
            $stmt->bindValue(":zip_code", $data['zip_code']);
            $stmt->bindValue(":country", $data['country'] ?? 'Nepal');
            $stmt->bindValue(":is_default", isset($data['is_default']) ? 1 : 0, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->conn->commit();
                return ['success' => true, 'message' => $message];
            }
            
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to save address'];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Address save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Security: Verify address ownership
    private function verifyAddressOwnership($user_id, $address_id) {
        $query = "SELECT id FROM " . $this->table_addresses . " 
                  WHERE id = :address_id AND customer_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":address_id", $address_id, PDO::PARAM_INT);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    // Remove default address flag
    private function removeDefaultAddress($user_id) {
        $query = "UPDATE " . $this->table_addresses . " 
                 SET is_default = 0 
                 WHERE customer_id = :user_id AND is_default = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Delete address
    public function deleteAddress($user_id, $address_id) {
        // Security: Validate inputs
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0 ||
            !filter_var($address_id, FILTER_VALIDATE_INT) || $address_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user or address ID'];
        }
        
        // Security: Verify address belongs to user
        if (!$this->verifyAddressOwnership($user_id, $address_id)) {
            return ['success' => false, 'message' => 'Address not found'];
        }
        
        $query = "DELETE FROM " . $this->table_addresses . " 
                  WHERE id = :address_id AND customer_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":address_id", $address_id, PDO::PARAM_INT);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Address deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete address'];
    }
    
    // Set default address
    public function setDefaultAddress($user_id, $address_id) {
        // Security: Validate inputs
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0 ||
            !filter_var($address_id, FILTER_VALIDATE_INT) || $address_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user or address ID'];
        }
        
        // Security: Verify address belongs to user
        if (!$this->verifyAddressOwnership($user_id, $address_id)) {
            return ['success' => false, 'message' => 'Address not found'];
        }
        
        try {
            $this->conn->beginTransaction();
            
            // Remove default from all addresses
            $remove_query = "UPDATE " . $this->table_addresses . " 
                           SET is_default = 0 
                           WHERE customer_id = :user_id";
            $stmt = $this->conn->prepare($remove_query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Set new default address
            $set_query = "UPDATE " . $this->table_addresses . " 
                         SET is_default = 1 
                         WHERE id = :address_id AND customer_id = :user_id";
            $stmt = $this->conn->prepare($set_query);
            $stmt->bindValue(":address_id", $address_id, PDO::PARAM_INT);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Default address updated successfully'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Set default address error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update default address'];
        }
    }
    
    // Get user orders (for dashboard - limited)
    public function getUserOrders($user_id, $limit = null) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return [];
        }
        
        $query = "SELECT o.*, COUNT(oi.id) as item_count, 
                         SUM(oi.quantity * oi.unit_price) as total_amount
                  FROM " . $this->table_orders . " o 
                  LEFT JOIN " . $this->table_order_items . " oi ON o.id = oi.order_id 
                  WHERE o.customer_id = :user_id 
                  GROUP BY o.id 
                  ORDER BY o.created_at DESC";
        
        if ($limit) {
            // Security: Validate limit
            $limit = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if (!$limit) {
                $limit = 10; // Default limit
            }
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        
        if ($limit) {
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get wishlist items
    public function getWishlist($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return [];
        }
        
        $query = "SELECT w.*, p.name, p.price, p.image, p.stock_status 
                  FROM " . $this->table_wishlist . " w 
                  JOIN " . $this->table_products . " p ON w.product_id = p.id 
                  WHERE w.user_id = :user_id 
                  ORDER BY w.added_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get wishlist count
    public function getWishlistCount($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return 0;
        }
        
        $query = "SELECT COUNT(*) as count 
                  FROM " . $this->table_wishlist . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    // Remove from wishlist
    public function removeFromWishlist($user_id, $product_id) {
        // Security: Validate inputs
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0 ||
            !filter_var($product_id, FILTER_VALIDATE_INT) || $product_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user or product ID'];
        }
        
        $query = "DELETE FROM " . $this->table_wishlist . " 
                  WHERE user_id = :user_id AND product_id = :product_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindValue(":product_id", $product_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Removed from wishlist'];
        }
        
        return ['success' => false, 'message' => 'Failed to remove from wishlist'];
    }
    
    // Get payment methods
    public function getPaymentMethods($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return [];
        }
        
        $query = "SELECT * FROM " . $this->table_payments . " 
                  WHERE user_id = :user_id 
                  ORDER BY is_default DESC, created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Save payment method
    public function savePaymentMethod($user_id, $data) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Security: Input validation
        $validationRules = [
            'payment_type' => 'required',
            'card_number' => 'required'
        ];
        
        $validationErrors = $this->validateInput($data, $validationRules);
        if (!empty($validationErrors)) {
            return ['success' => false, 'message' => implode(', ', $validationErrors)];
        }
        
        // Security: Sanitize input data
        $data = $this->sanitizeData($data);
        
        // Security: Validate payment type
        $allowedTypes = ['credit_card', 'debit_card', 'paypal', 'bank_transfer'];
        $paymentType = in_array($data['payment_type'], $allowedTypes) ? $data['payment_type'] : 'credit_card';
        
        // Security: Validate card number (basic Luhn check could be added here)
        $cardNumber = preg_replace('/\D/', '', $data['card_number'] ?? '');
        if (strlen($cardNumber) < 13) {
            return ['success' => false, 'message' => 'Invalid card number'];
        }

        try {
            $this->conn->beginTransaction();

            // If this is set as default, remove default from other methods
            if (isset($data['is_default']) && $data['is_default']) {
                $this->removeDefaultPaymentMethod($user_id);
            }
            
            $query = "INSERT INTO " . $this->table_payments . " 
                     (user_id, payment_type, provider, last_four, expiry_month, expiry_year, is_default, token)
                     VALUES (:user_id, :payment_type, :provider, :last_four, :expiry_month, :expiry_year, :is_default, :token)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":payment_type", $paymentType);
            $stmt->bindValue(":provider", $data['provider'] ?? '');
            $stmt->bindValue(":last_four", substr($cardNumber, -4));
            $stmt->bindValue(":expiry_month", $data['expiry_month'] ?? '');
            $stmt->bindValue(":expiry_year", $data['expiry_year'] ?? '');
            $stmt->bindValue(":is_default", isset($data['is_default']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(":token", $data['token'] ?? '');
            
            if ($stmt->execute()) {
                $this->conn->commit();
                return ['success' => true, 'message' => 'Payment method added successfully'];
            }
            
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to add payment method'];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Payment method save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Remove default payment method flag
    private function removeDefaultPaymentMethod($user_id) {
        $query = "UPDATE " . $this->table_payments . " 
                 SET is_default = 0 
                 WHERE user_id = :user_id AND is_default = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Delete payment method
    public function deletePaymentMethod($user_id, $method_id) {
        // Security: Validate inputs
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0 ||
            !filter_var($method_id, FILTER_VALIDATE_INT) || $method_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user or method ID'];
        }
        
        // Security: Verify payment method belongs to user
        if (!$this->verifyPaymentMethodOwnership($user_id, $method_id)) {
            return ['success' => false, 'message' => 'Payment method not found'];
        }
        
        $query = "DELETE FROM " . $this->table_payments . " 
                  WHERE id = :method_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":method_id", $method_id, PDO::PARAM_INT);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Payment method deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete payment method'];
    }
    
    // Security: Verify payment method ownership
    private function verifyPaymentMethodOwnership($user_id, $method_id) {
        $query = "SELECT id FROM " . $this->table_payments . " 
                  WHERE id = :method_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":method_id", $method_id, PDO::PARAM_INT);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    // Get user preferences
    public function getPreferences($user_id) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return $this->getDefaultPreferences();
        }
        
        $query = "SELECT * FROM " . $this->table_preferences . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return default preferences if none exist
        if (!$preferences) {
            return $this->getDefaultPreferences();
        }
        
        return $preferences;
    }
    
    // Get default preferences
    private function getDefaultPreferences() {
        return [
            'email_notifications' => 1,
            'sms_notifications' => 1,
            'newsletter_subscription' => 1,
            'language' => 'en',
            'theme' => 'light'
        ];
    }
    
    // Save preferences - UPDATED
    public function savePreferences($user_id, $data) {
        // Security: Validate user_id
        if (!filter_var($user_id, FILTER_VALIDATE_INT) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Security: Sanitize input data
        $data = $this->sanitizeData($data);
        
        // Security: Validate language and theme
        $allowedLanguages = ['en', 'np', 'hi'];
        $allowedThemes = ['light', 'dark', 'auto'];
        
        $language = in_array($data['language'] ?? 'en', $allowedLanguages) ? $data['language'] : 'en';
        $theme = in_array($data['theme'] ?? 'light', $allowedThemes) ? $data['theme'] : 'light';

        try {
            // Check if preferences already exist
            $check_query = "SELECT id FROM " . $this->table_preferences . " 
                           WHERE user_id = :user_id";
            
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing preferences
                $query = "UPDATE " . $this->table_preferences . " 
                         SET email_notifications = :email_notifications,
                             sms_notifications = :sms_notifications,
                             newsletter_subscription = :newsletter_subscription,
                             language = :language,
                             theme = :theme,
                             updated_at = NOW()
                         WHERE user_id = :user_id";
            } else {
                // Insert new preferences
                $query = "INSERT INTO " . $this->table_preferences . " 
                         (user_id, email_notifications, sms_notifications, 
                          newsletter_subscription, language, theme)
                         VALUES (:user_id, :email_notifications, :sms_notifications,
                                 :newsletter_subscription, :language, :theme)";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":email_notifications", isset($data['email_notifications']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(":sms_notifications", isset($data['sms_notifications']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(":newsletter_subscription", isset($data['newsletter_subscription']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(":language", $language);
            $stmt->bindValue(":theme", $theme);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Preferences saved successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to save preferences'];
            
        } catch (Exception $e) {
            error_log("Preferences save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
}
?>