-- M14 每日簽到（可重複執行）
-- 修復：GET /api/v1/checkins/calendar 500（缺表）

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

-- 簽到積分設定（不存在才插入）
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.base_reward', '10', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.base_reward');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.streak_bonus', '1', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.streak_bonus');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'checkin.streak_cap', '30', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'checkin.streak_cap');
