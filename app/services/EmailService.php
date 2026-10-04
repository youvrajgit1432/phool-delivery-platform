<?php
class EmailService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    public function sendOrderConfirmation($order_id, $customer_id) {
        // Get order details
        $order_query = "SELECT o.*, c.name as customer_name, c.email 
                       FROM orders o 
                       JOIN customers c ON o.customer_id = c.id 
                       WHERE o.id = ?";
        
        $stmt = $this->db->prepare($order_query);
        $stmt->bindParam(1, $order_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get order items
        $items_query = "SELECT oi.*, p.name as product_name 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?";
        
        $stmt = $this->db->prepare($items_query);
        $stmt->bindParam(1, $order_id);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare email content
        $subject = "Order Confirmation #" . $order['order_number'];
        $message = $this->buildOrderEmailTemplate($order, $items);

        // Send email (this would use PHPMailer or similar in production)
        return $this->sendEmail($order['email'], $subject, $message);
    }

    private function buildOrderEmailTemplate($order, $items) {
        // Build HTML email template
        ob_start();
        include '../emails/order_confirmation.php';
        return ob_get_clean();
    }
}
?>