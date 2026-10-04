-- ============================================================
-- Phool Delivery Platform - fictional demo data
-- Target database: phool_delivery_demo
-- ALL DATA BELOW IS FICTIONAL. No real customer/vendor/rider data.
-- Import after database/schema.sql.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Admin user (users) ----------
INSERT INTO `users`
  (`id`,`username`,`email`,`password`,`full_name`,`phone`,`role`,`status`,`language`,`timezone`)
VALUES
  (1,'demo_admin','demo.admin@example.test','$2y$10$8UhY3cROtmbejJOLEKr5oOxhGDc8oQ2hszkmrlwgB2nBeiM0.zgEe','Demo Admin','9800000000','admin','active','en','Asia/Kathmandu');

-- ---------- Customer ----------
INSERT INTO `customers`
  (`id`,`name`,`email`,`phone`,`password`,`registration_type`,`address`,`city`,`customer_type`,`status`,`verification_status`,`contact`)
VALUES
  (1,'Demo Customer','demo.customer@example.test','9800000001','$2y$10$6fSUwldFlpQ4k40QMqt7G.w2GW9YlO2UXqR8pArM.jkMcOfbbCfJ2','direct','Demo Street 12, Ward 4','Kathmandu','regular','active','verified','9800000001');

-- ---------- Vendors ----------
INSERT INTO `vendors`
  (`id`,`first_name`,`last_name`,`email`,`phone`,`password`,`store_name`,`store_category`,`store_description`,`registration_number`,`tax_id`,`business_address`,`city`,`state`,`postal_code`,`country`,`bank_name`,`account_number`,`ifsc_code`,`account_holder`,`status`,`email_verified`,`verified_at`,`bank_verified`)
VALUES
  (1,'Demo','Vendor','demo.vendor@example.test','9800000010','$2y$10$klg5Yvlxe0YhE5yzoJKeUOC5KowjPvHc5NMy15RWTePuBhj91cGOC','Demo Flower Studio','Flowers','Fictional flower studio used for the public demo.','REG-DEMO-0001','TAX-DEMO-0001','Demo Market Road 5','Kathmandu','Bagmati','44600','Nepal','Demo Bank','0000000001','DEMO0001','Demo Vendor','active',1,NOW(),1),
  (2,'Second','Vendor','demo.vendor2@example.test','9800000011','$2y$10$YTPpkIn0N6Qq0uJlJaWUHe3qBq/LXa3LuIO1ajeQ94NoV9srZvYiK','Demo Events Florist','Events','Second fictional vendor for the public demo.','REG-DEMO-0002','TAX-DEMO-0002','Demo Avenue 9','Lalitpur','Bagmati','44700','Nepal','Demo Bank','0000000002','DEMO0002','Second Vendor','active',1,NOW(),1);

-- ---------- Riders ----------
INSERT INTO `riders`
  (`id`,`first_name`,`last_name`,`email`,`phone`,`password`,`city`,`state`,`country`,`vehicle_type`,`vehicle_number`,`rider_type`,`status`,`email_verified`,`documents_verified`,`is_available`)
VALUES
  (1,'Demo','Rider','demo.rider@example.test','9800000020','$2y$10$ComugUyZAfyyOjq0oWdZaejqCNGUQikqod/xcAHIwup4L6JjE6ra.','Kathmandu','Bagmati','Nepal','motorcycle','BA-DEMO-0001','in_house','active',1,1,1),
  (2,'Second','Rider','demo.rider2@example.test','9800000021','$2y$10$nJ679KxNXKGEgWCc9htR9eSJnRSDFxHIDrygqtwWN021ByjjN2H6i','Lalitpur','Bagmati','Nepal','scooter','BA-DEMO-0002','gig','active',1,1,1);

-- ---------- Categories ----------
INSERT INTO `categories` (`id`,`name_en`,`name_ne`,`status`,`parent_id`,`type`,`is_product_allowed`,`sort_order`) VALUES
  (1,'Flowers','फूल','active',NULL,'main',1,1),
  (2,'Bouquets','गुच्छा','active',1,'subcategory',1,1),
  (3,'Garlands','माला','active',1,'subcategory',1,2),
  (4,'Event Decor','कार्यक्रम सजावट','active',NULL,'main',1,2),
  (5,'Plants','बिरुवा','active',NULL,'main',1,3),
  (6,'Gifts','उपहार','active',NULL,'main',1,4);

