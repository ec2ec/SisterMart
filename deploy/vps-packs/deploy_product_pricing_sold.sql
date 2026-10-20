-- 商品：疊加銷量 sold_boost；SKU：代理價 agent_price、成本價 cost_price（可重跑）
-- 遷移策略：既有 sold_count 視為真實銷售（只讀，由訂單加減）；sold_boost 預設 0
-- mysql -uUSER -pPASSWORD DBNAME < apps/api/database/deploy_product_pricing_sold.sql

SET @db := DATABASE();

-- products.sold_boost
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sold_boost'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `products` ADD COLUMN `sold_boost` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `sold_count`',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- skus.agent_price
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'agent_price'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `skus` ADD COLUMN `agent_price` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `compare_at`',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- skus.cost_price
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'cost_price'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `skus` ADD COLUMN `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `agent_price`',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'deploy_product_pricing_sold ok' AS result;
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='products' AND COLUMN_NAME='sold_boost') AS has_sold_boost,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='skus' AND COLUMN_NAME='agent_price') AS has_agent_price,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='skus' AND COLUMN_NAME='cost_price') AS has_cost_price;
