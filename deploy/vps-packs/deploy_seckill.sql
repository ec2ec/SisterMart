-- SisterMart 秒殺活動：建表 + orders.seckill_item_id + 可選種子（可重跑）
-- 用法：mysql -uUSER -pPASSWORD DBNAME < apps/api/database/deploy_seckill.sql
-- 若只要空表、自行在後台建活動，種子 INSERT 可略過（已用 NOT EXISTS 冪等）。

CREATE TABLE IF NOT EXISTS seckill_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name_zh VARCHAR(128) NOT NULL,
  name_en VARCHAR(128) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_seckill_sessions_code (code),
  KEY idx_seckill_sessions_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seckill_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  seckill_price DECIMAL(12,2) NOT NULL,
  origin_price DECIMAL(12,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_seckill_items_session (session_id),
  KEY idx_seckill_items_product_code (product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders.seckill_item_id（冪等）
SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'seckill_item_id'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `orders` ADD COLUMN seckill_item_id BIGINT UNSIGNED NULL AFTER coupon_grant_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 種子場次：進行中 sk-live（若尚無）
INSERT INTO seckill_sessions (code, name_zh, name_en, starts_at, ends_at, created_at, updated_at)
SELECT
  'sk-live',
  '午間閃購',
  'Noon Flash',
  DATE_SUB(NOW(), INTERVAL 30 MINUTE),
  DATE_ADD(NOW(), INTERVAL 7 DAY),
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM seckill_sessions WHERE code = 'sk-live');

-- 種子場次：未開始 sk-soon（若尚無）
INSERT INTO seckill_sessions (code, name_zh, name_en, starts_at, ends_at, created_at, updated_at)
SELECT
  'sk-soon',
  '晚間預告',
  'Evening Soon',
  DATE_ADD(NOW(), INTERVAL 1 HOUR),
  DATE_ADD(NOW(), INTERVAL 7 DAY) + INTERVAL 3 HOUR,
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM seckill_sessions WHERE code = 'sk-soon');

-- 刷新時間窗（已存在場次也拉開窗口，避免線上全過期）
UPDATE seckill_sessions
SET
  starts_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE),
  ends_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
  updated_at = NOW()
WHERE code = 'sk-live';

UPDATE seckill_sessions
SET
  starts_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
  ends_at = DATE_ADD(NOW(), INTERVAL 7 DAY) + INTERVAL 3 HOUR,
  updated_at = NOW()
WHERE code = 'sk-soon';

-- 種子商品：p1 / p4（商品與 SKU 須已存在；缺則略過）
INSERT INTO seckill_items (
  session_id, product_id, product_code, sku_id,
  seckill_price, origin_price, stock, sold, image_url,
  name_zh, name_en, created_at, updated_at
)
SELECT
  s.id,
  p.id,
  p.code,
  sk.id,
  CASE p.code WHEN 'p1' THEN 88.00 WHEN 'p4' THEN 49.00 ELSE sk.price END,
  CASE p.code
    WHEN 'p1' THEN 128.00
    WHEN 'p4' THEN 69.00
    ELSE COALESCE(NULLIF(sk.compare_at, 0), sk.price)
  END,
  CASE WHEN s.code = 'sk-live' THEN
    CASE p.code WHEN 'p1' THEN 15 WHEN 'p4' THEN 8 ELSE 10 END
  ELSE
    CASE p.code WHEN 'p1' THEN 20 WHEN 'p4' THEN 12 ELSE 10 END
  END,
  0,
  COALESCE(NULLIF(sk.image_url, ''), p.cover_url, ''),
  COALESCE(izh.name, p.code),
  COALESCE(ien.name, p.code),
  NOW(),
  NOW()
FROM seckill_sessions s
JOIN products p ON p.code IN ('p1', 'p4') AND p.status = 1
JOIN skus sk ON sk.product_id = p.id AND sk.status = 1
  AND sk.id = (
    SELECT MIN(sk2.id) FROM skus sk2
    WHERE sk2.product_id = p.id AND sk2.status = 1
  )
LEFT JOIN product_i18n izh ON izh.product_id = p.id AND izh.locale <> 'en'
LEFT JOIN product_i18n ien ON ien.product_id = p.id AND ien.locale = 'en'
WHERE s.code IN ('sk-live', 'sk-soon')
  AND NOT EXISTS (
    SELECT 1 FROM seckill_items si
    WHERE si.session_id = s.id AND si.product_code = p.code
  );

SELECT 'deploy_seckill ok' AS result;
SELECT code, name_zh, status_hint, starts_at, ends_at
FROM (
  SELECT
    code,
    name_zh,
    CASE
      WHEN NOW() < starts_at THEN 'upcoming'
      WHEN NOW() >= ends_at THEN 'ended'
      ELSE 'live'
    END AS status_hint,
    starts_at,
    ends_at
  FROM seckill_sessions
  WHERE code IN ('sk-live', 'sk-soon')
) t;
