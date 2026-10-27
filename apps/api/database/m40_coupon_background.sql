-- M40 coupons.background_image: optional card background URL (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coupons'
    AND COLUMN_NAME = 'background_image'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE coupons ADD COLUMN background_image VARCHAR(512) NULL DEFAULT NULL COMMENT ''券卡背景圖 URL，空則用前端漸變'' AFTER rules_en',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
