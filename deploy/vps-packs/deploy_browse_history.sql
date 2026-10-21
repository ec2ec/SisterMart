-- M26 瀏覽足跡（可單獨執行；API 亦會冪等建表，單會員最多保留 50 條）
CREATE TABLE IF NOT EXISTS browse_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  viewed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_browse_member_product (member_id, product_id),
  KEY idx_browse_member_viewed (member_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
