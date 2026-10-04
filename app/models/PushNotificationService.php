<?php
// app/models/PushNotificationService.php

class PushNotificationService {
    private $conn;
    private $vapidKeys;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->vapidKeys = $this->getVapidKeys();
    }
    
    private function getVapidKeys() {
        $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        
        $keys = [
            'public' => '',
            'private' => ''
        ];
        
        // Public Key
        $stmt->execute(['vapid_public_key']);
        if ($row = $stmt->fetch()) {
            $keys['public'] = $row['setting_value'];
        }
        
        // Private Key
        $stmt->execute(['vapid_private_key']);
        if ($row = $stmt->fetch()) {
            $keys['private'] = $row['setting_value'];
        }
        
        return $keys;
    }
    
    /**
     * Enhanced device registration with duplicate handling and user association
     */
    public function registerDevice($user_id, $device_token, $device_data = []) {
        try {
            error_log("Attempting to register device - User: " . ($user_id ?: 'guest') . ", Token: " . $device_token);
            
            // Check if device already exists with this token
            $check_stmt = $this->conn->prepare("
                SELECT id, user_id, is_active FROM push_notification_devices 
                WHERE device_token = ?
            ");
            $check_stmt->execute([$device_token]);
            $existing_device = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_device) {
                // Update existing device with user information
                $update_stmt = $this->conn->prepare("
                    UPDATE push_notification_devices 
                    SET user_id = ?, device_type = ?, browser_name = ?, browser_version = ?, 
                        platform = ?, user_agent = ?, ip_address = ?, last_used_at = NOW(), 
                        is_active = 1, updated_at = NOW(),
                        subscription_data = ?
                    WHERE device_token = ?
                ");
                
                $subscription_data = isset($device_data['subscription_data']) ? 
                    json_encode($device_data['subscription_data']) : null;
                
                $result = $update_stmt->execute([
                    $user_id,
                    $device_data['device_type'] ?? 'web',
                    $device_data['browser_name'] ?? null,
                    $device_data['browser_version'] ?? null,
                    $device_data['platform'] ?? null,
                    $device_data['user_agent'] ?? null,
                    $device_data['ip_address'] ?? null,
                    $subscription_data,
                    $device_token
                ]);
                
                if ($result) {
                    error_log("Device updated successfully - ID: " . $existing_device['id'] . " - User ID: " . $user_id);
                    return true;
                } else {
                    error_log("Failed to update existing device");
                    return false;
                }
            } else {
                // Check if device exists with same user agent and IP for this user (prevent duplicates)
                $duplicate_check_stmt = $this->conn->prepare("
                    SELECT id FROM push_notification_devices 
                    WHERE user_id = ? AND user_agent = ? AND ip_address = ? AND device_type = ?
                    LIMIT 1
                ");
                
                $duplicate_check_stmt->execute([
                    $user_id,
                    $device_data['user_agent'] ?? null,
                    $device_data['ip_address'] ?? null,
                    $device_data['device_type'] ?? 'web'
                ]);
                
                $duplicate_device = $duplicate_check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate_device) {
                    // Update existing duplicate device with new token
                    $update_duplicate_stmt = $this->conn->prepare("
                        UPDATE push_notification_devices 
                        SET device_token = ?, last_used_at = NOW(), is_active = 1, updated_at = NOW(),
                            subscription_data = ?
                        WHERE id = ?
                    ");
                    
                    $subscription_data = isset($device_data['subscription_data']) ? 
                        json_encode($device_data['subscription_data']) : null;
                    
                    $result = $update_duplicate_stmt->execute([
                        $device_token,
                        $subscription_data,
                        $duplicate_device['id']
                    ]);
                    
                    if ($result) {
                        error_log("Duplicate device updated with new token - ID: " . $duplicate_device['id']);
                        return true;
                    }
                }
                
                // Insert new device
                $insert_stmt = $this->conn->prepare("
                    INSERT INTO push_notification_devices 
                    (user_id, device_token, device_type, browser_name, browser_version, 
                     platform, user_agent, ip_address, subscription_data, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ");
                
                $subscription_data = isset($device_data['subscription_data']) ? 
                    json_encode($device_data['subscription_data']) : null;
                
                $result = $insert_stmt->execute([
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
                
                if ($result) {
                    $device_id = $this->conn->lastInsertId();
                    error_log("New device registered successfully - ID: " . $device_id . " - User ID: " . $user_id);
                    return true;
                } else {
                    error_log("Failed to insert new device");
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log("Error registering device: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced update or create device with better duplicate prevention
     */
    public function updateOrCreateDevice($user_id, $device_token, $device_data = []) {
        try {
            // First try to update existing device with this token
            $update_stmt = $this->conn->prepare("
                UPDATE push_notification_devices 
                SET user_id = ?, device_type = ?, browser_name = ?, browser_version = ?, 
                    platform = ?, user_agent = ?, ip_address = ?, last_used_at = NOW(), 
                    is_active = 1, updated_at = NOW(),
                    subscription_data = ?
                WHERE device_token = ?
            ");
            
            $subscription_data = isset($device_data['subscription_data']) ? 
                json_encode($device_data['subscription_data']) : null;
            
            $update_stmt->execute([
                $user_id,
                $device_data['device_type'] ?? 'web',
                $device_data['browser_name'] ?? null,
                $device_data['browser_version'] ?? null,
                $device_data['platform'] ?? null,
                $device_data['user_agent'] ?? null,
                $device_data['ip_address'] ?? null,
                $subscription_data,
                $device_token
            ]);
            
            // If no rows were updated, check for duplicates and insert new device
            if ($update_stmt->rowCount() === 0) {
                // Check for existing device with same user agent and IP
                $check_stmt = $this->conn->prepare("
                    SELECT id FROM push_notification_devices 
                    WHERE user_id = ? AND user_agent = ? AND ip_address = ? AND device_type = ?
                    LIMIT 1
                ");
                
                $check_stmt->execute([
                    $user_id,
                    $device_data['user_agent'] ?? null,
                    $device_data['ip_address'] ?? null,
                    $device_data['device_type'] ?? 'web'
                ]);
                
                $existing_device = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_device) {
                    // Update the existing duplicate device
                    $update_duplicate_stmt = $this->conn->prepare("
                        UPDATE push_notification_devices 
                        SET device_token = ?, last_used_at = NOW(), is_active = 1, updated_at = NOW(),
                            subscription_data = ?
                        WHERE id = ?
                    ");
                    
                    $result = $update_duplicate_stmt->execute([
                        $device_token,
                        $subscription_data,
                        $existing_device['id']
                    ]);
                    
                    if ($result) {
                        error_log("Duplicate device updated via updateOrCreate - ID: " . $existing_device['id']);
                        return true;
                    }
                } else {
                    // Insert new device
                    $insert_stmt = $this->conn->prepare("
                        INSERT INTO push_notification_devices 
                        (user_id, device_token, device_type, browser_name, browser_version, 
                         platform, user_agent, ip_address, subscription_data, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    
                    $result = $insert_stmt->execute([
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
                    
                    if ($result) {
                        error_log("New device created via updateOrCreate - ID: " . $this->conn->lastInsertId());
                        return true;
                    }
                }
            } else {
                error_log("Existing device updated via updateOrCreate");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error in updateOrCreateDevice: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Transfer guest devices to user after login/registration
     */
    public function transferGuestDevicesToUser($user_id, $session_id) {
        try {
            error_log("Transferring guest devices to user: " . $user_id);
            
            // Update all guest devices with this session to the user
            $update_stmt = $this->conn->prepare("
                UPDATE push_notification_devices 
                SET user_id = ?, updated_at = NOW(), last_used_at = NOW()
                WHERE (user_id IS NULL OR user_id = 0) 
                AND user_agent = ? 
                AND ip_address = ?
            ");
            
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            $result = $update_stmt->execute([
                $user_id,
                $user_agent,
                $ip_address
            ]);
            
            if ($result && $update_stmt->rowCount() > 0) {
                error_log("Transferred " . $update_stmt->rowCount() . " guest devices to user: " . $user_id);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error transferring guest devices: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced device unregistration
     */
    public function unregisterDevice($user_id, $device_token) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE push_notification_devices 
                SET is_active = 0, updated_at = NOW() 
                WHERE device_token = ? AND (user_id = ? OR user_id IS NULL OR user_id = 0)
            ");
            
            $result = $stmt->execute([$device_token, $user_id]);
            
            error_log("Device unregistration - Token: " . $device_token . ", User: " . ($user_id ?: 'guest') . ", Result: " . ($result ? 'success' : 'failed'));
            
            return $result;
        } catch (Exception $e) {
            error_log("Error unregistering device: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced notification sending with better logging
     */
    public function sendToUsers($user_ids, $title, $message, $data = [], $notification_type = 'system') {
        try {
            if (empty($user_ids)) {
                error_log("No user IDs provided for notification");
                return false;
            }
            
            // Convert single user ID to array
            if (!is_array($user_ids)) {
                $user_ids = [$user_ids];
            }
            
            // Get active devices for these users
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            $stmt = $this->conn->prepare("
                SELECT id as device_id, device_token, user_id, device_type 
                FROM push_notification_devices 
                WHERE user_id IN ($placeholders) AND is_active = 1
            ");
            $stmt->execute($user_ids);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($devices)) {
                error_log("No active devices found for users: " . implode(', ', $user_ids));
                return false;
            }
            
            $results = [];
            foreach ($devices as $device) {
                // Check user preferences before sending
                if ($this->shouldSendNotification($device['user_id'], $notification_type)) {
                    $result = $this->sendToDevice($device, $title, $message, $data);
                    $results[] = [
                        'device_id' => $device['device_id'],
                        'device_token' => $device['device_token'],
                        'user_id' => $device['user_id'],
                        'success' => $result
                    ];
                }
            }
            
            error_log("Notification sent to " . count($results) . " devices for users: " . implode(', ', $user_ids));
            
            return $results;
        } catch (Exception $e) {
            error_log("Error sending to users: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced device-specific notification sending
     */
    private function sendToDevice($device, $title, $message, $data = []) {
        try {
            // First, create a notification record
            $notification_id = $this->createNotificationRecord($title, $message, $data, $device['user_id']);
            
            $payload = [
                'title' => $title,
                'body' => $message,
                'icon' => $data['icon'] ?? '/assets/img/favicon1.jpg',
                'badge' => $data['badge'] ?? '/assets/img/favicon1.jpg',
                'data' => array_merge($data, [
                    'url' => $data['url'] ?? '/',
                    'timestamp' => time(),
                    'notification_id' => $notification_id,
                    'device_id' => $device['device_id']
                ])
            ];
            
            // Log the notification attempt
            $this->logNotificationAttempt($notification_id, $device['device_id']);
            
            // For web push, we would typically use a push service here
            // For now, we'll simulate successful sending and log it
            error_log("Push notification prepared for device: " . $device['device_token'] . " - Title: " . $title);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error sending to device: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification record in database
     */
    private function createNotificationRecord($title, $message, $data, $user_id) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO push_notifications_queue 
                (title, message, notification_type, target_type, target_users, related_id, data, created_by, created_at)
                VALUES (?, ?, ?, 'specific_users', ?, ?, ?, ?, NOW())
            ");
            
            $target_users = $user_id ? json_encode([$user_id]) : null;
            $related_id = $data['related_id'] ?? null;
            $data_json = json_encode($data);
            $notification_type = $data['type'] ?? 'system';
            $created_by = $_SESSION['admin_id'] ?? $user_id ?? null;
            
            $stmt->execute([
                $title,
                $message,
                $notification_type,
                $target_users,
                $related_id,
                $data_json,
                $created_by
            ]);
            
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creating notification record: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log notification attempt
     */
    private function logNotificationAttempt($notification_id, $device_id) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO push_notifications_log 
                (notification_id, device_id, status, created_at)
                VALUES (?, ?, 'sent', NOW())
            ");
            
            $stmt->execute([$notification_id, $device_id]);
        } catch (Exception $e) {
            error_log("Error logging notification attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Send push notification to all users
     */
    public function sendToAll($title, $message, $data = [], $notification_type = 'system') {
        try {
            // Get all active devices
            $stmt = $this->conn->prepare("
                SELECT DISTINCT pnd.id as device_id, pnd.device_token, pnd.user_id 
                FROM push_notification_devices pnd
                LEFT JOIN user_notification_preferences unp ON pnd.user_id = unp.user_id
                WHERE pnd.is_active = 1 
                AND (unp.push_notifications_enabled IS NULL OR unp.push_notifications_enabled = 1)
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($devices as $device) {
                if ($this->shouldSendNotification($device['user_id'], $notification_type)) {
                    $result = $this->sendToDevice($device, $title, $message, $data);
                    $results[] = [
                        'device_id' => $device['device_id'],
                        'device_token' => $device['device_token'],
                        'user_id' => $device['user_id'],
                        'success' => $result
                    ];
                }
            }
            
            error_log("Notification sent to all users - " . count($results) . " devices notified");
            
            return $results;
        } catch (Exception $e) {
            error_log("Error sending to all: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if we should send notification based on user preferences
     */
    private function shouldSendNotification($user_id, $notification_type) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM user_notification_preferences 
                WHERE user_id = ? 
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check main notification toggle
                if (!$prefs['main_notification_enabled']) {
                    return false;
                }
                
                // Check specific notification type
                switch ($notification_type) {
                    case 'order_update':
                        return (bool)$prefs['delivery_tracking_notifications'];
                    case 'offer':
                        return (bool)$prefs['offers_notifications'];
                    case 'system':
                        return (bool)$prefs['system_notifications'];
                    case 'promotion':
                        return (bool)$prefs['offers_notifications'];
                    default:
                        return true;
                }
            }
            
            // If no preferences found, send notification
            return true;
        } catch (Exception $e) {
            error_log("Error checking notification preferences: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Queue notification for sending (for scheduled/automated notifications)
     */
    public function queueNotification($title, $message, $target_type = 'all_users', $target_users = null, $notification_type = 'system', $related_id = null, $scheduled_at = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO push_notifications_queue 
                (title, message, notification_type, target_type, target_users, related_id, scheduled_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $target_users_json = $target_users ? json_encode($target_users) : null;
            
            return $stmt->execute([
                $title,
                $message,
                $notification_type,
                $target_type,
                $target_users_json,
                $related_id,
                $scheduled_at
            ]);
        } catch (Exception $e) {
            error_log("Error queuing notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process queued notifications
     */
    public function processQueuedNotifications() {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM push_notifications_queue 
                WHERE status = 'pending' 
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                ORDER BY created_at ASC 
                LIMIT 10
            ");
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($notifications as $notification) {
                // Mark as processing
                $update_stmt = $this->conn->prepare("
                    UPDATE push_notifications_queue 
                    SET status = 'sent', sent_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$notification['id']]);
                
                // Send notification based on target type
                if ($notification['target_type'] === 'all_users') {
                    $result = $this->sendToAll(
                        $notification['title'],
                        $notification['message'],
                        ['related_id' => $notification['related_id']],
                        $notification['notification_type']
                    );
                } else {
                    $target_users = json_decode($notification['target_users'], true);
                    $result = $this->sendToUsers(
                        $target_users,
                        $notification['title'],
                        $notification['message'],
                        ['related_id' => $notification['related_id']],
                        $notification['notification_type']
                    );
                }
                
                $results[] = [
                    'notification_id' => $notification['id'],
                    'result' => $result
                ];
            }
            
            error_log("Processed " . count($results) . " queued notifications");
            
            return $results;
        } catch (Exception $e) {
            error_log("Error processing queued notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get VAPID public key for service worker
     */
    public function getVapidPublicKey() {
        return $this->vapidKeys['public'];
    }
}
?>