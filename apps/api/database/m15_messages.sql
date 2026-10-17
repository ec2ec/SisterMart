-- M15 站內消息（訂單 + 系統）真實表
CREATE TABLE IF NOT EXISTS member_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL COMMENT 'order|system',
  title_zh VARCHAR(191) NOT NULL,
  title_en VARCHAR(191) NOT NULL,
  body_zh TEXT NOT NULL,
  body_en TEXT NOT NULL,
  ref_type VARCHAR(32) NULL COMMENT 'order|aftersale|broadcast',
  ref_id BIGINT UNSIGNED NULL,
  event_key VARCHAR(96) NULL COMMENT '冪等鍵，如 order:12:paid',
  batch_id VARCHAR(36) NULL COMMENT '系統廣播批次',
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_member_messages_event (member_id, event_key),
  KEY idx_member_messages_member_type (member_id, type, created_at),
  KEY idx_member_messages_unread (member_id, read_at),
  KEY idx_member_messages_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 演示：為現有會員寫入一條歡迎系統消息
INSERT INTO member_messages (
  member_id, type, title_zh, title_en, body_zh, body_en,
  ref_type, event_key, batch_id, created_at
)
SELECT
  m.id,
  'system',
  '系統：站內消息已開通',
  'Inbox is live',
  '訂單狀態與系統通知會出現在這裡；本條為 M15 演示。',
  'Order updates and system notices appear here. M15 demo seed.',
  'broadcast',
  CONCAT('seed:welcome:', m.id),
  'seed-welcome-m15',
  DATE_SUB(NOW(), INTERVAL 1 HOUR)
FROM members m
WHERE NOT EXISTS (
  SELECT 1 FROM member_messages mm
  WHERE mm.member_id = m.id AND mm.event_key = CONCAT('seed:welcome:', m.id)
);
