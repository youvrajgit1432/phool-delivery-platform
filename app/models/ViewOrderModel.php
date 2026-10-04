<?php
// app/models/ViewOrderModel.php

class ViewOrderModel {
    private $conn;
    private $table_users = "customers";
    private $table_orders = "orders";
    private $table_order_items = "order_items";
    private $table_products = "products";
    private $table_categories = "categories";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get user orders with status filtering
    public function getUserOrders($user_id, $status = 'pending', $limit = null) {
        // Validate and sanitize inputs
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        if ($user_id === false || $user_id <= 0) {
            throw new InvalidArgumentException("Invalid user ID");
        }
        
        $allowed_statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled', 'all'];
        if (!in_array($status, $allowed_statuses)) {
            throw new InvalidArgumentException("Invalid status parameter");
        }
        
        $query = "SELECT o.*, COUNT(oi.id) as item_count, 
                         SUM(oi.quantity * oi.unit_price) as total_amount,
                         c.name as customer_name
                  FROM " . $this->table_orders . " o 
                  LEFT JOIN " . $this->table_order_items . " oi ON o.id = oi.order_id 
                  LEFT JOIN " . $this->table_users . " c ON o.customer_id = c.id
                  WHERE o.customer_id = :user_id";
        
        if ($status !== 'all') {
            $query .= " AND o.status = :status";
        }
        
        $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
        
        if ($limit) {
            $limit = filter_var($limit, FILTER_VALIDATE_INT);
            if ($limit === false || $limit <= 0) {
                throw new InvalidArgumentException("Invalid limit parameter");
            }
            $query .= " LIMIT :limit";
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            
            if ($status !== 'all') {
                $stmt->bindValue(":status", $status, PDO::PARAM_STR);
            }
            
            if ($limit) {
                $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getUserOrders: " . $e->getMessage());
            return [];
        }
    }
    
    // Get orders count by status
    public function getOrdersCountByStatus($user_id) {
        // Validate user ID
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        if ($user_id === false || $user_id <= 0) {
            throw new InvalidArgumentException("Invalid user ID");
        }
        
        $query = "SELECT status, COUNT(*) as count 
                  FROM " . $this->table_orders . " 
                  WHERE customer_id = :user_id 
                  GROUP BY status";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $counts = [];
            
            foreach ($result as $row) {
                $counts[$row['status']] = (int)$row['count'];
            }
            
            return $counts;
        } catch (PDOException $e) {
            error_log("Database error in getOrdersCountByStatus: " . $e->getMessage());
            return [];
        }
    }
    
