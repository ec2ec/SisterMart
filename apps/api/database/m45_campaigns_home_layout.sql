-- M45：通用活動專區 + 首頁版面模組（可拖放排序）
-- mysql ... < apps/api/database/m45_campaigns_home_layout.sql
-- 或：cd apps/api && php database/m45_seed.php
--
-- P0 計劃摘要：
--   Step1 本檔：表結構 + 新人角標 + 預置新人活動 + 默認首頁模組順序
--   Step2 後台：活動 CRUD、首頁模組拖放保存
--   Step3 H5：按 layout 渲染；活動落地頁；橫滑櫥窗
--   Step4 購買攔截：註冊≤30天可買新人專屬；加購+下單雙擋
--   Step5 P1：星級／有下級等受眾；標籤／銷量等選品條件

CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 1 COMMENT '0=draft 1=published',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  audience_json JSON NULL COMMENT 'P0 e.g. {"registered_within_days":30} or {"all":true}',
  product_source VARCHAR(32) NOT NULL DEFAULT 'badge' COMMENT 'badge|manual|badge_and_manual',
  product_badge VARCHAR(64) NULL COMMENT 'product_badge_defs.code when source uses badge',
  banner_image_url VARCHAR(512) NULL,
  intro_html_zh MEDIUMTEXT NULL,
  intro_html_en MEDIUMTEXT NULL,
  show_on_home TINYINT NOT NULL DEFAULT 0,
  home_rail_limit INT NOT NULL DEFAULT 10,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_campaigns_code (code),
  KEY idx_campaigns_status (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_campaign_product (campaign_id, product_id),
  KEY idx_campaign_products_sort (campaign_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_layout_modules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(64) NOT NULL COMMENT 'stable key e.g. banner_swipe / campaign:newcomer',
  module_type VARCHAR(32) NOT NULL COMMENT 'banner_swipe|text_marquee|category_row|seckill_rail|recommend_feed|campaign_rail|product_showcase|image_block',
  ref_type VARCHAR(32) NULL COMMENT 'campaign|home_section|null',
  ref_code VARCHAR(64) NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  enabled TINYINT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  config_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_home_layout_module_key (module_key),
  KEY idx_home_layout_sort (enabled, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 新人專屬角標
SET @now = NOW();

INSERT INTO product_badge_defs (code, color, sort_order, status, created_at, updated_at)
SELECT 'newcomer', '#c45c26', 5, 1, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_badge_defs WHERE code = 'newcomer');

INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'zh-HK', '新人專屬' FROM product_badge_defs d
WHERE d.code = 'newcomer' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'zh-HK'
);

INSERT INTO product_badge_def_i18n (badge_def_id, locale, name)
SELECT d.id, 'en', 'Newcomer' FROM product_badge_defs d
WHERE d.code = 'newcomer' AND NOT EXISTS (
  SELECT 1 FROM product_badge_def_i18n i WHERE i.badge_def_id = d.id AND i.locale = 'en'
);

-- 預置新人專區活動
INSERT INTO campaigns (
  code, title_zh, title_en, status, audience_json, product_source, product_badge,
  banner_image_url, intro_html_zh, intro_html_en, show_on_home, home_rail_limit,
  sort_order, created_at, updated_at
)
SELECT
  'newcomer',
  '新人專區',
  'Newcomer Zone',
  1,
  CAST('{"registered_within_days":30}' AS JSON),
  'badge',
  'newcomer',
  NULL,
  '<p>這裡的商品專門為新會員精心挑選。</p>',
  '<p>Products curated for new members.</p>',
  1,
  10,
  0,
  @now,
  @now
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM campaigns WHERE code = 'newcomer');

-- 默認首頁模組（對齊現行硬編碼順序；活動橫滑插在分類與秒殺之間）
INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'banner_swipe', 'banner_swipe', NULL, NULL, '滑動廣告位', 'Banner carousel', 1, 10, NULL, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'banner_swipe');

INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'text_marquee', 'text_marquee', NULL, NULL, '文本廣告', 'Text marquee', 1, 20, NULL, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'text_marquee');

INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'category_row', 'category_row', NULL, NULL, '商品分類', 'Categories', 1, 30, NULL, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'category_row');

INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'campaign:newcomer', 'campaign_rail', 'campaign', 'newcomer', '新人專區', 'Newcomer Zone', 1, 40,
  CAST('{"rail_limit":10}' AS JSON), @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'campaign:newcomer');

INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'seckill_rail', 'seckill_rail', NULL, NULL, '秒殺專區', 'Flash sale', 1, 50, NULL, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'seckill_rail');

INSERT INTO home_layout_modules (module_key, module_type, ref_type, ref_code, title_zh, title_en, enabled, sort_order, config_json, created_at, updated_at)
SELECT 'recommend_feed', 'recommend_feed', NULL, NULL, '為你推薦', 'For you', 1, 900, NULL, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM home_layout_modules WHERE module_key = 'recommend_feed');
