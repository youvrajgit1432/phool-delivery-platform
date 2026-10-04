-- Add deleted_at column to orders table (if not already exists)
ALTER TABLE orders ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- Add index for faster trash queries
ALTER TABLE orders ADD INDEX idx_deleted_at (deleted_at);
