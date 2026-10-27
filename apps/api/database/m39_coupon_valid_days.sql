-- M39 coupons.valid_days: days valid after claim; 0 = never expires (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coupons'
    AND COLUMN_NAME = 'valid_days'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE coupons ADD COLUMN valid_days INT NOT NULL DEFAULT 30 COMMENT ''領取後有效天數，0=永久'' AFTER ends_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
