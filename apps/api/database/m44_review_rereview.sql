-- M44 product_reviews re-review (admin request → member overwrite within 30 days)

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_reviews'
    AND COLUMN_NAME = 're_review_requested_at'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE product_reviews ADD COLUMN re_review_requested_at DATETIME NULL DEFAULT NULL COMMENT ''管理員發起重評時間'' AFTER moderation_status',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col2 := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_reviews'
    AND COLUMN_NAME = 're_reviewed_at'
);
SET @sql2 := IF(
  @col2 = 0,
  'ALTER TABLE product_reviews ADD COLUMN re_reviewed_at DATETIME NULL DEFAULT NULL COMMENT ''用戶完成重評時間'' AFTER re_review_requested_at',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
