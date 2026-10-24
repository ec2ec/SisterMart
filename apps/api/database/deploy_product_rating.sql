-- 商品聚合評分（由已通過、未隱藏評價重算）
-- 可重跑：欄位已存在時跳過 ADD

SET @db := DATABASE();

SET @has_avg := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating_avg'
);
SET @sql_avg := IF(
  @has_avg = 0,
  'ALTER TABLE `products` ADD COLUMN `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00',
  'SELECT ''rating_avg exists'' AS info'
);
PREPARE stmt_avg FROM @sql_avg;
EXECUTE stmt_avg;
DEALLOCATE PREPARE stmt_avg;

SET @has_cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating_count'
);
SET @sql_cnt := IF(
  @has_cnt = 0,
  'ALTER TABLE `products` ADD COLUMN `rating_count` INT UNSIGNED NOT NULL DEFAULT 0',
  'SELECT ''rating_count exists'' AS info'
);
PREPARE stmt_cnt FROM @sql_cnt;
EXECUTE stmt_cnt;
DEALLOCATE PREPARE stmt_cnt;

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
