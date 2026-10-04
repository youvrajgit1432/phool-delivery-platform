<?php
// app/services/MessageService.php

class MessageService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new message
     */
    public function createMessage($customer_id, $title, $message, $type = 'system', $related_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (customer_id, title, message, type, related_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$customer_id, $title, $message, $type, $related_id]);
        } catch (PDOException $e) {
            error_log("Error creating message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get messages for a customer
     */
    public function getCustomerMessages($customer_id, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM messages 
                WHERE customer_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$customer_id, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead($message_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages SET is_read = 1 WHERE id = ?
            ");
            
            return $stmt->execute([$message_id]);
        } catch (PDOException $e) {
            error_log("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count for a customer
     */
    public function getUnreadCount($customer_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM messages 
                WHERE customer_id = ? AND is_read = 0
            ");
            
            $stmt->execute([$customer_id]);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create order status message
     */
    public function createOrderStatusMessage($order_id, $status) {
        try {
            // Get order details
            $stmt = $this->pdo->prepare("
                SELECT o.*, c.id as customer_id, c.name as customer_name 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?
            ");
            
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                return false;
            }
            
            $customer_id = $order['customer_id'];
            $order_number = $order['order_number'];
            
            // Create appropriate message based on status
            $message = "";
            $title = "Order Status Update";
            $message_type = 'order'; // Changed from 'order_status' to 'order' to match ENUM
            
            switch($status) {
                case 'preparing':
                    $message = "Your order #{$order_number} is now being prepared. We'll notify you when it's out for delivery.";
                    break;
                case 'out_for_delivery':
                    $message = "Good news! Your order #{$order_number} is out for delivery. Please be available to receive it.";
                    break;
                case 'delivered':
                    $message = "Your order #{$order_number} has been successfully delivered. Thank you for shopping with us!";
                    break;
                default:
                    // For other statuses, we might not need a notification
                    return true;
            }
            
            // Create the message
            return $this->createMessage(
                $customer_id, 
                $title, 
                $message, 
                $message_type, 
                $order_id
            );
        } catch (PDOException $e) {
            error_log("Error creating order status message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create verification status message
     */
    public function createVerificationMessage($customer_id, $status) {
        try {
            // Get customer details
            $stmt = $this->pdo->prepare("
                SELECT name FROM customers WHERE id = ?
            ");
            
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                return false;
            }
            
            $customer_name = $customer['name'];
            $message = "";
            $title = "Account Verification";
            
            switch($status) {
                case 'verified':
                    $message = "Hello {$customer_name}, your account has been successfully verified. You can now enjoy all features of our platform.";
                    break;
                case 'rejected':
                    $message = "Hello {$customer_name}, your verification request could not be approved. Please contact support for more information.";
                    break;
                default:
                    return true;
            }
            
            // Create the message
            return $this->createMessage(
                $customer_id, 
                $title, 
                $message, 
                'system' // Changed from 'verification' to 'system' to match ENUM
            );
        } catch (PDOException $e) {
            error_log("Error creating verification message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create registration welcome message
     */
    public function createWelcomeMessage($customer_id) {
        try {
            // Get customer details
            $stmt = $this->pdo->prepare("
                SELECT name FROM customers WHERE id = ?
            ");
            
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                return false;
            }
            
            $customer_name = $customer['name'];
            
            // Create welcome message
            $message = "Welcome to Phool Delivery, {$customer_name}! We're excited to have you on board. Start exploring our fresh flowers and place your first order today!";
            $title = "Welcome to Phool Delivery";
            
            // Create the message
            return $this->createMessage(
                $customer_id, 
                $title, 
                $message, 
                'system' // Changed from 'registration' to 'system' to match ENUM
            );
        } catch (PDOException $e) {
            error_log("Error creating welcome message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create promotion message (new method to utilize the 'promotion' type)
     */
    public function createPromotionMessage($customer_id, $title, $message, $related_id = null) {
        try {
            return $this->createMessage(
                $customer_id,
                $title,
                $message,
                'promotion',
                $related_id
            );
        } catch (PDOException $e) {
            error_log("Error creating promotion message: " . $e->getMessage());
            return false;
        }
    }
}
?>