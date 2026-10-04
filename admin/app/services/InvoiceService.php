<?php
require_once '../../../vendor/autoload.php';

class InvoiceService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function generateInvoice($order_id) {
        try {
            // Get order details
            $order_stmt = $this->pdo->prepare("
                SELECT o.*, c.name as customer_name, c.email, c.phone, c.address 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?
            ");
            $order_stmt->execute([$order_id]);
            $order = $order_stmt->fetch();
            
            if (!$order) {
                return false;
            }
            
            // Generate invoice number
            $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
            
            // Calculate dates
            $invoice_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+7 days'));
            
            // Insert invoice record
            $stmt = $this->pdo->prepare("
                INSERT INTO invoice_custom (
                    order_id, invoice_number, invoice_date, due_date, 
                    subtotal, total_amount, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            
            $result = $stmt->execute([
                $order_id,
                $invoice_number,
                $invoice_date,
                $due_date,
                $order['total_amount'],
                $order['total_amount']
            ]);
            
            if ($result) {
                return $this->pdo->lastInsertId();
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Invoice Generation Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendInvoiceEmail($invoice_id) {
        try {
            // Get invoice details
            $invoice_stmt = $this->pdo->prepare("
                SELECT ic.*, o.*, c.name as customer_name, c.email as customer_email 
                FROM invoice_custom ic 
                JOIN orders o ON ic.order_id = o.id 
                JOIN customers c ON o.customer_id = c.id 
                WHERE ic.id = ?
            ");
            $invoice_stmt->execute([$invoice_id]);
            $invoice = $invoice_stmt->fetch();
            
            if (!$invoice || empty($invoice['customer_email'])) {
                return ['success' => false, 'message' => 'Customer email not found'];
            }
            
            // Update invoice status
            $update_stmt = $this->pdo->prepare("
                UPDATE invoice_custom 
                SET status = 'sent', sent_via_email = TRUE, sent_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$invoice_id]);
            
            return ['success' => true, 'message' => 'Invoice sent successfully'];
            
        } catch (Exception $e) {
            error_log("Invoice Email Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send invoice email'];
        }
    }
    
    public function getInvoiceByOrderId($order_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM invoice_custom WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$order_id]);
        return $stmt->fetch();
    }
}
?>