-- M35 addresses.area locality under district (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'addresses'
    AND COLUMN_NAME = 'area'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE addresses ADD COLUMN area VARCHAR(64) NOT NULL DEFAULT '''' AFTER district',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
