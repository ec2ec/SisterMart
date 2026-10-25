-- M36 members.last_active_at last API activity (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'last_active_at'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE members ADD COLUMN last_active_at DATETIME NULL DEFAULT NULL AFTER updated_at, ADD KEY idx_members_last_active (last_active_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
