-- Delivery Panel PWA Push Notification Subscriptions Table
-- This table stores push subscription data for riders to receive notifications

-- Drop existing tables if they exist (to allow re-running migration)
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `push_notification_logs`;
DROP TABLE IF EXISTS `rider_push_subscriptions`;
SET FOREIGN_KEY_CHECKS=1;

-- Create rider push subscriptions table
CREATE TABLE `rider_push_subscriptions` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rider_id` INT NOT NULL,
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
  
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_rider_active` (`rider_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Store push notification subscriptions for riders';

-- Create push notification logs table
CREATE TABLE `push_notification_logs` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rider_id` INT NOT NULL,
  `subscription_id` INT,
  `notification_title` VARCHAR(255),
  `notification_body` TEXT,
  `status` ENUM('sent', 'delivered', 'failed', 'bounced') DEFAULT 'sent',
  `error_message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` TIMESTAMP NULL,
  
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_rider_status` (`rider_id`, `status`),
  KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Log of all push notifications sent to riders';

-- Add foreign key constraints
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `rider_push_subscriptions` 
ADD CONSTRAINT `fk_rider_push_subscriptions` 
FOREIGN KEY (`rider_id`) 
REFERENCES `riders` (`id`) 
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE `push_notification_logs` 
ADD CONSTRAINT `fk_push_notification_logs_rider` 
FOREIGN KEY (`rider_id`) 
REFERENCES `riders` (`id`) 
ON DELETE CASCADE
ON UPDATE CASCADE;

ALTER TABLE `push_notification_logs` 
ADD CONSTRAINT `fk_push_notification_logs_subscription` 
FOREIGN KEY (`subscription_id`) 
REFERENCES `rider_push_subscriptions` (`id`) 
ON DELETE SET NULL
ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;

-- Create a view for active subscriptions (optional but useful)
CREATE OR REPLACE VIEW `active_rider_subscriptions` AS
SELECT 
  rps.id,
  rps.rider_id,
  rps.device_id,
  rps.endpoint,
  rps.device_type,
  rps.created_at,
  rps.last_updated
FROM `rider_push_subscriptions` rps
WHERE rps.is_active = 1;

-- Optional: Create a view for active subscriptions
CREATE OR REPLACE VIEW `active_rider_subscriptions` AS
SELECT 
  rps.id,
  rps.rider_id,
  rps.device_id,
  rps.endpoint,
  rps.device_type,
  rps.created_at,
  rps.last_updated
FROM `rider_push_subscriptions` rps
WHERE rps.is_active = 1;
