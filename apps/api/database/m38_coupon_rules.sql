-- M38 coupons.rules_zh / rules_en usage rules rich text (idempotent)

SET @col_zh := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coupons'
    AND COLUMN_NAME = 'rules_zh'
);
SET @sql_zh := IF(
  @col_zh = 0,
  'ALTER TABLE coupons ADD COLUMN rules_zh MEDIUMTEXT NULL COMMENT ''使用細則（繁中富文本）'' AFTER name_en',
  'SELECT 1'
);
PREPARE stmt_zh FROM @sql_zh;
EXECUTE stmt_zh;
DEALLOCATE PREPARE stmt_zh;

SET @col_en := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coupons'
    AND COLUMN_NAME = 'rules_en'
);
SET @sql_en := IF(
  @col_en = 0,
  'ALTER TABLE coupons ADD COLUMN rules_en MEDIUMTEXT NULL COMMENT ''Usage rules (EN rich text)'' AFTER rules_zh',
  'SELECT 1'
);
PREPARE stmt_en FROM @sql_en;
EXECUTE stmt_en;
DEALLOCATE PREPARE stmt_en;
