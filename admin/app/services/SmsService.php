<?php
// app/services/SmsService.php

class SmsService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function sendVerificationSMS($customer_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer || empty($customer['phone'])) {
            return false;
        }
        
        // Generate verification code
        $verification_code = rand(1000, 9999);
        
        // Store verification code in database (optional)
        $stmt = $this->pdo->prepare("UPDATE customers SET sms_verification_code = ?, sms_verification_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $stmt->execute([$verification_code, $customer_id]);
        
        // Prepare SMS message
        $message = "Your Phool Delivery verification code is: $verification_code. This code will expire in 1 hour.";
        
        // Send SMS using your preferred SMS gateway
        return $this->sendSMS($customer['phone'], $message);
    }
    
    public function sendOrderConfirmationSMS($customer_id, $order_details) {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer || empty($customer['phone'])) {
            return false;
        }
        
        $message = "Your order #{$order_details['order_number']} has been confirmed. Total: Rs. {$order_details['total_amount']}. Thank you for choosing Phool Delivery!";
        
        return $this->sendSMS($customer['phone'], $message);
    }
    
    private function sendSMS($phone, $message) {
        // Implement your SMS gateway integration here
        // This is a placeholder implementation
        
        // Example using a hypothetical SMS API:
        /*
        $api_key = getenv('SMS_API_KEY');
        $sender_id = getenv('SMS_SENDER_ID');
        
        $url = "https://api.smsgateway.com/send";
        $data = [
            'api_key' => $api_key,
            'to' => $phone,
            'message' => $message,
            'sender_id' => $sender_id
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return strpos($response, 'success') !== false;
        */
        
        // For development purposes, just log the SMS
        error_log("SMS to $phone: $message");
        return true;
    }
}