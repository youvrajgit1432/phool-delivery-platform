<?php
class NotificationController {
    private $db;
    private $notificationModel;
    private $pushService;
    private $pathConfig;
    
    public function __construct($database = null) {
        try {
            if ($database instanceof PDO) {
                $this->db = $database;
            } elseif ($database && method_exists($database, 'getConnection')) {
                $this->db = $database->getConnection();
            } else {
                if (class_exists('Database')) {
                    $database = new Database();
                    $this->db = $database->getConnection();
                } else {
                    $this->db = null;
                }
            }
        } catch (Exception $e) {
            error_log("NotificationController database connection failed: " . $e->getMessage());
            $this->db = null;
        }
        
        try {
            $this->pathConfig = PathConfig::getInstance();
            $this->notificationModel = new Notification($this->db);
            $this->pushService = new PushNotificationService($this->db);
        } catch (Exception $e) {
            error_log("NotificationController initialization failed: " . $e->getMessage());
            $this->pathConfig = null;
            $this->notificationModel = null;
            $this->pushService = null;
        }
    }
    
    /**
     * API: Auto-enable notifications without user interaction
     */
    public function autoEnableNotifications() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            
            $device_token = $data['device_token'] ?? null;
            $user_id = $_SESSION['customer_id'] ?? null;
            $session_id = session_id();
            
            // Set default preferences
            $defaultPreferences = [
                'push_notifications_enabled' => true,
                'main_notification_enabled' => true,
                'offers_notifications' => true,
                'delivery_tracking_notifications' => true,
                'system_notifications' => true
            ];
            
            // Save to session
            $_SESSION['user_notification_preferences'] = $defaultPreferences;
            $_SESSION['notification_preference_set'] = true;
            $_SESSION['notification_preference_shown'] = true;
            $_SESSION['show_notification_modal'] = false;
            $_SESSION['push_auto_registered'] = true;
            $_SESSION['push_notification_enabled'] = true;
            
            // Save to database
            if ($this->notificationModel) {
                if ($user_id) {
                    $this->notificationModel->savePreferences($user_id, null, $defaultPreferences);
                } else {
                    $this->notificationModel->savePreferences(null, $session_id, $defaultPreferences);
                }
            }
            
            // Auto-register device if token provided
            $device_registered = false;
            if ($device_token && $this->pushService) {
                $device_data = [
                    'device_type' => 'web',
                    'browser_name' => $data['browser_name'] ?? null,
                    'browser_version' => $data['browser_version'] ?? null,
                    'platform' => $data['platform'] ?? navigator.platform,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'auto_registered' => true,
                    'force_registered' => true
                ];
                
                $result = $this->forcefulDeviceRegistration($user_id, $device_token, $device_data);
                $device_registered = $result['success'];
                
                if ($device_registered) {
                    $_SESSION['device_registration_forced'] = true;
                }
            }
            
