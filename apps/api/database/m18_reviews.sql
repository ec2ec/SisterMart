-- M18：商品評價（主評 + 追評）

CREATE TABLE IF NOT EXISTS product_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  stars TINYINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  images_json JSON NULL,
  follow_body TEXT NULL,
  follow_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_review_order_item (order_item_id),
  KEY idx_reviews_product (product_id, created_at),
  KEY idx_reviews_member (member_id),
  KEY idx_reviews_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
