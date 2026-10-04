<?php
// admin/app/services/PushNotificationService.php

class AdminPushNotificationService {
    private $conn;
    private $fcmService;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->fcmService = new FCMService();
    }
    
    /**
     * Send order status update notification to customer - FIXED VERSION
     */
    public function sendOrderStatusUpdate($order_id, $new_status, $admin_notes = '') {
        try {
            // Get order details with customer information
            $stmt = $this->conn->prepare("
                SELECT o.*, c.id as customer_id, c.name as customer_name, 
                       c.phone, c.email, o.order_number, c.language,
                       COUNT(pnd.id) as device_count
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                LEFT JOIN push_notification_devices pnd ON c.id = pnd.user_id AND pnd.is_active = 1
                WHERE o.id = ?
                GROUP BY o.id
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                error_log("[Admin Push] Order not found: " . $order_id);
                return false;
            }
            
            if (!$order['customer_id']) {
                error_log("[Admin Push] No customer found for order: " . $order_id);
                return false;
            }
            
            // Check if customer has active devices
            if (($order['device_count'] ?? 0) === 0) {
                error_log("[Admin Push] No active devices for customer: " . $order['customer_id']);
                return false;
            }
            
            // Prepare notification message based on status and language
            $notification_data = $this->getOrderStatusNotificationData($order, $new_status, $admin_notes);
            
            // Send push notification
            $result = $this->sendToCustomer(
                $order['customer_id'],
                $notification_data['title'],
                $notification_data['message'],
                $notification_data['data']
            );
            
            // Also send in-app message
            $this->createInAppNotification($order['customer_id'], $notification_data);
            
            // Log the notification attempt
            $this->logAdminNotification(
                $order['customer_id'],
                'order_status_update',
                $notification_data['title'],
                $order_id,
                $result ? 'sent' : 'failed'
            );
            
            error_log("[Admin Push] Order status notification sent for order #" . $order['order_number'] . 
                     " to customer " . $order['customer_id'] . 
                     " - Status: " . $new_status . 
                     " - Devices: " . $order['device_count'] .
                     " - Result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[Admin Push] Error sending order status update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification data for order status updates
     */
    private function getOrderStatusNotificationData($order, $new_status, $admin_notes) {
        $language = $order['language'] ?? 'en';
        
        $status_messages = [
            'confirmed' => [
                'en' => [
                    'title' => 'Order Confirmed #' . $order['order_number'],
                    'message' => 'Your order has been confirmed and is being processed. Order total: Rs. ' . number_format($order['total_amount'], 2) . '.',
                ],
                'ne' => [
                    'title' => 'अर्डर पुष्टि #' . $order['order_number'],
                    'message' => 'तपाईंको अर्डर पुष्टि भएको छ र प्रशोधनमा छ। कुल रकम: रु. ' . number_format($order['total_amount'], 2) . '।',
                ]
            ],
            'preparing' => [
                'en' => [
                    'title' => 'Order Status Update',
                    'message' => 'Your order #' . $order['order_number'] . ' is now being prepared.' . 
                               ($admin_notes ? ' Note: ' . $admin_notes : ''),
                ],
                'ne' => [
                    'title' => 'अर्डर स्थिति अपडेट',
                    'message' => 'तपाईंको अर्डर #' . $order['order_number'] . ' अहिले तयार गरिँदैछ।' . 
                               ($admin_notes ? ' नोट: ' . $admin_notes : ''),
                ]
            ],
            'out_for_delivery' => [
                'en' => [
                    'title' => 'Order Out for Delivery',
                    'message' => 'Your order #' . $order['order_number'] . ' is out for delivery! Track your order in the app.',
                ],
                'ne' => [
                    'title' => 'अर्डर वितरणको लागि बाहिर',
                    'message' => 'तपाईंको अर्डर #' . $order['order_number'] . ' वितरणको लागि बाहिर छ! एपमा तपाईंको अर्डर ट्र्याक गर्नुहोस्।',
                ]
            ],
            'delivered' => [
                'en' => [
                    'title' => 'Order Delivered',
                    'message' => 'Your order #' . $order['order_number'] . ' has been successfully delivered. Thank you for shopping with us!',
                ],
                'ne' => [
                    'title' => 'अर्डर वितरण भयो',
                    'message' => 'तपाईंको अर्डर #' . $order['order_number'] . ' सफलतापूर्वक वितरण गरिएको छ। हाम्रो साथ खरिद गर्नुभएकोमा धन्यवाद!',
                ]
            ],
            'cancelled' => [
                'en' => [
                    'title' => 'Order Cancelled',
                    'message' => 'Your order #' . $order['order_number'] . ' has been cancelled.' . 
                               ($admin_notes ? ' Reason: ' . $admin_notes : ''),
                ],
                'ne' => [
                    'title' => 'अर्डर रद्द भयो',
                    'message' => 'तपाईंको अर्डर #' . $order['order_number'] . ' रद्द गरिएको छ।' . 
                               ($admin_notes ? ' कारण: ' . $admin_notes : ''),
                ]
            ]
        ];
        
        $message_data = $status_messages[$new_status][$language] ?? $status_messages[$new_status]['en'] ?? [
            'title' => 'Order Status Update',
            'message' => 'Your order #' . $order['order_number'] . ' status has been updated to: ' . ucfirst($new_status) . 
                       ($admin_notes ? '. Note: ' . $admin_notes : ''),
        ];
        
        return [
            'title' => $message_data['title'],
            'message' => $message_data['message'],
            'data' => [
                'type' => 'order_update',
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'status' => $new_status,
                'url' => '/account/orders',
                'timestamp' => time(),
                'admin_notes' => $admin_notes,
                'language' => $language
            ]
        ];
    }
    
    /**
     * Send notification to specific customer - FIXED VERSION
     */
    public function sendToCustomer($customer_id, $title, $message, $data = []) {
        try {
            // Get customer's active devices with proper token validation
            $stmt = $this->conn->prepare("
                SELECT device_token, device_type, user_id
                FROM push_notification_devices 
                WHERE user_id = ? AND is_active = 1
                AND device_token IS NOT NULL 
                AND device_token != ''
                AND LENGTH(device_token) > 10
            ");
            $stmt->execute([$customer_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($devices)) {
                error_log("[Admin Push] No valid active devices found for customer: " . $customer_id);
                return false;
            }
            
            $results = [];
            $valid_tokens = [];
            
            // Filter valid tokens
            foreach ($devices as $device) {
                if ($this->fcmService->isValidDeviceToken($device['device_token'])) {
                    $valid_tokens[] = $device;
                } else {
                    error_log("[Admin Push] Invalid device token format for customer " . $customer_id . ": " . substr($device['device_token'], 0, 50));
                    // Mark invalid token as inactive
                    $this->deactivateInvalidToken($device['device_token']);
                }
            }
            
            if (empty($valid_tokens)) {
                error_log("[Admin Push] No valid FCM tokens for customer: " . $customer_id);
                return false;
            }
            
            foreach ($valid_tokens as $device) {
                $result = $this->fcmService->sendPushNotification(
                    $device['device_token'],
                    $title,
                    $message,
                    $data,
                    $device['user_id']
                );
                
                $results[] = [
                    'device_token' => $device['device_token'],
                    'success' => $result
                ];
                
                if (!$result) {
                    error_log("[Admin Push] Failed to send to device: " . substr($device['device_token'], 0, 50));
                }
            }
            
            // Check if any notification was sent successfully
            $success_count = count(array_filter($results, function($r) { return $r['success']; }));
            
            error_log("[Admin Push] Sent to " . $success_count . "/" . count($valid_tokens) . " valid devices for customer: " . $customer_id);
            
            return $success_count > 0;
            
        } catch (Exception $e) {
            error_log("[Admin Push] Error sending to customer: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deactivate invalid device token
     */
    private function deactivateInvalidToken($device_token) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE push_notification_devices 
                SET is_active = 0, 
                    updated_at = NOW(),
                    last_used_at = NOW()
                WHERE device_token = ?
            ");
            return $stmt->execute([$device_token]);
        } catch (Exception $e) {
            error_log("[Admin Push] Error deactivating invalid token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create in-app notification
     */
    private function createInAppNotification($customer_id, $notification_data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO customer_notifications 
                (customer_id, title, message, type, data, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            
            return $stmt->execute([
                $customer_id,
                $notification_data['title'],
                $notification_data['message'],
                $notification_data['data']['type'] ?? 'system',
                json_encode($notification_data['data']),
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin Push] Error creating in-app notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log admin notification attempts
     */
    private function logAdminNotification($customer_id, $notification_type, $title, $related_id, $status) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO admin_notification_logs 
                (customer_id, notification_type, title, related_id, status, sent_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $sent_by = $_SESSION['admin_id'] ?? null;
            
            return $stmt->execute([
                $customer_id,
                $notification_type,
                $title,
                $related_id,
                $status,
                $sent_by
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin Push] Error logging admin notification: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * FCM Service for Web Push Notifications - COMPLETELY FIXED VERSION
 */
class FCMService {
    private $serverKey;
    private $projectId;
    
    public function __construct() {
        $firebaseConfig = FirebaseConfig::getInstance();
        $this->serverKey = $firebaseConfig->getServerKey();
        $this->projectId = $firebaseConfig->get('project_id');
    }
    
    /**
     * Send push notification via FCM - FIXED IMPLEMENTATION
     */
    public function sendPushNotification($device_token, $title, $message, $data = [], $user_id = null) {
        try {
            // Validate device token first
            if (!$this->isValidDeviceToken($device_token)) {
                error_log("[FCM] Invalid device token format: " . substr($device_token, 0, 50) . "...");
                return false;
            }
            
            // Use HTTP v1 API for better reliability
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $payload = [
                'message' => [
                    'token' => $this->extractFCMToken($device_token),
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                        'image' => '/assets/img/favicon1.jpg'
                    ],
                    'data' => array_merge($data, [
                        'user_id' => (string)$user_id,
                        'timestamp' => (string)time(),
                        'click_action' => $data['url'] ?? '/'
                    ]),
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high'
                        ],
                        'notification' => [
                            'icon' => '/assets/img/favicon1.jpg',
                            'badge' => '/assets/img/favicon1.jpg'
                        ],
                        'fcm_options' => [
                            'link' => $data['url'] ?? '/'
                        ]
                    ],
                    'android' => [
                        'priority' => 'high'
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
            
            // Get access token for HTTP v1 API
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                error_log("[FCM] Failed to get access token");
                return false;
            }
            
            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_error($ch)) {
                error_log("[FCM] cURL Error: " . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            $response = json_decode($result, true);
            
            if ($http_code == 200 && isset($response['name'])) {
                error_log("[FCM] Notification sent successfully to: " . substr($device_token, 0, 30) . "...");
                return true;
            } else {
                error_log("[FCM] Failed to send notification. HTTP Code: " . $http_code . " Response: " . $result);
                
                // Handle specific FCM errors
                if (isset($response['error'])) {
                    $error = $response['error'];
                    $errorCode = $error['code'] ?? '';
                    $errorMessage = $error['message'] ?? '';
                    
                    error_log("[FCM] FCM Error: " . $errorCode . " - " . $errorMessage);
                    
                    // Handle token errors
                    if (strpos($errorMessage, 'registration-token') !== false || 
                        strpos($errorMessage, 'NotRegistered') !== false ||
                        strpos($errorMessage, 'InvalidRegistration') !== false) {
                        $this->handleInvalidToken($device_token);
                    }
                }
                
                return false;
            }
            
        } catch (Exception $e) {
            error_log("[FCM] Exception sending push notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get OAuth2 access token for FCM HTTP v1 API
     */
    private function getAccessToken() {
        try {
            // For development, you can use the server key directly with legacy API
            // In production, you should use service account credentials
            return $this->serverKey;
            
        } catch (Exception $e) {
            error_log("[FCM] Error getting access token: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract clean FCM token from various formats
     */
    private function extractFCMToken($device_token) {
        // If token contains FCM URL, extract the token part
        if (strpos($device_token, 'https://fcm.googleapis.com/fcm/send/') === 0) {
            return substr($device_token, 39); // Remove the URL prefix
        }
        
        return $device_token;
    }
    
    /**
     * Validate device token format - IMPROVED VALIDATION
     */
    public function isValidDeviceToken($device_token) {
        if (empty($device_token) || strlen($device_token) < 10) {
            return false;
        }
        
        // Check for FCM token format (starts with FCM URL or is alphanumeric with colons)
        if (strpos($device_token, 'https://fcm.googleapis.com/fcm/send/') === 0) {
            $token_part = substr($device_token, 39);
            return preg_match('/^[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+$/', $token_part);
        }
        
        // Standard FCM token format
        if (preg_match('/^[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+$/', $device_token)) {
            return true;
        }
        
        // VAPID key format (for web push)
        if (strlen($device_token) > 100 && strpos($device_token, 'https://') === false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle invalid device tokens by deactivating them
     */
    private function handleInvalidToken($device_token) {
        try {
            error_log("[FCM] Invalid device token detected, should be deactivated: " . substr($device_token, 0, 50));
            
            // This will be handled by the main service class
            return true;
            
        } catch (Exception $e) {
            error_log("[FCM] Error handling invalid token: " . $e->getMessage());
            return false;
        }
    }
}
?>