-- SisterMart PR #55 / A2.3 + A5.2 + 積分商城缺表（可重複執行）
-- 建議：phpMyAdmin 選中業務庫後導入本檔，或：
--   mysql -u帳號 -p 資料庫名 < deploy_a23_a52.sql
--
-- 含：
--   - points_mall_items + orders.points_mall_item_id（積分商城 500 自愈）
--   - withdrawals.reviewed_by_admin_id（提現審核人）
-- A2.3 賬單類型篩選僅 API／H5，無 schema 變更。

-- ========== M12 積分商城 ==========
CREATE TABLE IF NOT EXISTS points_mall_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  points_cost INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  detail_zh TEXT NULL,
  detail_en TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_points_mall_items_code (code),
  KEY idx_points_mall_items_product (product_code),
  KEY idx_points_mall_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'points_mall_item_id'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `orders` ADD COLUMN `points_mall_item_id` BIGINT UNSIGNED NULL AFTER `seckill_item_id`',
  'SELECT ''orders.points_mall_item_id already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========== A5.2 提現審核人 ==========
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'withdrawals' AND COLUMN_NAME = 'reviewed_by_admin_id'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `withdrawals` ADD COLUMN `reviewed_by_admin_id` BIGINT UNSIGNED NULL AFTER `reviewed_at`',
  'SELECT ''withdrawals.reviewed_by_admin_id already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'deploy_a23_a52 ok' AS result;
