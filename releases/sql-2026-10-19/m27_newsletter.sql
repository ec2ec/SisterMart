-- M27 訂閱郵箱名單（可重複執行）
-- 修復：GET /newsletter/me、POST /newsletter/subscribe 500（缺表）

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=有效 0=已關閉',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_newsletter_email (email),
  KEY idx_newsletter_member (member_id),
  KEY idx_newsletter_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
