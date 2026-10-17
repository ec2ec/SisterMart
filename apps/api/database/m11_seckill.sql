-- M11 秒殺活動庫存（真實扣減；支付仍為 mock）
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
  KEY idx_seckill_items_product_code (product_code),
  CONSTRAINT fk_seckill_items_session FOREIGN KEY (session_id) REFERENCES seckill_sessions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders.seckill_item_id：請用 m11_seed.php 的 ensureColumn 冪等添加
-- ALTER TABLE orders ADD COLUMN seckill_item_id BIGINT UNSIGNED NULL AFTER coupon_grant_id;
