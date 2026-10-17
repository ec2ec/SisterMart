-- M16：售後佣金沖回冪等表 + 分享瀏覽真實表
-- commissions.original_amount 由 m16_seed.php ensureColumn 冪等添加

CREATE TABLE IF NOT EXISTS commission_clawbacks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  aftersale_id BIGINT UNSIGNED NOT NULL,
  commission_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_clawback_aftersale (aftersale_id),
  KEY idx_clawback_commission (commission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS share_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  viewer_ip VARCHAR(64) NOT NULL,
  viewer_member_id BIGINT UNSIGNED NULL,
  points INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_share_views_dedupe (owner_id, product_code, viewer_ip),
  KEY idx_share_views_owner (owner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
