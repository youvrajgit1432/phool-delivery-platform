<?php
/**
 * Create admin_activity_log table
 * Run this script once to create the missing table
 */

require_once '../admin/bootstrap/app.php';

try {
    $db = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS `admin_activity_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `admin_id` int(11) NOT NULL COMMENT 'FK to admin users',
      `action` varchar(100) NOT NULL COMMENT 'create, update, delete, suspend, approve, reject, etc.',
      `entity_type` varchar(100) DEFAULT NULL COMMENT 'riders, vendors, orders, products, etc.',
      `entity_id` int(11) DEFAULT NULL,
      `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
      `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
      `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `admin_id` (`admin_id`),
      KEY `action` (`action`),
      KEY `entity_type` (`entity_type`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin activity audit log'";
    
    $db->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'admin_activity_log table created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
