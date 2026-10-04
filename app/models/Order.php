<?php
class Order {
    private $conn;
    private $table_name = "orders";
    private $items_table = "order_items";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($order_data) {
        try {
            $this->conn->beginTransaction();

            // Generate order number
            $order_number = 'ORD-' . strtoupper(substr(uniqid(), -8));
            
            // Determine payment status based on payment method
            $payment_status = ($order_data['payment_method'] === 'cod') ? 'pending' : 'paid_pending_verification';
            
            // Get order type (default to 'website')
            $order_type = $order_data['order_type'] ?? 'website';
            
            // Get order-specific details (for this order, not permanent customer changes)
            $delivery_address = $order_data['delivery_address'] ?? null;
            $delivery_phone = $order_data['delivery_phone'] ?? null;
            $secondary_phone = $order_data['secondary_phone'] ?? null;
            $applied_discount = $order_data['total_discount'] ?? 0;
            $final_amount = $order_data['total_amount'] ?? 0;
            
            // Insert order with all required fields
            $query = "INSERT INTO " . $this->table_name . "
                     (customer_id, order_number, quantity, rate, total_amount, applied_discount, final_amount,
                      payment_method, payment_status, payment_screenshot, order_type, delivery_date, notes,
                      delivery_address, delivery_phone, secondary_phone)
                     VALUES (:customer_id, :order_number, :quantity, :rate, :total_amount, :applied_discount, :final_amount,
                             :payment_method, :payment_status, :payment_screenshot, :order_type, :delivery_date, :notes,
                             :delivery_address, :delivery_phone, :secondary_phone)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":customer_id", $order_data['customer_id']);
            $stmt->bindParam(":order_number", $order_number);
            $stmt->bindParam(":quantity", $order_data['quantity']);
            $stmt->bindParam(":rate", $order_data['rate']);
            $stmt->bindParam(":total_amount", $order_data['total_amount']);
            $stmt->bindParam(":applied_discount", $applied_discount);
            $stmt->bindParam(":final_amount", $final_amount);
            $stmt->bindParam(":payment_method", $order_data['payment_method']);
            $stmt->bindParam(":payment_status", $payment_status);
            $stmt->bindParam(":payment_screenshot", $order_data['payment_screenshot']);
            $stmt->bindParam(":order_type", $order_type);
            $stmt->bindParam(":delivery_date", $order_data['delivery_date']);
            $stmt->bindParam(":notes", $order_data['notes']);
            $stmt->bindParam(":delivery_address", $delivery_address);
            $stmt->bindParam(":delivery_phone", $delivery_phone);
            $stmt->bindParam(":secondary_phone", $secondary_phone);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order");
            }
            
            $order_id = $this->conn->lastInsertId();
            
            // Create order items
            $this->createOrderItems($order_id, $order_data['items']);
            
            // Update product stock
            $this->updateProductStock($order_data['items']);
            
            $this->conn->commit();
            return $order_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function createOrderItems($order_id, $items) {
        $query = "INSERT INTO " . $this->items_table . " 
                 (order_id, product_id, quantity, unit_price, total_price)
                 VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($items as $item) {
            $total_price = $item['final_price'] * $item['quantity'];
            
            $stmt->bindParam(":order_id", $order_id);
            $stmt->bindParam(":product_id", $item['id']);
            $stmt->bindParam(":quantity", $item['quantity']);
            $stmt->bindParam(":unit_price", $item['final_price']);
            $stmt->bindParam(":total_price", $total_price);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order items");
            }
        }
    }
    
    private function updateProductStock($items) {
        $query = "UPDATE products SET stock_quantity = stock_quantity - ? 
                 WHERE id = ? AND stock_quantity >= ?";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($items as $item) {
            $stmt->bindParam(1, $item['quantity']);
            $stmt->bindParam(2, $item['id']);
            $stmt->bindParam(3, $item['quantity']);
            
            if (!$stmt->execute()) {
                throw new Exception("Insufficient stock for product ID: " . $item['id']);
            }
        }
    }

    public function getOrder($order_id, $customer_id = null) {
        $query = "SELECT o.*, c.name as customer_name, c.email, c.phone, c.address 
                 FROM " . $this->table_name . " o 
                 JOIN customers c ON o.customer_id = c.id 
                 WHERE o.id = ?";
        
        if ($customer_id) {
            $query .= " AND o.customer_id = ?";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $order_id);
        
        if ($customer_id) {
            $stmt->bindParam(2, $customer_id);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCustomerOrders($customer_id) {
        $query = "SELECT o.*, 
                 (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
                 FROM " . $this->table_name . " o 
                 WHERE o.customer_id = ? 
                 ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $customer_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderItems($order_id) {
        $query = "SELECT oi.*, p.name as product_name, p.images 
                 FROM " . $this->items_table . " oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $order_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($order_id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = :status, updated_at = NOW() 
                 WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":order_id", $order_id);
        
        return $stmt->execute();
    }
    
    public function updatePaymentStatus($order_id, $payment_status) {
        $query = "UPDATE " . $this->table_name . " 
                 SET payment_status = :payment_status, updated_at = NOW() 
                 WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":payment_status", $payment_status);
        $stmt->bindParam(":order_id", $order_id);
        
        return $stmt->execute();
    }
    
    /**
     * Get orders by type (website/whatsapp)
     */
    public function getOrdersByType($order_type) {
        $query = "SELECT o.*, c.name as customer_name, c.phone 
                 FROM " . $this->table_name . " o 
                 JOIN customers c ON o.customer_id = c.id 
                 WHERE o.order_type = :order_type 
                 ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":order_type", $order_type);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>