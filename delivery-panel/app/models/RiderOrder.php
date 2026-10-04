<?php
/**
 * Rider Order Model
 * Represents an order assigned to a delivery rider
 */

namespace Phool\DeliveryPanel\Models;

// Ensure BaseModel is loaded before this class is defined
if (!class_exists('Phool\DeliveryPanel\Models\BaseModel')) {
    require_once __DIR__ . '/BaseModel.php';
}

class RiderOrder extends BaseModel {
    protected $table = 'rider_orders';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'rider_id',
        'order_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'pickup_address',
        'delivery_address',
        'pickup_city',
        'delivery_city',
        'total_items',
        'total_amount',
        'distance_km',
        'estimated_delivery_time',
        'delivery_status',
        'payment_method',
        'payment_status',
        'cod_amount',
        'cod_collected',
        'special_instructions',
        'otp_code',
        'failed_reason',
        'cancellation_reason',
    ];
    
    protected $casts = [
        'total_items' => 'integer',
        'total_amount' => 'float',
        'distance_km' => 'float',
        'estimated_delivery_time' => 'integer',
        'cod_amount' => 'float',
        'cod_collected' => 'float',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
    
    protected $statuses = [
        'assigned',
        'accepted',
        'picked_up',
        'on_the_way',
        'arrived',
        'delivered',
        'failed',
        'cancelled',
        'returned',
    ];
    
    public function rider() {
        // Belongs to Rider
        return [];
    }
    
    public function order() {
        // Belongs to Order
        return [];
    }
    
    public function earnings() {
        // Has one RiderEarning
        return [];
    }
    
    public function logs() {
        // Has many DeliveryLog
        return [];
    }
    
    public function review() {
        // Has one RiderReview
        return [];
    }
    
    /**
     * Check if order is delivered
     */
    public function isDelivered() {
        return $this->delivery_status === 'delivered';
    }
    
    /**
     * Check if order is active (not delivered/failed/cancelled)
     */
    public function isActive() {
        return in_array($this->delivery_status, [
            'accepted',
            'picked_up',
            'on_the_way',
            'arrived'
        ]);
    }
    
    /**
     * Check if order can be accepted
     */
    public function canBeAccepted() {
        return $this->delivery_status === 'assigned';
    }
    
    /**
     * Check if order can be picked up
     */
    public function canBePickedUp() {
        return $this->delivery_status === 'accepted';
    }
    
    /**
     * Check if order can be delivered
     */
    public function canBeDelivered() {
        return in_array($this->delivery_status, [
            'picked_up',
            'on_the_way',
            'arrived'
        ]);
    }
    
    /**
     * Get time remaining for delivery
     */
    public function getTimeRemaining() {
        if ($this->isDelivered()) {
            return 0;
        }
        
        $estimatedTime = strtotime($this->assigned_at) + ($this->estimated_delivery_time * 60);
        $remainingSeconds = $estimatedTime - time();
        
        return max(0, $remainingSeconds);
    }
    
    /**
     * Check if delivery is late
     */
    public function isLate() {
        return $this->getTimeRemaining() <= 0 && !$this->isDelivered();
    }
    
    /**
     * Get delivery distance
     */
    public function getDistance() {
        return $this->distance_km ?? 0;
    }

    /**
     * Get complete order details with items and related data
     */
    public function getOrderDetails($riderId, $orderId) {
        try {
            error_log('DEBUG getOrderDetails: riderId=' . $riderId . ', orderId=' . $orderId);
            
            // Use the existing PDO connection from bootstrap instead of creating a new one
            $pdo = $GLOBALS['pdo'] ?? null;
            
            if (!$pdo) {
                error_log('ERROR: No PDO connection available in $GLOBALS');
                // Fallback: try to create connection
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
                $user = getenv('DB_USER') ?: 'root';
                $password = getenv('DB_PASSWORD') ?: '';
                
                error_log('DB Fallback Config: host=' . $host . ', dbname=' . $dbName . ', user=' . $user);
                
                $pdo = new \PDO(
                    'mysql:host=' . $host . ';dbname=' . $dbName,
                    $user,
                    $password
                );
                
                error_log('DB Connected successfully (fallback) for getOrderDetails');
            } else {
                error_log('Using existing PDO connection from bootstrap');
            }

            // Fetch order with customer and rider info
            $stmt = $pdo->prepare("
                SELECT 
                    ro.*,
                    o.total_amount,
                    o.status as order_status,
                    o.payment_method,
                    o.payment_status,
                    c.first_name as customer_first_name,
                    c.last_name as customer_last_name,
                    c.email as customer_email,
                    c.phone as customer_phone,
                    c.delivery_address,
                    c.delivery_city,
                    c.delivery_district
                FROM rider_orders ro
                LEFT JOIN orders o ON ro.order_id = o.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE ro.rider_id = ? AND ro.order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$riderId, $orderId]);
            $order = $stmt->fetch(\PDO::FETCH_OBJ);
            
            error_log('Query executed. Result: ' . ($order ? 'FOUND (order_number=' . ($order->order_number ?? 'NULL') . ')' : 'NOT FOUND'));

            if (!$order) {
                error_log('DEBUG getOrderDetails: NOT FOUND for riderId=' . $riderId . ', orderId=' . $orderId);
                return null;
            }

            // Fetch order items with product details
            $stmt = $pdo->prepare("
                SELECT 
                    oi.id,
                    oi.product_id,
                    oi.quantity,
                    oi.unit_price,
                    oi.total_price,
                    p.name_en as product_name,
                    p.unit
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id
            ");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(\PDO::FETCH_OBJ);
            
            error_log('Items fetched: ' . count($items) . ' items found');

            // Fetch rider earnings for this order
            $stmt = $pdo->prepare("
                SELECT *
                FROM rider_earnings
                WHERE rider_id = ? AND order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$riderId, $orderId]);
            $earnings = $stmt->fetch(\PDO::FETCH_OBJ);
            
            error_log('Earnings: ' . ($earnings ? 'FOUND' : 'NOT FOUND'));

            return [
                'order' => $order,
                'items' => $items,
                'earnings' => $earnings
            ];
        } catch (\Exception $e) {
            error_log('Error fetching order details: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return null;
        }
    }

    /**
     * Get orders with complete details for a rider
     */
    public function getOrdersWithDetails($riderId, $filters = []) {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';
            
            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password
            );

            $query = "
                SELECT 
                    ro.id,
                    ro.rider_id,
                    ro.order_id,
                    ro.order_number,
                    ro.customer_name,
                    ro.customer_phone,
                    ro.delivery_city,
                    ro.total_items,
                    ro.total_amount,
                    ro.delivery_status,
                    ro.payment_status,
                    ro.assigned_at,
                    ro.accepted_at,
                    ro.delivered_at,
                    o.total_amount as order_total,
                    o.status as order_status,
                    COUNT(DISTINCT oi.id) as item_count,
                    COALESCE(SUM(oi.total_price), 0) as items_total
                FROM rider_orders ro
                LEFT JOIN orders o ON ro.order_id = o.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE ro.rider_id = ?
            ";

            // Apply filters
            if (isset($filters['status'])) {
                $query .= " AND ro.delivery_status = '" . $filters['status'] . "'";
            }
            if (isset($filters['statuses']) && is_array($filters['statuses'])) {
                $statuses = array_map(function($s) { return "'" . $s . "'"; }, $filters['statuses']);
                $query .= " AND ro.delivery_status IN (" . implode(',', $statuses) . ")";
            }

            $query .= " GROUP BY ro.id ORDER BY ro.assigned_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$riderId]);
            $orders = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Convert to arrays for consistency
            $result = [];
            foreach ($orders as $order) {
                $result[] = (array) $order;
            }

            return $result;
        } catch (\Exception $e) {
            error_log('Error fetching orders with details: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order with items (complete data)
     */
    public function getWithItems($orderId) {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';
            
            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password
            );

            // Get order
            $stmt = $pdo->prepare("SELECT * FROM rider_orders WHERE order_id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                return null;
            }

            // Get items
            $stmt = $pdo->prepare("
                SELECT 
                    oi.*,
                    p.name_en as product_name,
                    p.unit,
                    p.category_id
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'order' => $order,
                'items' => $items
            ];
        } catch (\Exception $e) {
            error_log('Error in getWithItems: ' . $e->getMessage());
            return null;
        }
    }
}
