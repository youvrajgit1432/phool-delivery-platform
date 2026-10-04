<?php
namespace Phool\DeliveryPanel\Models;

use PDO;

class Ticket {
    private $pdo;
    private $table = 'rider_support_tickets';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all tickets for a rider
     */
    public function getByRiderId($riderId, $status = null) {
        $query = "SELECT * FROM {$this->table} WHERE rider_id = :rider_id";
        
        if ($status) {
            $query .= " AND status = :status";
        }
        
        $query .= " ORDER BY opened_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':rider_id', $riderId, PDO::PARAM_INT);
        
        if ($status) {
            $stmt->bindValue(':status', $status);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get single ticket details
     */
    public function getById($id, $riderId) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id AND rider_id = :rider_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':rider_id', $riderId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Create new support ticket
     */
    public function create($riderId, $subject, $description, $category, $priority = 'medium') {
        $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(
            $this->pdo->query("SELECT COUNT(*) FROM {$this->table} WHERE YEAR(created_at) = YEAR(NOW())")->fetchColumn() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        $query = "INSERT INTO {$this->table} 
                  (rider_id, ticket_number, subject, description, category, priority, status, opened_at, created_at, updated_at)
                  VALUES (:rider_id, :ticket_number, :subject, :description, :category, :priority, 'open', NOW(), NOW(), NOW())";

        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':rider_id' => $riderId,
            ':ticket_number' => $ticketNumber,
            ':subject' => $subject,
            ':description' => $description,
            ':category' => $category,
            ':priority' => $priority
        ]);
    }

    /**
     * Update ticket status
     */
    public function updateStatus($id, $riderId, $status) {
        $query = "UPDATE {$this->table} SET status = :status, updated_at = NOW()";
        
        if ($status === 'resolved') {
            $query .= ", resolved_at = NOW()";
        } elseif ($status === 'closed') {
            $query .= ", closed_at = NOW()";
        }
        
        $query .= " WHERE id = :id AND rider_id = :rider_id";
        
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':id' => $id,
            ':rider_id' => $riderId,
            ':status' => $status
        ]);
    }

    /**
     * Add response/message to ticket
     */
    public function addMessage($id, $riderId, $message) {
        $query = "UPDATE {$this->table} SET response_message = CONCAT(IFNULL(response_message, ''), '\n---\n', :message), updated_at = NOW() 
                  WHERE id = :id AND rider_id = :rider_id";
        
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':id' => $id,
            ':rider_id' => $riderId,
            ':message' => $message
        ]);
    }

    /**
     * Get ticket statistics for rider
     */
    public function getStats($riderId) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count
                  FROM {$this->table} 
                  WHERE rider_id = :rider_id";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':rider_id', $riderId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get category options
     */
    public static function getCategories() {
        return [
            'payment' => 'Payment Issues',
            'technical' => 'Technical Problem',
            'account' => 'Account',
            'order' => 'Order Related',
            'documents' => 'Documents',
            'other' => 'Other'
        ];
    }

    /**
     * Get status options
     */
    public static function getStatuses() {
        return [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'reopened' => 'Reopened'
        ];
    }

    /**
     * Get priority options
     */
    public static function getPriorities() {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];
    }
}
