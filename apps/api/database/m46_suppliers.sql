-- M46 suppliers master + products.supplier_id permanent bind (idempotent)
-- Run: cd apps/api && php database/m46_seed.php
-- Or pack with --migrate apps/api/database/m46_suppliers.sql

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(16) NOT NULL COMMENT 'code e.g. S001',
  `name` VARCHAR(120) NOT NULL COMMENT 'full name',
  `type` VARCHAR(32) NOT NULL DEFAULT 'factory' COMMENT 'factory|wholesaler|dropship|bulk|local_stall',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1 active 0 inactive',
  `remark` VARCHAR(500) NULL,
  `contact_person` VARCHAR(80) NULL,
  `phone` VARCHAR(40) NULL,
  `whatsapp` VARCHAR(40) NULL,
  `email` VARCHAR(120) NULL,
  `address` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suppliers_code` (`code`),
  KEY `idx_suppliers_status` (`status`),
  KEY `idx_suppliers_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (
  `code`, `name`, `type`, `status`, `remark`,
  `contact_person`, `phone`, `whatsapp`, `email`, `address`,
  `created_at`, `updated_at`
)
SELECT
  'S001',
  '默認供應商',
  'factory',
  1,
  '系統預置；舊測試商品已綁定此供應商',
  NULL, NULL, NULL, NULL, NULL,
  NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `suppliers` WHERE `code` = 'S001' LIMIT 1);

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'supplier_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `products` ADD COLUMN `supplier_id` BIGINT UNSIGNED NULL COMMENT ''supplier'' AFTER `category_id`, ADD KEY `idx_products_supplier` (`supplier_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `products` p
SET p.`supplier_id` = (SELECT s.`id` FROM `suppliers` s ORDER BY s.`id` ASC LIMIT 1)
WHERE p.`supplier_id` IS NULL OR p.`supplier_id` = 0;

SET @left := (SELECT COUNT(*) FROM `products` WHERE `supplier_id` IS NULL);
SET @sql := IF(
  @left = 0,
  'ALTER TABLE `products` MODIFY COLUMN `supplier_id` BIGINT UNSIGNED NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
