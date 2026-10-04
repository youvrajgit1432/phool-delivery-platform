<?php
// app/services/EmailService.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $conn;
    private $table_settings = "system_settings";
    
    // SMTP configuration
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->loadSMTPSettings();
    }
    
    /**
     * Load SMTP settings from database
     */
    private function loadSMTPSettings() {
        try {
            $query = "SELECT setting_key, setting_value FROM " . $this->table_settings . " 
                     WHERE setting_group = 'email'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Set SMTP configuration from database
            $this->smtp_host = $settings['smtp_host'] ?? 'smtp.gmail.com';
            $this->smtp_port = $settings['smtp_port'] ?? 587;
            $this->smtp_username = $settings['smtp_username'] ?? '';
            $this->smtp_password = $settings['smtp_password'] ?? '';
            $this->smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
            
        } catch (Exception $e) {
            error_log("Error loading SMTP settings: " . $e->getMessage());
            $this->setDefaultSMTPSettings();
        }
    }
    
    private function setDefaultSMTPSettings() {
        $this->smtp_host = 'smtp.gmail.com';
        $this->smtp_port = 587;
        $this->smtp_username = '';
        $this->smtp_password = '';
        $this->smtp_encryption = 'tls';
    }
    
    /**
     * Send order status update email - ONLY for specific statuses
     */
    public function sendOrderStatusUpdate($order_id, $status) {
        error_log("=== Sending Order Status Email ===");
        error_log("Order ID: $order_id, Status: $status");
        
        // Check if email should be sent for this status
        $allowedStatuses = ['preparing', 'out_for_delivery', 'delivered'];
        if (!in_array($status, $allowedStatuses)) {
            error_log("Email not sent for status: $status - Only allowed for: " . implode(', ', $allowedStatuses));
            return ['success' => true, 'message' => 'Email not required for this status'];
        }
        
        try {
            // Get order details with customer information and product names
            $order = $this->getOrderDetailsWithProducts($order_id);
            if (!$order) {
                throw new Exception("Order not found: $order_id");
            }
            
            // Check if customer has email
            if (empty($order['email'])) {
                throw new Exception("Customer email not available for order: $order_id");
            }
            
            $to_email = $order['email'];
            $subject = $this->getStatusSubject($status);
            $body = $this->getStatusEmailTemplate($order, $status);
            
            error_log("Sending status email to: $to_email");
            
            // Send email
            $result = $this->sendEmail($to_email, $subject, $body);
            
            if ($result['success']) {
                // Update email status in orders table
                $this->updateOrderEmailStatus($order_id, 'sent', $status);
                error_log("✅ Order status email sent successfully");
                return ['success' => true, 'message' => 'Status email sent successfully'];
            } else {
                // Update email status as failed
                $this->updateOrderEmailStatus($order_id, 'failed', $status, $result['message']);
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            error_log("❌ Order status email failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send status email: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get order details with customer information and product names
     */
    private function getOrderDetailsWithProducts($order_id) {
        $query = "SELECT 
                    o.*, 
                    c.name as customer_name, 
                    c.email, 
                    c.phone,
                    GROUP_CONCAT(p.name_en SEPARATOR ', ') as product_names,
                    GROUP_CONCAT(oi.quantity SEPARATOR ', ') as product_quantities
                 FROM orders o 
                 LEFT JOIN customers c ON o.customer_id = c.id 
                 LEFT JOIN order_items oi ON o.id = oi.order_id
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE o.id = ?
                 GROUP BY o.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get email subject based on status
     */
    private function getStatusSubject($status) {
        $subjects = [
            'preparing' => 'Your Order is Being Prepared - Phool Delivery',
            'out_for_delivery' => 'Your Order is Out for Delivery - Phool Delivery',
            'delivered' => 'Your Order Has Been Delivered - Phool Delivery'
        ];
        
        return $subjects[$status] ?? 'Order Status Update - Phool Delivery';
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to_email, $subject, $body) {
        error_log("Starting sendEmail to: $to_email");
        
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->getEncryptionConstant($this->smtp_encryption);
            $mail->Port = $this->smtp_port;
            
            // Important settings for Windows/XAMPP
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Debugging
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug (Level $level): $str");
            };
            
            // Timeout settings
            $mail->Timeout = 30;
            
            // Recipients
            $mail->setFrom($this->smtp_username, 'Phool Delivery');
            $mail->addAddress($to_email);
            $mail->addReplyTo($this->smtp_username, 'Phool Delivery');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            error_log("Attempting to send email via PHPMailer...");
            
            if ($mail->send()) {
                error_log("✅ PHPMailer: Email sent successfully to: $to_email");
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                $error = $mail->ErrorInfo;
                error_log("❌ PHPMailer failed: $error");
                return ['success' => false, 'message' => "Email service error: $error"];
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("❌ PHPMailer Exception: $error");
            return ['success' => false, 'message' => "Email service error: $error"];
        }
    }
    
    /**
     * Convert encryption string to PHPMailer constant
     */
    private function getEncryptionConstant($encryption) {
        switch (strtolower($encryption)) {
            case 'ssl':
                return PHPMailer::ENCRYPTION_SMTPS;
            case 'tls':
            default:
                return PHPMailer::ENCRYPTION_STARTTLS;
        }
    }
    
    /**
     * Update order email status in database
     */
    private function updateOrderEmailStatus($order_id, $status, $email_type, $error_message = null) {
        try {
            $query = "UPDATE orders SET 
                     confirmation_email_status = ?,
                     confirmation_email_sent_at = NOW(),
                     confirmation_email_error = ?
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $error_msg = $status === 'failed' ? $error_message : null;
            $result = $stmt->execute([$status, $error_msg, $order_id]);
            
            if ($result) {
                error_log("✅ Order email status updated to: $status");
            } else {
                error_log("❌ Failed to update order email status");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("❌ Update email status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template for order status with product information
     */
    private function getStatusEmailTemplate($order, $status) {
        $order_number = $order['order_number'];
        $customer_name = $order['customer_name'];
        $total_amount = number_format($order['total_amount'], 2);
        $delivery_date = date('M d, Y', strtotime($order['delivery_date']));
        $product_names = $order['product_names'] ?? 'Flower Products';
        
        $status_messages = [
            'preparing' => [
                'title' => 'Order Being Prepared!',
                'message' => 'We are now preparing your beautiful flowers with care.',
                'next_step' => 'Your order will be out for delivery soon.'
            ],
            'out_for_delivery' => [
                'title' => 'Order Out for Delivery!',
                'message' => 'Your order is on its way to you!',
                'next_step' => 'Please ensure someone is available to receive the delivery.'
            ],
            'delivered' => [
                'title' => 'Order Delivered!',
                'message' => 'Your order has been successfully delivered.',
                'next_step' => 'Thank you for choosing Phool Delivery!'
            ]
        ];
        
        $status_info = $status_messages[$status] ?? $status_messages['preparing'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Order Status - Phool Delivery</title>
            <style>
                body { 
                    font-family: 'Arial', sans-serif; 
                    background-color: #f4f4f4; 
                    margin: 0; 
                    padding: 20px; 
                    line-height: 1.6;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 30px; 
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header { 
                    text-align: center; 
                    color: #e91e63; 
                    margin-bottom: 30px;
                }
                .header h2 {
                    margin: 0;
                    font-size: 28px;
                }
                .status-badge {
                    background: #e91e63;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 20px;
                    font-weight: bold;
                    display: inline-block;
                    margin: 10px 0;
                }
                .order-info {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                }
                .info-label {
                    font-weight: bold;
                    color: #555;
                }
                .product-info {
                    background: #fff3cd;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                    border-left: 4px solid #ffc107;
                }
                .next-steps {
                    background: #e8f5e8;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    border-left: 4px solid #4caf50;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #666; 
                    font-size: 12px;
                    border-top: 1px solid #eee;
                    padding-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🌺 Phool Delivery</h2>
                    <h3>Order Status Update</h3>
                </div>
                
                <p>Dear $customer_name,</p>
                
                <div style='text-align: center;'>
                    <div class='status-badge'>" . strtoupper(str_replace('_', ' ', $status)) . "</div>
                    <h3>{$status_info['title']}</h3>
                    <p>{$status_info['message']}</p>
                </div>
                
                <div class='order-info'>
                    <h4>Order Details:</h4>
                    <div class='info-row'>
                        <span class='info-label'>Order Number:</span>
                        <span>$order_number</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Total Amount:</span>
                        <span>Rs. $total_amount</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Delivery Date:</span>
                        <span>$delivery_date</span>
                    </div>
                </div>
                
                <div class='product-info'>
                    <h4>🛍️ Your Products:</h4>
                    <p><strong>$product_names</strong></p>
                    <p>We're handling your flowers with the utmost care to ensure they reach you fresh and beautiful.</p>
                </div>
                
                <div class='next-steps'>
                    <h4>What's Next?</h4>
                    <p>{$status_info['next_step']}</p>
                </div>
                
                <p>If you have any questions about your order, please contact our customer service team.</p>
                
                <div class='footer'>
                    <p>Need help? Contact us at <a href='mailto:support@phooldelivery.example'>support@phooldelivery.example</a> or call 9844634579</p>
                    <p>&copy; 2024 Phool Delivery. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>