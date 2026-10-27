-- M42 home_popups.targets + daily impressions (idempotent)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'home_popups'
    AND COLUMN_NAME = 'targets'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE home_popups ADD COLUMN targets JSON NULL COMMENT ''定向複選 JSON 陣列'' AFTER frequency',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS home_popup_impressions (
  member_id BIGINT UNSIGNED NOT NULL,
  popup_id BIGINT UNSIGNED NOT NULL,
  shown_on DATE NOT NULL,
  shown_at DATETIME NOT NULL,
  PRIMARY KEY (member_id, popup_id, shown_on),
  KEY idx_home_popup_impressions_day (member_id, shown_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