    // Get recent orders for dashboard
    public function getRecentOrders($user_id, $limit = 5) {
        $limit = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 50]
        ]);
        if ($limit === false) {
            $limit = 5; // Default fallback
        }
        
        return $this->getUserOrders($user_id, 'all', $limit);
    }
    
    // Update order quantity (only for pending orders)
    public function updateOrderQuantity($order_id, $user_id, $item_id, $new_quantity) {
        // Validate all inputs
        $order_id = filter_var($order_id, FILTER_VALIDATE_INT);
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        $item_id = filter_var($item_id, FILTER_VALIDATE_INT);
        $new_quantity = filter_var($new_quantity, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 999]
        ]);
        
        if ($order_id === false || $user_id === false || $item_id === false || $new_quantity === false) {
            return false;
        }
        
        try {
            // Start transaction to prevent race conditions
            $this->conn->beginTransaction();
            
            // First verify order belongs to user and is pending
            $verify_query = "SELECT o.status 
                            FROM " . $this->table_orders . " o 
                            WHERE o.id = :order_id AND o.customer_id = :user_id
                            FOR UPDATE"; // Lock the row for update
            
            $verify_stmt = $this->conn->prepare($verify_query);
            $verify_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $verify_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            
            $order = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order || $order['status'] !== 'pending') {
                $this->conn->rollBack();
                return false;
            }
            
            // Check product stock availability
            $stock_query = "SELECT p.stock_quantity, p.name_en 
                           FROM " . $this->table_products . " p 
                           JOIN " . $this->table_order_items . " oi ON p.id = oi.product_id 
                           WHERE oi.id = :item_id AND oi.order_id = :order_id";
            
            $stock_stmt = $this->conn->prepare($stock_query);
            $stock_stmt->bindValue(":item_id", $item_id, PDO::PARAM_INT);
            $stock_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $stock_stmt->execute();
            
            $product = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product || $product['stock_quantity'] < $new_quantity) {
                $this->conn->rollBack();
                return false;
            }
            
            // Update quantity
            $update_query = "UPDATE " . $this->table_order_items . " 
                            SET quantity = :quantity, updated_at = NOW()
                            WHERE id = :item_id AND order_id = :order_id";
            
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindValue(":quantity", $new_quantity, PDO::PARAM_INT);
            $update_stmt->bindValue(":item_id", $item_id, PDO::PARAM_INT);
            $update_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            
            $result = $update_stmt->execute();
            
            if ($result) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Database error in updateOrderQuantity: " . $e->getMessage());
            return false;
        }
    }
    
    // Cancel order (only for pending orders)
    public function cancelOrder($order_id, $user_id) {
        // Validate inputs
        $order_id = filter_var($order_id, FILTER_VALIDATE_INT);
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        
        if ($order_id === false || $user_id === false) {
            return false;
        }
        
        try {
            $this->conn->beginTransaction();
            
            // Verify order belongs to user and is pending
            $verify_query = "SELECT status FROM " . $this->table_orders . " 
                            WHERE id = :order_id AND customer_id = :user_id
                            FOR UPDATE";
            
            $verify_stmt = $this->conn->prepare($verify_query);
            $verify_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $verify_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            
            $order = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order || $order['status'] !== 'pending') {
                $this->conn->rollBack();
                return false;
            }
            
            // Update order status to cancelled
            $update_query = "UPDATE " . $this->table_orders . " 
                            SET status = 'cancelled', updated_at = NOW() 
                            WHERE id = :order_id";
            
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            
            $result = $update_stmt->execute();
            
            if ($result) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Database error in cancelOrder: " . $e->getMessage());
            return false;
        }
    }
    
    // Get order details
    public function getOrderDetails($user_id, $order_id) {
        // Validate inputs
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        $order_id = filter_var($order_id, FILTER_VALIDATE_INT);
        
        if ($user_id === false || $order_id === false) {
            return null;
        }
        
        try {
            // Get order basic info
            $query = "SELECT o.*, c.name as customer_name, c.email, c.phone, c.address, c.city
                      FROM " . $this->table_orders . " o 
                      JOIN " . $this->table_users . " c ON o.customer_id = c.id 
                      WHERE o.id = :order_id AND o.customer_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Get order items - FIXED: using name_en instead of name
                $items_query = "SELECT oi.*, p.name_en as product_name, p.description_en as description 
                               FROM " . $this->table_order_items . " oi 
                               LEFT JOIN " . $this->table_products . " p ON oi.product_id = p.id 
                               WHERE oi.order_id = :order_id";
                
                $items_stmt = $this->conn->prepare($items_query);
                $items_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
                $items_stmt->execute();
                
                $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Sanitize output data
                $order = $this->sanitizeOrderData($order);
                
                // Generate tracking timeline based on order status and timestamps
                $order['tracking'] = $this->generateTrackingTimeline($order);
            }
            
            return $order;
        } catch (PDOException $e) {
            error_log("Database error in getOrderDetails: " . $e->getMessage());
            return null;
        }
    }
    
    // Sanitize order data for output
    private function sanitizeOrderData($order) {
        if (isset($order['items'])) {
            foreach ($order['items'] as &$item) {
                $item['product_name'] = htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $item['description'] = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $item['unit_price'] = (float)$item['unit_price'];
                $item['quantity'] = (int)$item['quantity'];
            }
        }
        
        $order['customer_name'] = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $order['email'] = htmlspecialchars($order['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $order['address'] = htmlspecialchars($order['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $order['city'] = htmlspecialchars($order['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $order['street'] = htmlspecialchars($order['street'] ?? '', ENT_QUOTES, 'UTF-8');
        
        return $order;
    }
    
    // Generate tracking timeline from order data (since no order_tracking table exists)
    private function generateTrackingTimeline($order) {
        $timeline = [];
        $status_flow = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
        
        foreach ($status_flow as $status) {
            $timeline_item = [
                'status' => $status,
                'created_at' => $order['created_at'],
                'description' => $this->getStatusDescription($status)
            ];
            
            // If this status is the current or a past status, use appropriate timestamps
            if ($status === 'pending') {
                $timeline_item['created_at'] = $order['created_at'];
            } elseif ($status === $order['status']) {
                // For current status, use updated_at time
                $timeline_item['created_at'] = $order['updated_at'];
            }
            
            $timeline[] = $timeline_item;
            
            // Stop if we've reached the current status
            if ($status === $order['status']) {
                break;
            }
        }
        
        return $timeline;
    }
    
    // Get description for order status
    private function getStatusDescription($status) {
        $descriptions = [
            'pending' => 'Order placed successfully',
            'confirmed' => 'Order confirmed by restaurant',
            'preparing' => 'Chef is preparing your food',
            'out_for_delivery' => 'Order is on the way to you',
            'delivered' => 'Order has been delivered'
        ];
        
        return $descriptions[$status] ?? 'Order status updated';
    }
    
    // Get order items for editing - FIXED: using name_en instead of name
    public function getOrderItemsForEdit($order_id, $user_id) {
        // Validate inputs
        $order_id = filter_var($order_id, FILTER_VALIDATE_INT);
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        
        if ($order_id === false || $user_id === false) {
            return false;
        }
        
        try {
            // Verify order belongs to user and is pending
            $verify_query = "SELECT o.status 
                            FROM " . $this->table_orders . " o 
                            WHERE o.id = :order_id AND o.customer_id = :user_id";
            
            $verify_stmt = $this->conn->prepare($verify_query);
            $verify_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $verify_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            
            $order = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order || $order['status'] !== 'pending') {
                return false;
            }
            
            // Get order items - FIXED: using name_en instead of name
            $items_query = "SELECT oi.*, p.name_en as product_name, p.stock_quantity 
                           FROM " . $this->table_order_items . " oi 
                           LEFT JOIN " . $this->table_products . " p ON oi.product_id = p.id 
                           WHERE oi.order_id = :order_id";
            
            $items_stmt = $this->conn->prepare($items_query);
            $items_stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $items_stmt->execute();
            
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output
            foreach ($items as &$item) {
                $item['product_name'] = htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8');
            }
            
            return $items;
        } catch (PDOException $e) {
            error_log("Database error in getOrderItemsForEdit: " . $e->getMessage());
            return false;
        }
    }
    
    // Additional security: Validate user ownership of order
    public function validateOrderOwnership($order_id, $user_id) {
        $order_id = filter_var($order_id, FILTER_VALIDATE_INT);
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        
        if ($order_id === false || $user_id === false) {
            return false;
        }
        
        try {
            $query = "SELECT id FROM " . $this->table_orders . " 
                      WHERE id = :order_id AND customer_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            error_log("Database error in validateOrderOwnership: " . $e->getMessage());
            return false;
        }
    }
}
?> 