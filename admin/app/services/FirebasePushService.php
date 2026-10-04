<?php
// admin/app/services/FirebasePushService.php

require_once __DIR__ . '/../config/firebase-config.php';

class FirebasePushService {
    private $conn;
    private $firebaseConfig;
    private $accessToken;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->firebaseConfig = FirebaseConfig::getInstance();
    }
    
    /**
     * Send order status update notification to customer
     */
    public function sendOrderStatusUpdate($order_id, $new_status, $admin_notes = '') {
        try {
            // Get order details with customer information
            $stmt = $this->conn->prepare("
                SELECT o.*, c.id as customer_id, c.name as customer_name, 
                       c.phone, c.email, o.order_number, o.total_amount
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                error_log("[Firebase] Order not found: " . $order_id);
                return false;
            }
            
            if (!$order['customer_id']) {
                error_log("[Firebase] No customer found for order: " . $order_id);
                return false;
            }
            
            // Prepare notification message
            $notification_data = $this->getOrderStatusNotificationData($order, $new_status, $admin_notes);
            
            // Send push notification
            $result = $this->sendToCustomer(
                $order['customer_id'],
                $notification_data['title'],
                $notification_data['message'],
                $notification_data['data']
            );
            
            // Log the notification attempt
            $this->logNotification(
                $order['customer_id'],
                'order_status_update',
                $notification_data['title'],
                $order_id,
                $result ? 'sent' : 'failed'
            );
            
            error_log("[Firebase] Order status notification for order #" . $order['order_number'] . 
                     " - Status: " . $new_status . " - Result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[Firebase] Error sending order status update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification data for order status updates
     */
    private function getOrderStatusNotificationData($order, $new_status, $admin_notes) {
        $status_messages = [
            'confirmed' => [
                'title' => '✅ Order Confirmed #' . $order['order_number'],
                'message' => 'Your order has been confirmed and is being processed.',
            ],
            'preparing' => [
                'title' => '👨‍🍳 Preparing Your Order',
                'message' => 'Your order #' . $order['order_number'] . ' is now being prepared.',
            ],
            'out_for_delivery' => [
                'title' => '🚚 Out for Delivery',
                'message' => 'Your order is on the way! Track your delivery in real-time.',
            ],
            'delivered' => [
                'title' => '📦 Order Delivered',
                'message' => 'Your order #' . $order['order_number'] . ' has been successfully delivered.',
            ],
            'cancelled' => [
                'title' => '❌ Order Cancelled',
                'message' => 'Your order #' . $order['order_number'] . ' has been cancelled.',
            ]
        ];
        
        $message_data = $status_messages[$new_status] ?? [
            'title' => 'Order Status Update',
            'message' => 'Your order #' . $order['order_number'] . ' status has been updated.',
        ];
        
        // Add admin notes if provided
        if ($admin_notes && in_array($new_status, ['cancelled', 'delivered'])) {
            $message_data['message'] .= ' Note: ' . $admin_notes;
        }
        
        return [
            'title' => $message_data['title'],
            'message' => $message_data['message'],
            'data' => [
                'type' => 'order_update',
                'order_id' => (string)$order['id'],
                'order_number' => $order['order_number'],
                'status' => $new_status,
                'url' => '/account/orders',
                'timestamp' => (string)time(),
                'click_action' => '/account/orders'
            ]
        ];
    }
    
    /**
     * Send notification to customer
     */
    private function sendToCustomer($customer_id, $title, $body, $data = []) {
        try {
            // Get customer's active devices
            $stmt = $this->conn->prepare("
                SELECT device_token, device_type 
                FROM push_notification_devices 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$customer_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($devices)) {
                error_log("[Firebase] No active devices found for customer: " . $customer_id);
                return false;
            }
            
            $success_count = 0;
            foreach ($devices as $device) {
                $result = $this->sendFcmMessage($device['device_token'], $title, $body, $data);
                if ($result) {
                    $success_count++;
                }
            }
            
            error_log("[Firebase] Sent to " . $success_count . "/" . count($devices) . " devices for customer: " . $customer_id);
            
            return $success_count > 0;
            
        } catch (Exception $e) {
            error_log("[Firebase] Error sending to customer: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send FCM message using HTTP v1 API
     */
    private function sendFcmMessage($device_token, $title, $body, $data = []) {
        try {
            // Get access token
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                throw new Exception('Failed to get access token');
            }
            
            // Prepare FCM message
            $message = [
                'message' => [
                    'token' => $device_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => '/assets/img/favicon1.jpg',
                        'badge' => '/assets/img/favicon1.jpg'
                    ],
                    'data' => $data,
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high'
                        ],
                        'notification' => [
                            'icon' => '/assets/img/favicon1.jpg',
                            'badge' => '/assets/img/favicon1.jpg',
                            'vibrate' => [200, 100, 200],
                            'requireInteraction' => true
                        ],
                        'fcm_options' => [
                            'link' => $data['url'] ?? '/'
                        ]
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'order_updates'
                        ]
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1
                            ]
                        ]
                    ]
                ]
            ];
            
            // Send FCM request
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->firebaseConfig->getFcmUrl(),
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($message),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                if (isset($response_data['name'])) {
                    error_log("[Firebase FCM] Message sent successfully: " . $response_data['name']);
                    return true;
                }
            }
            
            error_log("[Firebase FCM] Failed to send message. HTTP: " . $http_code . " Error: " . $error . " Response: " . $response);
            return false;
            
        } catch (Exception $e) {
            error_log("[Firebase FCM] Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get Google OAuth2 access token
     */
    private function getAccessToken() {
        // Return cached token if still valid
        if ($this->accessToken && $this->accessToken['expires'] > time() + 300) {
            return $this->accessToken['token'];
        }
        
        try {
            $service_account = $this->firebaseConfig->getServiceAccount();
            
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];
            
            $now = time();
            $payload = [
                'iss' => $service_account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ];
            
            $header_encoded = $this->base64UrlEncode(json_encode($header));
            $payload_encoded = $this->base64UrlEncode(json_encode($payload));
            
            $signature_input = $header_encoded . '.' . $payload_encoded;
            $signature = $this->generateSignature($signature_input, $service_account['private_key']);
            
            $jwt = $signature_input . '.' . $this->base64UrlEncode($signature);
            
            // Exchange JWT for access token
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://oauth2.googleapis.com/token',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $token_data = json_decode($response, true);
            
            if (isset($token_data['access_token'])) {
                $this->accessToken = [
                    'token' => $token_data['access_token'],
                    'expires' => $now + 3500 // 5 minutes buffer
                ];
                return $token_data['access_token'];
            }
            
            error_log("[Firebase] Failed to get access token: " . $response);
            return null;
            
        } catch (Exception $e) {
            error_log("[Firebase] Error getting access token: " . $e->getMessage());
            return null;
        }
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function generateSignature($data, $private_key) {
        $key = openssl_pkey_get_private($private_key);
        if (!$key) {
            throw new Exception('Invalid private key');
        }
        
        openssl_sign($data, $signature, $key, 'SHA256');
        openssl_free_key($key);
        
        return $signature;
    }
    
    /**
     * Log notification
     */
    private function logNotification($customer_id, $notification_type, $title, $related_id, $status) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO admin_notification_logs 
                (customer_id, notification_type, title, related_id, status, sent_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $sent_by = $_SESSION['admin_id'] ?? null;
            
            $stmt->execute([
                $customer_id,
                $notification_type,
                $title,
                $related_id,
                $status,
                $sent_by
            ]);
            
        } catch (Exception $e) {
            error_log("[Firebase] Error logging notification: " . $e->getMessage());
        }
    }
    
    /**
     * Send bulk notification
     */
    public function sendBulkNotification($title, $message, $data = [], $notification_type = 'system') {
        try {
            // Get all active devices
            $stmt = $this->conn->prepare("
                SELECT device_token, user_id 
                FROM push_notification_devices 
                WHERE is_active = 1
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($devices)) {
                error_log("[Firebase] No active devices found for bulk notification");
                return 0;
            }
            
            $success_count = 0;
            foreach ($devices as $device) {
                if ($this->shouldSendNotification($device['user_id'], $notification_type)) {
                    $result = $this->sendFcmMessage($device['device_token'], $title, $message, $data);
                    if ($result) {
                        $success_count++;
                    }
                }
            }
            
            $this->logNotification(null, 'bulk_' . $notification_type, $title, null, 'sent_to_' . $success_count);
            
            error_log("[Firebase] Bulk notification sent to " . $success_count . " devices");
            
            return $success_count;
            
        } catch (Exception $e) {
            error_log("[Firebase] Error sending bulk notification: " . $e->getMessage());
            return 0;
        }
    }
    
    private function shouldSendNotification($user_id, $notification_type) {
        // Implementation for checking user preferences
        return true; // Simplified for now
    }
}
?>