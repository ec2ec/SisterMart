-- M41 home overlay popups + member dismissals (idempotent)

CREATE TABLE IF NOT EXISTS home_popups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  image_url VARCHAR(512) NOT NULL,
  title_zh VARCHAR(191) NOT NULL DEFAULT '',
  title_en VARCHAR(191) NOT NULL DEFAULT '',
  link_type VARCHAR(32) NOT NULL DEFAULT 'none',
  link_value VARCHAR(512) NULL,
  frequency VARCHAR(16) NOT NULL DEFAULT 'once' COMMENT 'once|daily|always',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_home_popups_status_sort (status, sort_order, id),
  KEY idx_home_popups_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_popup_dismissals (
  member_id BIGINT UNSIGNED NOT NULL,
  popup_id BIGINT UNSIGNED NOT NULL,
  last_dismissed_at DATETIME NOT NULL,
  PRIMARY KEY (member_id, popup_id),
  KEY idx_home_popup_dismissals_popup (popup_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
