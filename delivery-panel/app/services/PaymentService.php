<?php
// delivery-panel/app/services/PaymentService.php

namespace App\Services;

use PDO;
use Exception;

/**
 * PaymentService - Handles payment collection and verification
 * Replaces stored procedure: sp_record_cod_collection
 */
class PaymentService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Record COD (Cash on Delivery) collection
     * Replaces: sp_record_cod_collection
     */
    public function recordCODCollection($riderOrderId, $riderId, $amountCollected, $varianceReason = null)
    {
        try {
            $this->db->beginTransaction();

            // Get current order details
            $stmt = $this->db->prepare("
                SELECT order_id, cod_amount, delivery_status
                FROM rider_orders
                WHERE id = ? AND rider_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$riderOrderId, $riderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found'
                ];
            }

            $orderId = $order['order_id'];
            $codAmount = $order['cod_amount'];

            // Validate COD amount
            if ($codAmount <= 0) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'This order does not have COD payment'
                ];
            }

            // Calculate variance
            $amountVariance = $codAmount - $amountCollected;

            // Log payment verification
            $logStmt = $this->db->prepare("
                INSERT INTO payment_verification_logs (
                    rider_order_id,
                    order_id,
                    rider_id,
                    cod_amount,
                    amount_collected,
                    amount_variance,
                    variance_reason,
                    payment_status_before,
                    payment_status_after,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $riderOrderId,
                $orderId,
                $riderId,
                $codAmount,
                $amountCollected,
                $amountVariance,
                $varianceReason,
                'pending',
                'collected',
                'Payment collected from customer'
            ]);

            // Update rider_orders
            $updateStmt = $this->db->prepare("
                UPDATE rider_orders
                SET 
                    cod_collected = ?,
                    payment_status = 'collected',
                    updated_at = NOW()
                WHERE id = ? AND rider_id = ?
            ");
            $updateStmt->execute([
                $amountCollected,
                $riderOrderId,
                $riderId
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Payment collected: NPR ' . number_format($amountCollected, 2),
                'amount_collected' => $amountCollected,
                'variance' => $amountVariance
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Database error occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment verification logs for an order
     */
    public function getPaymentLogs($orderId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM payment_verification_logs
                WHERE order_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get COD summary for a rider
     */
    public function getRiderCODSummary($riderId, $startDate = null, $endDate = null)
    {
        try {
            $query = "
                SELECT 
                    SUM(cod_amount) as total_cod_amount,
                    SUM(cod_collected) as total_collected,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'collected' THEN 1 ELSE 0 END) as collected_count
                FROM rider_orders
                WHERE rider_id = ?
            ";
            
            $params = [$riderId];

            if ($startDate && $endDate) {
                $query .= " AND created_at BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}
