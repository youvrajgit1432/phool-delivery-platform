-- Create vendor_notifications table if not exists (admin DB)
CREATE TABLE IF NOT EXISTS vendor_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    notification_type VARCHAR(100),
    title VARCHAR(255),
    message TEXT,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
