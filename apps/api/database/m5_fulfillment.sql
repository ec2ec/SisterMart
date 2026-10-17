-- M5：發貨、物流節點、售後。可重複執行。
-- mysql -usistermart -psistermart -h127.0.0.1 sistermart < apps/api/database/m5_fulfillment.sql

CREATE TABLE IF NOT EXISTS shipments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  company VARCHAR(64) NOT NULL,
  tracking_no VARCHAR(64) NOT NULL,
  shipped_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_shipments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_nodes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shipment_id BIGINT UNSIGNED NOT NULL,
  occurred_at DATETIME NOT NULL,
  note VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_shipment_nodes_shipment (shipment_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aftersales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aftersale_no VARCHAR(32) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL,
  reason VARCHAR(128) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  description VARCHAR(500) NOT NULL DEFAULT '',
  evidence_json JSON NULL,
  refund_to VARCHAR(16) NOT NULL,
  status VARCHAR(32) NOT NULL,
  admin_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_aftersales_no (aftersale_no),
  KEY idx_aftersales_order (order_id),
  KEY idx_aftersales_member (member_id),
  KEY idx_aftersales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'order.auto_receive_days', '7', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'order.auto_receive_days');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'points.order_earn_per_hkd', '1', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'points.order_earn_per_hkd');
