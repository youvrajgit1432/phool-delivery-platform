<?php
// delivery-panel/app/services/DeliveryService.php

namespace App\Services;

use PDO;
use Exception;

/**
 * DeliveryService - Handles all delivery workflow operations
 * Replaces stored procedures: sp_mark_picked_up, sp_mark_on_the_way, sp_mark_delivered
 */
class DeliveryService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Mark an order as picked up from vendor
     * Replaces: sp_mark_picked_up
     */
    public function markPickedUp($riderOrderId, $riderId)
    {
        try {
            $this->db->beginTransaction();

            // Get current order details
            $stmt = $this->db->prepare("
                SELECT order_id, delivery_status
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

            $currentStatus = $order['delivery_status'];
            $orderId = $order['order_id'];

            // Validate current status
            if ($currentStatus !== 'accepted') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "Order must be accepted first. Current status: {$currentStatus}"
                ];
            }

            // Update rider_orders
            $updateStmt = $this->db->prepare("
                UPDATE rider_orders
                SET 
                    delivery_status = 'picked_up',
                    picked_up_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND rider_id = ?
            ");
            $updateStmt->execute([$riderOrderId, $riderId]);

            // Log the status change
            $logStmt = $this->db->prepare("
                INSERT INTO delivery_workflow_logs (
                    rider_order_id,
                    order_id,
                    rider_id,
                    old_status,
                    new_status,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $riderOrderId,
                $orderId,
                $riderId,
                $currentStatus,
                'picked_up',
                'Order picked up from vendor'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Order marked as picked up'
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
     * Mark an order as on the way
     * Replaces: sp_mark_on_the_way
     */
    public function markOnTheWay($riderOrderId, $riderId)
    {
        try {
            $this->db->beginTransaction();

            // Get current order details
            $stmt = $this->db->prepare("
                SELECT order_id, delivery_status
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

            $currentStatus = $order['delivery_status'];
            $orderId = $order['order_id'];

            // Validate current status
            if ($currentStatus !== 'picked_up') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "Order must be picked up first. Current status: {$currentStatus}"
                ];
            }

            // Update rider_orders
            $updateStmt = $this->db->prepare("
                UPDATE rider_orders
                SET 
                    delivery_status = 'on_the_way',
                    on_the_way_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND rider_id = ?
            ");
            $updateStmt->execute([$riderOrderId, $riderId]);

            // Log the status change
            $logStmt = $this->db->prepare("
                INSERT INTO delivery_workflow_logs (
                    rider_order_id,
                    order_id,
                    rider_id,
                    old_status,
                    new_status,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $riderOrderId,
                $orderId,
                $riderId,
                $currentStatus,
                'on_the_way',
                'Rider started delivery'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Order marked as on the way'
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
     * Mark an order as delivered
     * Replaces: sp_mark_delivered
     */
    public function markDelivered($riderOrderId, $riderId, $codCollected = 0.00, $signatureUrl = null, $proofImageUrl = null)
    {
        try {
            $this->db->beginTransaction();

            // Get current order details
            $stmt = $this->db->prepare("
                SELECT order_id, cod_amount, delivery_status, payment_status
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
            $currentStatus = $order['delivery_status'];
            $paymentStatus = $order['payment_status'];

            // Validate current status
            if (!in_array($currentStatus, ['arrived', 'on_the_way'])) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "Order is in {$currentStatus} status. Cannot mark as delivered."
                ];
            }

            // Validate COD payment if required
            if ($codAmount > 0 && $paymentStatus !== 'collected') {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Payment must be collected before marking order as delivered'
                ];
            }

            // Determine new payment status
            $newPaymentStatus = ($codAmount > 0) ? 'submitted' : $paymentStatus;

            // Update rider_orders
            $updateStmt = $this->db->prepare("
                UPDATE rider_orders
                SET 
                    delivery_status = 'delivered',
                    delivered_at = NOW(),
                    cod_collected = ?,
                    signature_image_url = ?,
                    proof_of_delivery_image = ?,
                    payment_status = ?,
                    updated_at = NOW()
                WHERE id = ? AND rider_id = ?
            ");
            $updateStmt->execute([
                $codCollected,
                $signatureUrl,
                $proofImageUrl,
                $newPaymentStatus,
                $riderOrderId,
                $riderId
            ]);

            // Log the status change
            $logStmt = $this->db->prepare("
                INSERT INTO delivery_workflow_logs (
                    rider_order_id,
                    order_id,
                    rider_id,
                    old_status,
                    new_status,
                    payment_status,
                    cod_collected_amount,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $riderOrderId,
                $orderId,
                $riderId,
                $currentStatus,
                'delivered',
                $newPaymentStatus,
                $codCollected,
                'Order marked as delivered with proof'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Order successfully marked as delivered'
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
     * Get delivery status summary for a rider
     */
    public function getRiderDeliverySummary($riderId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    delivery_status,
                    COUNT(*) as count
                FROM rider_orders
                WHERE rider_id = ?
                GROUP BY delivery_status
            ");
            $stmt->execute([$riderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
