-- Add total_amount to bookings for rental billing and payment handoff.
-- Compatible with MySQL versions without "ADD COLUMN IF NOT EXISTS".
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'bookings'
    AND COLUMN_NAME = 'total_amount'
);

SET @ddl := IF(
  @col_exists = 0,
  'ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(12,2) NULL AFTER payment_status',
  'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill where payment_amount already exists.
UPDATE bookings
SET total_amount = payment_amount
WHERE total_amount IS NULL
  AND payment_amount IS NOT NULL;
