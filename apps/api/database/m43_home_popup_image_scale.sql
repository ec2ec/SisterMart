-- M43 home_popups.image_scale: display scale percent (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'home_popups'
    AND COLUMN_NAME = 'image_scale'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE home_popups ADD COLUMN image_scale INT NOT NULL DEFAULT 100 COMMENT ''圖片縮放比例百分比 40-150'' AFTER targets',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
