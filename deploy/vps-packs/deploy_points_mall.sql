-- M12 積分商城：表 + 種子 pm1/pm2（依賴商品 p3/p4）
-- 可單獨在 VPS 執行；API 亦會冪等建表並在空表時種子。

CREATE TABLE IF NOT EXISTS points_mall_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  points_cost INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  detail_zh TEXT NULL,
  detail_en TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_points_mall_items_code (code),
  KEY idx_points_mall_items_product (product_code),
  KEY idx_points_mall_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders.points_mall_item_id（若已存在會報錯可忽略）
-- ALTER TABLE orders ADD COLUMN points_mall_item_id BIGINT UNSIGNED NULL AFTER seckill_item_id;
