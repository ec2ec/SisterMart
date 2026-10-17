-- M13 一級分銷（真實佣金入帳 / 提現；支付仍 mock）
-- products.commission_*、members.commission_available 由 m13_seed.php ensureColumn 冪等添加

CREATE TABLE IF NOT EXISTS commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  beneficiary_id BIGINT UNSIGNED NOT NULL,
  buyer_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  settled_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_commissions_beneficiary (beneficiary_id, status),
  KEY idx_commissions_order (order_id),
  KEY idx_commissions_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(16) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  fps_name VARCHAR(128) NULL,
  fps_id VARCHAR(128) NULL,
  review_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_withdrawals_member (member_id, status),
  KEY idx_withdrawals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
