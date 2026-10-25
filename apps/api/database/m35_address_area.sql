-- M35：收貨地址增加社區／地點欄位 area（十八區之下一級）。可重複執行。

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
