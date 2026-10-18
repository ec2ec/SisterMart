-- SisterMart 生產升級：M28–M32 所需 schema（一次執行，可重跑）
-- M31 訂單買家留言、M32 積分商城後台：無新表/欄，沿用 member_messages / points_mall_items
--
-- 用法（先備份）：
--   mysql -u... -p... sistermart < apps/api/database/deploy_m28_m32.sql
-- 或分步執行同目錄：
--   m28_favorites_avatar.sql → m29_home_sections.sql → m30_member_nickname.sql

-- ========== M28：收藏 + 頭像 ==========
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

-- ========== M29：首頁商品展示區 ==========
CREATE TABLE IF NOT EXISTS home_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  view_all_type VARCHAR(16) NOT NULL DEFAULT 'list',
  view_all_value VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_home_sections_code (code),
  KEY idx_home_sections_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_section_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  section_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_section_product (section_id, product_id),
  KEY idx_section_sort (section_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== M30：暱稱 ==========
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'members' AND COLUMN_NAME = 'nickname'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `members` ADD COLUMN nickname VARCHAR(64) NULL COMMENT ''暱稱'' AFTER locale',
  'SELECT ''members.nickname already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'deploy_m28_m32 ok' AS result;
