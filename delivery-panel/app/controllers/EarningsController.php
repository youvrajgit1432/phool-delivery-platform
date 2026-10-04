<?php
/**
 * Earnings Controller
 * Handles rider earnings, payouts, and settlement based on rider type
 */

namespace Phool\DeliveryPanel\Controllers;

use Phool\DeliveryPanel\Models\Rider;

// Ensure models are properly loaded with their dependencies
require_once dirname(__DIR__) . '/models/BaseModel.php';
require_once dirname(__DIR__) . '/models/Rider.php';

class EarningsController {
    /**
     * Show earnings page with type-specific display
     */
    public function index() {
        if (!isset($_SESSION['rider_id'])) {
            header('Location: ' . app_url('/login'));
            exit;
        }
        
        $riderId = $_SESSION['rider_id'];
        
        // Fetch rider
        $riderModel = new Rider();
        $rider = $riderModel->find($riderId);
        
        if (!$rider) {
            header('Location: ' . app_url('/logout'));
            exit;
        }
        
        $riderType = $rider->rider_type;
        
        // Get earnings based on rider type
        $earnings = $this->getEarningsData($riderId, $riderType);
        
        // Prepare data for view
        $data = [
            'rider' => $rider,
            'rider_type' => $riderType,
            'earnings_data' => $earnings,
            'page_title' => 'Earnings'
        ];
        
        extract($data);
        require_once dirname(dirname(__FILE__)) . '/views/earnings/index.php';
    }
    
    /**
     * Get earnings data based on rider type
     */
    private function getEarningsData($riderId, $riderType) {
        $riderModel = new Rider();
        
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        
        switch ($riderType) {
            case 'gig':
                return $this->getGigEarnings($riderId, $today, $monthStart, $monthEnd);
            
            case 'in_house':
                return $this->getInHouseEarnings($riderId, $today, $monthStart, $monthEnd);
            
            case 'partner':
                return $this->getPartnerEarnings($riderId, $today, $monthStart, $monthEnd);
            
            default:
                return [];
        }
    }
    
