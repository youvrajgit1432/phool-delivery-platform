-- ============================================================
-- Phool Delivery Platform - database schema (structure only)
-- Target database: phool_delivery_demo
-- Derived from the original operational schema. NO production rows.
-- Import:  mysql -u root -p < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `phool_delivery_demo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `phool_delivery_demo`;

SET FOREIGN_KEY_CHECKS=0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `active_vendor_subscriptions`;
CREATE TABLE `active_vendor_subscriptions` (
  `id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `endpoint` longtext DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `browser_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `vendor_name` varchar(201) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `admin_notification_logs`;
CREATE TABLE `admin_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'sent',
  `sent_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_notifications_customer` (`customer_id`),
  KEY `idx_admin_notifications_type` (`notification_type`),
  KEY `idx_admin_notifications_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `ads`;
CREATE TABLE `ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `media_type` enum('photo','video') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `assignment_audit`;
CREATE TABLE `assignment_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `attempted_vendor_id` int(10) unsigned DEFAULT NULL,
  `attempted_vendor_name` varchar(255) DEFAULT NULL,
  `attempted_admin_id` int(11) DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `attempted_vendor_id` (`attempted_vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `buyer_payments`;
CREATE TABLE `buyer_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date DEFAULT curdate(),
  `payment_mode` enum('cash','bank','esewa','khalti','connectips','other') DEFAULT 'cash',
  `transaction_reference` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `delivery_id` (`delivery_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `buyers`;
