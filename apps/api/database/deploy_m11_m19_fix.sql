-- 補齊生產常見缺口：M11 秒殺表 + M19 首頁輪播（可重跑）
-- 若首頁 /api/v1/home/banners 或 /api/v1/seckill/sessions 回 500，優先執行本腳本
-- mysql -u... -p... < apps/api/database/deploy_m11_m19_fix.sql

CREATE TABLE IF NOT EXISTS home_banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  image_url VARCHAR(512) NOT NULL,
  title_zh VARCHAR(191) NOT NULL DEFAULT '',
  title_en VARCHAR(191) NOT NULL DEFAULT '',
  link_type VARCHAR(32) NOT NULL DEFAULT 'none',
  link_value VARCHAR(512) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_home_banners_status_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO home_banners (image_url, title_zh, title_en, link_type, link_value, sort_order, status, created_at, updated_at)
SELECT '/mock/home-banner-1.png', '精緻珠寶', 'Fine jewelry', 'none', NULL, 10, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM home_banners LIMIT 1);

INSERT INTO home_banners (image_url, title_zh, title_en, link_type, link_value, sort_order, status, created_at, updated_at)
SELECT '/mock/home-banner-2.png', '新品上市', 'New arrivals', 'none', NULL, 20, 1, NOW(), NOW()
FROM DUAL
WHERE (SELECT COUNT(*) FROM home_banners) = 1;

CREATE TABLE IF NOT EXISTS seckill_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name_zh VARCHAR(128) NOT NULL,
  name_en VARCHAR(128) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_seckill_sessions_code (code),
  KEY idx_seckill_sessions_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seckill_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  seckill_price DECIMAL(12,2) NOT NULL,
  origin_price DECIMAL(12,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_seckill_items_session (session_id),
  KEY idx_seckill_items_product_code (product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
