<?php
class MessageController {
    private $db;
    private $messageModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->messageModel = new Message($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public function index() {
        try {
            // FIXED: Check if user is logged in but don't redirect
            if (!isset($_SESSION['customer_id'])) {
                // Return data for non-logged-in users to show access denied message
                return [
                    'messages' => [],
                    'page_title' => LanguageHelper::t('access_denied', 'Access Denied') . ' - Phool Delivery',
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_messages' => 0,
                    'user_logged_in' => false // Add flag to indicate user status
                ];
            }
            
            $customer_id = $_SESSION['customer_id'];
            
            // Pagination setup
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $per_page = 10;
            $offset = ($page - 1) * $per_page;
            
            // Get messages with pagination
            $messages = $this->messageModel->getCustomerMessages($customer_id, $per_page, $offset);
            $total_messages = $this->messageModel->getCustomerMessagesCount($customer_id);
            $total_pages = ceil($total_messages / $per_page);
            
            // Ensure current page doesn't exceed total pages
            if ($page > $total_pages && $total_pages > 0) {
                $redirectUrl = $this->pathConfig->url('messages') . '?page=' . $total_pages;
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            // If page is 0 or negative, redirect to page 1
            if ($page < 1) {
                $redirectUrl = $this->pathConfig->url('messages') . '?page=1';
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            return [
                'messages' => $messages,
                'page_title' => 'My Messages - Phool Delivery',
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_messages' => $total_messages,
                'user_logged_in' => true // User is logged in
            ];
        } catch (Exception $e) {
            error_log("MessageController index error: " . $e->getMessage());
            return [
                'messages' => [],
                'page_title' => 'My Messages - Phool Delivery',
                'current_page' => 1,
                'total_pages' => 1,
                'total_messages' => 0,
                'user_logged_in' => isset($_SESSION['customer_id']) // Check if user was logged in
            ];
        }
    }
    
    public function getUnreadCount() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode(['count' => 0]);
                exit;
            }
            
            $customer_id = $_SESSION['customer_id'];
            $count = $this->messageModel->getUnreadCount($customer_id);
            
            // Update session
            $_SESSION['message_count'] = $count;
            
            echo json_encode(['count' => $count]);
            exit;
        } catch (Exception $e) {
            error_log("MessageController getUnreadCount error: " . $e->getMessage());
            echo json_encode(['count' => 0]);
            exit;
        }
    }
    
    public function markAsRead() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            }
            
            // Get raw input data
            $input = json_decode(file_get_contents('php://input'), true);
            $customer_id = $_SESSION['customer_id'];
            $message_id = $input['message_id'] ?? null;
            
            if (!$message_id) {
                echo json_encode(['success' => false, 'message' => 'Message ID is required']);
                exit;
            }
            
            // Validate message_id is numeric
            if (!is_numeric($message_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
                exit;
            }
            
            $success = $this->messageModel->updateReadStatus($message_id, $customer_id);
            
            if ($success) {
                // Update session count
                $_SESSION['message_count'] = $this->messageModel->getUnreadCount($customer_id);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Message marked as read',
                    'unread_count' => $_SESSION['message_count']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark message as read']);
            }
            exit;
        } catch (Exception $e) {
            error_log("MessageController markAsRead error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Operation failed']);
            exit;
        }
    }
    
    public function markAllAsRead() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            }
            
            $customer_id = $_SESSION['customer_id'];
            $success = $this->messageModel->markAllAsRead($customer_id);
            
            if ($success) {
                $_SESSION['message_count'] = 0;
                echo json_encode([
                    'success' => true, 
                    'message' => 'All messages marked as read',
                    'unread_count' => 0
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark messages as read']);
            }
            exit;
        } catch (Exception $e) {
            error_log("MessageController markAllAsRead error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Operation failed']);
            exit;
        }
    }
    
    public function deleteMessage() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            }
            
            $customer_id = $_SESSION['customer_id'];
            $message_id = $_POST['message_id'] ?? null;
            
            if (!$message_id) {
                echo json_encode(['success' => false, 'message' => 'Message ID is required']);
                exit;
            }
            
            // Validate message_id is numeric
            if (!is_numeric($message_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
                exit;
            }
            
            $success = $this->messageModel->deleteMessage($message_id, $customer_id);
            
            if ($success) {
                // Update session count
                $_SESSION['message_count'] = $this->messageModel->getUnreadCount($customer_id);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Message deleted successfully',
                    'unread_count' => $_SESSION['message_count']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
            }
            exit;
        } catch (Exception $e) {
            error_log("MessageController deleteMessage error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Operation failed']);
            exit;
        }
    }
    
    public function getRecentMessages() {
        try {
            if (!isset($_SESSION['customer_id'])) {
                echo json_encode(['messages' => [], 'count' => 0]);
                exit;
            }
            
            $customer_id = $_SESSION['customer_id'];
            $messages = $this->messageModel->getCustomerMessages($customer_id, 3);
            
            // Format messages for display
            $formattedMessages = [];
            foreach ($messages as $message) {
                $formattedMessages[] = [
                    'id' => $message['id'],
                    'title' => htmlspecialchars($message['title']),
                    'message' => htmlspecialchars($message['message']),
                    'type' => $message['type'],
                    'is_read' => (bool)$message['is_read'],
                    'created_at' => date('M j, g:i A', strtotime($message['created_at'])),
                    'related_id' => $message['related_id']
                ];
            }
            
            $count = $this->messageModel->getUnreadCount($customer_id);
            $_SESSION['message_count'] = $count;
            
            echo json_encode([
                'messages' => $formattedMessages,
                'count' => $count
            ]);
            exit;
        } catch (Exception $e) {
            error_log("MessageController getRecentMessages error: " . $e->getMessage());
            echo json_encode(['messages' => [], 'count' => 0]);
            exit;
        }
    }
}
?>