<?php
class ExpenseService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function addExpense($data) {
        $sql = "INSERT INTO expenses (category_id, amount, description, expense_date, bill_receipt, remarks, payment_method, status, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['category_id'],
            $data['amount'],
            $data['description'],
            $data['expense_date'],
            $data['bill_receipt'] ?? null,
            $data['remarks'] ?? null,
            $data['payment_method'] ?? 'cash',
            $data['status'] ?? 'approved',
            $data['recorded_by']
        ]);
    }
    
    public function updateExpense($id, $data) {
        $sql = "UPDATE expenses SET category_id = ?, amount = ?, description = ?, expense_date = ?, 
                bill_receipt = ?, remarks = ?, payment_method = ?, status = ? 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['category_id'],
            $data['amount'],
            $data['description'],
            $data['expense_date'],
            $data['bill_receipt'] ?? null,
            $data['remarks'] ?? null,
            $data['payment_method'] ?? 'cash',
            $data['status'] ?? 'approved',
            $id
        ]);
    }
    
    public function deleteExpense($id) {
        $stmt = $this->pdo->prepare("DELETE FROM expenses WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getExpense($id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ec.name as category_name, ec.parent_category, u.full_name as recorded_by_name 
            FROM expenses e 
            LEFT JOIN expense_categories ec ON e.category_id = ec.id 
            LEFT JOIN users u ON e.recorded_by = u.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getExpenses($filters = []) {
        $where = "1=1";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $where .= " AND e.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['parent_category'])) {
            $where .= " AND ec.parent_category = ?";
            $params[] = $filters['parent_category'];
        }
        
        if (!empty($filters['start_date'])) {
            $where .= " AND e.expense_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where .= " AND e.expense_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['payment_method'])) {
            $where .= " AND e.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        $sql = "
            SELECT e.*, ec.name as category_name, ec.parent_category, u.full_name as recorded_by_name 
            FROM expenses e 
            LEFT JOIN expense_categories ec ON e.category_id = ec.id 
            LEFT JOIN users u ON e.recorded_by = u.id 
            WHERE $where 
            ORDER BY e.expense_date DESC, e.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getExpenseSummary($start_date = null, $end_date = null) {
        $where = "1=1";
        $params = [];
        
        if ($start_date) {
            $where .= " AND e.expense_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where .= " AND e.expense_date <= ?";
            $params[] = $end_date;
        }
        
        $sql = "
            SELECT 
                ec.parent_category,
                ec.name as category_name,
                COUNT(e.id) as expense_count,
                SUM(e.amount) as total_amount
            FROM expense_categories ec
            LEFT JOIN expenses e ON ec.id = e.category_id AND $where
            WHERE ec.status = 'active'
            GROUP BY ec.id, ec.parent_category, ec.name
            ORDER BY ec.parent_category, ec.name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getTotalExpenses($start_date = null, $end_date = null) {
        $where = "1=1";
        $params = [];
        
        if ($start_date) {
            $where .= " AND expense_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where .= " AND expense_date <= ?";
            $params[] = $end_date;
        }
        
        $stmt = $this->pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE $where");
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}
?>