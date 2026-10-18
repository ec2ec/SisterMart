-- VPS 常見缺口：消息 / 評價 / 訂單欄（可重跑）
-- 修復：系統消息發送 500、訂單詳情 500

CREATE TABLE IF NOT EXISTS member_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL COMMENT 'order|system',
  title_zh VARCHAR(191) NOT NULL,
  title_en VARCHAR(191) NOT NULL,
  body_zh TEXT NOT NULL,
  body_en TEXT NOT NULL,
  ref_type VARCHAR(32) NULL,
  ref_id BIGINT UNSIGNED NULL,
  event_key VARCHAR(96) NULL,
  batch_id VARCHAR(36) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_member_messages_event (member_id, event_key),
  KEY idx_member_messages_member_type (member_id, type, created_at),
  KEY idx_member_messages_unread (member_id, read_at),
  KEY idx_member_messages_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_message_batches (
  batch_id VARCHAR(36) NOT NULL PRIMARY KEY,
  title_zh VARCHAR(191) NOT NULL,
  title_en VARCHAR(191) NOT NULL,
  body_zh MEDIUMTEXT NOT NULL,
  body_en MEDIUMTEXT NOT NULL,
  scope VARCHAR(16) NOT NULL DEFAULT 'all',
  member_ids_json JSON NULL,
  send_email TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  email_queued INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(191) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  ref_type VARCHAR(32) NULL,
  ref_id VARCHAR(64) NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY idx_mail_outbox_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  is_hidden TINYINT NOT NULL DEFAULT 0,
  admin_reply TEXT NULL,
  replied_at DATETIME NULL,
  moderation_status VARCHAR(16) NOT NULL DEFAULT 'approved',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_review_order_item (order_item_id),
  KEY idx_reviews_product (product_id, created_at),
  KEY idx_reviews_member (member_id),
  KEY idx_reviews_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders 擴展欄（冪等）
SET @db := DATABASE();
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='orders' AND COLUMN_NAME='seckill_item_id');
SET @sql := IF(@exists=0, 'ALTER TABLE `orders` ADD COLUMN seckill_item_id BIGINT UNSIGNED NULL AFTER coupon_grant_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='orders' AND COLUMN_NAME='points_mall_item_id');
SET @sql := IF(@exists=0, 'ALTER TABLE `orders` ADD COLUMN points_mall_item_id BIGINT UNSIGNED NULL AFTER seckill_item_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='order_items' AND COLUMN_NAME='compare_at');
SET @sql := IF(@exists=0, 'ALTER TABLE `order_items` ADD COLUMN compare_at DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'deploy_vps_schema_gaps ok' AS result;
