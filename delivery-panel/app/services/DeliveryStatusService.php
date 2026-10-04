<?php
namespace Phool\DeliveryPanel\Services;

class DeliveryStatusService {
    /**
     * Order status lifecycle:
     * assigned -> accepted -> picked_up -> on_way -> arrived -> delivered
     * 
     * Exceptions:
     * - cancelled (from assigned/accepted)
     * - failed (from on_way/arrived)
     * - returned (from delivered)
     */

    protected $validTransitions = [
        'assigned' => ['accepted', 'rejected'],
        'accepted' => ['picked_up', 'cancelled'],
        'picked_up' => ['on_way', 'failed'],
        'on_way' => ['arrived', 'failed'],
        'arrived' => ['delivered', 'failed'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'failed' => [],
        'returned' => []
    ];

    /**
     * Update order delivery status
     * 
     * @param int $orderId
     * @param string $newStatus
     * @param array $metadata Additional data (location, notes, photos, etc)
     * @return bool
     */
    public function updateStatus($orderId, $newStatus, $metadata = []) {
        // Validate transition
        // Update rider_orders table
        // Log status change to delivery_logs
        // Trigger notifications
        // Update timestamps
        
        return false;
    }

    /**
     * Accept order assignment
     * 
     * @param int $orderId
     * @param int $riderId
     * @return bool
     */
    public function acceptOrder($orderId, $riderId) {
        return $this->updateStatus($orderId, 'accepted', [
            'rider_id' => $riderId,
            'accepted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reject order assignment
     * 
     * @param int $orderId
     * @param string|null $reason
     * @return bool
     */
    public function rejectOrder($orderId, $reason = null) {
        return $this->updateStatus($orderId, 'rejected', [
            'reason' => $reason,
            'rejected_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Mark order as picked up
     * 
     * @param int $orderId
     * @param array $proofData Photo/signature proof
     * @return bool
     */
    public function markPickedUp($orderId, $proofData = []) {
        return $this->updateStatus($orderId, 'picked_up', [
            'picked_up_at' => date('Y-m-d H:i:s'),
            'proof' => $proofData
        ]);
    }

    /**
     * Mark order as out for delivery
     * 
     * @param int $orderId
     * @param float $lat
     * @param float $lng
     * @return bool
     */
    public function markOutForDelivery($orderId, $lat, $lng) {
        return $this->updateStatus($orderId, 'on_way', [
            'departed_at' => date('Y-m-d H:i:s'),
            'current_location' => ['latitude' => $lat, 'longitude' => $lng]
        ]);
    }

    /**
     * Mark order as delivered
     * 
     * @param int $orderId
     * @param array $proofData Photo/signature
     * @param float|null $lat Final location
     * @param float|null $lng Final location
     * @return bool
     */
    public function markDelivered($orderId, $proofData = [], $lat = null, $lng = null) {
        return $this->updateStatus($orderId, 'delivered', [
            'delivered_at' => date('Y-m-d H:i:s'),
            'proof' => $proofData,
            'final_location' => $lat && $lng ? ['latitude' => $lat, 'longitude' => $lng] : null
        ]);
    }

    /**
     * Mark order as failed
     * 
     * @param int $orderId
     * @param string $reason Reason for failure
     * @param array $metadata
     * @return bool
     */
    public function markFailed($orderId, $reason, $metadata = []) {
        return $this->updateStatus($orderId, 'failed', array_merge([
            'failed_at' => date('Y-m-d H:i:s'),
            'failure_reason' => $reason
        ], $metadata));
    }

    /**
     * Get order status
     * 
     * @param int $orderId
     * @return string|null
     */
    public function getStatus($orderId) {
        // Query from rider_orders table
        return null;
    }

    /**
     * Get order timeline
     * 
     * @param int $orderId
     * @return array Status change history with timestamps
     */
    public function getTimeline($orderId) {
        // Query delivery_logs table for order
        return [];
    }

    /**
     * Validate status transition
     * 
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    protected function isValidTransition($currentStatus, $newStatus) {
        if (!isset($this->validTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $this->validTransitions[$currentStatus]);
    }

    /**
     * Log status change
     * 
     * @param int $orderId
     * @param string $oldStatus
     * @param string $newStatus
     * @param array $metadata
     */
    protected function logStatusChange($orderId, $oldStatus, $newStatus, $metadata = []) {
        // Insert into delivery_logs table
    }
}
