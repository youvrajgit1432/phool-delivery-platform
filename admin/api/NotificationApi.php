<?php
// admin/api/NotificationApi.php - ENHANCED VERSION

require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

class NotificationApi {
    private $db;
    private $pushService;
    
    public function __construct($db) {
        $this->db = $db;
        $this->pushService = new AdminPushNotificationService($db);
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'send_order_status':
                $this->sendOrderStatusUpdate();
                break;
            case 'send_bulk':
                $this->sendBulkNotification();
                break;
            case 'test':
                $this->sendTestNotification();
                break;
            case 'stats':
                $this->getNotificationStats();
                break;
            case 'devices':
                $this->getUserDevices();
                break;
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Action not found']);
                break;
        }
    }
    
    /**
     * Send order status update notification - ENHANCED
     */
    public function sendOrderStatusUpdate() {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $order_id = $input['order_id'] ?? null;
            $status = $input['status'] ?? null;
            $admin_notes = $input['admin_notes'] ?? '';
            
            if (!$order_id || !$status) {
                throw new Exception('Order ID and status are required');
            }
            
            $result = $this->pushService->sendOrderStatusUpdate($order_id, $status, $admin_notes);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Notification sent successfully' : 'Failed to send notification',
                'notification_data' => [
                    'order_id' => $order_id,
                    'status' => $status,
                    'notes' => $admin_notes
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin API] Error in sendOrderStatusUpdate: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send bulk notification to all customers - ENHANCED
     */
    public function sendBulkNotification() {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $title = $input['title'] ?? null;
            $message = $input['message'] ?? null;
            $notification_type = $input['notification_type'] ?? 'system';
            $send_to_all = $input['send_to_all'] ?? true;
            $target_users = $input['target_users'] ?? [];
            
            if (!$title || !$message) {
                throw new Exception('Title and message are required');
            }
            
            $data = [
                'type' => $notification_type,
                'url' => $input['url'] ?? '/',
                'timestamp' => time()
            ];
            
            $result = 0;
            
            if ($send_to_all) {
                $result = $this->pushService->sendToAllCustomers($title, $message, $data, $notification_type);
            } else if (!empty($target_users)) {
                // Send to specific users
                foreach ($target_users as $user_id) {
                    $user_result = $this->pushService->sendToCustomer($user_id, $title, $message, $data);
                    if ($user_result) $result++;
                }
            }
            
            echo json_encode([
                'success' => $result > 0,
                'message' => $result > 0 ? "Notification sent to {$result} users" : 'No users to notify',
                'users_notified' => $result,
                'notification_type' => $notification_type
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin API] Error in sendBulkNotification: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send test notification
     */
    public function sendTestNotification() {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $order_id = $input['order_id'] ?? null;
            $customer_id = $input['customer_id'] ?? null;
            $title = $input['title'] ?? 'Test Notification';
            $message = $input['message'] ?? 'This is a test notification from admin panel';
            
            if (!$customer_id) {
                throw new Exception('Customer ID is required for test notification');
            }
            
            $result = $this->pushService->sendToCustomer(
                $customer_id,
                $title,
                $message,
                [
                    'type' => 'test', 
                    'url' => '/', 
                    'timestamp' => time(),
                    'order_id' => $order_id
                ]
            );
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Test notification sent successfully' : 'Failed to send test notification',
                'customer_id' => $customer_id
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin API] Error in sendTestNotification: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get notification statistics - ENHANCED
     */
    public function getNotificationStats() {
        header('Content-Type: application/json');
        
        try {
            $days = $_GET['days'] ?? 30;
            $stats = $this->pushService->getNotificationStats($days);
            $devices = $this->pushService->getActiveDevicesCount();
            
            // Get recent notifications
            $recent_stmt = $this->db->prepare("
                SELECT anl.*, c.name as customer_name, c.phone 
                FROM admin_notification_logs anl
                LEFT JOIN customers c ON anl.customer_id = c.id
                ORDER BY anl.created_at DESC 
                LIMIT 10
            ");
            $recent_stmt->execute();
            $recent_notifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'devices' => $devices,
                'recent_notifications' => $recent_notifications,
                'summary' => [
                    'total_devices' => array_sum(array_column($devices, 'device_count')),
                    'unique_users' => array_sum(array_column($devices, 'unique_users')),
                    'total_notifications' => array_sum(array_column($stats, 'total_notifications')),
                    'success_rate' => $this->calculateSuccessRate($stats)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin API] Error in getNotificationStats: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Calculate notification success rate
     */
    private function calculateSuccessRate($stats) {
        if (empty($stats)) return 0;
        
        $total = array_sum(array_column($stats, 'total_notifications'));
        $successful = array_sum(array_column($stats, 'successful_sends'));
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }
    
    /**
     * Get user devices for targeting
     */
    public function getUserDevices() {
        header('Content-Type: application/json');
        
        try {
            $user_id = $_GET['user_id'] ?? null;
            
            if ($user_id) {
                // Get specific user devices
                $stmt = $this->db->prepare("
                    SELECT pnd.*, c.name, c.phone, c.email 
                    FROM push_notification_devices pnd
                    LEFT JOIN customers c ON pnd.user_id = c.id
                    WHERE pnd.user_id = ? AND pnd.is_active = 1
                ");
                $stmt->execute([$user_id]);
            } else {
                // Get all active devices
                $stmt = $this->db->prepare("
                    SELECT pnd.*, c.name, c.phone, c.email, 
                           COUNT(pnd.id) OVER() as total_count
                    FROM push_notification_devices pnd
                    LEFT JOIN customers c ON pnd.user_id = c.id
                    WHERE pnd.is_active = 1
                    ORDER BY pnd.updated_at DESC
                    LIMIT 100
                ");
                $stmt->execute();
            }
            
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'devices' => $devices,
                'total_count' => $devices[0]['total_count'] ?? count($devices)
            ]);
            
        } catch (Exception $e) {
            error_log("[Admin API] Error in getUserDevices: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Initialize and handle request
try {
    $pdo = getDBConnection();
    $api = new NotificationApi($pdo);
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>