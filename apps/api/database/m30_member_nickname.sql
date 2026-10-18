-- M30：會員暱稱欄
-- mysql -u... -p... sistermart < apps/api/database/m30_member_nickname.sql
-- 也可：cd apps/api && php database/m30_seed.php
-- 建議先執行 m28（avatar_url），再執行本腳本；nickname 會插在 locale 之後

SET @db := DATABASE();
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