-- ---------- Products ----------
INSERT INTO `products` (`id`,`name_en`,`name_ne`,`slug`,`slug_en`,`slug_ne`,`description_en`,`category_id`,`price`,`unit`,`stock_quantity`,`status`) VALUES
  (1,'Red Rose Bunch','रातो गुलाब','red-rose-bunch','red-rose-bunch','rato-gulab','A fictional bunch of red roses.',2,500.00,'bunch',50,'active'),
  (2,'White Lily Bouquet','सेतो लिली','white-lily-bouquet','white-lily-bouquet','seto-lily','A fictional lily bouquet.',2,750.00,'bunch',30,'active'),
  (3,'Mixed Seasonal Bouquet','मिश्रित गुच्छा','mixed-seasonal-bouquet','mixed-seasonal-bouquet','misrit-guchha','A fictional mixed bouquet.',2,900.00,'bunch',25,'active'),
  (4,'Marigold Garland','सयपत्री माला','marigold-garland','marigold-garland','sayapatri-mala','A fictional marigold garland.',3,300.00,'garland',100,'active'),
  (5,'Jasmine Garland','मल्लीको माला','jasmine-garland','jasmine-garland','malliko-mala','A fictional jasmine garland.',3,400.00,'garland',60,'active'),
  (6,'Wedding Stage Decor','विवाह मञ्च','wedding-stage-decor','wedding-stage-decor','bibaha-manch','Fictional event stage decoration.',4,15000.00,'piece',5,'active'),
  (7,'Birthday Balloon Set','जन्मदिन बेलुन','birthday-balloon-set','birthday-balloon-set','janmadin-belun','A fictional balloon set.',4,1200.00,'piece',20,'active'),
  (8,'Money Plant','मनी प्लान्ट','money-plant','money-plant','money-plant','A fictional potted money plant.',5,450.00,'piece',15,'active'),
  (9,'Orchid Plant','अर्किड','orchid-plant','orchid-plant','orchid','A fictional orchid plant.',5,1500.00,'piece',10,'active'),
  (10,'Gift Hamper','उपहार टोकरी','gift-hamper','gift-hamper','upahar-tokari','A fictional gift hamper.',6,2200.00,'piece',12,'active');

INSERT INTO `product_category_map` (`product_id`,`category_id`,`is_primary`) VALUES
  (1,2,1),(2,2,1),(3,2,1),(4,3,1),(5,3,1),(6,4,1),(7,4,1),(8,5,1),(9,5,1),(10,6,1);

-- ---------- Delivery cities & zones ----------
INSERT INTO `delivery_cities` (`id`,`city_name`,`standard_delivery_fee`,`nighttime_urgent_fee`,`min_order_amount`) VALUES
  (1,'Kathmandu',100.00,200.00,500.00),
  (2,'Lalitpur',120.00,240.00,500.00);

INSERT INTO `delivery_zones` (`id`,`zone_name`,`city`,`area_description`,`base_delivery_fee`,`distance_charge_per_km`,`status`) VALUES
  (1,'Central','Kathmandu','Fictional central zone',100.00,20.00,'active'),
  (2,'Outer','Kathmandu','Fictional outer zone',150.00,25.00,'active'),
  (3,'City','Lalitpur','Fictional Lalitpur zone',120.00,22.00,'active');

-- ---------- Customer address ----------
INSERT INTO `customer_addresses`
  (`id`,`customer_id`,`address_type`,`address_line1`,`address_line2`,`city`,`state`,`zip_code`,`country`,`street`,`latitude`,`longitude`,`is_default`)
VALUES
  (1,1,'home','Demo House 12','Ward 4','Kathmandu','Bagmati','44600','Nepal','Demo Street',27.70000000,85.30000000,1);

-- ---------- Orders (varied lifecycle states) ----------
INSERT INTO `orders`
  (`id`,`customer_id`,`order_number`,`quantity`,`rate`,`total_amount`,`applied_discount`,`final_amount`,`status`,`payment_method`,`payment_status`,`order_type`,`delivery_date`,`delivery_address`,`delivery_phone`,`assigned_vendor_id`,`assigned_rider_id`,`assign_status`,`rider_assignment_status`)
VALUES
  (1,1,'DEMO-1001',2,500.00,1000.00,0.00,1100.00,'delivered','cod','paid','website',NOW(),'Demo House 12, Ward 4, Kathmandu','9800000001',1,1,'accepted','delivered'),
  (2,1,'DEMO-1002',1,750.00,750.00,0.00,850.00,'out_for_delivery','esewa','paid','website',NOW(),'Demo House 12, Ward 4, Kathmandu','9800000001',1,1,'accepted','in_delivery'),
  (3,1,'DEMO-1003',3,300.00,900.00,0.00,1000.00,'preparing','cod','pending','website',NOW(),'Demo House 12, Ward 4, Kathmandu','9800000001',2,NULL,'accepted','unassigned'),
  (4,1,'DEMO-1004',1,1500.00,1500.00,100.00,1500.00,'confirmed','bank_transfer','paid_confirmed','website',NOW(),'Demo House 12, Ward 4, Kathmandu','9800000001',1,NULL,'assigned','unassigned'),
  (5,1,'DEMO-1005',2,400.00,800.00,0.00,900.00,'pending','cod','pending','website',NOW(),'Demo House 12, Ward 4, Kathmandu','9800000001',NULL,NULL,'unassigned','unassigned');

INSERT INTO `order_items` (`order_id`,`product_id`,`quantity`,`unit_price`,`total_price`) VALUES
  (1,1,2,500.00,1000.00),
  (2,2,1,750.00,750.00),
  (3,4,3,300.00,900.00),
  (4,9,1,1500.00,1500.00),
  (5,5,2,400.00,800.00);

