-- M14 每日簽到真實表
CREATE TABLE IF NOT EXISTS member_checkins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  checkin_date DATE NOT NULL,
  reward INT NOT NULL DEFAULT 0,
  streak INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_member_checkins_day (member_id, checkin_date),
  KEY idx_member_checkins_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
