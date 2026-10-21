-- A5.2：提現審核人審計欄（可重複執行；API 亦會冪等 ensure）
-- mysql -u帳號 -p 資料庫名 < deploy_withdrawals_reviewer.sql

SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'withdrawals' AND COLUMN_NAME = 'reviewed_by_admin_id'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `withdrawals` ADD COLUMN `reviewed_by_admin_id` BIGINT UNSIGNED NULL AFTER `reviewed_at`',
  'SELECT ''withdrawals.reviewed_by_admin_id already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'deploy_withdrawals_reviewer ok' AS result;
