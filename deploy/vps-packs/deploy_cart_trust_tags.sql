-- 購物車頂部信任標籤（可單獨執行；API 亦會冪等建表並種子三條默認標籤）
CREATE TABLE IF NOT EXISTS cart_trust_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  name_zh VARCHAR(64) NOT NULL,
  name_en VARCHAR(64) NOT NULL,
  detail_zh TEXT NULL,
  detail_en TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_cart_trust_tags_code (code),
  KEY idx_cart_trust_tags_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
