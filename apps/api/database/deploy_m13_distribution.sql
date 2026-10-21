-- SisterMart M13 分銷 schema（可重複執行）
-- 修復：GET /api/v1/distribution/overview|orders|commissions → 500
--   根因：缺少 commissions 表（及配套欄／提現表）
--
-- phpMyAdmin：選中業務庫 → 導入本檔
-- 命令列：mysql -u帳號 -p 資料庫名 < deploy_m13_distribution.sql

-- ========== commissions ==========
CREATE TABLE IF NOT EXISTS commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  beneficiary_id BIGINT UNSIGNED NOT NULL,
  buyer_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  original_amount DECIMAL(12,2) NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  settled_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_commissions_beneficiary (beneficiary_id, status),
  KEY idx_commissions_order (order_id),
  KEY idx_commissions_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== withdrawals ==========
CREATE TABLE IF NOT EXISTS withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(16) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  fps_name VARCHAR(128) NULL,
  fps_id VARCHAR(128) NULL,
  review_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  reviewed_by_admin_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_withdrawals_member (member_id, status),
  KEY idx_withdrawals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== M16 售後沖回冪等表（分銷售後會用到）==========
CREATE TABLE IF NOT EXISTS commission_clawbacks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  aftersale_id BIGINT UNSIGNED NOT NULL,
  commission_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_clawback_aftersale (aftersale_id),
  KEY idx_clawback_commission (commission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 冪等加欄 ==========
SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'original_amount'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `commissions` ADD COLUMN `original_amount` DECIMAL(12,2) NULL AFTER `amount`',
  'SELECT ''commissions.original_amount already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'members' AND COLUMN_NAME = 'commission_available'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `members` ADD COLUMN `commission_available` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `points`',
  'SELECT ''members.commission_available already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'commission_type'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `products` ADD COLUMN `commission_type` VARCHAR(16) NULL COMMENT ''percent|fixed'' AFTER `cover_url`',
  'SELECT ''products.commission_type already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'commission_value'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `products` ADD COLUMN `commission_value` DECIMAL(12,2) NULL AFTER `commission_type`',
  'SELECT ''products.commission_value already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'withdrawals' AND COLUMN_NAME = 'reviewed_by_admin_id'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `withdrawals` ADD COLUMN `reviewed_by_admin_id` BIGINT UNSIGNED NULL AFTER `reviewed_at`',
  'SELECT ''withdrawals.reviewed_by_admin_id already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 最低提現額設定（不存在才插入）
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'distribution.withdraw_min', '100', NOW() FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM shop_settings WHERE setting_key = 'distribution.withdraw_min'
);

SELECT 'deploy_m13_distribution ok' AS result;
