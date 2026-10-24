-- 商品聚合評分（由已通過、未隱藏評價重算）
-- mysql … < apps/api/database/deploy_product_rating.sql

SET @db := DATABASE();

SET @has_avg := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating_avg'
);
SET @sql := IF(
  @has_avg = 0,
  'ALTER TABLE `products` ADD COLUMN `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating_count'
);
SET @sql := IF(
  @has_cnt = 0,
  'ALTER TABLE `products` ADD COLUMN `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rating_avg`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 用現有已通過評價回填
UPDATE products p
LEFT JOIN (
  SELECT product_id,
         ROUND(AVG(stars), 1) AS avg_stars,
         COUNT(*) AS cnt
  FROM product_reviews
  WHERE moderation_status = 'approved' AND is_hidden = 0
  GROUP BY product_id
) r ON r.product_id = p.id
SET p.rating_avg = COALESCE(r.avg_stars, 0),
    p.rating_count = COALESCE(r.cnt, 0);

SELECT 'deploy_product_rating ok' AS result;
