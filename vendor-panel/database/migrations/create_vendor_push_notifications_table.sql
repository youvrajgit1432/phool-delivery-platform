-- Vendor Panel Push Notifications Database Migration
-- Creates tables for storing vendor push notification subscriptions and delivery logs
-- Last Updated: January 10, 2026

SET FOREIGN_KEY_CHECKS=0;

-- Drop existing tables if they exist (to allow re-running migration)
DROP TABLE IF EXISTS `vendor_push_notification_logs`;
DROP TABLE IF EXISTS `vendor_push_subscriptions`;

SET FOREIGN_KEY_CHECKS=1;

-- Create vendor push subscriptions table
CREATE TABLE IF NOT EXISTS `vendor_push_subscriptions` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `device_id` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Hash of endpoint for uniqueness',
  `endpoint` LONGTEXT NOT NULL COMMENT 'Push endpoint URL',
  `subscription_data` LONGTEXT NOT NULL COMMENT 'Full subscription object as JSON',
  `auth_key` VARCHAR(255) COMMENT 'VAPID authentication key',
  `p256dh_key` VARCHAR(255) COMMENT 'VAPID encryption key',
  `device_type` VARCHAR(50) DEFAULT 'web' COMMENT 'web, ios, android',
  `device_name` VARCHAR(255) COMMENT 'Device name/model',
  `browser_info` VARCHAR(255) COMMENT 'Browser name and version',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether subscription is active',
  `last_used` TIMESTAMP NULL COMMENT 'Last time notification was sent',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_vendor_active` (`vendor_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Store push notification subscriptions for vendors';

-- Create push notification logs table for vendors
CREATE TABLE IF NOT EXISTS `vendor_push_notification_logs` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `subscription_id` INT,
  `notification_title` VARCHAR(255),
  `notification_body` TEXT,
  `status` ENUM('sent', 'delivered', 'failed', 'bounced') DEFAULT 'sent',
  `error_message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` TIMESTAMP NULL,
  
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_vendor_status` (`vendor_id`, `status`),
  KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Log of all push notifications sent to vendors';

-- Add foreign key constraints
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `vendor_push_subscriptions` 
ADD CONSTRAINT `fk_vendor_push_subscriptions` 
FOREIGN KEY (`vendor_id`) 
REFERENCES `vendors` (`id`) 
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE `vendor_push_notification_logs` 
ADD CONSTRAINT `fk_vendor_push_logs_vendor` 
FOREIGN KEY (`vendor_id`) 
REFERENCES `vendors` (`id`) 
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE `vendor_push_notification_logs` 
ADD CONSTRAINT `fk_vendor_push_logs_subscription` 
FOREIGN KEY (`subscription_id`) 
REFERENCES `vendor_push_subscriptions` (`id`) 
ON DELETE SET NULL
ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;

-- Create a view for active subscriptions (optional but useful)
CREATE OR REPLACE VIEW `active_vendor_subscriptions` AS
SELECT 
  vps.id,
  vps.vendor_id,
  vps.device_id,
  vps.endpoint,
  vps.device_type,
  vps.device_name,
  vps.browser_info,
  vps.created_at,
  vps.last_updated,
  CONCAT(v.first_name, ' ', v.last_name) as vendor_name,
  v.store_name,
  v.email
FROM `vendor_push_subscriptions` vps
JOIN `vendors` v ON vps.vendor_id = v.id
WHERE vps.is_active = 1
ORDER BY vps.last_updated DESC;

-- Verification query (run this to confirm tables exist)
-- SELECT COUNT(*) as subscription_count FROM vendor_push_subscriptions;
-- SELECT COUNT(*) as log_count FROM vendor_push_notification_logs;
