<?php
/**
 * Dashboard Controller
 * Handles rider dashboard and overview
 */

namespace Phool\DeliveryPanel\Controllers;

use Phool\DeliveryPanel\Models\Rider;
use Phool\DeliveryPanel\Models\RiderOrder;

// Ensure models are properly loaded with their dependencies
require_once dirname(__DIR__) . '/models/BaseModel.php';
require_once dirname(__DIR__) . '/models/Rider.php';
require_once dirname(__DIR__) . '/models/RiderOrder.php';

class DashboardController {
    /**
     * Show dashboard
     */
    public function index() {
        // Check if rider is authenticated
        if (!isset($_SESSION['rider_id'])) {
            header('Location: ' . app_url('/login'));
            exit;
        }
        
        $riderId = $_SESSION['rider_id'];
        
        // Fetch rider data
        $rider = new Rider();
        $riderData = $rider->find($riderId);
        
        if (!$riderData) {
            header('Location: ' . app_url('/logout'));
            exit;
        }
        
        // Create rider object
        $rider = new Rider();
        $rider = (object) $riderData;
        
        // Get pending orders (assigned, accepted, picked_up, on_the_way, arrived)
        $orderModel = new RiderOrder();
        $pendingOrdersData = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['assigned', 'accepted', 'picked_up', 'on_the_way', 'arrived']
        ]);
        
        // Convert objects to arrays for view compatibility
        $pendingOrders = [];
        if ($pendingOrdersData) {
            foreach ($pendingOrdersData as $order) {
                if (is_object($order)) {
                    $pendingOrders[] = (array) $order;
                } else {
                    $pendingOrders[] = $order;
                }
            }
        }
        
        // Calculate today's stats, total stats, and ratings
        $todayDeliveries = 0;
        $todayEarnings = 0.00;
        $totalDeliveries = 0;
        $totalEarnings = 0.00;
        $averageRating = 0.0;
        $onTimeRate = 0.0;
        
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
            
            // Get today's deliveries count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM rider_orders 
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) = CURDATE()
            ");
            $stmt->execute([$riderId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $todayDeliveries = intval($result['total'] ?? 0);
            
            // Get total deliveries (all time)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM rider_orders 
                WHERE rider_id = ? AND delivery_status = 'delivered'
            ");
            $stmt->execute([$riderId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalDeliveries = intval($result['total'] ?? 0);
            
            // Get today's earnings (sum of order amounts for delivered orders today)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as total FROM rider_orders 
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) = CURDATE()
            ");
            $stmt->execute([$riderId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $todayEarnings = floatval($result['total'] ?? 0);
            
            // Get total earnings (all time)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as total FROM rider_orders 
                WHERE rider_id = ? AND delivery_status = 'delivered'
            ");
            $stmt->execute([$riderId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalEarnings = floatval($result['total'] ?? 0);
            
            // Get average rating
            $stmt = $pdo->prepare("
                SELECT COALESCE(AVG(feedback_rating), 0) as avg_rating FROM rider_orders 
                WHERE rider_id = ? AND feedback_rating IS NOT NULL AND feedback_rating > 0
            ");
            $stmt->execute([$riderId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $averageRating = floatval($result['avg_rating'] ?? 0);
            
            // Get on-time delivery rate
            if ($totalDeliveries > 0) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN delivered_at <= DATE_ADD(assigned_at, INTERVAL estimated_delivery_time MINUTE) THEN 1 ELSE 0 END) as on_time
                    FROM rider_orders 
                    WHERE rider_id = ? AND delivery_status = 'delivered' AND estimated_delivery_time IS NOT NULL
                ");
                $stmt->execute([$riderId]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $onTimeCount = intval($result['on_time'] ?? 0);
                $totalCount = intval($result['total'] ?? 1);
                $onTimeRate = ($totalCount > 0) ? ($onTimeCount / $totalCount) * 100 : 0;
            }
            
        } catch (\Exception $e) {
            // Database error, use defaults
            error_log('Dashboard stats error: ' . $e->getMessage());
        }
        
        // Prepare data for view
        $data = [
            'rider' => $rider,
            'rider_type' => $riderData->rider_type ?? 'in_house',
            'today_deliveries' => $todayDeliveries,
            'today_earnings' => $todayEarnings,
            'total_earnings' => $totalEarnings,
            'pending_orders' => $pendingOrders ?? [],
            'pending_orders_count' => count($pendingOrders ?? []),
            'average_rating' => round($averageRating, 1),
            'total_deliveries' => $totalDeliveries,
            'on_time_rate' => round($onTimeRate, 1),
            'page_title' => 'Dashboard'
        ];
        
        // Extract data into variables for view
        extract($data);
        
        // Load view
        require_once dirname(dirname(__FILE__)) . '/views/dashboard/index.php';
    }
    
    /**
     * Get dashboard data via AJAX
     */
    public function getDashboardData() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['rider_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        // TODO: Fetch and return dashboard data
        
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
    }
}
