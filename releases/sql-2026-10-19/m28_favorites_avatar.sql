-- M28：收藏表 + 會員頭像欄
-- mysql -u... -p... sistermart < apps/api/database/m28_favorites_avatar.sql
-- 也可：cd apps/api && php database/m28_seed.php

CREATE TABLE IF NOT EXISTS member_favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_fav_member_product (member_id, product_id),
  KEY idx_fav_member_created (member_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- members.avatar_url（冪等）
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
