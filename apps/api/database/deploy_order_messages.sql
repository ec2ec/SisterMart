-- 訂單買賣雙方留言對話（可重跑）
CREATE TABLE IF NOT EXISTS order_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  sender VARCHAR(16) NOT NULL COMMENT 'seller|buyer',
  body VARCHAR(256) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_order_messages_order (order_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'deploy_order_messages ok' AS result;
