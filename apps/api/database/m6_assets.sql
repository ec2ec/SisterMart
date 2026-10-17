-- M6：會員等級、券完善、積分/餘額流水。可重複執行。
-- mysql -usistermart -psistermart -h127.0.0.1 sistermart < apps/api/database/m6_assets.sql

CREATE TABLE IF NOT EXISTS member_levels (
  id TINYINT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  stars TINYINT UNSIGNED NOT NULL,
  name_zh VARCHAR(32) NOT NULL,
  name_en VARCHAR(32) NOT NULL,
  min_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_spent DECIMAL(12,2) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_member_levels_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO member_levels (id, code, stars, name_zh, name_en, min_spent, max_spent, sort_order)
SELECT 1, 'l1', 1, '一星會員', '1-Star', 0, 99.99, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM member_levels WHERE id = 1);
INSERT INTO member_levels (id, code, stars, name_zh, name_en, min_spent, max_spent, sort_order)
SELECT 2, 'l2', 2, '二星會員', '2-Star', 100, 499.99, 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM member_levels WHERE id = 2);
INSERT INTO member_levels (id, code, stars, name_zh, name_en, min_spent, max_spent, sort_order)
SELECT 3, 'l3', 3, '三星會員', '3-Star', 500, 1999.99, 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM member_levels WHERE id = 3);
INSERT INTO member_levels (id, code, stars, name_zh, name_en, min_spent, max_spent, sort_order)
SELECT 4, 'l4', 4, '四星會員', '4-Star', 2000, 4999.99, 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM member_levels WHERE id = 4);
INSERT INTO member_levels (id, code, stars, name_zh, name_en, min_spent, max_spent, sort_order)
SELECT 5, 'l5', 5, '五星會員', '5-Star', 5000, NULL, 5 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM member_levels WHERE id = 5);

CREATE TABLE IF NOT EXISTS point_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  delta INT NOT NULL,
  balance_after INT NOT NULL,
  type VARCHAR(32) NOT NULL,
  ref_type VARCHAR(32) NULL,
  ref_id BIGINT UNSIGNED NULL,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_point_logs_member (member_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS balance_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  delta DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) NOT NULL,
  type VARCHAR(32) NOT NULL,
  ref_type VARCHAR(32) NULL,
  ref_id BIGINT UNSIGNED NULL,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_balance_logs_member (member_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
