<?php
/**
 * Rider Model
 * Represents delivery rider profile and data
 */

namespace Phool\DeliveryPanel\Models;

// Ensure BaseModel is loaded before this class is defined
if (!class_exists('Phool\DeliveryPanel\Models\BaseModel')) {
    require_once __DIR__ . '/BaseModel.php';
}

class Rider extends BaseModel {
    protected $table = 'riders';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_verified',
        'password',
        'profile_image_url',
        'rider_type',
        'partner_name',
        'vehicle_type',
        'vehicle_number',
        'home_address',
        'city',
        'country',
        'bank_name',
        'account_number',
        'ifsc_code',
        'base_salary',
        'status',
        'email_verified',
        'documents_verified',
        'bank_verified',
        'is_available',
    ];
    
    protected $hidden = [
        'password',
        'account_number',
        'ifsc_code',
    ];
    
    protected $casts = [
        'phone_verified' => 'boolean',
        'email_verified' => 'boolean',
        'documents_verified' => 'boolean',
        'bank_verified' => 'boolean',
        'is_available' => 'boolean',
        'base_salary' => 'float',
        'average_rating' => 'float',
        'total_earnings' => 'float',
    ];
    
    public function orders() {
        // Relationship to rider_orders
        return [];
    }
    
    public function earnings() {
        // Relationship to rider_earnings
        return [];
    }
    
    public function performance() {
        // Relationship to rider_performance
        return [];
    }
    
    public function documents() {
        // Relationship to rider_documents
        return [];
    }
    
    public function reviews() {
        // Relationship to rider_reviews
        return [];
    }
    
    /**
     * Get pending orders for this rider
     */
    public function getPendingOrders() {
        $sql = "SELECT * FROM rider_orders WHERE rider_id = ? AND delivery_status IN ('assigned', 'accepted', 'picked_up', 'on_the_way') ORDER BY assigned_at DESC";
        return $this->raw($sql, [$this->id])->fetchAll();
    }
    
    /**
     * Get today's deliveries
     */
    public function getTodayDeliveries() {
        $sql = "SELECT COUNT(*) as total FROM rider_orders WHERE rider_id = ? AND DATE(delivered_at) = CURDATE() AND delivery_status = 'delivered'";
        $result = $this->raw($sql, [$this->id])->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get today's earnings
     */
    public function getTodayEarnings() {
        $sql = "SELECT COALESCE(SUM(net_earnings), 0) as total FROM rider_earnings WHERE rider_id = ? AND DATE(created_at) = CURDATE()";
        $result = $this->raw($sql, [$this->id])->fetch();
        return floatval($result['total'] ?? 0);
    }
    
    /**
     * Get total earnings for date range
     */
    public function getTotalEarnings($fromDate = null, $toDate = null) {
        $sql = "SELECT COALESCE(SUM(net_earnings), 0) as total FROM rider_earnings WHERE rider_id = ?";
        $params = [$this->id];
        
        if ($fromDate) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $toDate;
        }
        
        $result = $this->raw($sql, $params)->fetch();
        return floatval($result['total'] ?? 0);
    }
    
    /**
     * Get delivery count for date range
     */
    public function getDeliveryCount($fromDate = null, $toDate = null) {
        $sql = "SELECT COUNT(*) as total FROM rider_orders WHERE rider_id = ? AND delivery_status = 'delivered'";
        $params = [$this->id];
        
        if ($fromDate) {
            $sql .= " AND DATE(delivered_at) >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= " AND DATE(delivered_at) <= ?";
            $params[] = $toDate;
        }
        
        $result = $this->raw($sql, $params)->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get all earnings history
     */
    public function getEarningsHistory($limit = 50) {
        $sql = "SELECT * FROM rider_earnings WHERE rider_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->raw($sql, [$this->id, $limit])->fetchAll();
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics() {
        $sql = "SELECT * FROM rider_performance WHERE rider_id = ? ORDER BY date DESC LIMIT 1";
        return $this->raw($sql, [$this->id])->fetch();
    }
    
    /**
     * Get available earnings (unpaid)
     */
    public function getAvailableEarnings() {
        $sql = "SELECT COALESCE(SUM(net_earnings), 0) as total FROM rider_earnings WHERE rider_id = ? AND payment_status IN ('pending', 'due')";
        $result = $this->raw($sql, [$this->id])->fetch();
        return floatval($result['total'] ?? 0);
    }
    
    /**
     * Check if rider is active
     */
    public function isActive() {
        return $this->status === 'active';
    }
    
    /**
     * Check if rider is online
     */
    public function isOnline() {
        return $this->is_available === 1 || $this->is_available === true;
    }
    
    /**
     * Get rider's display name
     */
    public function getDisplayName() {
        return ucwords($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Get rider type badge
     */
    public function getRiderTypeBadge() {
        $types = [
            'in_house' => 'In-House',
            'gig' => 'Gig Worker',
            'partner' => 'Partner'
        ];
        return $types[$this->rider_type] ?? 'Unknown';
    }

    /**
     * Create a new rider (static method for registration)
     */
    public static function create($data) {
        $instance = new self();
        
        // Insert the rider data
        $id = $instance->insert($data);
        
        if ($id) {
            // Return the newly created rider instance
            return $instance->find($id);
        }
        
        return null;
    }

    /**
     * Find by email or phone (static method)
     */
    public static function findByEmailOrPhone($identifier) {
        try {
            // Ensure database is loaded
            if (!class_exists('Database')) {
                require_once dirname(dirname(dirname(__FILE__))) . '/config/database.php';
            }
            
            $pdo = $GLOBALS['pdo'] ?? null;
            
            // If global PDO is not available, create a new connection
            if (!$pdo) {
                $dbClass = new Database();
                $pdo = $dbClass->getConnection();
            }
            
            if (!$pdo) {
                error_log("ERROR: Could not establish database connection in findByEmailOrPhone");
                return null;
            }
            
            $sql = "SELECT * FROM riders WHERE email = ? OR phone = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$identifier, $identifier]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $data ? (object)$data : null;
        } catch (\Exception $e) {
            error_log("EXCEPTION in findByEmailOrPhone: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        if (empty($hash)) return false;
        return password_verify($password, $hash);
    }

    /**
     * Or where condition
     */
    protected function orWhere($column, $operator = '=', $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->query = str_replace("WHERE {$column}", "WHERE email OR phone", $this->query);
        if (!$this->query) {
            $this->query = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ? OR {$column} {$operator} ?";
            $this->bindings = [$value, $value];
        } else {
            $this->query .= " OR {$column} {$operator} ?";
            $this->bindings[] = $value;
        }
        return $this;
    }
}
