<?php
class Message {
    private $conn;
    private $table_name = "messages";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($customer_id, $title, $message, $type = "system", $related_id = null) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        $query = "INSERT INTO " . $this->table_name . "
                SET customer_id=:customer_id, title=:title, message=:message,
                type=:type, related_id=:related_id, created_at=NOW(), updated_at=NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        // Enhanced input validation and sanitization
        $title = $this->sanitizeInput($title, 255);
        $message = $this->sanitizeInput($message, 2000);
        $type = in_array($type, ['system', 'order', 'promotion']) ? $type : 'system';
        
        // Validate related_id if provided
        if ($related_id && !is_numeric($related_id)) {
            $related_id = null;
        }
        
        // Bind parameters
        $stmt->bindParam(":customer_id", $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(":title", $title, PDO::PARAM_STR);
        $stmt->bindParam(":message", $message, PDO::PARAM_STR);
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->bindParam(":related_id", $related_id, $related_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        
        return $stmt->execute();
    }
    
    public function getCustomerMessages($customer_id, $limit = null, $offset = 0) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        $query = "SELECT id, title, message, type, is_read, created_at 
                  FROM " . $this->table_name . "
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        
        if ($limit) {
            // Validate and sanitize limit/offset
            $limit = max(1, min(100, (int)$limit)); // Enforce reasonable limits
            $offset = max(0, (int)$offset);
            
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCustomerMessagesCount($customer_id) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . "
                WHERE customer_id = :customer_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
    
    public function updateReadStatus($message_id, $customer_id) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        // Validate message_id format
        if (!is_numeric($message_id) || $message_id <= 0) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . "
                SET is_read = 1, updated_at = NOW()
                WHERE id = :message_id AND customer_id = :customer_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":message_id", $message_id, PDO::PARAM_INT);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    public function getUnreadCount($customer_id) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        $query = "SELECT COUNT(*) as count
                FROM " . $this->table_name . "
                WHERE customer_id = :customer_id AND is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    public function markAllAsRead($customer_id) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        $query = "UPDATE " . $this->table_name . "
                SET is_read = 1, updated_at = NOW()
                WHERE customer_id = :customer_id AND is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    public function deleteMessage($message_id, $customer_id) {
        // Validate customer_id ownership
        if (!$this->validateCustomerOwnership($customer_id)) {
            throw new Exception("Unauthorized access");
        }
        
        // Validate message_id format
        if (!is_numeric($message_id) || $message_id <= 0) {
            return false;
        }
        
        $query = "DELETE FROM " . $this->table_name . "
                WHERE id = :message_id AND customer_id = :customer_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":message_id", $message_id, PDO::PARAM_INT);
        $stmt->bindValue(":customer_id", $customer_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Enhanced security helper methods
     */
    
    private function validateCustomerOwnership($customer_id) {
        // Ensure the customer_id matches the authenticated user
        if (!isset($_SESSION['customer_id'])) {
            return false;
        }
        
        return ($_SESSION['customer_id'] == $customer_id);
    }
    
    private function sanitizeInput($input, $max_length = null) {
        if (is_null($input)) {
            return '';
        }
        
        // Remove potentially harmful characters while preserving meaningful content
        $sanitized = htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        
        // Enforce maximum length if specified
        if ($max_length && mb_strlen($sanitized) > $max_length) {
            $sanitized = mb_substr($sanitized, 0, $max_length);
        }
        
        return $sanitized;
    }
    
    private function validateInteger($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $value = (int)$value;
        
        if (!is_null($min) && $value < $min) {
            return false;
        }
        
        if (!is_null($max) && $value > $max) {
            return false;
        }
        
        return true;
    }
}
?>