-- M37 global sale-spec templates (idempotent)
-- Chinese labels via UNHEX to avoid release migrate charset issues

CREATE TABLE IF NOT EXISTS spec_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_spec_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_template_i18n (
  template_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (template_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_template_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sto_tpl_code (template_id, code),
  KEY idx_sto_tpl (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_template_option_i18n (
  option_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (option_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO spec_templates (code, sort_order, status, created_at, updated_at)
SELECT 'color', 1, 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM spec_templates WHERE code = 'color');

INSERT INTO spec_template_i18n (template_id, locale, name)
SELECT t.id, 'zh-HK', CONVERT(UNHEX('E9A18FE889B2') USING utf8mb4) FROM spec_templates t
WHERE t.code = 'color'
  AND NOT EXISTS (SELECT 1 FROM spec_template_i18n i WHERE i.template_id = t.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_i18n (template_id, locale, name)
SELECT t.id, 'en', 'Color' FROM spec_templates t
WHERE t.code = 'color'
  AND NOT EXISTS (SELECT 1 FROM spec_template_i18n i WHERE i.template_id = t.id AND i.locale = 'en');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 'red', 1, 1 FROM spec_templates t
WHERE t.code = 'color'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 'red');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 'blue', 2, 1 FROM spec_templates t
WHERE t.code = 'color'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 'blue');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 'gold', 3, 1 FROM spec_templates t
WHERE t.code = 'color'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 'gold');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', CONVERT(UNHEX('E7B485E889B2') USING utf8mb4) FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'red'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', 'Red' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'red'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', CONVERT(UNHEX('E8978DE889B2') USING utf8mb4) FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'blue'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', 'Blue' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'blue'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', CONVERT(UNHEX('E98791E889B2') USING utf8mb4) FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'gold'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', 'Gold' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'color'
WHERE o.code = 'gold'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');

INSERT INTO spec_templates (code, sort_order, status, created_at, updated_at)
SELECT 'size', 2, 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM spec_templates WHERE code = 'size');

INSERT INTO spec_template_i18n (template_id, locale, name)
SELECT t.id, 'zh-HK', CONVERT(UNHEX('E5B0BAE7A2BC') USING utf8mb4) FROM spec_templates t
WHERE t.code = 'size'
  AND NOT EXISTS (SELECT 1 FROM spec_template_i18n i WHERE i.template_id = t.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_i18n (template_id, locale, name)
SELECT t.id, 'en', 'Size' FROM spec_templates t
WHERE t.code = 'size'
  AND NOT EXISTS (SELECT 1 FROM spec_template_i18n i WHERE i.template_id = t.id AND i.locale = 'en');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 's16', 1, 1 FROM spec_templates t
WHERE t.code = 'size'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 's16');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 's17', 2, 1 FROM spec_templates t
WHERE t.code = 'size'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 's17');

INSERT INTO spec_template_options (template_id, code, sort_order, status)
SELECT t.id, 's18', 3, 1 FROM spec_templates t
WHERE t.code = 'size'
  AND NOT EXISTS (SELECT 1 FROM spec_template_options o WHERE o.template_id = t.id AND o.code = 's18');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', '16cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's16'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', '16cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's16'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', '17cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's17'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', '17cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's17'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'zh-HK', '18cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's18'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'zh-HK');

INSERT INTO spec_template_option_i18n (option_id, locale, name)
SELECT o.id, 'en', '18cm' FROM spec_template_options o
JOIN spec_templates t ON t.id = o.template_id AND t.code = 'size'
WHERE o.code = 's18'
  AND NOT EXISTS (SELECT 1 FROM spec_template_option_i18n i WHERE i.option_id = o.id AND i.locale = 'en');
