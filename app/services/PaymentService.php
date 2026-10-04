<?php
class PaymentService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    public function processPayment($payment_data) {
        $payment_method = $payment_data['payment_method'];
        
        switch ($payment_method) {
            case 'esewa':
                return $this->processEsewaPayment($payment_data);
            case 'khalti':
                return $this->processKhaltiPayment($payment_data);
            case 'bank_transfer':
                return $this->processBankTransfer($payment_data);
            case 'cod':
                return $this->processCOD($payment_data);
            default:
                throw new Exception('Invalid payment method');
        }
    }

    private function processEsewaPayment($payment_data) {
        // Esewa payment integration
        // This would contain actual API calls to eSewa
        return [
            'success' => true,
            'transaction_id' => 'ESW' . uniqid(),
            'message' => 'Payment processed successfully'
        ];
    }

    private function processKhaltiPayment($payment_data) {
        // Khalti payment integration
        return [
            'success' => true,
            'transaction_id' => 'KHT' . uniqid(),
            'message' => 'Payment processed successfully'
        ];
    }
}
?>