            error_log("Notifications auto-enabled for user: " . ($user_id ?: 'guest'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Notifications enabled automatically',
                'preferences' => $defaultPreferences,
                'device_registered' => $device_registered,
                'auto_enabled' => true
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Auto-enable notifications error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error auto-enabling notifications: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * API: Register device for push notifications - ENHANCED with FORCEFUL registration
     */
    public function registerDevice($forceRegistration = false) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            // Get user ID from session or create guest session
            $user_id = $_SESSION['customer_id'] ?? null;
            $session_id = session_id();
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                throw new Exception('No input data received');
            }
            
            $device_token = $input['device_token'] ?? null;
            if (!$device_token) {
                throw new Exception('Device token is required');
            }
            
            // Validate device token format - relaxed validation for forceful registration
            if (!$forceRegistration && !filter_var($device_token, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\//', $device_token)) {
                throw new Exception('Invalid device token format');
            }
            
            $device_data = [
                'device_type' => $input['device_type'] ?? 'web',
                'browser_name' => $input['browser_name'] ?? null,
                'browser_version' => $input['browser_version'] ?? null,
                'platform' => $input['platform'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'subscription_data' => $input['subscription_data'] ?? null,
                'auto_registered' => $input['auto_registered'] ?? false,
                'manually_registered' => $input['manually_registered'] ?? false,
                'force_registered' => $forceRegistration
            ];
            
            // Log registration attempt
            error_log("Device registration attempt - User: " . ($user_id ?: 'guest') . ", Token: " . $device_token . ", Force: " . ($forceRegistration ? 'yes' : 'no'));
            
            // FORCEFUL REGISTRATION - try multiple methods
            $result = $this->forcefulDeviceRegistration($user_id, $device_token, $device_data);
            
            if ($result['success']) {
                error_log("Device registered SUCCESSFULLY - User: " . ($user_id ?: 'guest') . ", Token: " . $device_token . ", Method: " . $result['method']);
                
                // Set session flags
                $_SESSION['push_auto_registered'] = true;
                $_SESSION['push_notification_enabled'] = true;
                $_SESSION['device_registration_forced'] = $forceRegistration;
                
                // Set default notification preferences if not set
                $this->setDefaultNotificationPreferences($user_id, $session_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Device registered successfully',
                    'user_id' => $user_id,
                    'device_token' => $device_token,
                    'force_registered' => $forceRegistration,
                    'registration_method' => $result['method']
                ]);
            } else {
                throw new Exception('Failed to register device after multiple attempts: ' . $result['error']);
            }
            
        } catch (Exception $e) {
            error_log("Device registration error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => [
                    'user_id' => $_SESSION['customer_id'] ?? null,
                    'session_id' => session_id(),
                    'input_received' => !empty($input)
                ]
            ]);
        }
        exit;
    }
    
    /**
     * FORCEFUL device registration with multiple fallback methods
     */
    private function forcefulDeviceRegistration($user_id, $device_token, $device_data) {
        $methods = [
            'standard' => 'Standard registration',
            'update_existing' => 'Update existing device',
            'create_guest' => 'Create guest registration',
            'direct_insert' => 'Direct database insert'
        ];
        
        foreach ($methods as $method => $description) {
            try {
                error_log("Trying registration method: " . $method);
                
                $result = false;
                switch ($method) {
                    case 'standard':
                        $result = $this->pushService->registerDevice($user_id, $device_token, $device_data);
                        break;
                        
                    case 'update_existing':
                        $result = $this->pushService->updateOrCreateDevice($user_id, $device_token, $device_data);
                        break;
                        
                    case 'create_guest':
                        // Force create with guest user if no user_id
                        $temp_user_id = $user_id ?: 0; // Use 0 for guest
                        $result = $this->pushService->registerDevice($temp_user_id, $device_token, $device_data);
                        break;
                        
                    case 'direct_insert':
                        $result = $this->directDeviceInsert($user_id, $device_token, $device_data);
                        break;
                }
                
                if ($result) {
                    return ['success' => true, 'method' => $method];
                }
                
            } catch (Exception $e) {
                error_log("Registration method {$method} failed: " . $e->getMessage());
                continue; // Try next method
            }
        }
        
        return [
            'success' => false, 
            'error' => 'All registration methods failed',
            'methods_tried' => array_keys($methods)
        ];
    }
    
    /**
     * Direct database insert as last resort
     */
    private function directDeviceInsert($user_id, $device_token, $device_data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO push_notification_devices 
                (user_id, device_token, device_type, browser_name, browser_version, 
                 platform, user_agent, ip_address, subscription_data, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                user_id = VALUES(user_id),
                device_type = VALUES(device_type),
                browser_name = VALUES(browser_name),
                browser_version = VALUES(browser_version),
                platform = VALUES(platform),
                user_agent = VALUES(user_agent),
                ip_address = VALUES(ip_address),
                subscription_data = VALUES(subscription_data),
                is_active = 1,
                updated_at = NOW()
            ");
            
            $subscription_data = isset($device_data['subscription_data']) ? 
                json_encode($device_data['subscription_data']) : null;
            
            return $stmt->execute([
                $user_id,
                $device_token,
                $device_data['device_type'] ?? 'web',
                $device_data['browser_name'] ?? null,
                $device_data['browser_version'] ?? null,
                $device_data['platform'] ?? null,
                $device_data['user_agent'] ?? null,
                $device_data['ip_address'] ?? null,
                $subscription_data
            ]);
            
        } catch (Exception $e) {
            error_log("Direct device insert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set default notification preferences for new users
     */
    private function setDefaultNotificationPreferences($user_id, $session_id) {
        try {
            $defaultPreferences = [
                'push_notifications_enabled' => 1,
                'main_notification_enabled' => 1,
                'offers_notifications' => 1,
                'delivery_tracking_notifications' => 1,
                'system_notifications' => 1
            ];
            
            // Check if preferences already exist
            $check_stmt = $this->db->prepare("
                SELECT id FROM user_notification_preferences 
                WHERE (user_id = ? OR session_id = ?) 
                LIMIT 1
            ");
            $check_stmt->execute([$user_id, $session_id]);
            
            if ($check_stmt->rowCount() === 0) {
                // Insert default preferences
                $insert_stmt = $this->db->prepare("
                    INSERT INTO user_notification_preferences 
                    (user_id, session_id, push_notifications_enabled, main_notification_enabled, 
                     offers_notifications, delivery_tracking_notifications, system_notifications, 
                     created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $insert_stmt->execute([
                    $user_id,
                    $session_id,
                    $defaultPreferences['push_notifications_enabled'],
                    $defaultPreferences['main_notification_enabled'],
                    $defaultPreferences['offers_notifications'],
                    $defaultPreferences['delivery_tracking_notifications'],
                    $defaultPreferences['system_notifications']
                ]);
                
                error_log("Default notification preferences set for user: " . ($user_id ?: 'guest'));
            }
        } catch (Exception $e) {
            error_log("Error setting default notification preferences: " . $e->getMessage());
        }
    }
    
    /**
     * API: Unregister device for push notifications
     */
    public function unregisterDevice() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $user_id = $_SESSION['customer_id'] ?? null;
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                throw new Exception('No input data received');
            }
            
            $device_token = $input['device_token'] ?? null;
            if (!$device_token) {
                throw new Exception('Device token is required');
            }
            
            $result = $this->pushService->unregisterDevice(
                $user_id,
                $device_token
            );
            
            if ($result) {
                // Clear session flags
                unset($_SESSION['push_auto_registered']);
                unset($_SESSION['push_notification_enabled']);
                unset($_SESSION['device_registration_forced']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Device unregistered successfully'
                ]);
            } else {
                throw new Exception('Failed to unregister device');
            }
            
        } catch (Exception $e) {
            error_log("Device unregistration error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Get device registration status
     */
    public function getDeviceStatus() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $user_id = $_SESSION['customer_id'] ?? null;
            $session_id = session_id();
            
            $device_token = $_GET['device_token'] ?? null;
            
            if (!$device_token) {
                throw new Exception('Device token is required');
            }
            
            $stmt = $this->db->prepare("
                SELECT id, user_id, is_active, created_at, updated_at 
                FROM push_notification_devices 
                WHERE device_token = ? AND (user_id = ? OR user_id IS NULL OR user_id = 0)
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$device_token, $user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device) {
                echo json_encode([
                    'success' => true,
                    'registered' => true,
                    'active' => (bool)$device['is_active'],
                    'user_id' => $device['user_id'],
                    'registered_at' => $device['created_at']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'registered' => false
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Device status check error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Show notification settings page
     */
    public function settings() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $preferences = $this->notificationModel->getPreferences($user_id);
        
        // Get device status
        $devices = $this->getUserDevices($user_id);
        
        $success_message = $_SESSION['success_message'] ?? '';
        $error_message = $_SESSION['error_message'] ?? '';
        
        unset($_SESSION['success_message'], $_SESSION['error_message']);
        
        return [
            'page_title' => LanguageHelper::t('notification_settings', 'Notification Settings') . ' - Phool Delivery',
            'preferences' => $preferences,
            'devices' => $devices,
            'success_message' => $success_message,
            'error_message' => $error_message,
            'push_auto_registered' => $_SESSION['push_auto_registered'] ?? false,
            'push_notification_enabled' => $_SESSION['push_notification_enabled'] ?? false,
            'device_registration_forced' => $_SESSION['device_registration_forced'] ?? false
        ];
    }
    
    /**
     * Get user's registered devices
     */
    private function getUserDevices($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, device_token, device_type, browser_name, platform, 
                       is_active, created_at, updated_at 
                FROM push_notification_devices 
                WHERE user_id = ? 
                ORDER BY updated_at DESC
            ");
            
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user devices: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update notification settings - ENHANCED with FORCEFUL device registration
     */
    public function updateSettings() {
        if (!isset($_SESSION['customer_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please login to update notification settings']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }
        
        try {
            $user_id = $_SESSION['customer_id'];
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input)) {
                echo json_encode(['success' => false, 'message' => 'No data received']);
                return;
            }
            
            $preferences = [
                'push_notifications_enabled' => boolval($input['push_notifications_enabled'] ?? true),
                'main_notification_enabled' => boolval($input['main_notification_enabled'] ?? true),
                'offers_notifications' => boolval($input['offers_notifications'] ?? true),
                'delivery_tracking_notifications' => boolval($input['delivery_tracking_notifications'] ?? true),
                'system_notifications' => boolval($input['system_notifications'] ?? true)
            ];
            
            // Save to database
            $result = $this->notificationModel->savePreferences($user_id, null, $preferences);
            
            if ($result) {
                // Update session
                $_SESSION['user_notification_preferences'] = $preferences;
                $_SESSION['notification_preference_set'] = true;
                
                // FORCEFUL DEVICE REGISTRATION when preferences are saved
                $this->forceDeviceRegistrationOnSave($user_id, $input);
                
                echo json_encode([
                    'success' => true, 
                    'message' => LanguageHelper::t('notification_preferences_updated', 'Notification preferences updated successfully'),
                    'preferences' => $preferences,
                    'device_registered' => $_SESSION['device_registration_forced'] ?? false
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update notification preferences']);
            }
            
        } catch (Exception $e) {
            error_log("Error updating notification settings: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error updating notification preferences']);
        }
    }
    
    /**
     * FORCE device registration when preferences are saved
     */
    private function forceDeviceRegistrationOnSave($user_id, $input) {
        try {
            // Check if we have device token in session or input
            $device_token = $input['device_token'] ?? $_SESSION['pending_device_token'] ?? null;
            
            if ($device_token) {
                error_log("Forceful device registration triggered for user: " . $user_id);
                
                // Prepare device data
                $device_data = [
                    'device_type' => 'web',
                    'browser_name' => $input['browser_name'] ?? null,
                    'browser_version' => $input['browser_version'] ?? null,
                    'platform' => $input['platform'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'subscription_data' => $input['subscription_data'] ?? null,
                    'manually_registered' => true,
                    'force_registered' => true
                ];
                
                // Use forceful registration
                $result = $this->forcefulDeviceRegistration($user_id, $device_token, $device_data);
                
                if ($result['success']) {
                    error_log("Device forcefully registered during preference save");
                    $_SESSION['device_registration_forced'] = true;
                    
                    // Clear pending device token
                    unset($_SESSION['pending_device_token']);
                } else {
                    error_log("Failed to force device registration during preference save");
                    // Store device token for retry
                    $_SESSION['pending_device_token'] = $device_token;
                }
            } else {
                error_log("No device token available for forceful registration");
            }
            
        } catch (Exception $e) {
            error_log("Error in forceDeviceRegistrationOnSave: " . $e->getMessage());
        }
    }
    
    /**
     * API: Set notification preferences (for initial modal) - ENHANCED with device registration
     */
    public function setNotifications() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            
            $input = file_get_contents('php://input');
            if (empty($input)) {
                throw new Exception('No input data received');
            }
            
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input: ' . json_last_error_msg());
            }
            
            $preferences = $data['preferences'] ?? [];
            $remember = $data['remember'] ?? false;
            $device_token = $data['device_token'] ?? null;
            
            if (empty($preferences)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid notification preferences']);
                exit;
            }
            
            // Validate preferences structure
            $validPreferences = [
                'push_notifications_enabled' => boolval($preferences['push_notifications_enabled'] ?? true),
                'main_notification_enabled' => boolval($preferences['main_notification_enabled'] ?? true),
                'offers_notifications' => boolval($preferences['offers_notifications'] ?? true),
                'delivery_tracking_notifications' => boolval($preferences['delivery_tracking_notifications'] ?? true),
                'system_notifications' => boolval($preferences['system_notifications'] ?? true)
            ];
            
            // Set notification preferences in session
            $_SESSION['user_notification_preferences'] = $validPreferences;
            $_SESSION['notification_preference_shown'] = true;
            $_SESSION['notification_preference_set'] = true;
            $_SESSION['show_notification_modal'] = false;
            
            // Save to database if user is logged in and wants to remember
            $userId = $_SESSION['customer_id'] ?? null;
            $sessionId = session_id();
            
            if ($remember && $this->notificationModel) {
                if ($userId) {
                    $this->notificationModel->savePreferences($userId, null, $validPreferences);
                } else {
                    $this->notificationModel->savePreferences(null, $sessionId, $validPreferences);
                }
            }
            
            // FORCEFUL DEVICE REGISTRATION when notification preferences are set
            if ($device_token) {
                $this->forceDeviceRegistrationWithPreferences($userId, $device_token, $validPreferences);
            } else {
                // Store device token for later registration
                $_SESSION['pending_device_token'] = $device_token;
            }
            
            error_log("Notification preferences set for user: " . ($userId ? $userId : 'guest'));
            
            $response = [
                'success' => true, 
                'message' => 'Notification preferences saved successfully',
                'preferences' => $validPreferences,
                'device_registered' => $_SESSION['device_registration_forced'] ?? false
            ];
            
            if ($this->pathConfig) {
                $response['base_url'] = $this->pathConfig->get('base_url');
            }
            
            echo json_encode($response);
            exit;
            
        } catch (Exception $e) {
            error_log("Notification preference setting error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error setting notification preferences: ' . $e->getMessage()]);
            exit;
        }
    }
    
    /**
     * Force device registration with preferences
     */
    private function forceDeviceRegistrationWithPreferences($user_id, $device_token, $preferences) {
        try {
            if ($preferences['push_notifications_enabled'] && $device_token) {
                error_log("Forceful device registration with preferences for user: " . ($user_id ?: 'guest'));
                
                $device_data = [
                    'device_type' => 'web',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'manually_registered' => true,
                    'force_registered' => true
                ];
                
                $result = $this->forcefulDeviceRegistration($user_id, $device_token, $device_data);
                
                if ($result['success']) {
                    $_SESSION['device_registration_forced'] = true;
                    error_log("Device registered successfully with preferences");
                } else {
                    error_log("Failed to register device with preferences");
                }
            }
        } catch (Exception $e) {
            error_log("Error in forceDeviceRegistrationWithPreferences: " . $e->getMessage());
        }
    }
    
    /**
     * API: Skip notifications (for initial modal)
     */
    public function skipNotifications() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        $_SESSION['notification_preference_shown'] = true;
        $_SESSION['notification_preference_set'] = false;
        $_SESSION['show_notification_modal'] = false;
        
        $response = [
            'success' => true, 
            'message' => 'Notification selection skipped'
        ];
        
        if ($this->pathConfig) {
            $response['base_url'] = $this->pathConfig->get('base_url');
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * API: Get current notifications
     */
    public function getCurrentNotifications() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        $currentPreferences = $_SESSION['user_notification_preferences'] ?? [
            'push_notifications_enabled' => true,
            'main_notification_enabled' => true,
            'offers_notifications' => true,
            'delivery_tracking_notifications' => true,
            'system_notifications' => true
        ];
        
        $response = [
            'success' => true,
            'preferences' => $currentPreferences,
            'has_preference' => isset($_SESSION['notification_preference_set']) && $_SESSION['notification_preference_set'] === true,
            'push_auto_registered' => $_SESSION['push_auto_registered'] ?? false,
            'push_enabled' => $_SESSION['push_notification_enabled'] ?? false,
            'device_registration_forced' => $_SESSION['device_registration_forced'] ?? false,
            'pending_device_token' => isset($_SESSION['pending_device_token'])
        ];
        
        if ($this->pathConfig) {
            $response['base_url'] = $this->pathConfig->get('base_url');
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Transfer session preferences to user (called after login/registration)
     */
    public function transferSessionPreferencesToUser($user_id, $session_id) {
        if ($this->notificationModel) {
            $result = $this->notificationModel->transferSessionPreferencesToUser($user_id, $session_id);
            
            // Also transfer any pending device registration
            if (isset($_SESSION['pending_device_token'])) {
                $this->forceDeviceRegistrationOnSave($user_id, [
                    'device_token' => $_SESSION['pending_device_token']
                ]);
            }
            
            // Transfer guest devices to user
            if ($this->pushService) {
                $this->pushService->transferGuestDevicesToUser($user_id, $session_id);
            }
            
            return $result;
        }
        return false;
    }
    
    /**
     * API: Get VAPID public key
     */
    public function getVapidKey() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $publicKey = $this->pushService->getVapidPublicKey();
            
            if ($publicKey) {
                echo json_encode([
                    'success' => true,
                    'publicKey' => $publicKey
                ]);
            } else {
                throw new Exception('VAPID keys not configured');
            }
            
        } catch (Exception $e) {
            error_log("VAPID key error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Send order status update notification
     */
    public function sendOrderStatusUpdate($order_id, $status) {
        try {
            // Get order details
            $order_stmt = $this->db->prepare("
                SELECT o.*, c.name as customer_name, c.id as customer_id 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?
            ");
            $order_stmt->execute([$order_id]);
            $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                error_log("Order not found for notification: " . $order_id);
                return false;
            }
            
            $status_messages = [
                'confirmed' => 'Your order has been confirmed and is being processed.',
                'preparing' => 'Your order is being prepared for delivery.',
                'out_for_delivery' => 'Your order is out for delivery!',
                'delivered' => 'Your order has been delivered. Thank you for shopping with us!',
                'cancelled' => 'Your order has been cancelled.'
            ];
            
            $message = $status_messages[$status] ?? "Your order status has been updated to: " . ucfirst($status);
            
            // Send push notification
            $result = $this->pushService->sendToUsers(
                $order['customer_id'],
                "Order #{$order['order_number']} Update",
                $message,
                [
                    'url' => $this->pathConfig->url('account/orders'),
                    'order_id' => $order_id,
                    'order_number' => $order['order_number'],
                    'type' => 'order_update'
                ],
                'order_update'
            );
            
            error_log("Order status notification sent for order #{$order_id}, status: {$status}, result: " . ($result ? 'success' : 'failed'));
            
            return $result;
        } catch (Exception $e) {
            error_log("Error sending order status update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * API: Update push subscription (for subscription changes)
     */
    public function updateSubscription() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $old_endpoint = $input['old_endpoint'] ?? null;
            $new_subscription = $input['new_subscription'] ?? null;
            
            if (!$old_endpoint || !$new_subscription) {
                throw new Exception('Old endpoint and new subscription are required');
            }
            
            // Update device token in database
            $stmt = $this->db->prepare("
                UPDATE push_notification_devices 
                SET device_token = ?, subscription_data = ?, updated_at = NOW()
                WHERE device_token = ?
            ");
            
            $new_endpoint = $new_subscription['endpoint'] ?? null;
            $subscription_data = json_encode($new_subscription);
            
            $result = $stmt->execute([$new_endpoint, $subscription_data, $old_endpoint]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Subscription updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update subscription');
            }
            
        } catch (Exception $e) {
            error_log("Subscription update error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Force register device (new endpoint for explicit forceful registration)
     */
    public function forceRegisterDevice() {
        // Call registerDevice with force flag
        $this->registerDevice(true);
    }
    
    /**
     * API: Auto-enable notifications (new endpoint for automatic enablement)
     */
    public function autoEnable() {
        $this->autoEnableNotifications();
    }
    
    /**
     * Log notification delivery
     */
    public function logNotificationDelivery() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $notification_id = $input['notification_id'] ?? null;
            $device_id = $input['device_id'] ?? null;
            $delivered_at = $input['delivered_at'] ?? null;
            
            if (!$notification_id || !$device_id) {
                throw new Exception('Notification ID and Device ID are required');
            }
            
            // Log delivery in database
            $stmt = $this->db->prepare("
                INSERT INTO push_notifications_log 
                (notification_id, device_id, delivered_at, status, created_at)
                VALUES (?, ?, ?, 'delivered', NOW())
                ON DUPLICATE KEY UPDATE 
                delivered_at = VALUES(delivered_at),
                status = 'delivered',
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([$notification_id, $device_id, $delivered_at]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Delivery logged successfully'
                ]);
            } else {
                throw new Exception('Failed to log delivery');
            }
            
        } catch (Exception $e) {
            error_log("Notification delivery logging error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationRead() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $notification_id = $input['notification_id'] ?? null;
            $device_id = $input['device_id'] ?? null;
            $read_at = $input['read_at'] ?? null;
            
            if (!$notification_id || !$device_id) {
                throw new Exception('Notification ID and Device ID are required');
            }
            
            // Mark as read in database
            $stmt = $this->db->prepare("
                UPDATE push_notifications_log 
                SET read_at = ?, status = 'read', updated_at = NOW()
                WHERE notification_id = ? AND device_id = ?
            ");
            
            $result = $stmt->execute([$read_at, $notification_id, $device_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification marked as read'
                ]);
            } else {
                throw new Exception('Failed to mark notification as read');
            }
            
        } catch (Exception $e) {
            error_log("Notification read marking error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}
?>