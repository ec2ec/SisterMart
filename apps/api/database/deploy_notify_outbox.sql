CREATE TABLE IF NOT EXISTS notify_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(16) NOT NULL,
  purpose VARCHAR(48) NOT NULL DEFAULT '',
  to_target VARCHAR(191) NOT NULL,
  subject VARCHAR(255) NULL,
  body_preview VARCHAR(500) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  provider VARCHAR(32) NULL,
  ref_type VARCHAR(32) NULL,
  ref_id VARCHAR(64) NULL,
  member_id INT UNSIGNED NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY idx_notify_created (created_at, id),
  KEY idx_notify_channel_created (channel, created_at),
  KEY idx_notify_status (status, id),
  KEY idx_notify_target (to_target),
  KEY idx_notify_purpose (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notify_outbox (
  channel, purpose, to_target, subject, body_preview, status, provider,
  ref_type, ref_id, member_id, error_message, created_at, sent_at
)
SELECT
  'email',
  COALESCE(NULLIF(m.ref_type, ''), 'mail'),
  m.to_email,
  m.subject,
  LEFT(REPLACE(REPLACE(REPLACE(m.body_html, '<', ' <'), '>', '> '), '  ', ' '), 500),
  m.status,
  CASE
    WHEN m.status = 'mock' THEN 'mock'
    WHEN m.status = 'sent' THEN 'php_mail'
    ELSE 'php_mail'
  END,
  m.ref_type,
  m.ref_id,
  NULL,
  m.error_message,
  m.created_at,
  m.sent_at
FROM mail_outbox m
WHERE NOT EXISTS (
  SELECT 1 FROM notify_outbox n
  WHERE n.channel = 'email'
    AND n.ref_type <=> m.ref_type
    AND n.ref_id <=> m.ref_id
    AND n.to_target = m.to_email
    AND n.created_at = m.created_at
);
