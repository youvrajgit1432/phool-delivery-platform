<?php
namespace Phool\DeliveryPanel\Models;

class RiderNotification {
    protected $table = 'notifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rider_id', 'order_id', 'notification_type', 'title', 'message',
        'is_read', 'read_at', 'created_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime'
    ];

    public static function getUnread($riderId) {
        // Get all unread notifications for rider
    }

    public static function markAsRead($notificationId) {
        // Mark single notification as read
    }

    public static function markAllAsRead($riderId) {
        // Mark all notifications as read for rider
    }

    public static function getRecent($riderId, $limit = 20) {
        // Get recent notifications with pagination
    }
}
