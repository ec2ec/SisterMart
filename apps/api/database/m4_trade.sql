-- M4：地址、購物車、訂單、模擬支付。可重複執行。
-- mysql -usistermart -psistermart -h127.0.0.1 sistermart < apps/api/database/m4_trade.sql
-- 會員 balance/points 由 m4_seed.php 補齊。

CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  receiver VARCHAR(64) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  region VARCHAR(16) NOT NULL,
  district VARCHAR(64) NOT NULL,
  detail VARCHAR(255) NOT NULL,
  is_default TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_addresses_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_carts_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  checked TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cart_items_sku (cart_id, sku_id),
  KEY idx_cart_items_cart (cart_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_no VARCHAR(32) NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  address_snapshot JSON NOT NULL,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  goods_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  points_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  points_used INT NOT NULL DEFAULT 0,
  balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  pay_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  use_points TINYINT NOT NULL DEFAULT 0,
  use_balance TINYINT NOT NULL DEFAULT 0,
  coupon_grant_id BIGINT UNSIGNED NULL,
  pay_channel VARCHAR(32) NULL,
  paid_at DATETIME NULL,
  cancel_reason VARCHAR(64) NULL,
  expire_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_orders_no (order_no),
  KEY idx_orders_member (member_id),
  KEY idx_orders_status (status),
  KEY idx_orders_expire (status, expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_code VARCHAR(64) NOT NULL,
  name_zh VARCHAR(191) NOT NULL,
  name_en VARCHAR(191) NOT NULL,
  spec_zh VARCHAR(191) NOT NULL DEFAULT '',
  spec_en VARCHAR(191) NOT NULL DEFAULT '',
  image_url VARCHAR(255) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  compare_at DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL,
  line_amount DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(32) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  mock_ref VARCHAR(64) NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name_zh VARCHAR(64) NOT NULL,
  name_en VARCHAR(64) NOT NULL,
  type VARCHAR(16) NOT NULL,
  threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'unused',
  order_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_coupon_grants_member (member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'shipping.free_threshold', '200', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'shipping.free_threshold');
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'shipping.fee', '25', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'shipping.fee');
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'order.unpaid_timeout_minutes', '30', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'order.unpaid_timeout_minutes');
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'points.per_hkd', '100', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'points.per_hkd');
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'points.max_percent', '50', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'points.max_percent');