CREATE TABLE `buyers` (
  `buyer_id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_name` varchar(255) NOT NULL,
  `contact_number1` varchar(15) NOT NULL,
  `contact_number2` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `buyer_type` enum('small','medium','large') DEFAULT 'small',
  `original_address` text DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `special_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`buyer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `call_customer_orders`;
CREATE TABLE `call_customer_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash on Delivery','Wallets','Bank Transfer') DEFAULT 'Cash on Delivery',
  `status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `rate`) STORED,
  `admin_notes` text DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `call_customer_id` (`call_customer_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `call_customers`;
CREATE TABLE `call_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `call_invoice_pdfs`;
CREATE TABLE `call_invoice_pdfs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `call_order_id` (`call_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `call_invoices`;
CREATE TABLE `call_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_order_id` int(11) NOT NULL,
  `call_customer_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `call_order_id` (`call_order_id`),
  KEY `call_customer_id` (`call_customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `call_order_payments`;
CREATE TABLE `call_order_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_order_id` int(11) NOT NULL,
  `payment_status` enum('Pending','Received','Partial') DEFAULT 'Pending',
  `received_amount` decimal(10,2) DEFAULT 0.00,
  `received_date` datetime DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `call_order_id` (`call_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `carousel_images`;
CREATE TABLE `carousel_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_category_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ne` varchar(255) DEFAULT NULL,
  `description_en` mediumtext DEFAULT NULL,
  `description_ne` mediumtext DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_main_category_id` (`main_category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_active_carousels` (`main_category_id`,`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores carousel banner images for product main categories';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`customer_id`,`product_id`),
  KEY `customer_id` (`customer_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5059 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`customer_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_en` varchar(255) DEFAULT NULL,
  `name_ne` varchar(255) DEFAULT NULL,
  `description_en` longtext DEFAULT NULL,
  `description_ne` longtext DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_id` int(11) DEFAULT NULL,
  `type` enum('main','subcategory') DEFAULT 'main',
  `is_product_allowed` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_category_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `crm_followups`;
CREATE TABLE `crm_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) DEFAULT NULL,
  `followup_date` datetime DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `crm_leads`;
CREATE TABLE `crm_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source` varchar(50) DEFAULT 'website',
  `status` varchar(20) DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `current_nepali_date`;
CREATE TABLE `current_nepali_date` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `english_date` date NOT NULL COMMENT 'Current English/Gregorian date',
  `year` int(11) NOT NULL COMMENT 'Nepali year (Bikram Sambat)',
  `month` int(11) NOT NULL COMMENT 'Nepali month (1-12)',
  `day` int(11) NOT NULL COMMENT 'Nepali day (1-31/32)',
  `weekday` int(11) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `month_name` varchar(50) NOT NULL COMMENT 'Nepali month name (Baishakh, Jestha, etc)',
  `weekday_name` varchar(20) NOT NULL COMMENT 'Weekday name (Sunday, Monday, etc)',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'When this record was last updated',
  PRIMARY KEY (`id`),
  KEY `english_date` (`english_date`),
  KEY `year_month` (`year`,`month`,`day`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Current Nepali date - Updated daily at midnight';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/  /*!50003 TRIGGER `update_current_nepali_date_timestamp` BEFORE UPDATE ON `current_nepali_date` FOR EACH ROW BEGIN
    SET NEW.last_updated = NOW();
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `address_type` enum('home','work','other') DEFAULT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `street` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_interests`;
CREATE TABLE `customer_interests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `product_type` varchar(100) NOT NULL,
  `delivery_address` mediumtext NOT NULL,
  `quantity_needed` varchar(50) DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `special_requirements` mediumtext DEFAULT NULL,
  `status` enum('pending','processed','notified') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_invoice_pdfs`;
CREATE TABLE `customer_invoice_pdfs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_invoices`;
CREATE TABLE `customer_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `sent_via_email` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  `email_status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `order_id` (`order_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_login_otps`;
CREATE TABLE `customer_login_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_otp_code` (`otp_code`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_used` (`used`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_otp_logs`;
CREATE TABLE `customer_otp_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_used_at` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customer_verification_logs`;
CREATE TABLE `customer_verification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `registration_type` enum('direct','guest','admin_created') DEFAULT 'direct',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `customer_type` enum('normal','bulk','event_planner','wholesaler','regular') DEFAULT 'normal',
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `loyalty_points` int(11) DEFAULT 0,
  `last_order_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verification_method` varchar(50) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_notes` mediumtext DEFAULT NULL,
  `id_proof` varchar(255) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_code_expires` datetime DEFAULT NULL,
  `id_proof_type` enum('citizenship','license','passport','other') DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `sms_verification_code` varchar(10) DEFAULT NULL,
  `sms_verification_expires` datetime DEFAULT NULL,
  `email_verification_code` varchar(10) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `remember_token_expires` datetime DEFAULT NULL,
  `contact` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `database_migrations`;
CREATE TABLE `database_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track executed migrations';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `delivery_cities`;
CREATE TABLE `delivery_cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_name` varchar(100) NOT NULL,
  `standard_delivery_fee` decimal(10,2) DEFAULT 0.00,
  `nighttime_urgent_fee` decimal(10,2) DEFAULT 0.00,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `city_name` (`city_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `delivery_logs`;
CREATE TABLE `delivery_logs` (
  `id` int(11) NOT NULL,
  `rider_order_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status_before` varchar(100) DEFAULT NULL,
  `status_after` varchar(100) NOT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `distance_from_delivery_point_m` decimal(10,2) DEFAULT NULL COMMENT 'Distance in meters',
  `photo_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT 'system' COMMENT 'rider, customer, system, admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_order_id` (`rider_order_id`),
  KEY `rider_id` (`rider_id`),
  KEY `order_id` (`order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail of all delivery status changes';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `delivery_workflow_logs`;
CREATE TABLE `delivery_workflow_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_order_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `cod_collected_amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin ID who verified',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rider_order` (`rider_order_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_status_change` (`old_status`,`new_status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all status changes in delivery workflow';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `delivery_zones`;
CREATE TABLE `delivery_zones` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `area_description` text DEFAULT NULL,
  `base_delivery_fee` decimal(10,2) NOT NULL,
  `distance_charge_per_km` decimal(10,2) NOT NULL COMMENT 'Extra charge per KM',
  `min_distance_km` decimal(10,2) DEFAULT 1.00,
  `max_distance_km` decimal(10,2) DEFAULT 50.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `zone_city` (`zone_name`,`city`),
  KEY `city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery zones for pricing and assignment';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `retry_count` int(11) DEFAULT 0,
  `last_retry_at` datetime DEFAULT NULL,
  `delivery_attempts` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_recipient_email` (`recipient_email`),
  KEY `idx_message_type` (`message_type`),
  KEY `idx_related_id` (`related_id`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `email_otp_attempts`;
CREATE TABLE `email_otp_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_type` enum('send','verify') NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_locked_until` (`locked_until`),
  KEY `idx_attempt_type` (`attempt_type`),
  KEY `idx_user_email_type` (`user_id`,`email`,`attempt_type`),
  KEY `idx_ip_type` (`ip_address`,`attempt_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `email_verification_otps`;
CREATE TABLE `email_verification_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `locked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  KEY `idx_user_attempts` (`user_id`),
  KEY `idx_expires_verified` (`expires_at`,`verified`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_locked_until` (`locked_until`),
  KEY `idx_user_expires` (`user_id`,`expires_at`),
  KEY `idx_otp_user` (`otp`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_category` enum('phool_delivery','farm_expenses') NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `expense_reports`;
CREATE TABLE `expense_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_data`)),
  `generated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `generated_by` (`generated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `bill_receipt` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','digital_wallet','credit_card','other') DEFAULT 'cash',
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `flower_deliveries`;
CREATE TABLE `flower_deliveries` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `delivery_date` date DEFAULT curdate(),
  `flower_type` varchar(100) NOT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `rate_per_kg` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_received` decimal(10,2) DEFAULT 0.00,
  `payment_pending` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('paid','partial','pending') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`delivery_id`),
  KEY `buyer_id` (`buyer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/  /*!50003 TRIGGER `update_payment_status_insert` BEFORE INSERT ON `flower_deliveries` FOR EACH ROW BEGIN
        SET NEW.payment_pending = NEW.total_amount - NEW.payment_received;

        IF NEW.payment_received = 0 THEN
            SET NEW.payment_status = 'pending';
        ELSEIF NEW.payment_received = NEW.total_amount THEN
            SET NEW.payment_status = 'paid';
        ELSE
            SET NEW.payment_status = 'partial';
        END IF;
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/  /*!50003 TRIGGER `update_payment_status` BEFORE UPDATE ON `flower_deliveries` FOR EACH ROW BEGIN
        SET NEW.payment_pending = NEW.total_amount - NEW.payment_received;
        
        IF NEW.payment_received = 0 THEN
            SET NEW.payment_status = 'pending';
        ELSEIF NEW.payment_received = NEW.total_amount THEN
            SET NEW.payment_status = 'paid';
        ELSE
            SET NEW.payment_status = 'partial';
        END IF;
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/  /*!50003 TRIGGER `update_payment_status_update` BEFORE UPDATE ON `flower_deliveries` FOR EACH ROW BEGIN
        SET NEW.payment_pending = NEW.total_amount - NEW.payment_received;

        IF NEW.payment_received = 0 THEN
            SET NEW.payment_status = 'pending';
        ELSEIF NEW.payment_received = NEW.total_amount THEN
            SET NEW.payment_status = 'paid';
        ELSE
            SET NEW.payment_status = 'partial';
        END IF;
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `invoice_pdfs`;
CREATE TABLE `invoice_pdfs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `delivery_id` (`delivery_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `sent_via_email` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  `email_status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 1,
  `failure_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `login_otp_verification`;
CREATE TABLE `login_otp_verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_verified` (`verified`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `media_categories`;
CREATE TABLE `media_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_en` varchar(100) NOT NULL,
  `name_ne` varchar(100) NOT NULL,
  `description_en` text DEFAULT NULL,
  `description_ne` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `media_items`;
CREATE TABLE `media_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ne` varchar(255) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ne` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `custom_thumbnail` tinyint(1) DEFAULT 0,
  `media_type` enum('image','video') NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `duration` int(11) DEFAULT 0 COMMENT 'In seconds, for videos',
  `category_id` int(11) DEFAULT NULL,
  `views_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('system','order','promotion') DEFAULT 'system',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_messages_customer` (`customer_id`),
  KEY `idx_messages_read` (`is_read`),
  KEY `idx_messages_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `nepali_calendar_months`;
CREATE TABLE `nepali_calendar_months` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `month_number` int(11) NOT NULL,
  `month_name` varchar(20) NOT NULL,
  `total_days` int(11) NOT NULL,
  `first_weekday` int(11) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year_month` (`year`,`month_number`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `nepali_calendar_years`;
CREATE TABLE `nepali_calendar_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `baishakh_first_day` int(11) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `year` (`year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `button_type` varchar(50) DEFAULT NULL,
  `media_type` enum('photo','video') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` mediumtext NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `offers`;
CREATE TABLE `offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed','bulk') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) DEFAULT 0.00,
  `customer_type` enum('all','normal','bulk','event_planner','wholesaler') DEFAULT 'all',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `applied_offer_id` int(11) DEFAULT NULL,
  `free_quantity` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=521 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `order_number` varchar(20) NOT NULL,
  `quantity` decimal(10,0) NOT NULL,
  `rate` decimal(10,0) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `applied_discount` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount amount applied to this order',
  `final_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Final amount after discount and delivery fee',
  `status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `payment_method` enum('cod','esewa','khalti','bank_transfer') DEFAULT 'cod',
  `payment_status` enum('pending','paid_confirmed','paid_pending_verification','paid','failed','refunded') DEFAULT 'pending',
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `order_type` enum('website','whatsapp') NOT NULL DEFAULT 'website',
  `delivery_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `delivery_address` text DEFAULT NULL COMMENT 'Order-specific delivery address (may differ from customer address)',
  `delivery_phone` varchar(20) DEFAULT NULL COMMENT 'Phone number for delivery (may differ from customer phone)',
  `secondary_phone` varchar(20) DEFAULT NULL COMMENT 'Secondary contact phone number for this order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `requires_verification` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `handled_by` int(11) DEFAULT NULL,
  `assigned_vendor_id` int(11) DEFAULT NULL COMMENT 'Vendor ID assigned to this order',
  `assigned_rider_id` int(11) DEFAULT NULL COMMENT 'Rider ID assigned to this order',
  `assigned_at` datetime DEFAULT NULL COMMENT 'Timestamp when order was assigned to vendor',
  `rider_assigned_at` datetime DEFAULT NULL COMMENT 'When order was assigned to rider',
  `assign_status` enum('unassigned','assigned','accepted','rejected') NOT NULL DEFAULT 'unassigned' COMMENT 'Status of vendor assignment (unassigned/assigned/accepted/rejected)',
  `rider_assignment_status` enum('unassigned','assigned','accepted','rejected','picked_up','in_delivery','delivered','failed','partial_delivered') DEFAULT 'unassigned' COMMENT 'Rider assignment workflow status',
  `confirmation_email_status` enum('sent','failed','pending') DEFAULT 'pending',
  `confirmation_email_sent_at` datetime DEFAULT NULL,
  `confirmation_email_error` mediumtext DEFAULT NULL,
  `created_ip` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `customer_id` (`customer_id`),
  KEY `handled_by` (`handled_by`),
  KEY `idx_assigned_vendor_id` (`assigned_vendor_id`),
  KEY `idx_assign_status` (`assign_status`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_assigned_rider_id` (`assigned_rider_id`),
  KEY `idx_rider_assignment_status` (`rider_assignment_status`),
  KEY `idx_delivery_phone` (`delivery_phone`),
  KEY `idx_secondary_phone` (`secondary_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `otp_codes`;
CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `code` varchar(10) NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_ip` int(10) unsigned DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_code` (`code`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `payment_verification_logs`;
CREATE TABLE `payment_verification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_order_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `cod_amount` decimal(10,2) NOT NULL,
  `amount_collected` decimal(10,2) NOT NULL,
  `amount_variance` decimal(10,2) DEFAULT NULL COMMENT 'cod_amount - amount_collected',
  `variance_reason` varchar(255) DEFAULT NULL,
  `payment_status_before` varchar(50) DEFAULT NULL,
  `payment_status_after` varchar(50) NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin ID',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rider_order` (`rider_order_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_verified_at` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks payment collection and verification';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `persistent_sessions`;
CREATE TABLE `persistent_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `session_data` longtext NOT NULL,
  `preference_flags` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_accessed` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_last_accessed` (`last_accessed`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `phone_blocks`;
CREATE TABLE `phone_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `block_expires` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `phone` (`phone`),
  KEY `ip_address` (`ip_address`),
  KEY `block_expires` (`block_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `phone_verifications`;
CREATE TABLE `phone_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `verification_code` varchar(20) NOT NULL,
  `status` enum('pending','verified','expired') DEFAULT 'pending',
  `attempts` tinyint(4) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone_status` (`phone`,`status`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_category` (`product_id`,`category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_category_map`;
CREATE TABLE `product_category_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_category` (`product_id`,`category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_city_availability`;
CREATE TABLE `product_city_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1 COMMENT '1=available, 0=not available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_city` (`product_id`,`city_id`),
  KEY `fk_product_city_product` (`product_id`),
  KEY `fk_product_city_city` (`city_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_minimum_quantities`;
CREATE TABLE `product_minimum_quantities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `minimum_quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit` enum('piece','bunch','kg','garland') DEFAULT 'piece',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_city` (`product_id`,`city_id`),
  KEY `product_id` (`product_id`),
  KEY `city_id` (`city_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `product_offers`;
CREATE TABLE `product_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `city_id` int(11) DEFAULT NULL,
  `offer_type` enum('buy_x_get_y','percentage_discount','fixed_discount') DEFAULT 'buy_x_get_y',
  `buy_quantity` decimal(10,2) DEFAULT NULL,
  `get_quantity` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `fixed_discount` decimal(10,2) DEFAULT 0.00,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `city_id` (`city_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_en` varchar(255) DEFAULT NULL,
  `name_ne` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `slug_en` varchar(255) DEFAULT NULL,
  `slug_ne` varchar(255) DEFAULT NULL,
  `description_en` mediumtext DEFAULT NULL,
  `description_ne` mediumtext DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `bulk_price` decimal(10,2) DEFAULT NULL,
  `event_price` decimal(10,2) DEFAULT NULL,
  `unit` enum('piece','bunch','kg','garland') DEFAULT 'piece',
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_alert` int(11) DEFAULT 10,
  `expiry_date` date DEFAULT NULL,
  `images` mediumtext DEFAULT NULL,
  `gallery_images` mediumtext DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_products_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `push_notification_devices`;
CREATE TABLE `push_notification_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `device_token` varchar(255) NOT NULL,
  `device_type` enum('web','android','ios') DEFAULT 'web',
  `browser_name` varchar(100) DEFAULT NULL,
  `browser_version` varchar(50) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `subscription_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`subscription_data`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `last_registered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_token` (`device_token`),
  KEY `user_id` (`user_id`),
  KEY `is_active` (`is_active`),
  KEY `idx_push_devices_user_active` (`user_id`,`is_active`),
  KEY `idx_push_devices_token` (`device_token`)
) ENGINE=InnoDB AUTO_INCREMENT=245 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `push_notification_logs`;
CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `notification_title` varchar(255) DEFAULT NULL,
  `notification_body` text DEFAULT NULL,
  `status` enum('sent','delivered','failed','bounced') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_rider_status` (`rider_id`,`status`),
  KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all push notifications sent to riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `push_notifications_log`;
CREATE TABLE `push_notifications_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `status` enum('sent','delivered','failed','read') DEFAULT 'sent',
  `failure_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `notification_id` (`notification_id`),
  KEY `device_id` (`device_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `idx_push_log_notification_device` (`notification_id`,`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `push_notifications_queue`;
CREATE TABLE `push_notifications_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('order_update','offer','event','system','promotion') DEFAULT 'system',
  `target_type` enum('all_users','specific_users','user_group') DEFAULT 'all_users',
  `target_users` text DEFAULT NULL COMMENT 'JSON array of user IDs or user groups',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `failure_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `notification_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(255) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_activity_log`;
CREATE TABLE `rider_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL COMMENT 'login, logout, accept_order, deliver_order, etc.',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL COMMENT 'For device tracking',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log of rider actions';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_availability`;
CREATE TABLE `rider_availability` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `is_available` tinyint(1) DEFAULT 0 COMMENT '1=online, 0=offline',
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  `current_city` varchar(100) DEFAULT NULL,
  `current_location_name` varchar(255) DEFAULT NULL,
  `availability_status` enum('idle','assigned','on_delivery','on_break','off_duty') DEFAULT 'off_duty',
  `went_online_at` timestamp NULL DEFAULT NULL,
  `went_offline_at` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_online_minutes_today` int(11) DEFAULT 0,
  `break_reason` varchar(255) DEFAULT NULL,
  `break_started_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_id` (`rider_id`),
  KEY `is_available` (`is_available`),
  KEY `current_city` (`current_city`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Real-time rider availability and location tracking';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_bank_details`;
CREATE TABLE `rider_bank_details` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','active','inactive','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payout bank account details for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_bonuses`;
CREATE TABLE `rider_bonuses` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `bonus_type` varchar(100) NOT NULL COMMENT 'on_time_delivery, high_rating, referral, performance, etc.',
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bonus_date` date NOT NULL,
  `for_period` varchar(50) DEFAULT NULL COMMENT 'daily, weekly, monthly',
  `status` enum('pending','approved','paid') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin who issued bonus',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `bonus_date` (`bonus_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Incentives and bonuses for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_documents`;
CREATE TABLE `rider_documents` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL COMMENT 'license, aadhar, insurance, registration, etc.',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_admin` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','expired') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `document_type` (`document_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores rider document verification records';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_earnings`;
CREATE TABLE `rider_earnings` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `rider_order_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `order_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Total order value',
  `base_delivery_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'Base fee for delivery',
  `distance_surge` decimal(10,2) DEFAULT 0.00 COMMENT 'Extra charge based on distance',
  `time_surge` decimal(10,2) DEFAULT 0.00 COMMENT 'Extra charge during peak hours',
  `on_time_bonus` decimal(10,2) DEFAULT 0.00 COMMENT 'Bonus for delivering on time',
  `total_earnings` decimal(10,2) DEFAULT 0.00 COMMENT 'Base + surges + bonuses',
  `cod_commission` decimal(10,2) DEFAULT 0.00 COMMENT 'Commission if handled COD',
  `penalties_deducted` decimal(10,2) DEFAULT 0.00 COMMENT 'For delays, damages, etc.',
  `net_earnings` decimal(10,2) DEFAULT 0.00 COMMENT 'Total earnings - penalties',
  `payment_status` enum('pending','due','paid','failed') DEFAULT 'pending',
  `payout_date` date DEFAULT NULL,
  `payout_mode` varchar(50) DEFAULT NULL COMMENT 'bank_transfer, upi, cash, etc.',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_order_id` (`rider_order_id`),
  KEY `rider_id` (`rider_id`),
  KEY `payment_status` (`payment_status`),
  KEY `created_at` (`created_at`),
  KEY `rider_date_range` (`rider_id`,`created_at`),
  KEY `payment_status_date` (`payment_status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks earnings per delivery';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_notifications`;
CREATE TABLE `rider_notifications` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `notification_type` varchar(100) DEFAULT NULL COMMENT 'order_assigned, order_accepted, payment_received, etc.',
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `order_id` int(11) DEFAULT NULL,
  `rider_order_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `push_sent` tinyint(1) DEFAULT 0,
  `sms_sent` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  KEY `created_read` (`created_at`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_orders`;
CREATE TABLE `rider_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `pickup_address` text DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `pickup_city` varchar(100) DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `distance_km` decimal(10,2) DEFAULT NULL COMMENT 'Calculated distance in KM',
  `estimated_delivery_time` int(11) DEFAULT NULL COMMENT 'Time in minutes',
  `delivery_status` enum('assigned','accepted','picked_up','on_the_way','arrived','delivered','failed','cancelled','returned') DEFAULT 'assigned' COMMENT 'Delivery status progression',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'cod, online, wallet, etc.',
  `payment_status` enum('pending','collected','submitted','verified','failed') DEFAULT 'pending' COMMENT 'For COD tracking',
  `cod_amount` decimal(10,2) DEFAULT 0.00,
  `cod_collected` decimal(10,2) DEFAULT 0.00 COMMENT 'Amount rider actually collected',
  `special_instructions` text DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `on_the_way_at` timestamp NULL DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failed_reason` varchar(255) DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL COMMENT 'For delivery verification',
  `otp_verified_at` timestamp NULL DEFAULT NULL,
  `signature_image_url` varchar(500) DEFAULT NULL,
  `proof_of_delivery_image` varchar(500) DEFAULT NULL,
  `feedback_rating` int(11) DEFAULT NULL COMMENT '1-5 star rating',
  `feedback_comment` text DEFAULT NULL,
  `feedback_submitted_at` timestamp NULL DEFAULT NULL,
  `rider_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `order_id` (`order_id`),
  KEY `delivery_status` (`delivery_status`),
  KEY `assigned_at` (`assigned_at`),
  KEY `delivered_at` (`delivered_at`),
  KEY `idx_delivery_payment_status` (`rider_id`,`delivery_status`,`payment_status`),
  KEY `idx_pickup_delivery` (`picked_up_at`,`delivered_at`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks orders assigned to riders with complete delivery workflow: assignedâ†’acceptedâ†’picked_upâ†’on_the_wayâ†’arrivedâ†’delivered. Includes COD payment tracking: pendingâ†’collectedâ†’submittedâ†’verified';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_payouts`;
CREATE TABLE `rider_payouts` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `payout_period_from` date NOT NULL,
  `payout_period_to` date NOT NULL,
  `total_deliveries` int(11) DEFAULT 0,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `penalties` decimal(12,2) DEFAULT 0.00,
  `advances_provided` decimal(12,2) DEFAULT 0.00 COMMENT 'If rider took cash advance',
  `net_payout_amount` decimal(12,2) DEFAULT 0.00,
  `payout_status` enum('pending','processed','paid','failed','rejected') DEFAULT 'pending',
  `payout_date` date DEFAULT NULL,
  `payout_mode` varchar(50) DEFAULT 'bank_transfer' COMMENT 'bank_transfer, upi, cash, check',
  `transaction_reference` varchar(255) DEFAULT NULL COMMENT 'Bank transaction ID',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `payout_status` (`payout_status`),
  KEY `payout_date` (`payout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payout records for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_penalties`;
CREATE TABLE `rider_penalties` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `rider_order_id` int(11) DEFAULT NULL,
  `penalty_type` varchar(100) NOT NULL COMMENT 'late_delivery, damaged_item, customer_complaint, absence, etc.',
  `reason` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected','appealed','resolved') DEFAULT 'pending',
  `appealed_at` timestamp NULL DEFAULT NULL,
  `appeal_reason` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin who issued penalty',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_order_id` (`rider_order_id`),
  KEY `rider_id` (`rider_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Records of penalties issued to riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_performance`;
CREATE TABLE `rider_performance` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `deliveries_completed` int(11) DEFAULT 0,
  `deliveries_attempted` int(11) DEFAULT 0,
  `deliveries_failed` int(11) DEFAULT 0,
  `deliveries_cancelled` int(11) DEFAULT 0,
  `on_time_deliveries` int(11) DEFAULT 0,
  `late_deliveries` int(11) DEFAULT 0,
  `total_distance_km` decimal(10,2) DEFAULT 0.00,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `penalties` decimal(12,2) DEFAULT 0.00,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `on_time_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage',
  `success_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage',
  `average_delivery_time` int(11) DEFAULT NULL COMMENT 'Time in minutes',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_date` (`rider_id`,`date`),
  KEY `date` (`date`),
  KEY `rider_month` (`rider_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily performance metrics for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_push_subscriptions`;
CREATE TABLE `rider_push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL COMMENT 'Hash of endpoint for uniqueness',
  `endpoint` longtext NOT NULL COMMENT 'Push endpoint URL',
  `subscription_data` longtext NOT NULL COMMENT 'Full subscription object as JSON',
  `auth_key` varchar(255) DEFAULT NULL COMMENT 'VAPID authentication key',
  `p256dh_key` varchar(255) DEFAULT NULL COMMENT 'VAPID encryption key',
  `device_type` varchar(50) DEFAULT 'web' COMMENT 'web, ios, android',
  `device_name` varchar(255) DEFAULT NULL COMMENT 'Device name/model',
  `browser_info` varchar(255) DEFAULT NULL COMMENT 'Browser name and version',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether subscription is active',
  `last_used` timestamp NULL DEFAULT NULL COMMENT 'Last time notification was sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  KEY `idx_rider_id` (`rider_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_rider_active` (`rider_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store push notification subscriptions for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_reviews`;
CREATE TABLE `rider_reviews` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `rider_order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL COMMENT '1-5 stars',
  `comment` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) DEFAULT 1,
  `review_date` date DEFAULT NULL,
  `helpful_count` int(11) DEFAULT 0 COMMENT 'Number of people who found helpful',
  `status` enum('pending','approved','rejected','hidden') DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_order_id` (`rider_order_id`),
  KEY `rider_id` (`rider_id`),
  KEY `rating` (`rating`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer reviews for riders';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_support_messages`;
CREATE TABLE `rider_support_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL COMMENT 'Foreign key to rider_support_tickets',
  `sender_type` enum('rider','admin') NOT NULL DEFAULT 'rider' COMMENT 'Who sent the message',
  `sender_id` int(11) NOT NULL COMMENT 'rider_id if rider, admin_id if admin',
  `sender_name` varchar(255) DEFAULT NULL COMMENT 'Name of sender for display',
  `message` longtext NOT NULL COMMENT 'Message content',
  `is_internal` tinyint(1) DEFAULT 0 COMMENT '1=internal admin note, 0=visible to rider',
  `attachments` varchar(500) DEFAULT NULL COMMENT 'JSON array of attachment URLs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `sender_type` (`sender_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual messages in ticket conversations';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_support_tickets`;
CREATE TABLE `rider_support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rider_id` int(11) NOT NULL,
  `ticket_number` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed','reopened') DEFAULT 'open',
  `assigned_to_admin` int(11) DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `created_status` (`created_at`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `rider_zone_assignments`;
CREATE TABLE `rider_zone_assignments` (
  `id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0 COMMENT 'Primary zone for this rider',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unassigned_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_zone` (`rider_id`,`zone_id`),
  KEY `zone_id` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assigns riders to delivery zones';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `riders`;
CREATE TABLE `riders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `rider_type` enum('in_house','gig','partner') DEFAULT 'in_house' COMMENT 'in_house=salaried, gig=freelance, partner=delivery company',
  `partner_name` varchar(255) DEFAULT NULL COMMENT 'For partner riders, the delivery company name',
  `partner_id` int(11) DEFAULT NULL COMMENT 'FK to delivery partners table (future)',
  `identification_type` varchar(50) DEFAULT NULL COMMENT 'e.g., License, Aadhar, Passport',
  `identification_number` varchar(100) DEFAULT NULL,
  `identification_image_url` varchar(500) DEFAULT NULL,
  `vehicle_type` enum('motorcycle','bicycle','scooter','car','van') DEFAULT 'motorcycle',
  `vehicle_number` varchar(50) DEFAULT NULL,
  `vehicle_registration_image` varchar(500) DEFAULT NULL,
  `vehicle_insurance_expiry` date DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `license_image_url` varchar(500) DEFAULT NULL,
  `home_address` text DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nepal',
  `date_of_birth` date DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `base_salary` decimal(10,2) DEFAULT NULL COMMENT 'For in-house riders only',
  `status` enum('pending','active','suspended','inactive','deleted') DEFAULT 'pending' COMMENT 'pending=awaiting verification, active=approved, suspended=temp disabled',
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `documents_verified` tinyint(1) DEFAULT 0,
  `documents_verified_at` timestamp NULL DEFAULT NULL,
  `bank_verified` tinyint(1) DEFAULT 0,
  `bank_verified_at` timestamp NULL DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 0 COMMENT '1=online, 0=offline',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_location_lat` decimal(10,8) DEFAULT NULL,
  `last_location_lng` decimal(11,8) DEFAULT NULL,
  `last_location_update` timestamp NULL DEFAULT NULL,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_deliveries` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `total_penalties` decimal(12,2) DEFAULT 0.00,
  `cancellation_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage of cancelled deliveries',
  `on_time_delivery_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage of on-time deliveries',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  KEY `rider_type_status` (`rider_type`,`status`),
  KEY `city` (`city`),
  KEY `is_available` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores all delivery rider profiles';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `security_events`;
CREATE TABLE `security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone_action` (`phone`,`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_phone` varchar(20) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` enum('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `retry_count` int(11) DEFAULT 0,
  `last_retry_at` datetime DEFAULT NULL,
  `delivery_attempts` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_recipient_phone` (`recipient_phone`),
  KEY `idx_message_type` (`message_type`),
  KEY `idx_related_id` (`related_id`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `special_events`;
CREATE TABLE `special_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `setting_group` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cod','esewa','khalti','bank_transfer') NOT NULL,
  `status` enum('success','pending','failed','refunded') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address_type` enum('home','work','other') DEFAULT 'home',
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` mediumtext NOT NULL,
  `city` varchar(100) NOT NULL,
  `street` varchar(100) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_city_preferences`;
CREATE TABLE `user_city_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `city_id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `city_id` (`city_id`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_notification_preferences`;
CREATE TABLE `user_notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `push_notifications_enabled` tinyint(1) DEFAULT 1,
  `main_notification_enabled` tinyint(1) DEFAULT 1,
  `offers_notifications` tinyint(1) DEFAULT 1,
  `delivery_tracking_notifications` tinyint(1) DEFAULT 1,
  `system_notifications` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=476 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_payment_methods`;
CREATE TABLE `user_payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `payment_type` enum('card','esewa','khalti','bank') NOT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `last_four` varchar(4) DEFAULT NULL,
  `expiry_month` varchar(2) DEFAULT NULL,
  `expiry_year` varchar(4) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_preferences`;
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 1,
  `newsletter_subscription` tinyint(1) DEFAULT 1,
  `language` enum('en','ne') DEFAULT 'en',
  `theme` enum('light','dark','auto') DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_security_settings`;
CREATE TABLE `user_security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `user_social_accounts`;
CREATE TABLE `user_social_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `provider_id` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_provider_id` (`provider`,`provider_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('super_admin','admin','manager','staff') DEFAULT 'staff',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notify_new_orders` tinyint(1) DEFAULT 1,
  `notify_low_stock` tinyint(1) DEFAULT 1,
  `notify_payments` tinyint(1) DEFAULT 1,
  `language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'Asia/Kathmandu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `v_pending_payments`;
CREATE TABLE `v_pending_payments` (
  `id` int(11) DEFAULT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `rider_name` varchar(201) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `delivery_status` enum('assigned','accepted','picked_up','on_the_way','arrived','delivered','failed','cancelled','returned') DEFAULT NULL,
  `payment_status` enum('pending','collected','submitted','verified','failed') DEFAULT NULL,
  `cod_amount` decimal(10,2) DEFAULT NULL,
  `cod_collected` decimal(10,2) DEFAULT NULL,
  `pending_amount` decimal(11,2) DEFAULT NULL,
  `accepted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `picked_up_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_activity_log`;
CREATE TABLE `vendor_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_availability`;
CREATE TABLE `vendor_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `available_from` time NOT NULL,
  `available_to` time NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_commission_rules`;
CREATE TABLE `vendor_commission_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `commission_percentage` decimal(5,2) DEFAULT NULL,
  `min_order_value` decimal(10,2) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_documents`;
CREATE TABLE `vendor_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `document_url` varchar(500) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_gallery_images`;
CREATE TABLE `vendor_gallery_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `image_type` enum('gallery','storefront','team','facility','other') DEFAULT 'gallery',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_image_type` (`image_type`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_vendor_gallery_upload_date` (`vendor_id`,`upload_date`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_notifications`;
CREATE TABLE `vendor_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `notification_type` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `performed_by_admin` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_performed_by_admin` (`performed_by_admin`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_order_items`;
CREATE TABLE `vendor_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `item_total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_order_id` (`vendor_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_orders`;
CREATE TABLE `vendor_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `total_items` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `vendor_commission` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `status` enum('assigned','accepted','preparing','ready','dispatched','picked_up','delivered','cancelled','returned') DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_at` (`assigned_at`),
  KEY `idx_vendor_orders_vendor_status` (`vendor_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_payouts`;
CREATE TABLE `vendor_payouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `payout_period_start` date NOT NULL,
  `payout_period_end` date NOT NULL,
  `total_orders` int(11) DEFAULT NULL,
  `total_revenue` decimal(15,2) DEFAULT NULL,
  `total_commission` decimal(15,2) DEFAULT NULL,
  `total_fees` decimal(15,2) DEFAULT NULL,
  `payout_amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_vendor_payouts_vendor_status` (`vendor_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_performance_metrics`;
CREATE TABLE `vendor_performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `orders_this_month` int(11) DEFAULT 0,
  `orders_delivered_on_time` int(11) DEFAULT 0,
  `orders_cancelled` int(11) DEFAULT 0,
  `orders_returned` int(11) DEFAULT 0,
  `on_time_delivery_rate` decimal(5,2) DEFAULT NULL,
  `customer_satisfaction_score` decimal(3,2) DEFAULT NULL,
  `chat_response_time_minutes` int(11) DEFAULT NULL,
  `average_preparation_time_minutes` int(11) DEFAULT NULL,
  `product_quality_score` decimal(3,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_product_map`;
CREATE TABLE `vendor_product_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'FK to master products table',
  `vendor_price` decimal(10,2) NOT NULL COMMENT 'Vendor base price (without platform margin)',
  `vendor_stock` int(11) DEFAULT 0 COMMENT 'Vendor stock level',
  `is_available` tinyint(1) DEFAULT 1 COMMENT '1=available, 0=not available',
  `preparation_time` int(11) DEFAULT 30 COMMENT 'Time in minutes to prepare',
  `packaging_type` varchar(100) DEFAULT NULL COMMENT 'e.g., box, bag, bouquet',
  `status` enum('active','inactive','discontinued') DEFAULT 'active',
  `minimum_order_quantity` decimal(10,2) DEFAULT 1.00 COMMENT 'Minimum quantity vendor will supply',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `unlinked_at` timestamp NULL DEFAULT NULL,
  `unlinked_by` int(11) DEFAULT NULL,
  `trash_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vendor_product` (`vendor_id`,`product_id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_vendor_product` (`vendor_id`,`product_id`),
  KEY `idx_availability` (`is_available`,`status`),
  KEY `idx_vendor_product_map_deleted_at` (`deleted_at`),
  KEY `idx_vendor_product_map_vendor_deleted` (`vendor_id`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_product_pricing_history`;
CREATE TABLE `vendor_product_pricing_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_product_map_id` int(11) NOT NULL,
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_product_map_id` (`vendor_product_map_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_products`;
CREATE TABLE `vendor_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `quantity_in_stock` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `unit_type` varchar(50) DEFAULT NULL,
  `primary_image_url` varchar(500) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','discontinued') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_sku` (`product_sku`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_vendor_products_vendor_status` (`vendor_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_push_notification_logs`;
CREATE TABLE `vendor_push_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `notification_title` varchar(255) DEFAULT NULL,
  `notification_body` text DEFAULT NULL,
  `status` enum('sent','delivered','failed','bounced') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_vendor_status` (`vendor_id`,`status`),
  KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all push notifications sent to vendors';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_push_subscriptions`;
CREATE TABLE `vendor_push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL COMMENT 'Hash of endpoint for uniqueness',
  `endpoint` longtext NOT NULL COMMENT 'Push endpoint URL',
  `subscription_data` longtext NOT NULL COMMENT 'Full subscription object as JSON',
  `auth_key` varchar(255) DEFAULT NULL COMMENT 'VAPID authentication key',
  `p256dh_key` varchar(255) DEFAULT NULL COMMENT 'VAPID encryption key',
  `device_type` varchar(50) DEFAULT 'web' COMMENT 'web, ios, android',
  `device_name` varchar(255) DEFAULT NULL COMMENT 'Device name/model',
  `browser_info` varchar(255) DEFAULT NULL COMMENT 'Browser name and version',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether subscription is active',
  `last_used` timestamp NULL DEFAULT NULL COMMENT 'Last time notification was sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_vendor_active` (`vendor_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store push notification subscriptions for vendors';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_ratings`;
CREATE TABLE `vendor_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `total_reviews` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `one_star` int(11) DEFAULT 0,
  `two_star` int(11) DEFAULT 0,
  `three_star` int(11) DEFAULT 0,
  `four_star` int(11) DEFAULT 0,
  `five_star` int(11) DEFAULT 0,
  `delivery_rating` decimal(3,2) DEFAULT NULL,
  `quality_rating` decimal(3,2) DEFAULT NULL,
  `packaging_rating` decimal(3,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_reviews`;
CREATE TABLE `vendor_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(255) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_settings`;
CREATE TABLE `vendor_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `auto_accept_orders` tinyint(1) DEFAULT 1,
  `order_preparation_time` int(11) DEFAULT 30,
  `delivery_radius` int(11) DEFAULT 5,
  `enable_notifications` tinyint(1) DEFAULT 1,
  `notification_email` varchar(255) DEFAULT NULL,
  `notification_phone` varchar(20) DEFAULT NULL,
  `language_preference` varchar(50) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_settlements`;
CREATE TABLE `vendor_settlements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `settlement_date` date DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT NULL,
  `total_sales` decimal(15,2) DEFAULT NULL,
  `total_refunds` decimal(15,2) DEFAULT NULL,
  `commissions` decimal(15,2) DEFAULT NULL,
  `penalties` decimal(15,2) DEFAULT NULL,
  `closing_balance` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','settled','disputed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_settlement_date` (`settlement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendor_support_tickets`;
CREATE TABLE `vendor_support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `ticket_number` varchar(50) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `assigned_to` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_category` varchar(50) NOT NULL,
  `store_description` text DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `registration_number` varchar(100) NOT NULL,
  `tax_id` varchar(100) NOT NULL,
  `business_address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'India',
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `account_holder` varchar(255) NOT NULL,
  `status` enum('pending','active','suspended','inactive') DEFAULT 'pending',
  `email_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `bank_verified` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `total_products` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `registration_number` (`registration_number`),
  UNIQUE KEY `tax_id` (`tax_id`),
  UNIQUE KEY `account_number` (`account_number`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `wallet_bank_names`;
CREATE TABLE `wallet_bank_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('wallet','bank') NOT NULL DEFAULT 'wallet',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `wallet_qrs`;
CREATE TABLE `wallet_qrs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_type` varchar(50) NOT NULL,
  `qr_image` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS=1;
