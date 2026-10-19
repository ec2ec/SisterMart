-- 角標定義 + 商品參數（可重複執行）
-- 現有 product_badges.badge 仍存 code，對應 product_badge_defs.code

CREATE TABLE IF NOT EXISTS product_badge_defs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  color VARCHAR(16) NOT NULL DEFAULT '#16162b',
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_product_badge_defs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_badge_def_i18n (
  badge_def_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (badge_def_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attrs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_product_attrs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attr_i18n (
  attr_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (attr_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attr_values (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attr_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_product_attr_values_attr_code (attr_id, code),
  KEY idx_product_attr_values_attr (attr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attr_value_i18n (
  attr_value_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (attr_value_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attr_links (
  product_id BIGINT UNSIGNED NOT NULL,
  attr_value_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, attr_value_id),
  KEY idx_product_attr_links_value (attr_value_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 種子：現有角標遷移為可維護主數據
SET @now = NOW();

INSERT INTO product_badge_defs (code, color, sort_order, status, created_at, updated_at)
SELECT 'new', '#16162b', 1, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_badge_defs WHERE code = 'new');
INSERT INTO product_badge_defs (code, color, sort_order, status, created_at, updated_at)
SELECT 'hot', '#16162b', 2, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_badge_defs WHERE code = 'hot');
INSERT INTO product_badge_defs (code, color, sort_order, status, created_at, updated_at)
SELECT 'editor', '#6b4a2a', 3, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_badge_defs WHERE code = 'editor');
INSERT INTO product_badge_defs (code, color, sort_order, status, created_at, updated_at)
SELECT 'flash', '#16162b', 4, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_badge_defs WHERE code = 'flash');

INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'zh-HK', '新品' FROM product_badge_defs d
WHERE d.code = 'new' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'zh-HK'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'en', 'New' FROM product_badge_defs d
WHERE d.code = 'new' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'en'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'zh-HK', '熱銷' FROM product_badge_defs d
WHERE d.code = 'hot' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'zh-HK'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'en', 'Hot' FROM product_badge_defs d
WHERE d.code = 'hot' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'en'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'zh-HK', '店長推薦' FROM product_badge_defs d
WHERE d.code = 'editor' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'zh-HK'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'en', 'Editor' FROM product_badge_defs d
WHERE d.code = 'editor' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'en'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'zh-HK', '秒殺' FROM product_badge_defs d
WHERE d.code = 'flash' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'zh-HK'
);
INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'en', 'Flash' FROM product_badge_defs d
WHERE d.code = 'flash' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'en'
);

-- 示例參數：材質
INSERT INTO product_attrs (code, sort_order, status, created_at, updated_at)
SELECT 'material', 1, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_attrs WHERE code = 'material');

INSERT INTO product_attr_i18n (attr_id, locale, name)
SELECT a.id, 'zh-HK', '材質' FROM product_attrs a
WHERE a.code = 'material' AND NOT EXISTS (
  SELECT 1 FROM product_attr_i18n i WHERE i.attr_id = a.id AND i.locale = 'zh-HK'
);
INSERT INTO product_attr_i18n (attr_id, locale, name)
SELECT a.id, 'en', 'Material' FROM product_attrs a
WHERE a.code = 'material' AND NOT EXISTS (
  SELECT 1 FROM product_attr_i18n i WHERE i.attr_id = a.id AND i.locale = 'en'
);

INSERT INTO product_attr_values (attr_id, code, sort_order, status)
SELECT a.id, 'silver925', 1, 1 FROM product_attrs a
WHERE a.code = 'material' AND NOT EXISTS (
  SELECT 1 FROM product_attr_values v WHERE v.attr_id = a.id AND v.code = 'silver925'
);
INSERT INTO product_attr_values (attr_id, code, sort_order, status)
SELECT a.id, 'copper_gilt', 2, 1 FROM product_attrs a
WHERE a.code = 'material' AND NOT EXISTS (
  SELECT 1 FROM product_attr_values v WHERE v.attr_id = a.id AND v.code = 'copper_gilt'
);
INSERT INTO product_attr_values (attr_id, code, sort_order, status)
SELECT a.id, 'titanium', 3, 1 FROM product_attrs a
WHERE a.code = 'material' AND NOT EXISTS (
  SELECT 1 FROM product_attr_values v WHERE v.attr_id = a.id AND v.code = 'titanium'
);

INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'zh-HK', '925銀' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'silver925' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'zh-HK'
);
INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'en', '925 Silver' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'silver925' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'en'
);
INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'zh-HK', '銅鍍金' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'copper_gilt' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'zh-HK'
);
INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'en', 'Gilt Copper' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'copper_gilt' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'en'
);
INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'zh-HK', '鈦鋼合金' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'titanium' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'zh-HK'
);
INSERT INTO product_attr_value_i18n (attr_value_id, locale, name)
SELECT v.id, 'en', 'Titanium Alloy' FROM product_attr_values v
JOIN product_attrs a ON a.id = v.attr_id AND a.code = 'material'
WHERE v.code = 'titanium' AND NOT EXISTS (
  SELECT 1 FROM product_attr_value_i18n i WHERE i.attr_value_id = v.id AND i.locale = 'en'
);