-- ---------- Vendor orders ----------
INSERT INTO `vendor_orders`
  (`id`,`vendor_id`,`order_id`,`order_number`,`customer_name`,`customer_phone`,`customer_address`,`total_items`,`subtotal`,`delivery_fee`,`discount`,`total_amount`,`vendor_commission`,`payment_method`,`payment_status`,`status`,`assigned_at`,`accepted_at`,`completed_at`,`created_at`,`updated_at`)
VALUES
  (1,1,1,'DEMO-1001','Demo Customer','9800000001','Demo House 12, Kathmandu',2,1000.00,100.00,0.00,1100.00,100.00,'cod','paid','completed',NOW(),NOW(),NOW(),NOW(),NOW()),
  (2,1,2,'DEMO-1002','Demo Customer','9800000001','Demo House 12, Kathmandu',1,750.00,100.00,0.00,850.00,75.00,'esewa','paid','processing',NOW(),NOW(),NULL,NOW(),NOW()),
  (3,2,3,'DEMO-1003','Demo Customer','9800000001','Demo House 12, Kathmandu',3,900.00,100.00,0.00,1000.00,90.00,'cod','pending','accepted',NOW(),NOW(),NULL,NOW(),NOW());
INSERT INTO `vendor_order_items` (`vendor_order_id`,`product_id`,`product_name`,`quantity`,`unit_price`,`item_total`,`created_at`) VALUES
  (1,1,'Red Rose Bunch',2,500.00,1000.00,NOW()),
  (2,2,'White Lily Bouquet',1,750.00,750.00,NOW()),
  (3,4,'Marigold Garland',3,300.00,900.00,NOW());
INSERT INTO `rider_orders`
  (`id`,`rider_id`,`order_id`,`order_number`,`customer_name`,`customer_phone`,`pickup_address`,`delivery_address`,`pickup_city`,`delivery_city`,`total_items`,`total_amount`,`distance_km`,`delivery_status`,`payment_method`,`payment_status`,`cod_amount`,`cod_collected`,`assigned_at`,`accepted_at`,`picked_up_at`,`on_the_way_at`,`delivered_at`,`created_at`,`updated_at`)
VALUES
  (1,1,1,'DEMO-1001','Demo Customer','9800000001','Demo Market Road 5, Kathmandu','Demo House 12, Kathmandu','Kathmandu','Kathmandu',2,1100.00,5.5,'delivered','cod','paid',1100.00,1,NOW(),NOW(),NOW(),NOW(),NOW(),NOW(),NOW()),
  (2,1,2,'DEMO-1002','Demo Customer','9800000001','Demo Market Road 5, Kathmandu','Demo House 12, Kathmandu','Kathmandu','Kathmandu',1,850.00,5.5,'in_delivery','esewa','paid',0.00,0,NOW(),NOW(),NOW(),NOW(),NULL,NOW(),NOW());
INSERT INTO `reviews` (`user_id`,`product_id`,`rating`,`title`,`content`,`status`,`created_at`,`updated_at`) VALUES
  (1,1,5,'Great roses','Fictional review for the demo.','approved',NOW(),NOW()),
  (1,2,4,'Nice lilies','Fictional review for the demo.','approved',NOW(),NOW());
INSERT INTO `notifications` (`title`,`message`,`type`,`is_read`,`created_by`,`created_at`) VALUES
  ('Welcome to the demo','This is a fictional demo notification.','info',0,1,NOW()),
  ('Order update','Order DEMO-1002 is out for delivery.','order',0,1,NOW());
INSERT INTO `rider_earnings`
  (`id`,`rider_id`,`rider_order_id`,`order_id`,`order_number`,`order_amount`,`base_delivery_fee`,`total_earnings`,`net_earnings`,`payment_status`,`created_at`,`updated_at`)
VALUES
  (1,1,1,1,'DEMO-1001',1100.00,100.00,100.00,100.00,'paid',NOW(),NOW()),
  (2,1,2,2,'DEMO-1002',850.00,100.00,100.00,100.00,'pending',NOW(),NOW());
INSERT INTO `vendor_payouts`
  (`vendor_id`,`payout_period_start`,`payout_period_end`,`total_orders`,`total_revenue`,`total_commission`,`total_fees`,`payout_amount`,`status`,`payment_method`,`requested_at`)
VALUES
  (1,DATE_SUB(CURDATE(), INTERVAL 30 DAY),CURDATE(),2,1850.00,175.00,0.00,1675.00,'pending','bank_transfer',NOW());
INSERT INTO `transactions` (`order_id`,`transaction_id`,`amount`,`payment_method`,`status`,`notes`,`created_at`) VALUES
  (1,'TXN-DEMO-0001',1100.00,'cod','completed','Fictional demo transaction.',NOW()),
  (2,'TXN-DEMO-0002',850.00,'esewa','completed','Fictional demo transaction.',NOW());
SET FOREIGN_KEY_CHECKS = 1;
