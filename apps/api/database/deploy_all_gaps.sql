-- SisterMart 一次補齊近期缺表／缺欄（可重複執行）
-- 建議：phpMyAdmin 選中業務庫後導入本檔，或：
--   mysql -u帳號 -p 資料庫名 < deploy_all_gaps.sql
--
-- 含：m14 簽到、m27 訂閱、m28 收藏+頭像

-- ========== M14 簽到 ==========
CREATE TABLE IF NOT EXISTS member_checkins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  checkin_date DATE NOT NULL,
  reward INT NOT NULL DEFAULT 0,
  streak INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_member_checkins_day (member_id, checkin_date),
  KEY idx_member_checkins_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.base_reward', '10', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.base_reward');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.streak_bonus', '1', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.streak_bonus');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.streak_cap', '30', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.streak_cap');

-- ========== M27 訂閱 ==========
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=有效 0=已關閉',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_newsletter_email (email),
  KEY idx_newsletter_member (member_id),
  KEY idx_newsletter_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== M28 收藏 + 頭像 ==========
CREATE TABLE IF NOT EXISTS member_favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_fav_member_product (member_id, product_id),
  KEY idx_fav_member_created (member_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'members' AND COLUMN_NAME = 'avatar_url'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `members` ADD COLUMN avatar_url VARCHAR(512) NULL COMMENT ''頭像路徑'' AFTER locale',
  'SELECT ''members.avatar_url already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'deploy_all_gaps ok' AS result;