    /**
     * Get earnings for Gig workers (per-order basis)
     */
    private function getGigEarnings($riderId, $today, $monthStart, $monthEnd) {
        try {
            // Use global PDO connection from bootstrap/app.php
            $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null;
            
            if (!$pdo) {
                throw new \Exception('Database connection not available');
            }
            
            // Count today's deliveries
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as deliveries FROM rider_orders
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) = ?
            ");
            $stmt->execute([$riderId, $today]);
            $todayDeliveries = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Today's earnings
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(base_delivery_fee), 0) as base_fees,
                    COALESCE(SUM(distance_surge), 0) as distance_surges,
                    COALESCE(SUM(time_surge), 0) as time_surges,
                    COALESCE(SUM(on_time_bonus), 0) as bonuses,
                    COALESCE(SUM(penalties_deducted), 0) as penalties,
                    COALESCE(SUM(net_earnings), 0) as total
                FROM rider_earnings 
                WHERE rider_id = ? AND DATE(created_at) = ?
            ");
            $stmt->execute([$riderId, $today]);
            $todayEarnings = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Count this month's deliveries
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_deliveries FROM rider_orders
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $monthDeliveries = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // This month's earnings
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(base_delivery_fee), 0) as base_fees,
                    COALESCE(SUM(distance_surge), 0) as distance_surges,
                    COALESCE(SUM(time_surge), 0) as time_surges,
                    COALESCE(SUM(on_time_bonus), 0) as bonuses,
                    COALESCE(SUM(penalties_deducted), 0) as penalties,
                    COALESCE(SUM(net_earnings), 0) as total
                FROM rider_earnings 
                WHERE rider_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $monthEarnings = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Add delivery counts to earnings arrays
            $todayEarnings['deliveries'] = intval($todayDeliveries['deliveries'] ?? 0);
            $monthEarnings['total_deliveries'] = intval($monthEarnings['total_deliveries'] ?? $monthDeliveries['total_deliveries'] ?? 0);
            
            // Calculate best day of the month
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(net_earnings), 0) as best_day_amount
                FROM rider_earnings 
                WHERE rider_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY best_day_amount DESC
                LIMIT 1
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $bestDay = $stmt->fetch(\PDO::FETCH_ASSOC);
            $monthEarnings['best_day'] = floatval($bestDay['best_day_amount'] ?? 0);
            
            // Get recent orders with earnings details
            $stmt = $pdo->prepare("
                SELECT 
                    ro.order_number,
                    ro.delivered_at as date,
                    COALESCE(re.base_delivery_fee, 0) as base_fee,
                    COALESCE(re.distance_surge + re.time_surge, 0) as surges,
                    COALESCE(re.on_time_bonus, 0) as bonuses,
                    COALESCE(re.penalties_deducted, 0) as penalties,
                    COALESCE(re.net_earnings, 0) as total
                FROM rider_orders ro
                LEFT JOIN rider_earnings re ON ro.id = re.rider_order_id
                WHERE ro.rider_id = ? AND ro.delivery_status = 'delivered'
                ORDER BY ro.delivered_at DESC
                LIMIT 20
            ");
            $stmt->execute([$riderId]);
            $recentEarnings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return [
                'type' => 'gig',
                'today' => $todayEarnings,
                'this_month' => $monthEarnings,
                'recent' => $recentEarnings
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to fetch earnings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get earnings for In-House riders (fixed salary + incentives)
     */
    private function getInHouseEarnings($riderId, $today, $monthStart, $monthEnd) {
        try {
            // Use global PDO connection from bootstrap/app.php
            $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null;
            
            if (!$pdo) {
                throw new \Exception('Database connection not available');
            }
            
            // Get rider's base salary
            $stmt = $pdo->prepare("SELECT base_salary FROM riders WHERE id = ?");
            $stmt->execute([$riderId]);
            $rider = $stmt->fetch(\PDO::FETCH_ASSOC);
            $baseSalary = $rider['base_salary'] ?? 0;
            
            // Get bonuses this month
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(amount), 0) as total
                FROM rider_bonuses 
                WHERE rider_id = ? AND status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $bonuses = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get penalties this month (from rider_earnings if penalties_deducted exists)
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(penalties_deducted), 0) as total
                FROM rider_earnings 
                WHERE rider_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $penaltiesEarnings = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Count completed deliveries this month from rider_orders
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_deliveries,
                    COALESCE(AVG(feedback_rating), 0) as avg_rating,
                    COALESCE(SUM(CASE WHEN TIME(delivered_at) <= TIME(DATE_ADD(picked_up_at, INTERVAL estimated_delivery_time MINUTE)) THEN 1 ELSE 0 END), 0) as on_time_count
                FROM rider_orders 
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $monthDeliveries = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Count cancelled orders
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM rider_orders
                WHERE rider_id = ? AND delivery_status IN ('cancelled', 'failed') AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $cancellations = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get bonus details for bonuses table
            $stmt = $pdo->prepare("
                SELECT id, bonus_type as type, description, amount, bonus_date as date, status
                FROM rider_bonuses 
                WHERE rider_id = ? AND DATE(created_at) BETWEEN ? AND ?
                ORDER BY bonus_date DESC
                LIMIT 10
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $bonusList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $totalDeliveries = intval($monthDeliveries['total_deliveries'] ?? 0);
            $onTimeCount = intval($monthDeliveries['on_time_count'] ?? 0);
            $onTimeRate = $totalDeliveries > 0 ? round(($onTimeCount / $totalDeliveries) * 100, 2) : 0;
            
            return [
                'type' => 'in_house',
                'base_salary' => $baseSalary,
                'bonuses_this_month' => floatval($bonuses['total'] ?? 0),
                'penalties_this_month' => floatval($penaltiesEarnings['total'] ?? 0),
                'allowances' => 0,
                'net_this_month' => $baseSalary + floatval($bonuses['total'] ?? 0) - floatval($penaltiesEarnings['total'] ?? 0),
                'this_month' => [
                    'deliveries' => $totalDeliveries,
                    'completion_rate' => $totalDeliveries > 0 ? round((($totalDeliveries - intval($cancellations['total'] ?? 0)) / $totalDeliveries) * 100, 2) : 0,
                    'avg_rating' => floatval($monthDeliveries['avg_rating'] ?? 0),
                    'on_time_rate' => $onTimeRate,
                    'cancellations' => intval($cancellations['total'] ?? 0),
                    'performance_score' => $totalDeliveries > 0 ? round((floatval($monthDeliveries['avg_rating'] ?? 0) / 5) * 10, 1) : 0
                ],
                'bonuses' => $bonusList
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to fetch earnings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get earnings for Partner riders (invoice-based)
     */
    private function getPartnerEarnings($riderId, $today, $monthStart, $monthEnd) {
        try {
            // Use global PDO connection from bootstrap/app.php
            $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null;
            
            if (!$pdo) {
                throw new \Exception('Database connection not available');
            }
            
            // Count deliveries this month
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM rider_orders
                WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $monthOrders = $stmt->fetch(\PDO::FETCH_ASSOC);
            $monthDeliveries = intval($monthOrders['total'] ?? 0);
            
            // Calculate total invoiced amount this month
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(ro.total_amount), 0) as total_invoiced
                FROM rider_orders ro
                WHERE ro.rider_id = ? AND ro.delivery_status = 'delivered' AND DATE(ro.delivered_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $invoiced = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalInvoiced = floatval($invoiced['total_invoiced'] ?? 0);
            
            // Get pending settlement (orders delivered but not yet invoiced/paid)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(ro.total_amount), 0) as pending
                FROM rider_orders ro
                WHERE ro.rider_id = ? AND ro.delivery_status IN ('delivered', 'on_the_way', 'arrived') AND DATE(ro.created_at) BETWEEN ? AND ?
                AND ro.id NOT IN (
                    SELECT DISTINCT rider_order_id FROM rider_earnings 
                    WHERE rider_id = ? AND payment_status != 'pending'
                )
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd, $riderId]);
            $pending = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pendingSettlement = floatval($pending['pending'] ?? 0);
            
            // Get last settlement/payout info
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(net_earnings), 0) as settled_amount
                FROM rider_earnings 
                WHERE rider_id = ? AND payment_status = 'paid' AND DATE(payout_date) BETWEEN DATE_SUB(?, INTERVAL 1 MONTH) AND ?
                ORDER BY payout_date DESC
                LIMIT 1
            ");
            $stmt->execute([$riderId, $today, $today]);
            $lastSettled = $stmt->fetch(\PDO::FETCH_ASSOC);
            $settledAmount = floatval($lastSettled['settled_amount'] ?? 0);
            
            // Get invoices list
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT('INV-', DATE_FORMAT(DATE(ro.delivered_at), '%Y%m'), '-', ro.rider_id) as invoice_no,
                    DATE_FORMAT(DATE(ro.delivered_at), '%M %Y') as period,
                    COUNT(*) as deliveries,
                    COALESCE(SUM(ro.total_amount), 0) as amount,
                    DATE(ro.delivered_at) as date,
                    'pending' as status
                FROM rider_orders ro
                WHERE ro.rider_id = ? AND ro.delivery_status = 'delivered' AND DATE(ro.delivered_at) BETWEEN ? AND ?
                GROUP BY DATE(ro.delivered_at)
                ORDER BY DATE(ro.delivered_at) DESC
                LIMIT 10
            ");
            $stmt->execute([$riderId, $monthStart, $monthEnd]);
            $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get bank details
            $stmt = $pdo->prepare("
                SELECT bank_name, account_number, account_holder_name as account_holder, ifsc_code
                FROM riders WHERE id = ?
            ");
            $stmt->execute([$riderId]);
            $bankDetails = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [
                'bank_name' => 'Not Set',
                'account_number' => '',
                'account_holder' => 'Not Set',
                'ifsc_code' => 'Not Set'
            ];
            
            return [
                'type' => 'partner',
                'month_deliveries' => $monthDeliveries,
                'total_invoiced' => $totalInvoiced,
                'pending_settlement' => $pendingSettlement,
                'settled_amount' => $settledAmount,
                'invoices' => $invoices,
                'bank_details' => $bankDetails
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to fetch earnings: ' . $e->getMessage()
            ];
        }
    }
}
