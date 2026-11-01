-- M47: skus.supplier_sku — supplier-provided product code (idempotent)
-- Pack: pnpm release pack -- --migrate apps/api/database/m47_sku_supplier_sku.sql

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'skus' AND COLUMN_NAME = 'supplier_sku'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `skus` ADD COLUMN `supplier_sku` VARCHAR(64) NOT NULL DEFAULT \'\' COMMENT \'supplier product code\' AFTER `cost_price`',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'm47_sku_supplier_sku ok' AS result;
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='skus' AND COLUMN_NAME='supplier_sku') AS has_supplier_sku;
